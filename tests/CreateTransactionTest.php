<?php

namespace DavidMaximous\Fawaterak\Tests;

use DavidMaximous\Fawaterak\Classes\FawaterakPayment;
use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use Illuminate\Support\Facades\Http;

class CreateTransactionTest extends TestCase
{
    protected function payment(): FawaterakPayment
    {
        return (new FawaterakPayment())
            ->setFirstName('Ahmed')
            ->setLastName('Ali')
            ->setUserEmail('ahmed@example.com')
            ->setUserPhone('01000000000')
            ->setAmount(100);
    }

    /** Hosted checkout response, straight from the OpenAPI examples. */
    protected function hostedCheckoutResponse()
    {
        return Http::response([
            'status' => 'success',
            'message' => 'Transaction link created',
            'data' => [
                'intent_key' => '550e8400-e29b-41d4-a716-446655440000',
                'url' => 'https://app.fawaterk.com/ts/a1b2c',
                'expires_in' => 2592000,
            ],
        ]);
    }

    public function test_hosted_checkout_returns_the_url_and_intent_key()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $response = $this->payment()->pay();

        $this->assertSame('success', $response['status']);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $response['intent_key']);
        $this->assertSame('https://app.fawaterk.com/ts/a1b2c', $response['url']);
        $this->assertSame(2592000, $response['expires_in']);
        $this->assertNull($response['payment_data']);
    }

    public function test_no_legacy_keys_are_returned()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $response = $this->payment()->pay();

        foreach (['invoice_id', 'invoice_key', 'link', 'pay_load'] as $key) {
            $this->assertArrayNotHasKey($key, $response);
        }
    }

    public function test_the_positional_pay_signature_works()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $response = (new FawaterakPayment())->pay(
            100, 'Ahmed', 'Ali', 'ahmed@example.com', '01000000000',
            'card', 'Session 4', 1, 'EGP', 'ar', ['user_id' => '1234576']
        );

        $this->assertSame('success', $response['status']);
        $this->assertNotEmpty($response['url']);

        $body = $this->bodyFor('createTransaction');

        $this->assertSame(2, $body['payment_method_id']);
        $this->assertSame('ar', $body['lang']);
        $this->assertSame('Session 4', $body['cartItems'][0]['name']);
    }

    public function test_the_payload_is_sent_flat_not_double_wrapped()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()->setPayload(['order_id' => 'ORD-1001'])->pay();

        $body = $this->bodyFor('createTransaction');

        $this->assertSame(['order_id' => 'ORD-1001'], $body['pay_load']);
        $this->assertArrayNotHasKey('payLoad', $body);
    }

    public function test_it_builds_the_customer_cart_and_total()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()->setQuantity(3)->setItemName('Ticket')->pay();

        $body = $this->bodyFor('createTransaction');

        $this->assertSame(300.0, $body['cartTotal']);
        $this->assertSame('EGP', $body['currency']);
        $this->assertSame('Ahmed', $body['customer']['first_name']);
        $this->assertSame('ahmed@example.com', $body['customer']['email']);
        $this->assertSame([
            ['name' => 'Ticket', 'price' => 100, 'quantity' => 3],
        ], $body['cartItems']);
    }

    public function test_multiple_cart_items_drive_the_total()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()
            ->addCartItem('Book', 50, 2)
            ->addCartItem('Pen', 10, 1)
            ->pay();

        $body = $this->bodyFor('createTransaction');

        $this->assertSame(110.0, $body['cartTotal']);
        $this->assertCount(2, $body['cartItems']);
    }

    public function test_optional_fields_are_omitted_when_not_set()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()->pay();

        $body = $this->bodyFor('createTransaction');

        foreach (['taxData', 'discountData', 'sendEmail', 'sendSMS', 'due_date', 'tr_number',
            'redirectOption', 'authAndCapture', 'list_style', 'mobileWalletNumber',
            'save_customer', 'payment_method_id'] as $key) {
            $this->assertArrayNotHasKey($key, $body);
        }
    }

    public function test_optional_fields_are_sent_when_set()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()
            ->setTax('VAT', 14)
            ->setDiscount('pcg', 10)
            ->setSendEmail(true)
            ->setSendSMS(false)
            ->setTrNumber('TR-1001')
            ->setListStyle('v')
            ->setAuthAndCapture(1)
            ->setMobileWalletNumber('01000000000')
            ->setDueDate('2026-06-06 12:00:00')
            ->pay();

        $body = $this->bodyFor('createTransaction');

        $this->assertSame(['title' => 'VAT', 'value' => 14], $body['taxData']);
        $this->assertSame(['type' => 'pcg', 'value' => 10], $body['discountData']);
        $this->assertTrue($body['sendEmail']);
        $this->assertFalse($body['sendSMS']);
        $this->assertSame('TR-1001', $body['tr_number']);
        $this->assertSame('v', $body['list_style']);
        $this->assertSame(1, $body['authAndCapture']);
        $this->assertSame('01000000000', $body['mobileWalletNumber']);
        $this->assertSame('2026-06-06 12:00:00', $body['due_date']);
    }

    public function test_the_legacy_redirect_route_still_builds_the_three_urls()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()->pay();

        $urls = $this->bodyFor('createTransaction')['redirectionUrls'];

        $this->assertStringEndsWith('/success', $urls['successUrl']);
        $this->assertStringEndsWith('/failed', $urls['failUrl']);
        $this->assertStringEndsWith('/pending', $urls['pendingUrl']);
        $this->assertArrayNotHasKey('backUrl', $urls);
        $this->assertArrayNotHasKey('webhookUrl', $urls);
    }

    public function test_the_url_setters_win_over_the_config()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()
            ->setSuccessUrl('https://shop.test/thanks')
            ->setBackUrl('https://shop.test/cart')
            ->setWebhookUrl('https://shop.test/webhooks/fawaterak_json')
            ->pay();

        $urls = $this->bodyFor('createTransaction')['redirectionUrls'];

        $this->assertSame('https://shop.test/thanks', $urls['successUrl']);
        $this->assertSame('https://shop.test/cart', $urls['backUrl']);
        $this->assertSame('https://shop.test/webhooks/fawaterak_json', $urls['webhookUrl']);
    }

    public function test_a_route_name_is_resolved_to_a_url()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()->setSuccessUrl('payment-redirect')->pay();

        $urls = $this->bodyFor('createTransaction')['redirectionUrls'];

        $this->assertStringStartsWith('http', $urls['successUrl']);
    }

    public function test_direct_card_payment_returns_the_redirect_url()
    {
        $this->fakeApi('*/api/v3/createTransaction', Http::response([
            'status' => 'success',
            'message' => 'Transaction link created',
            'data' => [
                'intent_key' => '550e8400-e29b-41d4-a716-446655440000',
                'expires_in' => 2592000,
                'payment_data' => ['redirectTo' => 'https://staging.fawaterk.com/link/I0PAH'],
            ],
        ]));

        $response = $this->payment()->setMethod('card')->pay();

        $this->assertSame('https://staging.fawaterk.com/link/I0PAH', $response['url']);
        $this->assertSame(2, $this->bodyFor('createTransaction')['payment_method_id']);
    }

    public function test_direct_fawry_payment_returns_the_reference_number()
    {
        $this->fakeApi('*/api/v3/createTransaction', Http::response([
            'status' => 'success',
            'message' => 'Transaction link created',
            'data' => [
                'intent_key' => '550e8400-e29b-41d4-a716-446655440000',
                'expires_in' => 2592000,
                'payment_data' => [
                    'referenceNumber' => '981335305',
                    'expireDate' => '2021-07-06 15:53:41',
                    'expirationTime' => '2021-07-06 15:53:41',
                ],
            ],
        ]));

        $response = $this->payment()->setMethod('fawry')->pay();

        $this->assertSame('981335305', $response['reference_number']);
        $this->assertSame('2021-07-06 15:53:41', $response['expire_date']);
        $this->assertNull($response['url']);
    }

    public function test_direct_mobile_wallet_payment_returns_the_qr()
    {
        $this->fakeApi('*/api/v3/createTransaction', Http::response([
            'status' => 'success',
            'message' => 'Transaction link created',
            'data' => [
                'intent_key' => '550e8400-e29b-41d4-a716-446655440000',
                'expires_in' => 2592000,
                'payment_data' => [
                    'systemReference' => '4266311',
                    'isoQr' => '00020101021226330016A0000007321000010109610055979',
                ],
            ],
        ]));

        $response = $this->payment()->setMethod('mwallet')->pay();

        $this->assertSame('4266311', $response['system_reference']);
        $this->assertSame('00020101021226330016A0000007321000010109610055979', $response['iso_qr']);
    }

    public function test_a_numeric_payment_method_id_is_passed_through()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()->setPaymentMethodId(37)->pay();

        $this->assertSame(37, $this->bodyFor('createTransaction')['payment_method_id']);
    }

    public function test_an_unknown_method_alias_throws()
    {
        $this->expectException(MissingPaymentInfoException::class);

        $this->payment()->setMethod('bitcoin');
    }

    public function test_a_missing_required_field_throws()
    {
        $this->expectException(MissingPaymentInfoException::class);

        (new FawaterakPayment())->setAmount(100)->pay();
    }

    public function test_save_customer_requires_a_customer_unique_id()
    {
        $this->expectException(MissingPaymentInfoException::class);

        $this->payment()->setSaveCustomer(true)->pay();
    }

    public function test_a_validation_error_is_surfaced()
    {
        $this->fakeApi('*/api/v3/createTransaction', Http::response([
            'status' => 'error',
            'message' => ['cartTotal' => ['The cart total field is required.']],
        ], 422));

        $response = $this->payment()->pay();

        $this->assertSame('error', $response['status']);
        $this->assertSame(422, $response['http_status']);
        $this->assertSame(['cartTotal' => ['The cart total field is required.']], $response['errors']);
        $this->assertSame('The cart total field is required.', $response['message']);
    }

    public function test_a_401_is_retried_once_with_a_fresh_token()
    {
        Http::fake([
            '*/oauth/token' => $this->tokenResponse(),
            '*/api/v3/createTransaction' => Http::sequence()
                ->push(['status' => 'error', 'message' => 'Unauthenticated.'], 401)
                ->push([
                    'status' => 'success',
                    'message' => 'Transaction link created',
                    'data' => [
                        'intent_key' => 'retried-key',
                        'url' => 'https://app.fawaterk.com/ts/retry',
                        'expires_in' => 2592000,
                    ],
                ]),
        ]);

        $response = $this->payment()->pay();

        $this->assertSame('retried-key', $response['intent_key']);
    }

    public function test_debug_returns_the_body_that_will_be_sent()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $payment = $this->payment()->setMethod('fawry')->setPayload(['order_id' => 7]);

        $preview = $payment->debug();

        $payment->pay();

        $this->assertSame($preview, $this->bodyFor('createTransaction'));
    }

    public function test_debug_sends_nothing_on_its_own()
    {
        Http::fake();

        $this->payment()->debug();

        Http::assertNothingSent();
    }

    public function test_the_bearer_token_is_attached()
    {
        $this->fakeApi('*/api/v3/createTransaction', $this->hostedCheckoutResponse());

        $this->payment()->pay();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'createTransaction')
                && $request->hasHeader('Authorization', 'Bearer ' . self::ACCESS_TOKEN);
        });
    }
}
