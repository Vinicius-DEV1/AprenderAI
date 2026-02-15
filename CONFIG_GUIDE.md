# CONFIG_GUIDE - Guia de Configuração e Testes

Este documento orienta a configuração do sistema de pagamentos via Asaas e gestão de cupons para os ambientes de homologação (testes) e produção.

---

## 1. Variáveis de Ambiente (.env)

Adicione ou verifique as seguintes chaves no arquivo `.env`.

| Chave | Descrição | Exemplo |
| :--- | :--- | :--- |
| `APP_URL` | Obrigatório para callbacks do Asaas. Deve ser a URL pública (com HTTPS). | `https://seusistema.com` |
| `DB_CONNECTION` | Conexão padrão do banco de dados (MySQL/MariaDB). | `mysql` |
| `DB_HOST` | Host do banco de dados. | `127.0.0.1` |
| `DB_PORT` | Porta do banco de dados. | `3306` |
| `QUEUE_CONNECTION` | Define driver de filas. Use `database` ou `redis` para processamento assíncrono. | `sync` (dev) ou `database` (prod) |

> **Nota:** As chaves específicas do Asaas (`ASAAS_API_KEY`, `ASAAS_SANDBOX`) são gerenciadas diretamente no Painel Administrativo para facilitar a troca sem necessidade de redeploy.

---

## 2. Configuração no Painel do Asaas

Siga os passos abaixo para obter suas credenciais e configurar o retorno automático (Webhook).

### 2.1. Gerar API Key

1. Acesse sua conta Asaas:
   - **Produção:** [www.asaas.com](https://www.asaas.com)
   - **Sandbox (Testes):** [sandbox.asaas.com](https://sandbox.asaas.com)
2. Vá em **Menu do Usuário** (Canto superior direito) > **Integrações**.
3. Na aba **Chave de API**, clique em **Gerar nova chave de API**.
4. Copie a chave gerada (inicia com `$aact_...`).

### 2.2. Configurar Webhook

Para que o sistema receba confirmações de pagamento automaticamente:

1. Ainda em **Integrações**, clique na aba **Webhooks**.
2. Clique em **Configurar Webhook para Cobranças**.
3. Preencha os campos:
   - **URL**: `https://seu-dominio.com/webhooks/asaas`
   - **Email de alerta**: Seu email para notificações de erro.
   - **Versão da API**: `V3`
   - **Token de Autenticação**: *Opcional (Desabilitado na verificação atual)*.
   - **Eventos da Fila**: Marque as seguintes opções:
     - [x] `PAYMENT_CONFIRMED` (Pagamento confirmado)
     - [x] `PAYMENT_RECEIVED` (Pagamento recebido)
     - [x] `PAYMENT_OVERDUE` (Pagamento vencido)
     - [x] `PAYMENT_REFUNDED` (Pagamento estornado)
     - [x] `PAYMENT_DELETED` (Cobrança removida)
4. Salve as alterações.

---

## 3. Configuração Interna (Painel Admin)

Com a chave em mãos, ative o sistema no seu painel administrativo.

### 3.1. Ativar Pagamentos

1. Faça login como Administrador no sistema.
2. No menu lateral, clique em **Pagamentos**.
3. Preencha o formulário:
   - **Chave da API Asaas**: Cole a chave obtida no passo 2.1.
   - **Ambiente de Testes (Sandbox)**:
     - Marque se estiver usando a conta Sandbox.
     - Desmarque para Produção.
   - **Pagamentos Ativos**: Marque para liberar o checkout para os usuários.
4. Clique em **Salvar Configurações**.

### 3.2. Criar Cupom de Teste

Para validar o fluxo de descontos:

1. No menu lateral, clique em **Cupons**.
2. Clique em **Novo Cupom**.
3. Preencha:
   - **Código**: `TESTE10`
   - **Tipo**: `Porcentagem`
   - **Valor**: `10` (10% de desconto)
   - **Validade**: Data futura.
   - **Ativo**: Sim.
4. Salve e verifique se aparece na listagem.

---

## 4. Requisitos de Servidor e Segurança

Para garantir o funcionamento seguro e estável:

### 4.1. HTTPS Obrigatório
O Asaas exige que a URL do Webhook utilize **HTTPS** válido em produção.
- Certifique-se de instalar um certificado SSL (Let's Encrypt, Cloudflare, etc.).

### 4.2. Filas (Queues)
Se o volume de transações for alto, configure o processamento de filas para não travar a requisição do Webhook.
- Configure o Supervisor ou Cron para rodar:
  ```bash
  php artisan queue:work --tries=3 --timeout=90
  ```

### 4.3. Permissões de Pasta
Garanta que as pastas de log e cache tenham permissão de escrita:
```bash
chmod -R 775 storage bootstrap/cache
```

---

## 5. Guia de Testes Rápidos (Sanity Check)

Antes de liberar para os clientes, faça este checklist rápido:

| Teste | Ação | Resultado Esperado |
| :--- | :--- | :--- |
| **1. Conexão API** | Salvar configurações no Admin. | Mensagem de sucesso e dados persistidos. |
| **2. Checkout Cartão** | Tentar assinar um plano com cartão de teste*. | Assinatura criada, redirecionado para sucesso/dashboard. |
| **3. Checkout Pix** | Tentar assinar com Pix. | Tela de "Pagamento Pendente" com QR Code exibido. |
| **4. Cupom** | Aplicar cupom `TESTE10` no checkout. | Valor total deve reduzir em 10%. |
| **5. Webhook** | Simular pagamento no painel Sandbox do Asaas. | Status da assinatura mudar para `active` no banco de dados. |

> ***Cartões de Teste (Sandbox):**
> - Número: `4444 4444 4444 4444`
> - Validade: Qualquer data futura (ex: `12/2030`)
> - CVV: `123`
> - Nome: `TESTE ASAAS`

---
**Suporte:** Em caso de dúvidas, consulte a [Documentação Oficial do Asaas](https://docs.asaas.com/).
