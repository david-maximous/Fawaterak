<?php

namespace DavidMaximous\Fawaterak\Tests;

use DavidMaximous\Fawaterak\Classes\FawaterakPayment;
use DavidMaximous\Fawaterak\Classes\FawaterakVerify;
use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use Illuminate\Support\Facades\Http;

class TransactionDataTest extends TestCase
{
    /** The getTransactionData example from the OpenAPI document. */
    public static function transactionPayload($paid = 1)
    {
        return [
            'status' => 'success',
            'data' => [
                'intent_key' => '550e8400-e29b-41d4-a716-446655440000',
                'transaction_id' => 12345,
                'customer_email' => 'ahmed@example.com',
                'commission' => 2.5,
                'transaction_created_at' => '2026-06-06 12:00:00',
                'paid' => $paid,
                'paid_at' => '2026-06-06 12:05:00',
                'status_text' => $paid ? 'paid' : 'unpaid',
                'total' => 100,
                'currency' => 'EGP',
                'payment_method' => 'Fawry',
                'pay_load' => ['order_id' => 'ORD-1001'],
                'due_date' => '2026-06-08 12:00:00',
                'transaction_link' => 'https://app.fawaterk.com/transactions/550e8400-e29b-41d4-a716-446655440000',
                'transaction_history' => [[
                    'method' => ['name' => 'Fawry', 'logo' => 'https://app.fawaterk.com/clients/payment_options/fawry.png'],
                    'amount' => '100.00 EGP',
                    'currency' => 'EGP',
                    'status' => 'success',
                    'reference' => '981335305',
                    'date' => '2026-06-06 12:05:00',
                ]],
            ],
        ];
    }

    public function test_it_reads_a_transaction_by_intent_key()
    {
        $this->fakeApi('*/api/v3/getTransactionData', Http::response(self::transactionPayload()));

        $result = (new FawaterakVerify())->getTransactionData('550e8400-e29b-41d4-a716-446655440000');

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['paid']);
        $this->assertSame(12345, $result['transaction_id']);
        $this->assertSame('Fawry', $result['payment_method']);
        $this->assertSame(100, $result['total']);
        $this->assertCount(1, $result['transaction_history']);

