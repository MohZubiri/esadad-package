<?php

namespace ESadad\PaymentGateway\Services;

use SoapClient;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use ESadad\PaymentGateway\Models\ESadadTransaction;
use ESadad\PaymentGateway\Models\ESadadLog;
use Carbon\Carbon;

class ESadadSoapService
{
    /**
     * SOAP clients for each service
     *
     * @var array
     */
    protected $soapClients = [];

    /**
     * Merchant credentials
     *
     * @var array
     */
    protected $merchantCredentials;

    /**
     * Public key for encryption
     *
     * @var resource
     */
    protected $publicKey;

    /**
     * Cache key for token
     *
     * @var string
     */
    protected $tokenCacheKey;

    /**
     * Create a new ESadadSoapService instance.
     */
    public function __construct()
    {
        // Set merchant credentials
        $this->merchantCredentials = [
            'code' => config('esadad.merchant_code'),
            'password' => config('esadad.merchant_password'),
        ];

        // Set token cache key
        $this->tokenCacheKey = 'esadad_token_' . $this->merchantCredentials['code'];

        // Load public key if path is provided
        $publicKeyPath = config('esadad.public_key_path');
        if (!empty($publicKeyPath) && file_exists($publicKeyPath)) {
            $this->publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
        }
    }

    /**
     * Get SOAP client for a specific service
     *
     * @param string $service Service name (authentication, payment_initiation, payment_request, payment_confirm)
     * @return SoapClient
     * @throws Exception
     */
    protected function getSoapClient($service)
    {
        if (!isset($this->soapClients[$service])) {
            if (!isset(config('esadad.wsdl_urls')[$service])) {
                throw new Exception("WSDL URL for service '{$service}' is not defined");
            }

            $wsdlUrl = config('esadad.wsdl_urls')[$service];
            $options = config('esadad.soap_options', []);

            try {
                $this->soapClients[$service] = new SoapClient($wsdlUrl, $options);
            } catch (Exception $e) {
                $this->logError($service, "Failed to create SOAP client: {$e->getMessage()}", [
                    'wsdl_url' => $wsdlUrl,
                    'options' => $options,
                ]);
                throw $e;
            }
        }

        return $this->soapClients[$service];
    }

    /**
     * Get valid token or authenticate to get a new one
     *
     * @param bool $forceNew Force new token even if a valid one exists
     * @return array Response containing token key and expiry date
     * @throws Exception
     */
    public function getToken($forceNew = false)
    {
        // Check if we have a valid token in cache
        if (!$forceNew) {
            $cachedToken = Cache::get($this->tokenCacheKey);
            if ($cachedToken) {
                $this->logInfo('token_cache', 'Using cached token', [
                    'token_key' => $cachedToken['tokenKey'],
                    'expiry_date' => $cachedToken['expiryDate'],
                ]);
                return $cachedToken;
            }
        }

        // No valid token found or forced new, authenticate to get a new one
        $response = $this->authenticate();
        
        // If authentication successful, cache the token
        if (isset($response['errorCode']) && $response['errorCode'] === '000') {
            $this->cacheToken($response);
        }
        
        return $response;
    }

