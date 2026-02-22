#!/bin/sh
set -e

echo "Aguardando inicialização do ambiente..."

# Ajusta permissões iniciais
chown -R 1337:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ -f .env ]; then
    # ----------------------------------------------------------------
    # Aguarda o MySQL estar PRONTO para aceitar conexões.
    # Aplicado a todos os serviços (app, worker, scheduler)
    # ----------------------------------------------------------------
    echo "Aguardando o banco de dados ficar disponível..."
    MAX_TRIES=30
    COUNT=0
    until php artisan db:show > /dev/null 2>&1; do
        COUNT=$((COUNT + 1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            echo "ERRO: Banco de dados não ficou disponível após ${MAX_TRIES} tentativas. Abortando."
            exit 1
        fi
        echo "  Banco não está pronto ainda. Tentativa ${COUNT}/${MAX_TRIES}. Aguardando 3s..."
        sleep 3
    done
    echo "Banco de dados disponível!"
fi

# ----------------------------------------------------------------
# Garantir link de storage antes de qualquer comando
# Se public/storage for um diretório real (e não um link), removemos.
# ----------------------------------------------------------------
if [ -d "public/storage" ] && [ ! -L "public/storage" ]; then
    echo "Limpando diretório real de storage para criar link simbólico..."
    rm -rf public/storage
fi
php artisan storage:link --force 2>/dev/null || true

if [ "$1" = "php-fpm" ] || [ -z "$1" ]; then
    if [ -f .env ]; then
        echo "Rodando migrações..."
        php artisan migrate --force
        
        echo "Otimizando aplicação..."
        php artisan optimize
    fi
    echo "Iniciando PHP-FPM..."
    exec php-fpm
else
    echo "Executando comando customizado: $@"
    exec "$@"
fi
