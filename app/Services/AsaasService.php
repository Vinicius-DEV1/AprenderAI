<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço responsável pela integração com a API do Asaas.
 * Gerencia Clientes, Assinaturas e Cobranças.
 */
class AsaasService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = Configuration::get('asaas_api_key');
        // Define a URL base dependendo do modo Sandbox ou Produção
        $isSandbox = Configuration::get('asaas_sandbox', false);
        $this->baseUrl = $isSandbox 
            ? 'https://sandbox.asaas.com/api/v3' 
            : 'https://www.asaas.com/api/v3';
    }

    /**
     * Busca um cliente existente no Asaas pelo email ou cria um novo.
     * 
     * @param User $user Usuário do sistema
     * @return string ID do cliente no Asaas (cus_...)
     * @throws \Exception Se houver erro na criação
     */
    public function getOrCreateCustomer(User $user): string
    {
        // 1. Tenta buscar cliente existente pelo email
        $response = Http::withHeader('access_token', $this->apiKey)
            ->get("{$this->baseUrl}/customers", [
                'email' => $user->email,
                'limit' => 1
            ]);
            
        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['data'])) {
                return $data['data'][0]['id'];
            }
        }

        // 2. Cria novo cliente se não existir
        $response = Http::withHeader('access_token', $this->apiKey)
            ->post("{$this->baseUrl}/customers", [
                'name' => $user->name,
                'email' => $user->email,
                'cpfCnpj' => null, // Assumindo que não temos CPF obrigatório neste ponto
                'externalReference' => $user->id,
            ]);

        if ($response->failed()) {
            Log::error('Erro ao Criar Cliente Asaas', ['response' => $response->body()]);
            throw new \Exception('Erro ao criar cliente no gateway de pagamento.');
        }

        return $response->json()['id'];
    }

    /**
     * Cria uma nova assinatura no Asaas.
     * 
     * @param User $user Usuário assinante
     * @param mixed $plan Plano escolhido
     * @param string $paymentMethod Método ('credit_card' ou 'pix')
     * @param array $cardData Dados do cartão (opcional)
     * @param array|null $discount Dados do desconto (opcional)
     * @return array Dados da assinatura criada
     * @throws \Exception Se houver erro no processamento
     */
    public function createSubscription(User $user, $plan, string $paymentMethod, array $cardData = [], ?array $discount = null): array
    {
        $customerId = $this->getOrCreateCustomer($user);

        $data = [
            'customer' => $customerId,
            'billingType' => $paymentMethod === 'credit_card' ? 'CREDIT_CARD' : 'PIX',
            'value' => $plan->price,
            'nextDueDate' => now()->format('Y-m-d'), // Cobrança imediata
            'cycle' => $plan->interval === 'yearly' ? 'YEARLY' : 'MONTHLY',
            'description' => "Assinatura Plano {$plan->name}",
            'externalReference' => $plan->id, 
        ];

        // Aplica cupom de desconto se houver
        if ($discount) {
            $data['discount'] = [
                'value' => $discount['value'],
                'type' => $discount['type'] === 'percent' ? 'PERCENTAGE' : 'FIXED'
            ];
        }

        // Adiciona dados do cartão se for o método escolhido
        if ($paymentMethod === 'credit_card') {
            $data['creditCard'] = [
                'holderName' => $cardData['holder_name'],
                'number' => $cardData['number'],
                'expiryMonth' => $cardData['expiry_month'],
                'expiryYear' => $cardData['expiry_year'],
                'ccv' => $cardData['ccv']
            ];
            $data['creditCardHolderInfo'] = [
                'name' => $user->name,
                'email' => $user->email,
                'cpfCnpj' => $cardData['cpf'],
                'postalCode' => '00000000', // CEP genérico se não coletado
                'addressNumber' => '0',
                'phone' => '0000000000'
            ];
        }

        $response = Http::withHeader('access_token', $this->apiKey)
            ->post("{$this->baseUrl}/subscriptions", $data);

        if ($response->failed()) {
            Log::error('Erro ao Criar Assinatura Asaas', ['response' => $response->body()]);
            // Extrai mensagem de erro amigável
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
                'limit' => 1
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
            Log::error('Erro ao buscar QR Code Pix Asaas', ['id' => $paymentId, 'response' => $response->body()]);
            return null;
        }

        return $response->json();
    }
}
