<?php

namespace DavidMaximous\Fawaterak\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class FawaterakVerify extends BaseController
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


/*    /**
     * @param Request $request
     * @return array|void
     */
    public function verifyCallback(Request $request)
    {
        //return $request->all();
        $invoice_id = $request?->offsetGet('invoice_id');
        if($request?->offsetGet('invoice_status') == 'paid')
        {
            $invoice_key = $request?->offsetGet('invoice_key');
            $payment_method = $request?->offsetGet('payment_method');
            $hash = hash_hmac('sha256', 'InvoiceId='.$invoice_id.'&InvoiceKey='.$invoice_key.'&PaymentMethod='.$payment_method, env('FAWATERAK_API_KEY'),false);
            if($hash == $request?->offsetGet('hashKey'))
            {
                $status = $this->getTransactionData($invoice_id);
                if($status['status'] == 'success')
                {
                    if($status['invoice_id'] == $invoice_id && $status['invoice_key'] == $invoice_key)
                    {
                        return [
                            'success' => true,
                            'invoice_id' => $invoice_id,
                            'payload' => $status['payload'],
                            'amount_paid' => $status['process_data']['total'],
                            'message' => __('fawaterak::messages.PAYMENT_DONE'),
                            'process_data' => $status['process_data']
                        ];
                    }
                    else $this->securityResponse($invoice_id, $status['process_data']);
                }
                else $this->failedResponse($invoice_id, $status['status'], $status['process_data']);
            }
            else $this->securityResponse($invoice_id, $request->all()); Log::info($request);
        }
        else $this->failedResponse($invoice_id, $request->offsetGet('statusCode'), $request->all());
    }

    public function getTransactionData($invoice_id)
    {
        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->fawaterak_api_key
        ])->get($this->fawaterak_url . 'api/v2/getInvoiceData/' . $invoice_id);
        //return $request;
        $status = $request?->offsetGet('status');
        //return $request['data']['paid'];
        if($status == 'success' && $request['data']['paid'] == 1)
        {
            return [
                'status' => $status,
                'invoice_id' => $request['data']['invoice_id'],
                'invoice_key' => $request['data']['invoice_key'],
                'payload' => $request['data']['pay_load'],
                'process_data' => $request['data'],
            ];
        }
        else {
            return [
                'status' => 'failed',
                'process_data' => $request->body(),
            ];
        }

    }

    public function securityResponse($reference_id, $process_data)
    {
        return [
            'success' => false,
            'payment_id'=>$reference_id,
            'message' => __('fawaterak::messages.Security_checks_are_not_passed_by_the_system'),
            'process_data' => $process_data
        ];
    }

    public function failedResponse($reference_id, $code, $process_data)
    {
        return [
            'success' => false,
            'payment_id'=>$reference_id,
            'message' => __('fawaterak::messages.PAYMENT_FAILED_WITH_CODE', ['CODE' => $code]),
            'process_data' => $process_data
        ];
    }
}
