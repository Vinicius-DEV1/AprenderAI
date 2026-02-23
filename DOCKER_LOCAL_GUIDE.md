# Guia: Execução Local com Docker (PHP 8.4) - 100% Automático

Este guia descreve como rodar o projeto **AprovadoAI** localmente de forma totalmente automatizada, sem depender do Sail.

## 1. Requisitos Prévios

- **Docker Desktop** instalado (Windows com WSL2 recomendado).
- Tenha o arquivo `.env.example` na raiz (o script criará o `.env` se não existir).

---

## 2. Como Rodar (Sequência Zero-Touch)

Basta um único comando para inicializar tudo (containers, composer, npm, migrations, key):

```bash
docker compose -f docker-compose.local.yml up -d --build
```

> [!IMPORTANT]
> O primeiro "up" pode demorar alguns minutos pois o container irá instalar automaticamente as pastas `vendor` e `node_modules` se elas não existirem na sua máquina.

---

## 3. O que acontece por baixo dos panos?

Ao subir os containers, um novo script de entrypoint (`docker-entrypoint-local.sh`) realiza as seguintes tarefas:
1.  **Cria o `.env`** caso não exista.
2.  **Instala o Composer** (`composer install`) se a pasta `vendor` estiver vazia.
3.  **Instala o NPM e Builda os assets** (`npm install` e `npm run build`) se `node_modules` estiver vazio.
4.  **Aguardar o Banco de Dados** estar pronto para receber conexões.
5.  **Gera a APP_KEY** caso necessário.
6.  **Executa as Migrations** automaticamente.
7.  **Cria o link simbólico** do storage.

---

## 4. Acesso e Verificação

- **Aplicação:** [http://localhost:8000](http://localhost:8000).
- **Acompanhar o progresso da inicialização:**
  ```bash
  docker logs -f aprovado-ai-app
  ```
- **Banco de Dados:** Host `localhost`, Porta `33061`, Usuário `root`, Senha `secret`.

---

## 5. Comandos Úteis

- **Acessar o terminal do app:** `docker exec -it aprovado-ai-app bash`
- **Forçar reinstalação:** Se quiser que o script instale tudo do zero novamente, apague as pastas `vendor` e `node_modules` e rode o docker compose novamente.
- **Parar tudo:** `docker compose -f docker-compose.local.yml down`
