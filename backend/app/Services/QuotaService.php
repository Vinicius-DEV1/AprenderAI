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
            $cycle = SubscriptionCycle::whereHas('subscription', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 'active');
            })
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->lockForUpdate() // Evita leituras de fora simultâneas, previnindo bugs de Double Submissions
                ->first();

            if (!$cycle) {
                abort(403, "Você não possui um plano ativo ou saldo com ciclos válidos para usar esta funcionalidade.");
            }

            // Verifica teto máximo de limite da feature no JSON congelado
            $limit = $cycle->limits[$feature] ?? 0;

            if ($limit === null || $limit === 'unlimited') {
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

        $cycle = SubscriptionCycle::whereHas('subscription', function ($query) use ($user) {
            $query->where('user_id', $user->id)->where('status', 'active');
        })->where('start_date', '<=', now())->where('end_date', '>=', now())->first();

        if (!$cycle)
            return ['used' => 0, 'limit' => 0];

        $limit = $cycle->limits[$feature] ?? 0;
        $used = UsageLedger::where('subscription_cycle_id', $cycle->id)
            ->where('feature_name', $feature)
            ->sum('amount');

        return [
            'used' => (int) $used,
            'limit' => $limit === 'unlimited' ? null : (int) $limit
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
            'subscription_id' => $subscription->id,
            'start_date' => now(),
            // O serviço é renovado com "ciclo" de 1 mês, mesmo se o plano contratado for anual
            'end_date' => now()->addMonth(),
            'limits' => $planLimits,
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
            $oldCycle = SubscriptionCycle::where('subscription_id', $subscription->id)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->lockForUpdate()
                ->first();

            // Se for o primeiro plan do usuário ou não houver ciclo anterior, cria limpo
            if (!$oldCycle) {
                return SubscriptionCycle::create([
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

                // Se algum dos dois diz "unlimited" (ilimitado), a feature fica ilimitada.
                if ($oldLimit === 'unlimited' || $newLimit === 'unlimited') {
                    $summedLimits[$key] = 'unlimited';
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
