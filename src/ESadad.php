<?php

namespace ESadad\PaymentGateway;

use ESadad\PaymentGateway\Services\ESadadSoapService;
use ESadad\PaymentGateway\Models\ESadadTransaction;
use Illuminate\Database\Eloquent\Collection;

class ESadad
{
    /**
     * The SOAP service instance.
     *
     * @var \ESadad\PaymentGateway\Services\ESadadSoapService
     */
    protected $soapService;

    /**
     * Create a new ESadad instance.
     *
     * @param \ESadad\PaymentGateway\Services\ESadadSoapService $soapService
     */
    public function __construct(ESadadSoapService $soapService)
    {
        $this->soapService = $soapService;
    }

    /**
     * Authenticate with e-SADAD gateway.
     *
     * @param bool $forceNew Force new token even if a valid one exists
     * @return array Response containing token key and expiry date or error
     */
    public function authenticate($forceNew = false)
    {
        if ($forceNew) {
            return $this->soapService->authenticate();
        }
        
        return $this->soapService->getToken($forceNew);
    }

    /**
     * Initiate payment (trigger OTP).
     *
     * @param string $customerId Customer's e-SADAD Online ID
     * @param string $customerPassword Customer's password
     * @param string $tokenKey Token key obtained from authentication (optional, will use cached token if not provided)
     * @return array Response containing success or error
     */
    public function initiatePayment($customerId, $customerPassword, $tokenKey = null)
    {
        return $this->soapService->initiatePayment($tokenKey, $customerId, $customerPassword);
    }

    /**
     * Request payment (with OTP).
     *
     * @param string $customerId Customer's e-SADAD Online ID
     * @param string $otp One-time password received by customer
     * @param string $invoiceId Invoice ID
     * @param float $amount Transaction amount
     * @param string $currency Currency code (default: 886 for Yemeni Riyal)
     * @param string $tokenKey Token key obtained from authentication (optional, will use cached token if not provided)
     * @return array Response containing transaction details or error
     */
    public function requestPayment($customerId, $otp, $invoiceId, $amount, $currency = null, $tokenKey = null)
    {
        return $this->soapService->requestPayment($tokenKey, $customerId, $otp, $invoiceId, $amount, $currency);
    }

    /**
     * Confirm payment.
     *
     * @param string $customerId Customer's e-SADAD Online ID
     * @param array $transactionDetails Transaction details from payment request
     * @param float $amount Transaction amount
     * @param string $currency Currency code (default: 886 for Yemeni Riyal)
     * @param string $paymentStatus Payment status (default: PmtNew)
     * @param string $tokenKey Token key obtained from authentication (optional, will use cached token if not provided)
     * @return array Response containing success or error
     */
    public function confirmPayment($customerId, $transactionDetails, $amount, $currency = null, $paymentStatus = 'PmtNew', $tokenKey = null)
    {
        return $this->soapService->confirmPayment($tokenKey, $customerId, $transactionDetails, $amount, $currency, $paymentStatus);
    }

    /**
     * Get transactions with optional filters.
     *
     * @param array $filters Filters to apply
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTransactions($filters = [])
    {
        $query = ESadadTransaction::query();

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        if (isset($filters['bank_trx_id'])) {
            $query->where('bank_trx_id', $filters['bank_trx_id']);
        }

        if (isset($filters['sep_trx_id'])) {
            $query->where('sep_trx_id', $filters['sep_trx_id']);
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        // Apply sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->get();
    }

    /**
     * Find a transaction by ID.
     *
     * @param int $id Transaction ID
     * @return \ESadad\PaymentGateway\Models\ESadadTransaction|null
     */
    public function findTransaction($id)
    {
        return ESadadTransaction::find($id);
    }

    /**
     * Find a transaction by invoice ID.
     *
     * @param string $invoiceId Invoice ID
     * @return \ESadad\PaymentGateway\Models\ESadadTransaction|null
     */
    public function findTransactionByInvoiceId($invoiceId)
    {
        return ESadadTransaction::where('invoice_id', $invoiceId)->first();
    }

    /**
     * Find transactions by customer ID.
     *
     * @param string $customerId Customer ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findTransactionsByCustomerId($customerId)
    {
        return ESadadTransaction::where('customer_id', $customerId)->get();
    }

    /**
     * Get successful transactions.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSuccessfulTransactions()
    {
        return ESadadTransaction::successful()->get();
    }

    /**
     * Get failed transactions.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFailedTransactions()
    {
        return ESadadTransaction::failed()->get();
    }
}
