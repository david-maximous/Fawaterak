<?php

namespace DavidMaximous\Fawaterak\Tests;

use DavidMaximous\Fawaterak\Classes\FawaterakTokenization;
use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use Illuminate\Support\Facades\Http;

class TokenizationTest extends TestCase
{
    protected function tokenization(): FawaterakTokenization
    {
        return (new FawaterakTokenization())
            ->setCustomerUniqueId('222111333')
            ->setCustomerFirstName('Fname')
            ->setCustomerLastName('Lname')
            ->setCustomerEmail('customer@example.com')
            ->setCustomerPhone('01111111111');
    }

    public function test_it_creates_a_card_token_screen()
    {
        Http::fake(['*/api/v2/createCardTokenScreen' => Http::response([
            'status' => 'success',
            'redirectUrl' => 'https://staging.fawaterk.com/nbe/storeToken/1994116',
        ])]);

        $result = $this->tokenization()
            ->setDeductTotalAmount(true)
            ->setAmount(100)
            ->setCurrency('EGP')
            ->setItemName('order total')
            ->setSuccessUrl('https://domain.com/success')
            ->setFailUrl('https://domain.com/fail')
            ->setTokenWebhookUrl('https://domain.com/token-webhook')
            ->setAllowedCardTypes([])
            ->createCardTokenScreen();

        $this->assertSame('success', $result['status']);
        $this->assertSame('https://staging.fawaterk.com/nbe/storeToken/1994116', $result['redirect_url']);
        $this->assertArrayNotHasKey('redirectUrl', $result);

        $body = $this->bodyFor('createCardTokenScreen');

        $this->assertSame('EGP', $body['order']['currency']);
        $this->assertSame(100.0, $body['order']['cartTotal']);
        $this->assertSame('222111333', $body['customerData']['customer_unique_id']);
        $this->assertSame('Fname', $body['customerData']['customer_first_name']);
        $this->assertTrue($body['deduct_total_amount']);

        // This endpoint uses snake_case redirection keys.
        $this->assertSame('https://domain.com/success', $body['redirectionUrls']['success_url']);
        $this->assertSame('https://domain.com/fail', $body['redirectionUrls']['fail_url']);
        $this->assertSame('https://domain.com/token-webhook', $body['redirectionUrls']['webhook_url']);
    }

