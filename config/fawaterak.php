<?php
return [
    'FAWATERAK_URL' => env('FAWATERAK_URL', "https://staging.fawaterk.com/"), //https://www.atfawry.com/ for production
    'FAWATERAK_API_KEY' => env('FAWATERAK_API_KEY'),
    'FAWATERAK_REDIRECT_URL' => "payment-redirect", //Route name for the payment redirect
    'APP_NAME'=>env('APP_NAME'),
];
