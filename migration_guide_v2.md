# 🛠️ Guia de Migração: Sail ➔ Produção (Nginx + PHP-FPM)

Este guia detalha como subir sua aplicação na AWS VPS usando a nova estrutura de alta performance com **PHP 8.4.16**.

---

## 📂 1. Estrutura de Arquivos Criada
Você deve copiar estes novos arquivos para a raiz do seu projeto na VPS:
1.  `Dockerfile.prod` (Configuração do PHP-FPM)
2.  `nginx.conf` (Configuração do Servidor Web)
3.  `docker-compose.prod.yml` (Orquestração de serviços)

---

## 🚀 2. Comandos de Build e Execução na VPS

Execute a sequência abaixo para limpar o ambiente Sail antigo e subir a nova estrutura:

```bash
# 1. Parar containers antigos (se houver)
docker-compose down

# 2. Build e Up usando o novo arquivo de produção
docker-compose -f docker-compose.prod.yml up -d --build

# 3. Instalar dependências (dentro do container app)
docker-compose -f docker-compose.prod.yml exec app composer install --optimize-autoloader --no-dev
```

---

## 🔒 3. Permissões e Otimização

O Laravel exige permissões de escrita em pastas específicas. Rode estes comandos após o build:

```bash
# Ajustar permissões de escrita
docker-compose -f docker-compose.prod.yml exec app chown -R 1337:www-data storage bootstrap/cache
docker-compose -f docker-compose.prod.yml exec app chmod -R 775 storage bootstrap/cache

# Otimizar Laravel para produção
docker-compose -f docker-compose.prod.yml exec app php artisan optimize
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

---

## 🛡️ 4. Checklist de Segurança DevOps

*   **Isolamento de DB**: O serviço `db` agora está na rede `production-network` sem mapeamento de portas externas. O container `app` o acessa via hostname `db`.
*   **Performance**: O Nginx foi configurado com `gzip` e o PHP-FPM utiliza a porta 9000 internamente.
*   **Persistence**: O banco de dados usa um volume nomeado `production-db-data` para garantir que seus dados não sumam ao reiniciar.

> [!IMPORTANT]
> Lembre-se de configurar seu `.env` na VPS com `APP_DEBUG=false` e as credenciais do DB apontando para `DB_HOST=db`.