    public function test_the_card_screen_authenticates_with_the_vendor_key()
    {
        Http::fake(['*/api/v2/createCardTokenScreen' => Http::response([
            'status' => 'success',
            'redirectUrl' => 'https://staging.fawaterk.com/nbe/storeToken/1994116',
        ])]);

        $this->tokenization()
            ->setCurrency('EGP')
            ->setSuccessUrl('https://domain.com/success')
            ->setFailUrl('https://domain.com/fail')
            ->createCardTokenScreen();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer ' . self::VENDOR_KEY);
        });

        // No OAuth token call is needed for the v2 endpoints.
        $this->assertNull($this->requestFor('oauth/token'));
    }

    public function test_the_card_screen_requires_redirection_urls()
    {
        $this->expectException(MissingPaymentInfoException::class);

        $this->tokenization()->setCurrency('EGP')->createCardTokenScreen();
    }

    public function test_the_card_screen_uses_pay_load_camel_case()
    {
        Http::fake(['*/api/v2/createCardTokenScreen' => Http::response([
            'status' => 'success',
            'redirectUrl' => 'https://staging.fawaterk.com/nbe/storeToken/1994116',
        ])]);

        $this->tokenization()
            ->setCurrency('EGP')
            ->setSuccessUrl('https://domain.com/success')
            ->setFailUrl('https://domain.com/fail')
            ->setPayload(['order_id' => 'ORD-1'])
            ->createCardTokenScreen();

        $body = $this->bodyFor('createCardTokenScreen');

        $this->assertSame(['order_id' => 'ORD-1'], $body['payLoad']);
        $this->assertArrayNotHasKey('payload', $body);
    }

    public function test_it_pays_with_a_saved_token()
    {
        Http::fake(['*/api/v2/createTokenizationPayRequest' => Http::response([
            'status' => 'success',
            'redirectTo' => 'https://staging.fawaterk.com/mpgs/abc/auth',
        ])]);

        $result = (new FawaterakTokenization())
            ->setOrder(10, 'USD')
            ->setCustomerToken('8lvfHBZC3n7lmTRO')
            ->setPayload(['merchant_reference' => '123'])
            ->setSuccessUrl('https://domain.com/success')
            ->setFailUrl('https://domain.com/fail')
            ->payWithToken();

        $this->assertSame('https://staging.fawaterk.com/mpgs/abc/auth', $result['redirect_to']);
        $this->assertArrayNotHasKey('redirectTo', $result);
        $this->assertArrayNotHasKey('link', $result);

        $body = $this->bodyFor('createTokenizationPayRequest');

        $this->assertSame('tokenization', $body['tokenAction']);
        $this->assertSame(10, $body['order']['amount']);
        $this->assertSame('USD', $body['order']['currency']);
        $this->assertSame('8lvfHBZC3n7lmTRO', $body['customerData']['customer_token']);
        $this->assertSame(['merchant_reference' => '123'], $body['payload']);

        // These endpoints use camelCase redirection keys.
        $this->assertSame('https://domain.com/success', $body['redirectionUrls']['successUrl']);
        $this->assertSame('https://domain.com/fail', $body['redirectionUrls']['failUrl']);
    }

    public function test_a_recurring_charge_omits_the_token_action()
    {
        Http::fake(['*/api/v2/createTokenizationPayRequest' => Http::response([
            'status' => 'success',
            'transaction_id' => 1011767,
        ])]);

        $result = (new FawaterakTokenization())
            ->setOrder(1000, 'EGP')
            ->setCustomerToken('UFZiaC98572rKZGZ')
            ->payRecurring();

        $this->assertSame(1011767, $result['transaction_id']);

        $body = $this->bodyFor('createTokenizationPayRequest');

        $this->assertArrayNotHasKey('tokenAction', $body);
    }

    public function test_the_cvv_is_only_sent_when_set()
    {
        Http::fake(['*/api/v2/createTokenizationPayRequest' => Http::response([
            'status' => 'success', 'transaction_id' => 1,
        ])]);

        (new FawaterakTokenization())
            ->setOrder(100, 'EGP')
            ->setCustomerToken('tok')
            ->setCardCvv('100')
            ->payRecurring();

        $this->assertSame('100', $this->bodyFor('createTokenizationPayRequest')['customerData']['card_cvv']);
    }

    public function test_paying_without_a_token_throws()
    {
        $this->expectException(MissingPaymentInfoException::class);

        (new FawaterakTokenization())->setOrder(100, 'EGP')->payRecurring();
    }

    public function test_it_deletes_a_customer_token()
    {
        Http::fake(['*/api/v2/deleteCustomerToken' => Http::response([
            'status' => 'success',
            'message' => 'token deleted',
        ])]);

        $result = (new FawaterakTokenization())
            ->setCustomerUniqueId('222111333')
            ->setCardTokenUniqueId('2345')
            ->setDeleteTokenFromBank('9731673377207107')
            ->deleteCustomerToken();

        $this->assertSame('success', $result['status']);
        $this->assertSame('token deleted', $result['message']);

        $this->assertSame([
            'customerUniqueId' => '222111333',
            'cardTokenUniqueId' => '2345',
            'deleteTokenFromBank' => '9731673377207107',
        ], $this->bodyFor('deleteCustomerToken'));
    }

    public function test_deleting_without_ids_throws()
    {
        $this->expectException(MissingPaymentInfoException::class);

        (new FawaterakTokenization())->deleteCustomerToken();
    }

    public function test_a_validation_error_is_surfaced()
    {
        Http::fake(['*/api/v2/createTokenizationPayRequest' => Http::response([
            'status' => 'error',
            'message' => 'Card token is invalid',
        ], 400)]);

        $result = (new FawaterakTokenization())
            ->setOrder(100, 'EGP')
            ->setCustomerToken('bad-token')
            ->payRecurring();

        $this->assertSame('error', $result['status']);
        $this->assertSame('Card token is invalid', $result['message']);
    }
}
