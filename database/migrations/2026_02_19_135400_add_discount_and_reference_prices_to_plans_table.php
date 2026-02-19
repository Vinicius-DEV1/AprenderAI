<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('monthly_price', 8, 2)->nullable()->after('price');
            $table->decimal('annual_price', 8, 2)->nullable()->after('monthly_price');
            $table->integer('discount_percentage')->default(0)->after('annual_price');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['monthly_price', 'annual_price', 'discount_percentage']);
        });
    }
};
