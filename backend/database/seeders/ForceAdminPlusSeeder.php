<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ForceAdminPlusSeeder
 *
 * Garante cirurgicamente que admin@aprenderai.com seja reconhecido
 * como Plano PLUS ativo em todo o sistema (backend + frontend).
 *
 * O que este seeder faz:
 *  1. Localiza o usuário admin@aprenderai.com
 *  2. Localiza o plano com slug='plus'
 *  3. Cancela quaisquer subscriptions ativas de outros planos
 *  4. Cria/atualiza uma subscription PLUS com current_period_end = +10 anos
 *  5. Sincroniza plan_id diretamente no user (fallback do activePlan())
 *
 * O que este seeder NÃO faz:
 *  - Não altera outros usuários
 *  - Não altera estrutura de planos
 *  - Não mexe em migrations
 *  - Não altera lógica de quotas
 */
class ForceAdminPlusSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Localizar o usuário admin ──────────────────────────────────────
        $user = User::where('email', 'admin@aprenderai.com')->first();

        if (!$user) {
            $this->command->error('Usuário admin@aprenderai.com não encontrado!');
            return;
        }

        $this->command->info("Usuário encontrado: [{$user->id}] {$user->name}");

        // ── 2. Localizar o plano Plus ─────────────────────────────────────────
        $plan = Plan::where('slug', 'plus')->first();

        if (!$plan) {
            $this->command->error('Plano com slug="plus" não encontrado! Execute PlanSeeder primeiro.');
            return;
        }

        $this->command->info("Plano encontrado: [{$plan->id}] {$plan->name}");

        // ── 3. Cancelar subscriptions ativas de outros planos (apenas deste user) ──
        $cancelledCount = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('plan_id', '!=', $plan->id)
            ->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

        if ($cancelledCount > 0) {
            $this->command->warn("{$cancelledCount} subscription(s) anteriores canceladas.");
        }

        // ── 4. Criar ou atualizar subscription PLUS ───────────────────────────
        $subscription = Subscription::where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->latest()
            ->first();

        if ($subscription) {
            // Já existe — apenas garantir que está ativa e com expiry no futuro
            $subscription->update([
                'status' => 'active',
                'is_manual_grant' => true,
                'is_sandbox' => true,
                'current_period_start' => now(),
                'current_period_end' => now()->addYears(10),
                'canceled_at' => null,
            ]);
            $this->command->info('Subscription Plus existente reativada e atualizada.');
        } else {
            // Criar nova subscription
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'is_manual_grant' => true,
                'is_sandbox' => true,
                'gateway' => 'manual',
                'gateway_id' => 'force-admin-plus-' . now()->format('Ymd'),
                'current_period_start' => now(),
                'current_period_end' => now()->addYears(10),
            ]);
            $this->command->info('Nova subscription Plus criada com vigência de 10 anos.');
        }

        // ── 5. Sincronizar plan_id no user (fallback do activePlan()) ─────────
        $user->update([
            'plan_id' => $plan->id,
            'plan_started_at' => now(),
            'plan_expires_at' => now()->addYears(10),
        ]);
        $this->command->info('plan_id, plan_started_at e plan_expires_at atualizados no user.');

        // ── Verificação final ─────────────────────────────────────────────────
        $user->refresh();
        $hasPlusPlan = $user->hasPlusPlan();

        $this->command->newLine();
        $this->command->table(
            ['Campo', 'Valor'],
            [
                ['Usuário', $user->email],
                ['plan_id (user)', $user->plan_id],
                ['Plano ativo', $user->activePlan()?->name ?? 'NENHUM'],
                ['hasPlusPlan()', $hasPlusPlan ? 'TRUE ✓' : 'FALSE ✗'],
                ['hasActiveSubscription()', $user->hasActiveSubscription() ? 'TRUE ✓' : 'FALSE ✗'],
            ]
        );

        if ($hasPlusPlan) {
            $this->command->info('✅ admin@aprenderai.com está corretamente reconhecido como PLUS ativo!');
        } else {
            $this->command->error('❌ Algo falhou. hasPlusPlan() ainda retorna false. Verifique manualmente.');
        }
    }
}
