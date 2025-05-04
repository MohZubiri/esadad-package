<?php

namespace ESadad\PaymentGateway\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array authenticate()
 * @method static array initiatePayment(string $tokenKey, string $customerId, string $customerPassword)
 * @method static array requestPayment(string $tokenKey, string $customerId, string $otp, string $invoiceId, float $amount, string $currency = null)
 * @method static array confirmPayment(string $tokenKey, string $customerId, array $transactionDetails, float $amount, string $currency = null, string $paymentStatus = 'PmtNew')
 * @method static \Illuminate\Database\Eloquent\Collection getTransactions(array $filters = [])
 * @method static \ESadad\PaymentGateway\Models\ESadadTransaction findTransaction(int $id)
 * @method static \ESadad\PaymentGateway\Models\ESadadTransaction findTransactionByInvoiceId(string $invoiceId)
 * 
 * @see \ESadad\PaymentGateway\ESadad
 */
class ESadad extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'esadad';
    }
}
