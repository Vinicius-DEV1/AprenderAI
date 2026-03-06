#!/bin/sh
set -e

echo "🚀 Iniciando ambiente de DESENVOLVIMENTO..."

# Fix git ownership
git config --global --add safe.directory /var/www

# Ajusta permissões iniciais (usando uid 1000 que é o padrão no Dockerfile.local)
# CRÍTICO: criar ANTES do composer install (package:discover precisa de bootstrap/cache)
mkdir -p bootstrap/cache storage/framework/sessions storage/framework/views storage/framework/cache storage/logs
chown -R 1000:www-data bootstrap/cache storage || true
chmod -R 775 bootstrap/cache storage || true


if [ -f .env ] || [ -f .env.example ]; then
    # Se não houver .env, copia do .env.example
    if [ ! -f .env ]; then
        echo "📄 Criando arquivo .env a partir do .env.example..."
        cp .env.example .env
    fi

    # Instala dependências do Composer se a pasta vendor/autoload.php não existir
    if [ ! -f "vendor/autoload.php" ]; then
        echo "📦 Autoload não encontrado. Instalando dependências do Composer no volume interno..."
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    # Instala dependências do Node se a pasta node_modules não existir ou estiver vazia
    if [ ! -d "node_modules" ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
        echo "🐌 Node modules não encontrados. Instalando dependências do Node no volume interno..."
        npm install
        # echo "🏗️ Buildando assets iniciais..."
        # npm run build
    fi

    # ----------------------------------------------------------------
    # Aguarda o MySQL estar PRONTO para aceitar conexões.
    # ----------------------------------------------------------------
    echo "⏳ Aguardando o banco de dados ficar disponível..."
    MAX_TRIES=100
    COUNT=0
    # Debug env
    echo "DB_HOST=$DB_HOST, DB_DATABASE=$DB_DATABASE, DB_USERNAME=$DB_USERNAME"
    # Usando PHP puro para testar a conexão sem carregar o framework (mais rápido e robusto)
    until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { echo \$e->getMessage() . PHP_EOL; exit(1); }" ; do
        COUNT=$((COUNT + 1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            echo "❌ ERRO: Banco de dados não ficou disponível após ${MAX_TRIES} tentativas. Abortando."
            exit 1
        fi
        echo "  Banco não está pronto ainda (Tentativa ${COUNT}/${MAX_TRIES}). Aguardando 3s..."
        sleep 3
    done
    echo "✅ Banco de dados disponível!"
fi

# ----------------------------------------------------------------
# Inicialização do Laravel
# ----------------------------------------------------------------
if [ -f .env ]; then
    if ! grep -q "APP_KEY=base64:" .env; then
        echo "🔑 Gerando chave da aplicação..."
        php artisan key:generate
    fi
    
    echo "🔗 Verificando link de storage..."
    php artisan storage:link --force || true

    echo "📂 Rodando migrações de banco..."
    php artisan migrate --force || true

    echo "🧹 Limpando caches de desenvolvimento..."
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
fi

if [ "$1" = "php-fpm" ] || [ -z "$1" ]; then
    echo "🚀 Tudo pronto! Iniciando PHP-FPM..."
    exec php-fpm
else
    echo "⚡ Executando comando customizado: $@"
    exec "$@"
fi
