<?php

return [
    'PAYMENT_DONE' => 'operation completed successfully',
    'PAYMENT_FAILED' => 'An error occurred while executing the operation',
    'PAYMENT_FAILED_WITH_CODE' => 'An error occurred while executing the operation, error code: :CODE',
    'Process_Has_Been_Blocked_From_System'=>"Process Has Been Blocked From System",
    'Security_checks_are_not_passed_by_the_system'=>"Security checks are not passed by the system",
    "Balance_is_not_enough"=>"Balance is not enough",
    'Declined'=>'Declined',
    'Your_card_is_not_secured_with_3D_protection_Check_with_the_bank'=>"Your card is not secured with 3D protection",
    'Incorrect_card_expiration_date'=>"Incorrect card expiration date",
    "The_transaction_was_rejected_by_your_bank_please_check_with_your_bank"=>"The transaction was rejected by your bank please check with your bank",
    "The_OTP_number_was_entered_incorrectly"=>"The OTP number was entered incorrectly",
    "An_error_occurred_while_executing_the_operation"=>"An error occurred while executing the operation",
    'Your_card_is_not_authorized_with_3D_secure'=>"Your card is not authorized with 3D secure",

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'AUTH_FAILED' => 'Fawaterak authentication failed',
    'AUTH_MISSING_CREDENTIALS' => 'Fawaterak client id and client secret are not configured',
    'AUTH_TOKEN_EXPIRED' => 'Fawaterak access token expired',

    /*
    |--------------------------------------------------------------------------
    | Transactions
    |--------------------------------------------------------------------------
    */
    'TRANSACTION_CREATED' => 'Transaction created successfully',
    'TRANSACTION_NOT_FOUND' => 'Transaction not found',
    'PAYMENT_PENDING' => 'Payment is pending, awaiting customer action',
    'PAYMENT_REFERENCE_ISSUED' => 'Payment reference issued successfully',
    'REFERENCE_EXPIRED' => 'The payment reference has expired',
    'REFERENCE_CANCELED' => 'The payment reference has been canceled',
    'PAYMENT_METHODS_FETCH_FAILED' => 'Could not load payment methods',

    /*
    |--------------------------------------------------------------------------
    | Refunds
    |--------------------------------------------------------------------------
    */
    'REFUND_APPROVED' => 'Refund request approved',
    'REFUND_CREATED' => 'Refund request submitted successfully',
    'REFUND_DELETED' => 'Refund request canceled successfully',
    'REFUND_FAILED' => 'The refund request could not be processed',

    /*
    |--------------------------------------------------------------------------
    | Tokenization and recurring
    |--------------------------------------------------------------------------
    */
    'TOKEN_CREATED' => 'Card token created successfully',
    'TOKEN_DELETED' => 'Card token deleted successfully',
    'TOKEN_PAYMENT_FAILED' => 'The saved-card payment could not be completed',
    'RECURRING_PAYMENT_DONE' => 'Recurring payment completed successfully',

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    'INVALID_WEBHOOK_PAYLOAD' => 'The webhook payload could not be recognized',
    'VALIDATION_FAILED' => 'The request data is invalid',
    'MISSING_REQUIRED_FIELD' => ':FIELD is required to use Fawaterak',
];
