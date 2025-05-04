<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateESadadLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('esadad.database.logs_table', 'esadad_logs'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained(config('esadad.database.transactions_table', 'esadad_transactions'))->onDelete('cascade');
            $table->string('level')->default('info'); // info, warning, error
            $table->string('message');
            $table->json('context')->nullable();
            $table->string('service')->nullable(); // authentication, payment_initiation, payment_request, payment_confirmation
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('transaction_id');
            $table->index('level');
            $table->index('service');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('esadad.database.logs_table', 'esadad_logs'));
    }
}
