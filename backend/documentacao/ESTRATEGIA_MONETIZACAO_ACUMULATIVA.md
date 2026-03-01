# 🚀 Modelo Acumulativo Real - Estratégia de Monetização e Arquitetura

Este documento formaliza a arquitetura e modelagem de dados proposta para implementar o modelo de limites **Acumulativo Real** no AprenderAI. O foco desta arquitetura é garantir recompensa instantânea de Upgrade para o usuário e proteção de caixa/custos (Unit Economics) para o negócio.

## Visão Geral das Regras de Negócio

1. **Upgrade Cumulativo:** O teto de consumo do novo plano SOMA-SE ao do antigo no ciclo vigente em que ocorre a transação. Nenhum contador de consumo é "resetado" arbitrariamente.
2. **Downgrade Agendado:** Não possui devolução de limites "não usados" ou roll-over. A vigência encerra-se na data oficial de faturamento e muda para o plano base no momento da renovação.
3. **Desconto Anual:** Pago antecipado com exatos 20% OFF. Seus limites não são despejados anualmente (ex: 150 redações de uma vez); eles continuam alimentando o pote **mensalmente**.
4. **Pró-Rata:** Abatimento de custo diário para Upgrades realizados no meio do ciclo.
5. **Cancelamento / CDC (7 dias):** Permitido reembolso via suporte. Usuários que consumiram e cancelaram no CDC sofrem *softban* nas rédeas de trial/renovações subsequentes para impedir fluxo pendular gratuito.

---

## 🏗️ Modelagem do Banco de Dados Sugerida

Para evitar o clássico erro de atrelar a `quota_used` rigidamente à assinatura de forma destrutiva, sugerimos o uso de **Event Sourcing de Ciclos**.

### Tabela: `plans`
*Descrição:* Catálogo mestre dos planos ofertados.
* `id` (PK)
* `name` (Básico, Plus, etc)
* `price` (Decimal)
* `interval` (Enum: monthly, yearly)
* `default_limits` (JSON): Ex `{"trials": 5, "essays": 2}`

### Tabela: `subscriptions`
*Descrição:* A ligação vitalícia entre o Usuário e a "intenção" de faturamento.
* `id` (PK)
* `user_id` (FK -> users)
* `plan_id` (FK -> plans)
* `status` (Enum: active, past_due, canceled, incomplete)
* `gateway_id` (Asaas ID)
* `next_billing_date` (DateTime)
* `pending_downgrade_to_plan_id` (FK nullable -> plans)

### Tabela Central: `subscription_cycles`
*Descrição:* **O coração do modelo**. Sempre que ocorre um pagamento, um novo ciclo nasce congelando o teto.
* `id` (PK)
* `subscription_id` (FK -> subscriptions)
* `start_date` (DateTime)
* `end_date` (DateTime - data que esse ciclo morre)
* `limits` (JSON): Congela o limite. Num upgrade, vai guardar o somatório `{"trials": 15, "essays": 17}`.
* `has_used_cumulative_bonus` (Boolean): Flag de proteção. Impede que o usuário dê 5 upgrades no mesmo mês e continue inflando esse JSON sem limite.

### Tabela de Consumo: `usage_ledgers`
*Descrição:* Livro-razão (Append-only). Em vez de `count_used = count_used + 1` no usuário, criamos uma linha de histórico para cada uso.
* `id` (PK)
* `subscription_cycle_id` (FK -> subscription_cycles)
* `feature_name` (Enum/String: 'essay', 'trial', 'ai_chat')
* `amount` (Integer: geralmente 1 para consumo, ou valores parciais se houver tokens)
* `type` (Enum: 'consumption', 'bonus_manual')
* `created_at` (DateTime do uso real)

*Por que Ledgers?*
Se quisermos saber quanto o usuário gastou no ciclo, fazemos `SUM(amount) FROM usage_ledgers WHERE subscription_cycle_id = X`. É extremamente resiliente contra *race conditions* de múltiplos disparos na API, desde que usando uma transaction com lock de leitura rápida na conta.

---

## 🛡️ Técnicas de Prevenção de Abuso

1. **Transaction & Row-Locking no Uso do Sistema AI**
   Quando a rota `/api/essays/submit` é acionada:
   ```php
   DB::transaction(function () {
       // O FOR UPDATE previne que 2 disparos simultâneos (Double Click) 
       // contornem o limite se passarem no if juntos.
       $cycle = SubscriptionCycle::where('user_id', $user->id)
                   ->where('end_date', '>=', now())
                   ->lockForUpdate()
                   ->first();

       $used = UsageLedger::where('subscription_cycle_id', $cycle->id)
                          ->where('feature_name', 'essay')
                          ->sum('amount');

       if ($used >= $cycle->limits['essays']) {
           throw new QuotaExceededException();
       }

       UsageLedger::create([...]);
   });
   ```

2. **Upgrade Cumulativo Restrito:**
   Se `has_used_cumulative_bonus == true` no `subscription_cycles` atual, um novo upgrade eleva o limite para o teto do novo plano, mas **não soma as metragens novamente**. (Isso trava o usuário de fazer Upgrade Básico -> Intermediário -> Pro -> Max *só para engordar* a quantidade de redações em menos de 30 dias).

3. **Rate Limiting no Endereço IP / Device:**
   O plano Gratuito deve amarrar o Fingerprint no login. A criação sucessiva de 10 contas free para burlar as 5 provas gratuitas é inevitável sem validação rigorosa de telefone nas inscrições free (caso seja necessário).

---

## Estratégia Mensal -> Anual (O "Pingo" de Quota)
Pagou upfront (via Asaas), o `Subscription` muda para `interval=yearly`.
CronJob/Job Semanal ou evento de Webhook:
Todo dia "X", o sistema cria um **novo `subscription_cycle` de 30 dias** preenchido com as métricas do JSON `default_limits`, inserido 12x ao longo do ano faturado. Isso espalha o risco dos custos de LLM e engaja o usuário a retornar na plataforma mensalmente para resgatar/utilizar os limites frescos.
