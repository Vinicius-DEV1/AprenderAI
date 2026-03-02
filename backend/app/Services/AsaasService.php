<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço responsável pela integração com a API do Asaas.
 * Gerencia Clientes, Assinaturas e Cobranças.
 *
 * PCI COMPLIANCE:
 * - Dados do cartão (number, ccv, expiryMonth, expiryYear) NUNCA são logados.
 * - Apenas respostas da API (sem campos sensíveis) são registradas em log.
 */
class AsaasService
{
    protected ?string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        // Define a URL base e a Chave de API dependendo do modo Sandbox ou Produção
        $isSandbox = Configuration::get('asaas_sandbox', false);

        $this->apiKey = $isSandbox
            ? Configuration::get('asaas_sandbox_api_key')
            : Configuration::get('asaas_production_api_key');

        $this->baseUrl = $isSandbox
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://www.asaas.com/api/v3';
    }

    /**
     * Retorna se o serviço está em modo Sandbox.
     */
    public function isSandbox(): bool
    {
        return Configuration::get('asaas_sandbox', false);
    }

    /**
     * Busca ou cria um cliente no Asaas, persistindo o ID no usuário local
     * para evitar duplicidade em chamadas subsequentes.
     *
     * Fluxo de prioridade:
     *  1. Se o usuário já tem asaas_customer_id → retorna diretamente (sem API call)
     *  2. Se não, busca por email no Asaas
     *  3. Se não encontrar, cria novo cliente e salva o ID
     *
     * @param User $user Usuário do sistema
     * @param string|null $cpf CPF ou CNPJ do cliente (opcional)
     * @return string ID do cliente no Asaas (cus_...)
     * @throws \Exception Se houver erro na criação
     */
    public function getOrCreateCustomer(User $user, ?string $cpf = null): string
    {
        // ---- STEP 1: Checar ID local (evita duplicidade) --------------------
        if (!empty($user->asaas_customer_id)) {
            $customerId = $user->asaas_customer_id;

            // Se recebemos um CPF mas o usuário já existia, tentamos atualizar 
            // no Asaas para garantir que ele tenha os dados necessários (como CPF para PIX).
            if ($cpf) {
                Http::withHeader('access_token', $this->apiKey)
                    ->post("{$this->baseUrl}/customers/{$customerId}", [
                        'cpfCnpj' => $cpf,
                        'notificationDisabled' => true
                    ]);
            }

            return $customerId;
        }

        // ---- STEP 2: Buscar cliente existente pelo email no Asaas -----------
        $response = Http::withHeader('access_token', $this->apiKey)
            ->get("{$this->baseUrl}/customers", [
                'email' => $user->email,
                'limit' => 1
            ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['data'])) {
                $customerId = $data['data'][0]['id'];
                // Persiste para não precisar buscar novamente
                $user->update(['asaas_customer_id' => $customerId]);
                return $customerId;
            }
        }

        // ---- STEP 3: Criar novo cliente -------------------------------------
        $payload = [
            'name' => $user->name,
            'email' => $user->email,
            'externalReference' => (string) $user->id,
            'notificationDisabled' => true, // 🛑 Desabilita notificações (Email, SMS, WhatsApp)
        ];

        if ($cpf) {
            $payload['cpfCnpj'] = $cpf;
        }

        $response = Http::withHeader('access_token', $this->apiKey)
            ->post("{$this->baseUrl}/customers", $payload);

        if ($response->failed()) {
            // ⚠️ PCI: Logar apenas o body da resposta da API, nunca dados do cartão
            Log::error('[Asaas] Erro ao criar cliente', [
                'user_id' => $user->id,
                'email' => $user->email,
                'response' => $response->body(),
            ]);
            throw new \Exception('Erro ao criar cliente no gateway de pagamento.');
        }

        $customerId = $response->json()['id'];

        // Persiste o ID para evitar criações futuras duplicadas
        $user->update(['asaas_customer_id' => $customerId]);

        return $customerId;
    }

    /**
     * Cria uma nova assinatura no Asaas.
     *
     * @param User $user Usuário assinante
     * @param mixed $plan Plano escolhido
     * @param string $paymentMethod Método ('credit_card' ou 'pix')
     * @param array $cardData Dados do cartão (opcional) — NUNCA logados
     * @param array|null $discount Dados do desconto (opcional)
     * @return array Dados da assinatura criada
     * @throws \Exception Se houver erro no processamento
     */
    public function createSubscription(User $user, $plan, string $paymentMethod, array $cardData = [], ?array $discount = null): array
    {
        $customerId = $this->getOrCreateCustomer($user, $cardData['cpf'] ?? null);

        $data = [
            'customer' => $customerId,
            'billingType' => $paymentMethod === 'credit_card' ? 'CREDIT_CARD' : 'PIX',
            'value' => $plan->price,
            'nextDueDate' => now()->format('Y-m-d'),
            'cycle' => $plan->interval === 'yearly' ? 'YEARLY' : 'MONTHLY',
            'description' => "Assinatura Plano {$plan->name}",
            'externalReference' => (string) $plan->id,
            'notificationDisabled' => true, // 🛑 Desabilita notificações para esta assinatura
        ];

        if ($discount) {
            $data['discount'] = [
                'value' => $discount['value'],
                'type' => $discount['type'] === 'percent' ? 'PERCENTAGE' : 'FIXED',
            ];
        }

        if ($paymentMethod === 'credit_card') {
            // ⚠️ PCI: Dados do cartão são enviados ao Asaas mas NUNCA persistidos/logados
            $data['creditCard'] = [
                'holderName' => $cardData['holder_name'],
                'number' => $cardData['number'],
                'expiryMonth' => $cardData['expiry_month'],
                'expiryYear' => $cardData['expiry_year'],
                'ccv' => $cardData['ccv'],
            ];
            $data['creditCardHolderInfo'] = [
                'name' => $user->name,
                'email' => $user->email,
                'cpfCnpj' => $cardData['cpf'],
                'postalCode' => $cardData['postal_code'] ?? '00000000',
                'addressNumber' => $cardData['address_number'] ?? '0',
                'phone' => $cardData['phone'] ?? '0000000000',
            ];
        }

        $response = Http::withHeader('access_token', $this->apiKey)
            ->post("{$this->baseUrl}/subscriptions", $data);

        if ($response->failed()) {
            // ⚠️ PCI: Logar apenas o body de RESPOSTA da API (nunca o $data com cardData)
            Log::error('[Asaas] Erro ao criar assinatura', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'payment_method' => $paymentMethod,
                'response' => $response->body(),
            ]);
            $errorMsg = $response->json()['errors'][0]['description'] ?? 'Erro no processamento do pagamento.';
            throw new \Exception($errorMsg);
        }

        return $response->json();
    }

    /**
     * Cria uma cobrança avulsa (não recorrente) no Asaas.
     * Utilizado para recargas de redações.
     *
     * @param User $user Usuário pagante
     * @param float $value Valor da cobrança
     * @param string $description Descrição da cobrança
     * @param string $paymentMethod 'credit_card' ou 'pix'
     * @param array $cardData Dados do cartão — NUNCA logados
     * @return array Dados do pagamento criado
     * @throws \Exception Se houver erro no processamento
     */
    public function createOneTimePayment(User $user, float $value, string $description, string $paymentMethod, array $cardData = []): array
    {
        $customerId = $this->getOrCreateCustomer($user, $cardData['cpf'] ?? null);

        $data = [
            'customer' => $customerId,
            'billingType' => $paymentMethod === 'credit_card' ? 'CREDIT_CARD' : 'PIX',
            'value' => $value,
            'dueDate' => now()->format('Y-m-d'),
            'description' => $description,
            'notificationDisabled' => true, // 🛑 Desabilita notificações para este pagamento
        ];

        if ($paymentMethod === 'credit_card') {
            // ⚠️ PCI: Dados enviados ao Asaas mas NUNCA persistidos/logados
            $data['creditCard'] = [
                'holderName' => $cardData['holder_name'],
                'number' => $cardData['number'],
                'expiryMonth' => $cardData['expiry_month'],
                'expiryYear' => $cardData['expiry_year'],
                'ccv' => $cardData['ccv'],
            ];
            $data['creditCardHolderInfo'] = [
                'name' => $user->name,
                'email' => $user->email,
                'cpfCnpj' => $cardData['cpf'],
                'postalCode' => $cardData['postal_code'] ?? '00000000',
                'addressNumber' => $cardData['address_number'] ?? '0',
                'phone' => $cardData['phone'] ?? '0000000000',
            ];
        }

        $response = Http::withHeader('access_token', $this->apiKey)
            ->post("{$this->baseUrl}/payments", $data);

        if ($response->failed()) {
            Log::error('[Asaas] Erro ao criar pagamento avulso', [
                'user_id' => $user->id,
                'value' => $value,
                'payment_method' => $paymentMethod,
                'response' => $response->body(),
            ]);
            $errorMsg = $response->json()['errors'][0]['description'] ?? 'Erro no processamento do pagamento.';
            throw new \Exception($errorMsg);
        }

        return $response->json();
    }

    /**
     * Busca a primeira cobrança pendente de uma assinatura.
     * Útil para obter o ID do pagamento gerado e consequentemente o Pix.
     *
     * @param string $subscriptionId ID da assinatura no Asaas
     * @return array|null Dados da cobrança ou null
     */
    public function getFirstPendingPayment(string $subscriptionId): ?array
    {
        $response = Http::withHeader('access_token', $this->apiKey)
            ->get("{$this->baseUrl}/subscriptions/{$subscriptionId}/payments", [
                'status' => 'PENDING',
                'limit' => 1,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['data'])) {
                return $data['data'][0];
            }
        }

        return null;
    }

    /**
     * Obtém o Payload e Imagem do QR Code Pix para um pagamento.
     *
     * @param string $paymentId ID da cobrança
     * @return array|null Dados do Pix (encodedImage, payload) ou null
     */
    public function getPixQrCode(string $paymentId): ?array
    {
        $response = Http::withHeader('access_token', $this->apiKey)
            ->get("{$this->baseUrl}/payments/{$paymentId}/pixQrCode");

        if ($response->failed()) {
            Log::error('[Asaas] Erro ao buscar QR Code Pix', [
                'payment_id' => $paymentId,
                'response' => $response->body(),
            ]);
            return null;
        }

        return $response->json();
    }
}
