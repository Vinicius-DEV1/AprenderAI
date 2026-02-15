# AprovadoAI 🎓
> **Sua jornada rumo à aprovação potencializada por Inteligência Artificial.**

[![Laravel Version](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php)](https://www.php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](LICENSE)

---

## 📝 Sobre o Projeto

O **AprovadoAI** é uma plataforma brasileira de vanguarda projetada para revolucionar a preparação de estudantes para o ENEM e concursos públicos. Ao integrar modelos de linguagem de última geração (LLMs), o sistema oferece feedbacks instantâneos e personalizados, transformando o erro em uma oportunidade real de aprendizado.

A plataforma automatiza a correção de redações e simulados, permitindo que o estudante foque no que realmente importa: a evolução constante. Com uma arquitetura moderna e escalável, o AprovadoAI une a robustez do ecossistema Laravel com a agilidade do Tailwind CSS 4 para entregar uma experiência de usuário (UX) fluida e focada na produtividade.

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

O diferencial técnico do AprovadoAI reside na sua capacidade de orquestrar diferentes provedores de IA de forma transparente para o usuário final:

-   🧠 **Correção Inteligente**: Feedback detalhado de pontos de melhoria em redações baseados em critérios oficiais.
-   📊 **Gestão de Simulados**: Geração e acompanhamento de desempenho em baterias de questões por matéria.
-   🔑 **Dynamic AI Key Management**: Painel administrativo para alternar entre provedores de IA em tempo real.
-   💳 **Assinaturas**: Sistema de planos (Gratuito, Básico, Plus) com limites dinâmicos controlados via Middleware.

---

## ⚙️ Instalação (Quick Start)

Siga os comandos abaixo para configurar o ambiente de desenvolvimento:

```bash
# 1. Clonar o repositório
git clone https://github.com/Antonio7s/AprovadoAI.git
cd AprovadoAI

# 2. Instalar dependências e configurar ambiente
composer install && npm install

# 3. Configurar banco e chaves
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

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

<p align="center">
  Desenvolvido com ❤️ para transformar a educação brasileira.
</p>
