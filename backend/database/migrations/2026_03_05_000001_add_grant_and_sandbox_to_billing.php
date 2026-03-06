<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('is_manual_grant')->default(false)->after('status')->comment('Identifica assinaturas concedidas manualmente');
            $table->boolean('is_sandbox')->default(false)->after('is_manual_grant')->comment('Identifica assinaturas criadas em ambiente de testes');
            $table->foreignId('granted_by')->nullable()->after('is_sandbox')->constrained('users')->nullOnDelete();
            $table->text('granted_reason')->nullable()->after('granted_by');
        });

        Schema::table('payment_logs', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('gateway')->comment('Identifica transações de ambiente de testes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['granted_by']);
            $table->dropColumn(['is_manual_grant', 'is_sandbox', 'granted_by', 'granted_reason']);
        });

        Schema::table('payment_logs', function (Blueprint $table) {
            $table->dropColumn('is_sandbox');
        });
    }
};
