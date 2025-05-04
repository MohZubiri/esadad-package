<?php

namespace ESadad\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;

class ESadadLog extends Model
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
        'transaction_id',
        'level',
        'message',
        'context',
        'service',
        'request_data',
        'response_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'context' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    /**
     * Create a new instance of the model.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->table = config('esadad.database.logs_table', 'esadad_logs');
    }

    /**
     * Get the transaction that owns the log.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo(ESadadTransaction::class, 'transaction_id');
    }

    /**
     * Scope a query to only include logs with a specific level.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $level
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope a query to only include logs for a specific service.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $service
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForService($query, $service)
    {
        return $query->where('service', $service);
    }
}
