# aprenderAI 🎓
> **Sua jornada rumo à aprovação potencializada por Inteligência Artificial.**

[![Laravel Version](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php)](https://www.php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](LICENSE)

---

## 📝 Sobre o Projeto

O **aprenderAI** é uma plataforma brasileira de vanguarda projetada para revolucionar a preparação de estudantes para o ENEM e concursos públicos. Ao integrar modelos de linguagem de última geração (LLMs), o sistema oferece feedbacks instantâneos e personalizados, transformando o erro em uma oportunidade real de aprendizado.

A plataforma automatiza a correção de redações e simulados, permitindo que o estudante foque no que realmente importa: a evolução constante. Com uma arquitetura moderna e escalável, o aprenderAI une a robustez do ecossistema Laravel com a agilidade do Tailwind CSS 4 para entregar uma experiência de usuário (UX) fluida e focada na produtividade.

---

## 🛠️ Stack Tecnológica

| Categoria | Tecnologia |
| :--- | :--- |
| **Backend** | PHP 8.4+ / Laravel 11.x |
| **Frontend** | Blade Templates / Tailwind CSS 4 / Vite 7 |
| **Banco de Dados** | SQLite (Desenvolvimento) / MySQL (Produção) |
| **Pagamentos** | Mercado Pago SDK |
| **AI Integration** | OpenAI / Gemini / Grok (Gestão Dinâmica de Chaves) |

---

## 🚀 Arquitetura & Funcionalidades

O diferencial técnico do aprenderAI reside na sua capacidade de orquestrar diferentes provedores de IA de forma transparente para o usuário final:

-   🧠 **Correção Inteligente**: Feedback detalhado de pontos de melhoria em redações baseados em critérios oficiais.
-   📊 **Gestão de Simulados**: Geração e acompanhamento de desempenho em baterias de questões por matéria.
-   🔑 **Dynamic AI Key Management**: Painel administrativo para alternar entre provedores de IA em tempo real.
-   💳 **Assinaturas**: Sistema de planos (Gratuito, Básico, Plus) com limites dinâmicos controlados via Middleware.

---

## ⚙️ Instalação & Execução Local

Siga os passos abaixo para configurar o ambiente de desenvolvimento. Este projeto é otimizado para rodar com **Laragon** no Windows, mas funciona em qualquer ambiente PHP 8.4+.

### 1. Requisitos
- PHP 8.4 ou superior
- Composer
- Node.js & NPM
- SQLite (ativado no PHP)

### 2. Configuração do Projeto
```powershell
# Clonar o repositório
1.  Clone o repositório:
    ```bash
    git clone https://github.com/Antonio7s/AprenderAI.git
    cd AprenderAI
    ```

# Instalar dependências do PHP (via Laragon ou Global)
composer install

# Instalar dependências do Frontend
npm install

# Configurar ambiente
copy .env.example .env
php artisan key:generate
```

### 3. Banco de Dados
O projeto utiliza SQLite por padrão para desenvolvimento.
```powershell
# Criar o arquivo do banco se não existir
if (!(Test-Path "database/database.sqlite")) { New-Item "database/database.sqlite" }

# Rodar Migrações e Seeders (Siga esta ordem para evitar erros de integridade)
php artisan migrate:fresh
php artisan db:seed --class=PlanSeeder
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=AddPortugueseQuestionsSeeder
```

### 4. Executando o Servidor
```powershell
# Compilar assets (produção)
npm run build

# Iniciar Servidor (Porta 8000)
php artisan serve
```
Acesse em: [http://127.0.0.1:8000](http://127.0.0.1:8000)

> [!TIP]
> **Dica para Laragon:** Se você usa Laragon, pode usar o executável do PHP 8.4 localizado em `C:\laragon\bin\php\php-8.4.16...` para garantir a compatibilidade.

---

## 💻 Fluxo de Desenvolvimento

Para rodar o projeto localmente, utilize o comando concorrente (configurado via `composer.json`):

```bash
# Inicia todos os serviços (Server, Vite, Queue, Pail) simultaneamente
composer dev
```

Acesse a aplicação em: [http://localhost:8000](http://localhost:8000)

---

## 📑 Documentação Adicional

Para um histórico detalhado de alterações, logs de verificação e diário de bordo da construção deste projeto, consulte o arquivo:
👉 [**walkthrough.md**](file:///C:/Users/vinic/.gemini/antigravity/brain/5392c893-6210-42ff-bbaf-394cab5e505f/walkthrough.md)

---

## 👤 Usuários de Teste (Seeders)

Ao rodar `php artisan db:seed`, os seguintes usuários são criados automaticamente para testes:

| Email | Função | Plano | Senha Padrão |
| :--- | :--- | :--- | :--- |
| `admin@aprovaai.com` | **Admin** | Plus | `Aprova@123` |
| `plus@aprovaai.test` | User | Plus | `Aprova@123` |
| `basic@aprovaai.test` | User | Basic | `Aprova@123` |
| `free@aprovaai.test` | User | Free | `Aprova@123` |

> **Nota:** A senha padrão é definida pela chave `DEFAULT_USER_PASSWORD` no `.env`. Se não estiver definida, o fallback é `Aprova@123`.

---

## 🔒 Documentação de Auditoria e Correções Asaas (27/02/2026)

Esta seção serve como registro das auditorias, correções críticas e débitos técnicos mapeados no ecossistema de pagamentos Asaas da aplicação.

### 🕒 Modificações Realizadas

**Correção de Idempotência e Cálculo Baseado no Payload no WebhookController**
- **Local:** `backend/app/Http/Controllers/WebhookController.php` (Bloco `PAYMENT_CONFIRMED` / `PAYMENT_RECEIVED`)
- **Justificativa do Problema:** Anteriormente, quando o Asaas confirmava um pagamento, o sistema usava a função `now()` do servidor para estipular o início e o fim do ciclo (`current_period_end`). Isso gerava dois problemas graves:
  1. Se houvesse atraso no disparo do webhook por instabilidade, o ciclo do assinante "ganhava" dias não faturados.
  2. Mais grave: se o webhook sofresse retentativas e fosse recebido duas vezes para o *mesmo* pagamento, o ciclo se expandia (somava +1 mês extra) indevidamente, burlando o faturamento da plataforma (Race Condition).
- **A Solução Implementada:** 
  1. Adicionado uma verificação no Model `PaymentLog`. Se aquele `gateway_payment_id` específico já tiver sido logado como `success` num desses eventos, a função garante a idempotência abortando a execução.
  2. A data estressora dos meses mudou de passiva (`now()`) para ativa (procura a chave `paymentDate` ou `dueDate` do payload do Asaas). O renewal passa a ser ancorado rigorosamente na string exata agendada pelo Asaas.

### 🚦 Alertas Identificados (Backlog Crítico Futuro)
- **Risco PCI-DSS no Checkout React:** O Componente de Pagamento (`PlanCheckout.tsx`) está capturando os dígitos do cartão e transportando por requisição HTTP para o backend.
  - *Mitigação Futura:* Migrar o front para usar a arquitetura de **Tokenização (Asaas.js)**. Os dados do cartão devem ser trocados por um token via Asaas no navegador, e somente este token descartável viaja ao backend.
- **Nota de Score Antifraude Asaas:** O Backend está hardcodando `postalCode='00000000'` e telefone vazio. Isso enfraquece a credibilidade da transação anti-fraude. Recomenda-se a futura captura do endereço.

---

<p align="center">
  Desenvolvido com ❤️ para transformar a educação brasileira.
</p>
