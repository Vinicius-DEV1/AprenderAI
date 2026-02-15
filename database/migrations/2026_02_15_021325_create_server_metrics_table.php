<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->float('cpu_usage'); // %
            $table->float('ram_usage'); // %
            $table->bigInteger('net_rx_speed')->default(0); // bytes/s
            $table->bigInteger('net_tx_speed')->default(0); // bytes/s
            $table->timestamps();

            $table->index('created_at'); // Critical for history queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
