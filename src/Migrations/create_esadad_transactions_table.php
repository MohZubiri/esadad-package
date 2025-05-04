<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateESadadTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('esadad.database.transactions_table', 'esadad_transactions'), function (Blueprint $table) {
            $table->id();
            $table->string('merchant_code');
            $table->string('token_key')->nullable();
            $table->string('customer_id');
            $table->string('invoice_id');
            $table->string('bank_trx_id')->nullable();
            $table->string('sep_trx_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('886'); // Default: Yemeni Riyal
            $table->timestamp('process_date')->nullable();
            $table->timestamp('stmt_date')->nullable();
            $table->string('status')->default('initiated'); // initiated, requested, confirmed, failed, cancelled
            $table->string('payment_status')->nullable(); // PmtNew, PmtCanc
            $table->string('error_code')->nullable();
            $table->string('error_description')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('merchant_code');
            $table->index('customer_id');
            $table->index('invoice_id');
            $table->index('bank_trx_id');
            $table->index('sep_trx_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('esadad.database.transactions_table', 'esadad_transactions'));
    }
}
