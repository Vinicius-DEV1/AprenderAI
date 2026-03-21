#!/bin/sh
set -e

echo "🚀 Iniciando ambiente de DESENVOLVIMENTO..."

# Fix git ownership
git config --global --add safe.directory /var/www

# --- SETUP INICIAL ---
if [ "$1" = "php-fpm" ] || [ -z "$1" ]; then
    if [ ! -f .env ] && [ -f .env.example ]; then
        echo "📄 Criando arquivo .env a partir do .env.example..."
        cp .env.example .env
    fi

    if [ "$INITIALIZE_APP" = "true" ]; then
        echo "🛡️  [CONTAINER PRINCIPAL] Realizando setup inicial..."

        # Instala dependências do Composer se a pasta vendor/autoload.php não existir
        if [ ! -f "vendor/autoload.php" ]; then
            echo "📦 Instalando dependências do Composer..."
            composer install --no-interaction --prefer-dist --optimize-autoloader
        fi

        # Gerar chave se necessário
        if ! grep -q "APP_KEY=base64:" .env; then
            echo "🔑 Gerando chave da aplicação..."
            php artisan key:generate
        fi

        # Storage link
        echo "🔗 Verificando link de storage..."
        php artisan storage:link --force || true

        # Aguarda o MySQL
        echo "⏳ Aguardando o banco de dados ficar disponível..."
        until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT => 3]); exit(0); } catch (Exception \$e) { exit(1); }" ; do
            echo "  Banco não está pronto ainda... aguardando 2s..."
            sleep 2
        done
        echo "✅ Banco de dados disponível!"

        echo "📂 Rodando migrações de banco..."
        php artisan migrate --force

        echo "🧹 Limpando caches de desenvolvimento..."
        php artisan config:clear
        php artisan route:clear
        php artisan view:clear
    else
        echo "⏳ [CONTAINER SECUNDÁRIO] Aguardando inicialização completa do App..."
        # Espera o vendor estar presente pelo menos
        while [ ! -f "vendor/autoload.php" ]; do
            sleep 2
        done
    fi

    echo "🚀 Tudo pronto! Iniciando PHP-FPM..."
    exec php-fpm
else
    echo "⚡ Executando comando customizado: $@"
    exec "$@"
fi