        $this->assertSame(
            ['intent_key' => '550e8400-e29b-41d4-a716-446655440000'],
            $this->bodyFor('getTransactionData')
        );
    }

    public function test_it_returns_payload_once_and_no_legacy_keys()
    {
        $this->fakeApi('*/api/v3/getTransactionData', Http::response(self::transactionPayload()));

        $result = (new FawaterakVerify())->getTransactionData('550e8400-e29b-41d4-a716-446655440000');

        $this->assertSame(['order_id' => 'ORD-1001'], $result['payload']);
        $this->assertIsArray($result['process_data']);

        foreach (['invoice_id', 'invoice_key', 'pay_load'] as $key) {
            $this->assertArrayNotHasKey($key, $result);
        }
    }

    public function test_an_unpaid_transaction_is_still_a_successful_read()
    {
        $this->fakeApi('*/api/v3/getTransactionData', Http::response(self::transactionPayload(0)));

        $result = (new FawaterakVerify())->getTransactionData('550e8400-e29b-41d4-a716-446655440000');

        $this->assertSame('success', $result['status']);
        $this->assertSame(0, $result['paid']);
        $this->assertSame('unpaid', $result['status_text']);
    }

    public function test_an_unknown_transaction_fails()
    {
        $this->fakeApi('*/api/v3/getTransactionData', Http::response([
            'status' => 'error',
            'message' => 'Transaction not found',
        ], 422));

        $result = (new FawaterakVerify())->getTransactionData('nope');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Transaction not found', $result['message']);
    }

    public function test_it_lists_transactions_with_pagination()
    {
        $this->fakeApi('*/api/v3/getTransactionsData*', Http::response([
            'status' => 'success',
            'data' => [[
                'transaction_id' => 12345,
                'intent_key' => '550e8400-e29b-41d4-a716-446655440000',
                'status_text' => 'paid',
                'total' => 100,
                'currency' => 'EGP',
                'payment_method' => 'Fawry',
            ]],
            'pagination' => [
                'total' => 1, 'per_page' => 15, 'current_page' => 1,
                'last_page' => 1, 'from' => 1, 'to' => 1,
            ],
        ]));

        $result = (new FawaterakVerify())->listTransactions('2026-01-01', '2026-01-31', 15, 1, 'ORD-1001');

        $this->assertSame('success', $result['status']);
        $this->assertCount(1, $result['data']);
        $this->assertSame(15, $result['pagination']['per_page']);

        $url = $this->requestFor('getTransactionsData')->url();

        $this->assertStringContainsString('start_date=2026-01-01', $url);
        $this->assertStringContainsString('end_date=2026-01-31', $url);
        $this->assertStringContainsString('per_page=15', $url);
        $this->assertStringContainsString('page=1', $url);
        $this->assertStringContainsString('pay_load=ORD-1001', $url);
    }

    public function test_listing_without_dates_throws()
    {
        $this->expectException(MissingPaymentInfoException::class);

        (new FawaterakVerify())->listTransactions('', '2026-01-31');
    }

    public function test_it_lists_payment_methods()
    {
        $this->fakeApi('*/api/v3/getTrPaymentmethods', Http::response([
            'status' => 'success',
            'vendorSettingsData' => ['custome_iframe_title' => null],
            'data' => [
                ['payment_method_id' => 2, 'name_en' => 'Visa-Mastercard', 'name_ar' => 'فيزا -ماستر كارد', 'redirect' => 'true', 'logo' => 'https://app.fawaterk.com/x.png', 'commission_on_customer' => 2, 'integration_status' => 1],
                ['payment_method_id' => 3, 'name_en' => 'Fawry', 'name_ar' => 'فوري', 'redirect' => 'false', 'logo' => 'https://app.fawaterk.com/y.png', 'commission_on_customer' => 2, 'integration_status' => 1],
            ],
        ]));

        $payment = new FawaterakPayment();
        $result = $payment->listPaymentMethods();

        $this->assertSame('success', $result['status']);
        $this->assertCount(2, $result['data']);
        $this->assertSame([2 => 'Visa-Mastercard', 3 => 'Fawry'], $payment->paymentMethodsList('en'));
        $this->assertSame([2 => 'فيزا -ماستر كارد', 3 => 'فوري'], $payment->paymentMethodsList('ar'));
    }

    public function test_the_payment_methods_list_is_cached_when_enabled()
    {
        config()->set('fawaterak.FAWATERAK_METHODS_CACHE_TTL', 600);

        $this->fakeApi('*/api/v3/getTrPaymentmethods', Http::response([
            'status' => 'success',
            'data' => [['payment_method_id' => 3, 'name_en' => 'Fawry', 'name_ar' => 'فوري']],
        ]));

        $payment = new FawaterakPayment();
        $payment->listPaymentMethods();
        $payment->listPaymentMethods();

        // One token call plus a single methods call.
        Http::assertSentCount(2);
    }

    public function test_match_payment_method_resolves_from_the_live_list()
    {
        $this->fakeApi('*/api/v3/getTrPaymentmethods', Http::response([
            'status' => 'success',
            'data' => [
                ['payment_method_id' => 4, 'name_en' => 'Meeza', 'name_ar' => 'ميزا'],
                ['payment_method_id' => 37, 'name_en' => 'valU', 'name_ar' => 'فاليو'],
            ],
        ]));

        $verify = new FawaterakVerify();

        $this->assertSame('Meeza', $verify->matchPaymentMethod(4));
        $this->assertSame('valU', $verify->matchPaymentMethod('37'));
        $this->assertSame('ميزا', $verify->matchPaymentMethod(4, 'ar'));
    }

    public function test_match_payment_method_falls_back_without_credentials()
    {
        config()->set('fawaterak.FAWATERAK_CLIENT_ID', null);
        config()->set('fawaterak.FAWATERAK_CLIENT_SECRET', null);

        $verify = new FawaterakVerify();

        // Must never throw, even though the lookup cannot authenticate.
        $this->assertSame('Visa-Mastercard', $verify->matchPaymentMethod(2));
        $this->assertSame('Fawry', $verify->matchPaymentMethod(3));
        $this->assertSame('Meeza', $verify->matchPaymentMethod(4));
        $this->assertSame('N/A', $verify->matchPaymentMethod(999));
    }

    public function test_the_readme_named_argument_call_works()
    {
        $this->fakeApi('*/api/v3/getTransactionsData*', Http::response([
            'status' => 'success', 'data' => [], 'pagination' => ['total' => 0],
        ]));

        $result = (new FawaterakVerify())->listTransactions(
            startDate: '2026-01-01',
            endDate: '2026-01-31',
            perPage: 50,
            page: 1,
            payLoad: 'ORD-'
        );

        $this->assertSame('success', $result['status']);
        $this->assertStringContainsString('per_page=50', $this->requestFor('getTransactionsData')->url());
    }

    public function test_reset_clears_the_builder_between_transactions()
    {
        $payment = new FawaterakPayment();

        $payment->setAmount(100)->setFirstName('Ahmed')->addCartItem('Book', 50, 1)->setTax('VAT', 14);

        $payment->reset();

        $this->assertNull($payment->getAmount());
        $this->assertNull($payment->getFirstName());
        $this->assertNull($payment->getCartItems());
        $this->assertNull($payment->getTax());
    }
}
