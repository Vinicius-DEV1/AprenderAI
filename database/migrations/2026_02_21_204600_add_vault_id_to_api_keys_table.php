<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->foreignId('vault_id')->nullable()->after('id')->constrained('api_key_vaults')->nullOnDelete();
            
            // Make legacy columns nullable as they will now be fetched from vault
            $table->string('provider')->nullable()->change();
            $table->text('key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['vault_id']);
            $table->dropColumn('vault_id');
            $table->string('provider')->nullable(false)->change();
            $table->text('key')->nullable(false)->change();
        });
    }
};