    /**
     * Cache token with expiry date
     *
     * @param array $response Authentication response
     * @return void
     */
    protected function cacheToken($response)
    {
        if (isset($response['tokenKey']) && isset($response['expiryDate'])) {
            // Parse expiry date from format YYYYMMDDHHmmss
            try {
                // Convert e-SADAD date format to Carbon instance
                $expiryDate = Carbon::createFromFormat('YmdHis', $response['expiryDate']);
                
                // Calculate minutes until expiry
                $minutesUntilExpiry = Carbon::now()->diffInMinutes($expiryDate);
                
                // Cache token with 5 minutes buffer before actual expiry
                $cacheMinutes = max(1, $minutesUntilExpiry - 5);
                
                Cache::put($this->tokenCacheKey, $response, $cacheMinutes * 60);
                
                $this->logInfo('token_cache', 'Token cached', [
                    'token_key' => $response['tokenKey'],
                    'expiry_date' => $response['expiryDate'],
                    'cache_minutes' => $cacheMinutes,
                ]);
            } catch (Exception $e) {
                $this->logError('token_cache', "Failed to cache token: {$e->getMessage()}", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Authentication Service
     *
     * The merchant obtains a Token Key which must be used for all subsequent communications
     * with the e-SADAD Gateway.
     *
     * @return array Response containing token key and expiry date or error
     * @throws Exception
     */
    public function authenticate()
    {
        $service = 'authentication';
        $transactionData = [
            'merchant_code' => $this->merchantCredentials['code'],
            'status' => 'initiated',
        ];

        try {
            // Create transaction record
            $transaction = ESadadTransaction::create($transactionData);

            // Get SOAP client
            $client = $this->getSoapClient($service);

            // Prepare request data
            $requestData = [
                'merchantCode' => $this->merchantCredentials['code'],
                'password' => $this->merchantCredentials['password'],
            ];

            // Log request
            $this->logInfo($service, 'Authentication request', [
                'transaction_id' => $transaction->id,
                'request' => $requestData,
            ], $transaction->id);

            // Call authentication service
            $response = $client->merc_online_authentication(
                $this->merchantCredentials['code'],
                $this->merchantCredentials['password']
            );

            // Update transaction with response
            $transaction->update([
                'token_key' => $response['tokenKey'] ?? null,
                'error_code' => $response['errorCode'] ?? null,
                'error_description' => $response['errorDescription'] ?? null,
                'status' => ($response['errorCode'] ?? '') === '000' ? 'confirmed' : 'failed',
                'response_data' => $response,
            ]);

            // Log response
            $this->logInfo($service, 'Authentication response', [
                'transaction_id' => $transaction->id,
                'response' => $response,
            ], $transaction->id);

            return $response;
        } catch (Exception $e) {
            // Update transaction with error
            if (isset($transaction)) {
                $transaction->update([
                    'status' => 'failed',
                    'error_code' => 'EXCEPTION',
                    'error_description' => $e->getMessage(),
                ]);
            }

            // Log error
            $this->logError($service, "Authentication error: {$e->getMessage()}", [
                'transaction_id' => $transaction->id ?? null,
                'exception' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], $transaction->id ?? null);

            throw $e;
        }
    }

    /**
     * Payment Initiation Service
     *
     * The merchant initiates a payment request for the customer.
     * The customer's bank will then send an OTP to the customer's mobile phone.
     *
     * @param string $tokenKey Token key obtained from authentication (optional, will use cached token if not provided)
     * @param string $customerId Customer's e-SADAD Online ID
     * @param string $customerPassword Customer's password
     * @return array Response containing success or error
     * @throws Exception
     */
    public function initiatePayment($tokenKey = null, $customerId, $customerPassword)
    {
        // If no token provided, get a valid token
        if (empty($tokenKey)) {
            $tokenResponse = $this->getToken();
            if ($tokenResponse['errorCode'] !== '000') {
                return $tokenResponse; // Return authentication error
            }
            $tokenKey = $tokenResponse['tokenKey'];
        }
        
        $service = 'payment_initiation';
        $transactionData = [
            'merchant_code' => $this->merchantCredentials['code'],
            'token_key' => $tokenKey,
            'customer_id' => $customerId,
            'status' => 'initiated',
            'process_date' => now(),
        ];

        try {
            // Create transaction record
            $transaction = ESadadTransaction::create($transactionData);

            // Get SOAP client
            $client = $this->getSoapClient($service);

            // Encrypt customer password
            $encryptedPassword = $this->encryptData($customerPassword);

            // Prepare transaction record
            $transRec = [
                'sepOnlineNo' => $customerId,
                'password' => $encryptedPassword,
            ];

            // Prepare request data for logging (without sensitive info)
            $requestDataForLog = [
                'merchantCode' => $this->merchantCredentials['code'],
                'tokenKey' => $tokenKey,
                'transRec' => [
                    'sepOnlineNo' => $customerId,
                    'password' => '[ENCRYPTED]',
                ],
            ];

            // Log request
            $this->logInfo($service, 'Payment initiation request', [
                'transaction_id' => $transaction->id,
                'request' => $requestDataForLog,
            ], $transaction->id);

            // Update transaction with request data
            $transaction->update([
                'request_data' => $requestDataForLog,
            ]);

            // Call payment initiation service
            $response = $client->merc_online_payment_initiation(
                $this->merchantCredentials['code'],
                $tokenKey,
                $transRec
            );

            // Update transaction with response
            $transaction->update([
                'error_code' => $response['errorCode'] ?? null,
                'error_description' => $response['errorDescription'] ?? null,
                'status' => ($response['errorCode'] ?? '') === '000' ? 'requested' : 'failed',
                'response_data' => $response,
            ]);

            // Log response
            $this->logInfo($service, 'Payment initiation response', [
                'transaction_id' => $transaction->id,
                'response' => $response,
            ], $transaction->id);

            return $response;
        } catch (Exception $e) {
            // Update transaction with error
            if (isset($transaction)) {
                $transaction->update([
                    'status' => 'failed',
                    'error_code' => 'EXCEPTION',
                    'error_description' => $e->getMessage(),
                ]);
            }

            // Log error
            $this->logError($service, "Payment initiation error: {$e->getMessage()}", [
                'transaction_id' => $transaction->id ?? null,
                'exception' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], $transaction->id ?? null);

            throw $e;
        }
    }

    /**
     * Payment Request Service
     *
     * After the customer inputs the OTP, the merchant submits the actual payment request.
     *
     * @param string $tokenKey Token key obtained from authentication (optional, will use cached token if not provided)
     * @param string $customerId Customer's e-SADAD Online ID
     * @param string $otp One-time password received by customer
     * @param string $invoiceId Invoice ID
     * @param float $amount Transaction amount
     * @param string $currency Currency code (default: 886 for Yemeni Riyal)
     * @return array Response containing transaction details or error
     * @throws Exception
     */
    public function requestPayment($tokenKey = null, $customerId, $otp, $invoiceId, $amount, $currency = null)
    {
        // If no token provided, get a valid token
        if (empty($tokenKey)) {
            $tokenResponse = $this->getToken();
            if ($tokenResponse['errorCode'] !== '000') {
                return $tokenResponse; // Return authentication error
            }
            $tokenKey = $tokenResponse['tokenKey'];
        }
        
        $service = 'payment_request';
        $currency = $currency ?? config('esadad.currency_code', '886');
        
        $transactionData = [
            'merchant_code' => $this->merchantCredentials['code'],
            'token_key' => $tokenKey,
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'initiated',
            'process_date' => now(),
        ];

        try {
            // Create transaction record
            $transaction = ESadadTransaction::create($transactionData);

            // Get SOAP client
            $client = $this->getSoapClient($service);

            // Encrypt OTP
            $encryptedOtp = $this->encryptData($otp);

            // Prepare transaction record
            $transRec = [
                'sepOnlineNo' => $customerId,
                'otp' => $encryptedOtp,
                'invoiceId' => $invoiceId,
                'processDate' => date('YmdHis'),
                'trxAmount' => $amount,
                'currency' => $currency,
            ];

            // Prepare request data for logging (without sensitive info)
            $requestDataForLog = [
                'merchantCode' => $this->merchantCredentials['code'],
                'tokenKey' => $tokenKey,
                'transRec' => [
                    'sepOnlineNo' => $customerId,
                    'otp' => '[ENCRYPTED]',
                    'invoiceId' => $invoiceId,
                    'processDate' => $transRec['processDate'],
                    'trxAmount' => $amount,
                    'currency' => $currency,
                ],
            ];

            // Log request
            $this->logInfo($service, 'Payment request', [
                'transaction_id' => $transaction->id,
                'request' => $requestDataForLog,
            ], $transaction->id);

            // Update transaction with request data
            $transaction->update([
                'request_data' => $requestDataForLog,
            ]);

            // Call payment request service
            $response = $client->merc_online_payment_request(
                $this->merchantCredentials['code'],
                $tokenKey,
                $transRec
            );

            // Update transaction with response
            $transaction->update([
                'bank_trx_id' => $response['bankTrxId'] ?? null,
                'sep_trx_id' => $response['sepTrxId'] ?? null,
                'stmt_date' => isset($response['stmtDate']) ? date('Y-m-d H:i:s', strtotime($response['stmtDate'])) : null,
                'error_code' => $response['errorCode'] ?? null,
                'error_description' => $response['errorDescription'] ?? null,
                'status' => ($response['errorCode'] ?? '') === '000' ? 'requested' : 'failed',
                'response_data' => $response,
            ]);

            // Log response
            $this->logInfo($service, 'Payment request response', [
                'transaction_id' => $transaction->id,
                'response' => $response,
            ], $transaction->id);

            return $response;
        } catch (Exception $e) {
            // Update transaction with error
            if (isset($transaction)) {
                $transaction->update([
                    'status' => 'failed',
                    'error_code' => 'EXCEPTION',
                    'error_description' => $e->getMessage(),
                ]);
            }

            // Log error
            $this->logError($service, "Payment request error: {$e->getMessage()}", [
                'transaction_id' => $transaction->id ?? null,
                'exception' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], $transaction->id ?? null);

            throw $e;
        }
    }

    /**
     * Payment Confirmation Service
     *
     * After payment is successfully completed, the merchant sends a confirmation
     * to finalize the transaction process.
     *
     * @param string $tokenKey Token key obtained from authentication (optional, will use cached token if not provided)
     * @param string $customerId Customer's e-SADAD Online ID
     * @param array $transactionDetails Transaction details from payment request
     * @param float $amount Transaction amount
     * @param string $currency Currency code (default: 886 for Yemeni Riyal)
     * @param string $paymentStatus Payment status (default: PmtNew)
     * @return array Response containing success or error
     * @throws Exception
     */
    public function confirmPayment($tokenKey = null, $customerId, $transactionDetails, $amount, $currency = null, $paymentStatus = 'PmtNew')
    {
        // If no token provided, get a valid token
        if (empty($tokenKey)) {
            $tokenResponse = $this->getToken();
            if ($tokenResponse['errorCode'] !== '000') {
                return $tokenResponse; // Return authentication error
            }
            $tokenKey = $tokenResponse['tokenKey'];
        }
        
        $service = 'payment_confirm';
        $currency = $currency ?? config('esadad.currency_code', '886');
        
        // Find existing transaction or create a new one
        $transaction = ESadadTransaction::where('bank_trx_id', $transactionDetails['bank_trx_id'])
            ->where('sep_trx_id', $transactionDetails['sep_trx_id'])
            ->first();
            
        if (!$transaction) {
            $transactionData = [
                'merchant_code' => $this->merchantCredentials['code'],
                'token_key' => $tokenKey,
                'customer_id' => $customerId,
                'invoice_id' => $transactionDetails['invoice_id'],
                'bank_trx_id' => $transactionDetails['bank_trx_id'],
                'sep_trx_id' => $transactionDetails['sep_trx_id'],
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'requested',
                'process_date' => now(),
                'stmt_date' => isset($transactionDetails['stmt_date']) ? date('Y-m-d H:i:s', strtotime($transactionDetails['stmt_date'])) : null,
            ];
            
            $transaction = ESadadTransaction::create($transactionData);
        }

        try {
            // Get SOAP client
            $client = $this->getSoapClient($service);

            // Prepare transaction record
            $transRec = [
                'sepOnlineNo' => $customerId,
                'bankTrxId' => $transactionDetails['bank_trx_id'],
                'sepTrxId' => $transactionDetails['sep_trx_id'],
                'invoiceId' => $transactionDetails['invoice_id'],
                'pmtStatus' => $paymentStatus,
                'stmtDate' => $transactionDetails['stmt_date'],
                'processDate' => date('YmdHis'),
                'trxAmount' => $amount,
                'currency' => $currency,
            ];

            // Log request
            $this->logInfo($service, 'Payment confirmation request', [
                'transaction_id' => $transaction->id,
                'request' => [
                    'merchantCode' => $this->merchantCredentials['code'],
                    'tokenKey' => $tokenKey,
                    'transRec' => $transRec,
                ],
            ], $transaction->id);

            // Update transaction with request data
            $transaction->update([
                'request_data' => [
                    'merchantCode' => $this->merchantCredentials['code'],
                    'tokenKey' => $tokenKey,
                    'transRec' => $transRec,
                ],
                'payment_status' => $paymentStatus,
            ]);

            // Call payment confirmation service
            $response = $client->merc_online_payment_confirm(
                $this->merchantCredentials['code'],
                $tokenKey,
                $transRec
            );

            // Update transaction with response
            $transaction->update([
                'error_code' => $response['errorCode'] ?? null,
                'error_description' => $response['errorDescription'] ?? null,
                'status' => ($response['errorCode'] ?? '') === '000' ? 'confirmed' : 'failed',
                'response_data' => $response,
            ]);

            // Log response
            $this->logInfo($service, 'Payment confirmation response', [
                'transaction_id' => $transaction->id,
                'response' => $response,
            ], $transaction->id);

            return $response;
        } catch (Exception $e) {
            // Update transaction with error
            $transaction->update([
                'status' => 'failed',
                'error_code' => 'EXCEPTION',
                'error_description' => $e->getMessage(),
            ]);

            // Log error
            $this->logError($service, "Payment confirmation error: {$e->getMessage()}", [
                'transaction_id' => $transaction->id,
                'exception' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], $transaction->id);

            throw $e;
        }
    }

    /**
     * Encrypt data using e-SADAD public key
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data
     * @throws Exception
     */
    protected function encryptData($data)
    {
        if (!$this->publicKey) {
            // If public key is not available, return a placeholder for development
            // In production, this should throw an exception
            return 'encrypted_' . $data;
        }

        $encrypted = '';
        if (!openssl_public_encrypt($data, $encrypted, $this->publicKey)) {
            throw new Exception('Failed to encrypt data with public key');
        }

        return base64_encode($encrypted);
    }

    /**
     * Log an info message
     *
     * @param string $service Service name
     * @param string $message Log message
     * @param array $context Context data
     * @param int|null $transactionId Related transaction ID
     * @return void
     */
    protected function logInfo($service, $message, $context = [], $transactionId = null)
    {
        $this->log('info', $service, $message, $context, $transactionId);
    }

    /**
     * Log an error message
     *
     * @param string $service Service name
     * @param string $message Error message
     * @param array $context Context data
     * @param int|null $transactionId Related transaction ID
     * @return void
     */
    protected function logError($service, $message, $context = [], $transactionId = null)
    {
        $this->log('error', $service, $message, $context, $transactionId);
    }

    /**
     * Log a message to both Laravel log and database
     *
     * @param string $level Log level
     * @param string $service Service name
     * @param string $message Log message
     * @param array $context Context data
     * @param int|null $transactionId Related transaction ID
     * @return void
     */
    protected function log($level, $service, $message, $context = [], $transactionId = null)
    {
        // Log to Laravel log
        Log::channel(config('esadad.log_channel', 'stack'))->$level($message, $context);

        // Log to database
        try {
            ESadadLog::create([
                'transaction_id' => $transactionId,
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'service' => $service,
                'request_data' => $context['request'] ?? null,
                'response_data' => $context['response'] ?? null,
            ]);
        } catch (Exception $e) {
            // If database logging fails, just log to Laravel log
            Log::error("Failed to log to database: {$e->getMessage()}", [
                'exception' => $e,
                'original_message' => $message,
                'original_context' => $context,
            ]);
        }
    }
}
