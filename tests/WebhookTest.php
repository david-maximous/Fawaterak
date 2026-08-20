<?php

namespace DavidMaximous\Fawaterak\Tests;

use DavidMaximous\Fawaterak\Classes\FawaterakVerify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebhookTest extends TestCase
{
    const INTENT_KEY = '550e8400-e29b-41d4-a716-446655440000';

    protected function paidPayload(array $overrides = [])
    {
        $payload = array_merge([
            'transaction_key' => self::INTENT_KEY,
            'transaction_id' => 12345,
            'payment_method' => 'Fawry',
            'status' => 'paid',
            'pay_load' => '{"order_id":"ORD-1001"}',
            'paidAmount' => '100.00',
            'paidCurrency' => 'EGP',
            'paidAt' => '2026-06-06 12:05:00',
            'customerData' => [
                'customer_unique_id' => 'cust-42',
                'customer_first_name' => 'Ahmed',
                'customer_last_name' => 'Ali',
                'customer_email' => 'ahmed@example.com',
                'customer_phone' => '01000000000',
            ],
        ], $overrides);

        $payload['transactionHashKey'] = $this->sign(
            'TransactionId=' . $payload['transaction_id'] .
            '&TransactionKey=' . $payload['transaction_key'] .
            '&PaymentMethod=' . $payload['payment_method']
        );

        return $payload;
    }

    protected function request(array $payload)
    {
        return Request::create('/webhooks/fawaterak', 'POST', $payload);
    }

    protected function fakeTransaction($paid = 1)
    {
        $this->fakeApi('*/api/v3/getTransactionData', Http::response(
            TransactionDataTest::transactionPayload($paid)
        ));
    }

    /* ---------------------------------------------------------------- Paid */

    public function test_a_valid_paid_webhook_passes_both_legs()
    {
        $this->fakeTransaction();

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($this->paidPayload()));

        $this->assertTrue($result['success']);
        $this->assertSame(self::INTENT_KEY, $result['transaction_key']);
        $this->assertSame(12345, $result['transaction_id']);
        $this->assertSame(100, $result['amount_paid']);
        $this->assertSame('100.00', $result['paid_amount']);
        $this->assertSame('EGP', $result['currency']);
        $this->assertSame('Fawry', $result['payment_method']);
        $this->assertSame(['order_id' => 'ORD-1001'], $result['payload']);
        $this->assertSame('ahmed@example.com', $result['customer']['customer_email']);
    }

    public function test_verify_callback_is_still_the_one_dot_x_entry_point()
    {
        $this->fakeTransaction();

        $result = (new FawaterakVerify())->verifyCallback($this->request($this->paidPayload()));

        $this->assertTrue($result['success']);
        $this->assertSame(self::INTENT_KEY, $result['transaction_key']);
    }

    public function test_no_legacy_keys_are_returned()
    {
        $this->fakeTransaction();

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($this->paidPayload()));

        foreach (['invoice_id', 'invoice_key', 'link', 'pay_load'] as $key) {
            $this->assertArrayNotHasKey($key, $result);
        }
    }

    public function test_a_tampered_hash_fails_leg_one()
    {
        $this->fakeTransaction();

        $payload = $this->paidPayload();
        $payload['transactionHashKey'] = str_repeat('a', 64);

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($payload));

        $this->assertFalse($result['success']);
        $this->assertSame(__('fawaterak::messages.Security_checks_are_not_passed_by_the_system'), $result['message']);

        // Leg 2 must never run when leg 1 fails.
        $this->assertNull($this->requestFor('getTransactionData'));
    }

    public function test_a_tampered_amount_does_not_change_the_signature_but_the_api_total_wins()
    {
        $this->fakeTransaction();

        // The attacker inflates paidAmount; the trusted total comes from the API.
        $result = (new FawaterakVerify())->verifyPaidCallback(
            $this->request($this->paidPayload(['paidAmount' => '999999.00']))
        );

        $this->assertTrue($result['success']);
        $this->assertSame(100, $result['amount_paid']);
    }

    public function test_a_key_mismatch_fails_leg_two()
    {
        // The API returns a different intent_key than the webhook claimed.
        $payload = TransactionDataTest::transactionPayload();
        $payload['data']['intent_key'] = 'a-completely-different-key';

        $this->fakeApi('*/api/v3/getTransactionData', Http::response($payload));

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($this->paidPayload()));

        $this->assertFalse($result['success']);
        $this->assertSame(__('fawaterak::messages.Security_checks_are_not_passed_by_the_system'), $result['message']);
    }

    public function test_a_transaction_id_mismatch_fails_leg_two()
    {
        $payload = TransactionDataTest::transactionPayload();
        $payload['data']['transaction_id'] = 99999;

        $this->fakeApi('*/api/v3/getTransactionData', Http::response($payload));

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($this->paidPayload()));

        $this->assertFalse($result['success']);
    }

    public function test_an_unpaid_transaction_fails_leg_two()
    {
        $this->fakeTransaction(0);

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($this->paidPayload()));

        $this->assertFalse($result['success']);
        $this->assertArrayNotHasKey('pending', $result);
    }

    public function test_a_pending_webhook_is_reported_as_pending()
    {
        $this->fakeTransaction(0);

        $result = (new FawaterakVerify())->verifyPaidCallback(
            $this->request($this->paidPayload(['status' => 'pending']))
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['pending']);
        $this->assertSame(__('fawaterak::messages.PAYMENT_PENDING'), $result['message']);
        $this->assertSame(['order_id' => 'ORD-1001'], $result['payload']);

        // A pending notification must not trigger the second leg.
        $this->assertNull($this->requestFor('getTransactionData'));
    }

    public function test_a_legacy_v2_invoice_payload_is_still_verified()
    {
        $this->fakeTransaction();

        $payload = [
            'invoice_key' => self::INTENT_KEY,
            'invoice_id' => 12345,
            'invoice_status' => 'paid',
            'payment_method' => 'Fawry',
            'pay_load' => '{"order_id":"ORD-1001"}',
        ];

        $payload['hashKey'] = $this->sign(
            'InvoiceId=12345&InvoiceKey=' . self::INTENT_KEY . '&PaymentMethod=Fawry'
        );

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($payload));

        $this->assertTrue($result['success']);
        $this->assertSame(self::INTENT_KEY, $result['transaction_key']);
    }

    public function test_an_unrecognised_payload_is_rejected()
    {
        $result = (new FawaterakVerify())->verifyPaidCallback($this->request(['foo' => 'bar']));

        $this->assertFalse($result['success']);
        $this->assertSame(__('fawaterak::messages.Security_checks_are_not_passed_by_the_system'), $result['message']);
    }

    public function test_a_plain_array_is_accepted_too()
    {
        $this->fakeTransaction();

        $result = (new FawaterakVerify())->verifyPaidCallback($this->paidPayload());

        $this->assertTrue($result['success']);
    }

    public function test_the_vendor_key_is_read_from_config_not_env()
    {
        // env() is empty here on purpose: only the config value is set.
        $this->assertNull(env('FAWATERAK_API_KEY'));

        $this->fakeTransaction();

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($this->paidPayload()));

        $this->assertTrue($result['success']);
    }

    /* -------------------------------------------------------------- Failed */

    public function test_a_valid_failed_webhook_is_verified()
    {
        $payload = [
            'transaction_key' => self::INTENT_KEY,
            'transaction_id' => 12345,
            'payment_method' => 'Visa-Mastercard',
            'pay_load' => '{"order_id":"ORD-1001"}',
            'amount' => '150.00',
            'paidCurrency' => 'EGP',
            'errorMessage' => 'Payment declined by issuer',
            'response' => '{"gatewayCode":"DECLINED"}',
        ];

        $payload['hashKey'] = $this->sign(
            'TransactionId=12345&TransactionKey=' . self::INTENT_KEY . '&PaymentMethod=Visa-Mastercard'
        );

        $result = (new FawaterakVerify())->verifyFailedCallback($this->request($payload));

        $this->assertFalse($result['success']);
        $this->assertTrue($result['verified']);
        $this->assertSame('Payment declined by issuer', $result['error_message']);
        $this->assertSame('150.00', $result['amount']);
        $this->assertSame(['order_id' => 'ORD-1001'], $result['payload']);
    }

    public function test_a_forged_failed_webhook_is_rejected()
    {
        $result = (new FawaterakVerify())->verifyFailedCallback($this->request([
            'transaction_key' => self::INTENT_KEY,
            'transaction_id' => 12345,
            'payment_method' => 'Visa-Mastercard',
            'hashKey' => str_repeat('b', 64),
        ]));

        $this->assertFalse($result['success']);
        $this->assertArrayNotHasKey('verified', $result);
    }

    /* -------------------------------------------------------------- Cancel */

    public function test_a_valid_cancel_webhook_is_verified()
    {
        $payload = [
            'referenceId' => 998877,
            'status' => 'EXPIRED',
            'paymentMethod' => 'Aman',
            'pay_load' => '{"order_id":"ORD-1001"}',
            'transactionId' => 12345,
            'transactionKey' => self::INTENT_KEY,
        ];

        $payload['hashKey'] = $this->sign('referenceId=998877&PaymentMethod=Aman');

        $result = (new FawaterakVerify())->verifyCancelCallback($this->request($payload));

        $this->assertTrue($result['verified']);
        $this->assertSame('EXPIRED', $result['status']);
        $this->assertSame(998877, $result['reference_id']);
        $this->assertSame(__('fawaterak::messages.REFERENCE_EXPIRED'), $result['message']);
    }

    public function test_a_canceled_reference_uses_the_canceled_message()
    {
        $payload = [
            'referenceId' => 998877,
            'status' => 'CANCELED',
            'paymentMethod' => 'Masary',
            'hashKey' => $this->sign('referenceId=998877&PaymentMethod=Masary'),
        ];

        $result = (new FawaterakVerify())->verifyCancelCallback($this->request($payload));

        $this->assertSame(__('fawaterak::messages.REFERENCE_CANCELED'), $result['message']);
    }

    public function test_a_forged_cancel_webhook_is_rejected()
    {
        $result = (new FawaterakVerify())->verifyCancelCallback($this->request([
            'referenceId' => 998877,
            'paymentMethod' => 'Aman',
            'hashKey' => str_repeat('c', 64),
        ]));

        $this->assertFalse($result['success']);
    }

    /* -------------------------------------------------------------- Refund */

    public function test_a_valid_refund_webhook_is_verified()
    {
        $payload = [
            'transactionId' => 12345,
            'amount' => '50.00',
            'currency' => 'EGP',
            'status' => 1,
            'reason' => 'Customer requested partial refund',
            'approvedAt' => '2026-06-02 10:15:00',
        ];

        $payload['hashKey'] = $this->sign('transactionId=12345&amount=50.00&currency=EGP');

        $result = (new FawaterakVerify())->verifyRefundCallback($this->request($payload));

        $this->assertTrue($result['success']);
        $this->assertTrue($result['refunded']);
        $this->assertSame('50.00', $result['amount']);
        $this->assertSame('2026-06-02 10:15:00', $result['approved_at']);
        $this->assertSame(__('fawaterak::messages.REFUND_APPROVED'), $result['message']);
    }

    public function test_a_forged_refund_webhook_is_rejected()
    {
        $result = (new FawaterakVerify())->verifyRefundCallback($this->request([
            'transactionId' => 12345,
            'amount' => '50.00',
            'currency' => 'EGP',
            'hashKey' => str_repeat('d', 64),
        ]));

        $this->assertFalse($result['success']);
    }

    /* --------------------------------------------------------------- Token */

    public function test_a_valid_token_webhook_is_verified()
    {
        $payload = [
            'customerUniqueId' => '222111333',
            'customerCard' => '512345xxxxxx0008',
            'customerCardToken' => '9731673377207107',
            'cardBrand' => 'MASTERCARD',
            'cardTokenUniqueId' => '2345',
        ];

        $payload['hashKey'] = $this->sign(
            'customerUniqueId=222111333&customerCardToken=9731673377207107'
        );

        $result = (new FawaterakVerify())->verifyTokenCallback($this->request($payload));

        $this->assertTrue($result['success']);
        $this->assertSame('9731673377207107', $result['customer_token']);
        $this->assertSame('MASTERCARD', $result['card_brand']);
        $this->assertSame('2345', $result['card_token_unique_id']);
    }

    public function test_a_forged_token_webhook_is_rejected()
    {
        $result = (new FawaterakVerify())->verifyTokenCallback($this->request([
            'customerUniqueId' => '222111333',
            'customerCardToken' => '9731673377207107',
            'hashKey' => str_repeat('e', 64),
        ]));

        $this->assertFalse($result['success']);
    }

    public function test_a_missing_hash_is_rejected()
    {
        $payload = $this->paidPayload();
        unset($payload['transactionHashKey']);

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($payload));

        $this->assertFalse($result['success']);
    }

    public function test_the_arabic_messages_resolve()
    {
        $this->app->setLocale('ar');
        $this->fakeTransaction();

        $result = (new FawaterakVerify())->verifyPaidCallback($this->request($this->paidPayload()));

        $this->assertSame('تمت العملية بنجاح', $result['message']);
    }
}
