#!/bin/sh
set -e

echo "🚀 Iniciando ambiente de DESENVOLVIMENTO..."

# Ajusta permissões iniciais (usando uid 1000 que é o padrão no Dockerfile.local)
# Ajusta permissões iniciais (Adicionado || true para não travar no Windows)
# Ajusta permissões iniciais (apenas nos diretórios base para evitar lentidão com milhares de arquivos no Windows)
chown 1000:www-data storage bootstrap/cache || true
chmod 775 storage bootstrap/cache || true
find storage -maxdepth 2 -not -path '*/.*' -exec chown 1000:www-data {} + || true
find storage -maxdepth 2 -not -path '*/.*' -exec chmod 775 {} + || true

if [ -f .env ] || [ -f .env.example ]; then
    # Se não houver .env, copia do .env.example
    if [ ! -f .env ]; then
        echo "📄 Criando arquivo .env a partir do .env.example..."
        cp .env.example .env
    fi

    # Instala dependências do Composer se a pasta vendor não existir
    if [ ! -d "vendor" ]; then
        echo "📦 Pasta vendor não encontrada. Instalando dependências do Composer..."
        composer install --no-interaction --prefer-dist
    fi

    # Instala dependências do Node se a pasta node_modules não existir
    if [ ! -d "node_modules" ]; then
        echo "🐌 Pasta node_modules não encontrada. Instalando dependências do Node..."
        npm install
        echo "🏗️ Buildando assets iniciais..."
        npm run build
    fi

    # ----------------------------------------------------------------
    # Aguarda o MySQL estar PRONTO para aceitar conexões.
    # ----------------------------------------------------------------
    echo "⏳ Aguardando o banco de dados ficar disponível..."
    MAX_TRIES=30
    COUNT=0
    until php artisan db:show > /dev/null 2>&1; do
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
    php artisan migrate --force

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
