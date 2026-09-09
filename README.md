# AprenderAI (Aprovado AI) - Monorepo

> Plataforma inteligente de preparação e estudos para concursos públicos e exames com inteligência artificial.

---

## 🏛️ Visão Geral

O **AprenderAI** é uma solução completa para estudantes e concurseiros que integra inteligência artificial para resolução comentada de questões, geração de simulados personalizados e análise de desempenho em tempo real.

---

## 📂 Estrutura do Monorepo

```plaintext
.
├── backend/            # API RESTful em PHP 8.x + Laravel
├── frontend/           # SPA moderna em React + TypeScript + Vite + TailwindCSS
├── scraper/            # Scripts de extração e higienização de questões e gabaritos
├── documentacao/       # Guias técnicos e regras de negócio
├── docker-compose.local.yml  # Orquestração de ambiente local (MySQL, Redis, App)
└── docker-compose.prod.yml   # Orquestração de produção
```

---

## 🚀 Como Executar

### Pré-requisitos
- Docker e Docker Compose instalados
- Node.js 18+ (para desenvolvimento local no frontend)
- PHP 8.2+ e Composer (opcional se utilizar Docker)

### 1. Inicialização via Docker (Recomendado)
```bash
# Iniciar todos os serviços do monorepo em background
docker compose -f docker-compose.local.yml up -d
```

### 2. Backend (Laravel)
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 3. Frontend (React + Vite)
```bash
cd frontend
npm install
npm run dev
```

---

## 📄 Licença
Propriedade privada de Vinicius Calado (@Vinicius-DEV1). Todos os direitos reservados.
