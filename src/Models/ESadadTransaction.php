<?php

namespace ESadad\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;

class ESadadTransaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_code',
        'token_key',
        'customer_id',
        'invoice_id',
        'bank_trx_id',
        'sep_trx_id',
        'amount',
        'currency',
        'process_date',
        'stmt_date',
        'status',
        'payment_status',
        'error_code',
        'error_description',
        'request_data',
        'response_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'float',
        'request_data' => 'array',
        'response_data' => 'array',
        'process_date' => 'datetime',
        'stmt_date' => 'datetime',
    ];

    /**
     * Create a new instance of the model.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->table = config('esadad.database.transactions_table', 'esadad_transactions');
    }

    /**
     * Scope a query to only include successful transactions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuccessful($query)
    {
        return $query->where('error_code', '000');
    }

    /**
     * Scope a query to only include failed transactions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('error_code', '!=', '000');
    }

    /**
     * Scope a query to only include transactions with a specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get the transaction status label.
     *
     * @return string
     */
    public function getStatusLabelAttribute()
    {
        $statuses = [
            'initiated' => 'تم البدء',
            'requested' => 'تم الطلب',
            'confirmed' => 'تم التأكيد',
            'failed' => 'فشلت',
            'cancelled' => 'ملغية',
        ];

        return $statuses[$this->status] ?? $this->status;
    }
}
