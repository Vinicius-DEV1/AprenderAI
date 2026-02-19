# DOCUMENTAÇÃO MESTRA DO PROJETO APRENDER AI

## 1. Visão Geral
O **aprenderAI** é uma plataforma SaaS desenvolvida com **Laravel 11**, **Tailwind CSS**, e **MySQL**. O sistema oferece funcionalidades de simulados, redações e planos de assinatura.

Esta documentação serve como guia centralizado para **Administradores do Sistema** configurarem as integrações (Google, Pagamentos) e para **Desenvolvedores** entenderem a arquitetura implementada.

---

## 2. Guia de Configuração de Serviços Externos

Para que o sistema funcione corretamente, é necessário configurar serviços externos e inserir as credenciais no Painel Administrativo.

### A. Google Cloud Console (Login com Google)
Necessário para habilitar o botão "Entrar com Google" na tela de login.

1.  Acesse o [Google Cloud Console](https://console.cloud.google.com/).
2.  Crie um novo projeto (ex: `aprenderAI-Prod`).
3.  No menu lateral, vá em **APIs e Serviços > Tela de permissão OAuth**.
    *   Selecione **Externo** e clique em Criar.
    *   Preencha o nome do App (`aprenderAI`) e emails de contato.
    *   Salve e continue.
4.  No menu lateral, vá em **Credenciais**.
    *   Clique em **+ CRIAR CREDENCIAIS** > **ID do cliente OAuth**.
    *   **Tipo de aplicativo**: Aplicação da Web.
    *   **Nome**: `aprenderAI Web`.
    *   **Origens JavaScript autorizadas**: Adicione a URL do seu site (ex: `https://seusite.com` e `http://localhost:8000` para testes).
    *   **URIs de redirecionamento autorizados**: Adicione a URL de callback exata:
        *   Produção: `https://seusite.com/auth/google/callback`
        *   Local: `http://localhost:8000/auth/google/callback`
5.  Clique em **Criar**.
6.  Copie o **ID do cliente** e a **Chave secreta do cliente**. Você usará esses dados no Painel Admin.

### B. Google Analytics (GA4)
Necessário para monitorar o tráfego do site.

1.  Acesse o [Google Analytics](https://analytics.google.com/).
2.  Vá em **Administrador** (ícone de engrenagem) > **Criar** > **Propriedade**.
3.  Preencha os dados e clique em **Próximo** até concluir.
4.  Selecione a plataforma **Web**.
5.  Insira a URL do site e o nome do fluxo.
6.  Após criar, copie o **ID da métrica** (começa com `G-XXXXXXXXXX`).

### C. Asaas (Gateway de Pagamento)
Necessário para processar assinaturas e pagamentos via Pix/Cartão.

1.  Crie uma conta no [Asaas](https://www.asaas.com/).
2.  No painel do Asaas, vá em **Minha Conta > Integração**.
3.  Gere uma **Chave API** (API Key).
4.  **Configuração de Webhook** (Crítico para aprovar pagamentos automaticamente):
    *   Na aba **Webhook** (em Integração), configure a URL:
        *   `https://seusite.com/webhooks/asaas`
    *   Marque os eventos: `PAYMENT_RECEIVED`, `PAYMENT_CONFIRMED`, `PAYMENT_OVERDUE`, `PAYMENT_REFUNDED`, `PAYMENT_DELETED`.
    *   Habilite a fila de sincronização.

---

## 3. Manual do Painel Administrativo

O sistema possui áreas dedicadas para gerenciar essas chaves sem precisar editar código.

### Menu: Integrações
Acesse `/admin/integrations`.

*   **Google Login**:
    *   **Habilitar Google Login**: Se desmarcado, o botão "Entrar com Google" desaparece imediatamente da tela de login.
    *   **Google Client ID**: Cole o ID gerado no passo 2A.
    *   **Google Client Secret**: Cole o segredo. *Nota: Por segurança, o valor é criptografado no banco e não é exibido após salvar.*
    *   **Redirect URI**: Campo apenas de leitura mostrando a URL que você deve cadastrar no Google Cloud.

*   **Google Analytics**:
    *   **Habilitar Analytics**: Se desmarcado, o script de rastreamento não é carregado no site.
    *   **Measurement ID**: Cole o ID `G-XXXXXXXXXX` gerado no passo 2B.

### Menu: Pagamentos
Acesse `/admin/payment-settings`.

*   **Asaas API Key**: Cole a chave API gerada no passo 2C.
*   **Modo Sandbox**: Marque APENAS se estiver usando a conta de testes do Asaas (`sandbox.asaas.com`).
*   **Pagamentos Ativos**: Chave geral para habilitar/desabilitar novas assinaturas.

---

## 4. Documentação Técnica (Para Desenvolvedores)

### Banco de Dados
Novas estruturas adicionadas:
*   **Tabela `configurations`**: Armazena configurações do sistema (chave-valor).
    *   Campos Chave: `google_client_id`, `google_client_secret` (criptografado), `analytics_measurement_id`, `asaas_api_key`.
*   **Tabela `users`**:
    *   `google_id`: Armazena o ID único do usuário no Google.
    *   `avatar_url`: URL da foto de perfil do Google.

### Arquitetura de Autenticação (Google OAuth)
O fluxo utiliza o pacote `laravel/socialite` mas com configuração dinâmica.

1.  **Rota**: `/auth/google` -> `GoogleAuthController@redirect`
    *   Carrega credenciais do banco (`configurations`).
    *   Configura `Config::set('services.google...')` em tempo de execução.
    *   Redireciona para o Google.
2.  **Callback**: `/auth/google/callback` -> `GoogleAuthController@callback`
    *   Recebe o código do Google.
    *   Busca usuário pelo `google_id` OU `email`.
    *   **Criação**: Se não existir, cria um novo usuário com senha aleatória.
    *   **Vinculação**: Se existir por email, atualiza o `google_id`.
    *   Autentica o usuário na sessão Laravel.

### Arquivos Principais Modificados/Criados
*   **Controllers**:
    *   `app/Http/Controllers/Auth/GoogleAuthController.php` (Lógica OAuth)
    *   `app/Http/Controllers/Admin/IntegrationController.php` (Configurações Admin)
    *   `app/Http/Controllers/WebhookController.php` (Lógica de Webhooks Asaas)
*   **Services**:
    *   `app/Services/AsaasService.php` (Integração API Asaas)
*   **Views**:
    *   `resources/views/admin/integrations.blade.php` (Tela de Configuração)
    *   `resources/views/auth/login.blade.php` (Botão de Login)
    *   `resources/views/partials/analytics.blade.php` (Script GA4)

### Novas Funcionalidades (Dashboard & Usuários)
*   **Banco de Dados**:
    *   `user_logs`: Tabela para auditoria de ações (login, update, ban).
    *   `users`: Adicionado colunas `phone` e `is_banned`.
*   **Admin Dashboard**:
    *   Utiliza `Chart.js` via CDN para gráficos.
    *   Lógica em `AdminController@dashboard`.
*   **Gestão de Usuários**:
    *   `Admin/UserController`: CRUD simplificado + ações de segurança.
    *   Views em `resources/views/admin/users/`.
*   **Gestão de Planos**:
    *   `Admin/PlanController`: CRUD de planos.
    *   Permite editar quotas e preços (novas assinaturas).
    *   `Admin/PlanController`: CRUD de planos.
    *   Permite editar quotas e preços (novas assinaturas).
    *   Views em `resources/views/admin/plans/`.
*   **Monitoramento VPS**:
    *   `ServerMetric`: Tabela para histórico de CPU/RAM/Rede.
    *   `CollectMetrics`: Comando agendado (Cron) a cada minuto.
    *   `Admin/MonitorController`: Dashboard com gráficos e gauges em tempo real.

