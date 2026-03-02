<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class QuotaService
{
    /**
     * Consome limite de uma feature para o ciclo atual do usuário, previnindo Race Conditions.
     *
     * @param User $user O usuário requisitante
     * @param string $feature Qual funcionalidade tentar abater ('essays', 'simulations')
     * @param int $amount Quantidade a debitar
     * @throws Exception Se não houver ciclo ativo ou se atingir a quota máxima
     */
    public function consumeQuota(User $user, string $feature, int $amount = 1): void
    {
        // Admin bypass QA
        if ($user->isAdmin()) {
            return;
        }

        DB::transaction(function () use ($user, $feature, $amount) {
            $cycle = $this->getOrCreateActiveCycle($user, true); // true = lock for update

            if (!$cycle) {
                abort(403, "Você não possui um plano ativo para usar esta funcionalidade.");
            }

            // Verifica teto máximo de limite da feature no JSON congelado
            $limit = $cycle->limits[$feature] ?? 0;

            if ($limit === null || $limit === 'unlimited' || $limit === 9999) {
                // Ilimitado no ciclo
            } else {
                $used = UsageLedger::where('subscription_cycle_id', $cycle->id)
                    ->where('feature_name', $feature)
                    ->sum('amount');

                if (($used + $amount) > $limit) {
                    abort(403, "Limite da quota de {$feature} atingido para este mês.");
                }
            }

            UsageLedger::create([
                'subscription_cycle_id' => $cycle->id,
                'feature_name' => $feature,
                'amount' => $amount,
                'type' => 'consumption'
            ]);
        });
    }

    /**
     * Retorna o uso sumarizado de uma determinada feature no ciclo vigente.
     */
    public function getUsage(User $user, string $feature): array
    {
        if ($user->isAdmin()) {
            return ['used' => 0, 'limit' => 'unlimited'];
        }

        $cycle = $this->getOrCreateActiveCycle($user, false);

        if (!$cycle) {
            return ['used' => 0, 'limit' => 0];
        }

        $limit = $cycle->limits[$feature] ?? 0;
        $used = UsageLedger::where('subscription_cycle_id', $cycle->id)
            ->where('feature_name', $feature)
            ->sum('amount');

        return [
            'used' => (int) $used,
            'limit' => ($limit === 'unlimited' || $limit === 9999) ? null : (int) $limit
        ];
    }

    /**
     * Cria ou renova um ciclo para uma assinatura mensal ou anual (chamado no webhook de pagamento).
     *
     * @param Subscription $subscription A assinatura pagante
     * @return SubscriptionCycle
     */
    public function createOrRenewCycle(Subscription $subscription): SubscriptionCycle
    {
        // Pega os limites default da tabela Plan que amarrou com a subscription
        $planLimits = $subscription->plan->default_limits ?? [];

        return SubscriptionCycle::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'start_date' => now(),
            // O serviço é renovado com "ciclo" de 1 mês, mesmo se o plano contratado for anual
            'end_date' => now()->addMonth(),
            'limits' => $planLimits,
            'has_used_cumulative_bonus' => false
        ]);
    }

    /**
     * Auto-creates a cycle for a user if they have an active plan but no formal cycle
     * (e.g. Free users, Admins, or gracefully handling missing data).
     */
    protected function getOrCreateActiveCycle(User $user, bool $lockForUpdate = false): ?SubscriptionCycle
    {
        $query = SubscriptionCycle::where('user_id', $user->id)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $cycle = $query->first();

        if ($cycle) {
            return $cycle;
        }

        // Se não tem ciclo, mas tem um plano, criamos um ciclo sob demanda para o mês atual
        if (!$user->plan) {
            Log::info("QuotaService: No active cycle and no plan loaded for User #{$user->id}. returning NULL.");
            return null;
        }

        if ($user->plan->essays_limit === 0 && ($user->essay_credits ?? 0) === 0) {
            Log::info("QuotaService: User #{$user->id} has plan '{$user->plan->slug}' with 0 essay limit and no credits.");
        }

        $limits = [
            'simulations' => $user->simulationQuotaLimit() === 0 ? 0 : ($user->simulationQuotaLimit() === 9999 ? 'unlimited' : $user->simulationQuotaLimit()),
            'essays' => $user->essayQuotaLimit() === 0 ? 0 : ($user->essayQuotaLimit() === 9999 ? 'unlimited' : $user->essayQuotaLimit()),
            'daily_questions' => $user->dailyQuestionQuotaLimit() === 0 ? 0 : ($user->dailyQuestionQuotaLimit() === 9999 ? 'unlimited' : $user->dailyQuestionQuotaLimit()),
        ];

        Log::info("QuotaService: Creating on-demand cycle for User #{$user->id} (Plan: {$user->plan->slug}). Limits: " . json_encode($limits));

        return SubscriptionCycle::create([
            'user_id' => $user->id,
            'subscription_id' => null, // No formal subscription
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'limits' => $limits,
            'has_used_cumulative_bonus' => false
        ]);
    }


    /**
     * Efetua o "Upgrade Modelo Acumulativo Real".
     *
     * O teto do ciclo vira a SOMA do contratado do ciclo anterior com os limites do novo plano.
     */
    public function processUpgradeSoma(Subscription $subscription, array $newPlanLimits): SubscriptionCycle
    {
        return DB::transaction(function () use ($subscription, $newPlanLimits) {
            // Pega o ciclo ativo deste exato minuto (lockado)
            $oldCycle = SubscriptionCycle::where('user_id', $subscription->user_id)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->lockForUpdate()
                ->first();

            // Se for o primeiro plan do usuário ou não houver ciclo anterior, cria limpo
            if (!$oldCycle) {
                return SubscriptionCycle::create([
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'start_date' => now(),
                    'end_date' => now()->addMonth(),
                    'limits' => $newPlanLimits,
                    'has_used_cumulative_bonus' => false
                ]);
            }

            // --- REGRA DE PREVENÇÃO DE ABUSO: Apenas 1 Soma Cumulativa por Ciclo ---
            if ($oldCycle->has_used_cumulative_bonus) {
                // Usuário deu um 2º upgrade no mesmo mês apenas para inflar saldo. 
                // A punição/trava é simplesmente aplicar os limites do novo plano sem somar de novo.
                return SubscriptionCycle::create([
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'start_date' => now(),
                    'end_date' => now()->addMonth(),
                    'limits' => $newPlanLimits,
                    'has_used_cumulative_bonus' => true // mantém a trava
                ]);
            }

            // --- A MÁGICA ACUMULATIVA ---
            // Junta as chaves JSON (ex: essays, trials)
            $oldLimits = $oldCycle->limits ?? [];
            $summedLimits = [];

            $keys = array_unique(array_merge(array_keys($oldLimits), array_keys($newPlanLimits)));

            foreach ($keys as $key) {
                $oldLimit = $oldLimits[$key] ?? 0;
                $newLimit = $newPlanLimits[$key] ?? 0;

                // Se algum dos dois diz "unlimited" (ilimitado) ou 9999, a feature fica ilimitada (conversão para novo padrão numérico).
                if ($oldLimit === 'unlimited' || $oldLimit === 9999 || $newLimit === 'unlimited' || $newLimit === 9999) {
                    $summedLimits[$key] = 9999;
                } else {
                    $summedLimits[$key] = ((int) $oldLimit) + ((int) $newLimit);
                }
            }

            // 1) Encerra o ciclo antigo instantaneamente (para não cruzar históricos)
            $oldCycle->update(['end_date' => now()]);

            // 2) Cria o Novo Ciclo Cumulativo. O consumo feito até aqui (ledgers do oldCyle) 
            // terá que ser transportado se quisermos contar o teto inteiro, ou podemos
            // simplesmente zerar o consumo e deixar ele usar os limites somados inteiros. 
            // Neste setup, para manter o teto total limpo na query, migrar os logs é seguro.

            $newCycle = SubscriptionCycle::create([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'limits' => $summedLimits,
                'has_used_cumulative_bonus' => true // Trava aplicada!
            ]);


            // Transporta o Histórico (Ledger) do ciclo anterior fechado para manter a conta coesa
            UsageLedger::where('subscription_cycle_id', $oldCycle->id)
                ->update(['subscription_cycle_id' => $newCycle->id]);

            return $newCycle;
        });
    }
}
