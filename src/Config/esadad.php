<?php

return [
    /*
    |--------------------------------------------------------------------------
    | e-SADAD Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the e-SADAD payment gateway.
    |
    */

    // Merchant credentials
    'merchant_code' => env('ESADAD_MERCHANT_CODE', ''),
    'merchant_password' => env('ESADAD_MERCHANT_PASSWORD', ''),

    // SOAP service URLs
    'wsdl_urls' => [
        'authentication' => env('ESADAD_AUTH_WSDL', 'https://172.19.0.17:8002/EBPP_ONLINE-MERC_ONLINE_AUTHENTICATION-context-root/MERC_ONLINE_AUTHENTICATIONPort?wsdl'),
        'payment_initiation' => env('ESADAD_INIT_WSDL', 'https://172.19.0.17:8002/EBPP_ONLINE-MERC_ONLINE_PAYMENT_INITIATION-context-root/MERC_ONLINE_PAYMENT_INITIATIONPort?WSDL'),
        'payment_request' => env('ESADAD_REQUEST_WSDL', 'https://172.19.0.17:8002/EBPP_ONLINE-MERC_ONLINE_PAYMENT_REQUEST-context-root/MERC_ONLINE_PAYMENT_REQUESTPort?WSDL'),
        'payment_confirm' => env('ESADAD_CONFIRM_WSDL', 'https://172.19.0.17:8002/EBPP_ONLINE-MERC_ONLINE_PAYMENT_CONFIRM-context-root/MERC_ONLINE_PAYMENT_CONFIRMPort?WSDL'),
    ],

    // SOAP client options
    'soap_options' => [
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => env('APP_DEBUG') ? WSDL_CACHE_NONE : WSDL_CACHE_DISK,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]),
    ],

    // Encryption settings
    'public_key_path' => env('ESADAD_PUBLIC_KEY_PATH', ''),

    // Currency code (ISO standard)
    'currency_code' => env('ESADAD_CURRENCY_CODE', '886'), // Default: Yemeni Riyal

    // Database settings
    'database' => [
        'connection' => env('ESADAD_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
        'transactions_table' => env('ESADAD_TRANSACTIONS_TABLE', 'esadad_transactions'),
        'logs_table' => env('ESADAD_LOGS_TABLE', 'esadad_logs'),
    ],

    // Routes
    'routes' => [
        'prefix' => 'esadad',
        'middleware' => ['web'],
    ],
];
