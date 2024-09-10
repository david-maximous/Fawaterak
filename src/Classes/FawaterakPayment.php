<?php

namespace DavidMaximous\Fawaterak\Classes;

use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use http\Env\Response;
use Illuminate\Support\Facades\Http;


class FawaterakPayment extends BaseController
{
    public $fawaterak_url;
    public $fawaterak_api_key;
    public $fawaterak_redirect_url;


    public function __construct()
    {
        $this->fawaterak_url = config('fawaterak.FAWATERAK_URL');
        $this->fawaterak_api_key = config('fawaterak.FAWATERAK_API_KEY');
        $this->fawaterak_redirect_url = config('fawaterak.FAWATERAK_REDIRECT_URL');
    }


    /**
     * @param $amount
     * @param null $first_name
     * @param null $last_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $method
     * @param null $item_name
     * @param null $quantity
     * @param null $currency
     * @param null $language
     * @param array|null $payload
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $first_name = null, $last_name = null, $user_email = null, $user_phone = null, $method = null, $item_name = null, $quantity = null, $currency = null, $language = null, ?array $payload = []): array
    {
        $this->setPassedVariablesToGlobal($amount, $first_name, $last_name, $user_email, $user_phone, $method, $item_name, $quantity, $currency, $language, $payload);
        $required_fields = ['amount', 'first_name', 'last_name', 'user_email', 'user_phone', 'method'];
        $this->checkRequiredFields($required_fields);

        //Set defaults if not set
            $this->item_name ?? $this->item_name = 'default';
            $this->quantity ?? $this->quantity = '1';
            $this->currency ?? $this->currency = 'EGP';
            $this->language ?? $this->language = 'en';


        $returnUrl = route($this->fawaterak_redirect_url);

        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->fawaterak_api_key,
            ])->post($this->fawaterak_url . 'api/v2/invoiceInitPay', [
                "payment_method_id" => $this->method,
                "cartTotal" => $this->amount * $this->quantity,
                "currency" => $this->currency,
                "customer" => [
                    "first_name" => $this->first_name,
                    "last_name" => $this->last_name,
                    "email" => $this->user_email,
                    "phone" => $this->user_phone,
                ],
                "redirectionUrls" => [
                    "successUrl" => $returnUrl . "/success",
                    "failUrl" => $returnUrl . "/failed",
                    "pendingUrl" => $returnUrl . "/pending"
                ],
                "cartItems" => [
                    [
                        "name" => $this->item_name,
                        "price" => $this->amount,
                        "quantity" => $this->quantity
                    ],
                ],
                "payLoad" => [
                    $this->payload
                ],
                "redirectOption" => true,
                "lang" => $this->language

            ]);
            $status = $response?->offsetGet('status');
            if ($status == 'success')
            {
                $data = $response?->offsetGet('data');
                return [
                    'status' => $status,
                    'invoice_id' => $data['invoice_id'],
                    'invoice_key' => $data['invoice_key'],
                    'link' => $data['payment_data']['redirectTo'],
                ];
            }
            else
            {
                return [
                    'status' => $status,
                    'message' => __('fawaterak::messages.Process_Has_Been_Blocked_From_System'),
                ];
            }
        }
        catch (\Exception $e) {
            return [
                'status' => $status,
                'message' => __('fawaterak::messages.PAYMENT_FAILED_WITH_CODE', ['CODE' => $e->getCode()]),
            ];
        }
    }
}
