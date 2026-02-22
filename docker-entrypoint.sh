#!/bin/sh
set -e

# Aguarda o banco de dados ficar disponível (opcional, mas recomendado)
echo "Aguardando inicialização do ambiente..."

# Ajusta permissões iniciais
chown -R 1337:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Otimização opcional
if [ "$1" = "php-fpm" ] || [ -z "$1" ]; then
    if [ -f .env ]; then
        echo "Rodando migrações..."
        php artisan migrate --force
        
        echo "Alimentando banco de dados (Seeds)..."
        php artisan db:seed --force
        
        echo "Otimizando aplicação..."
        php artisan optimize
    fi
    echo "Iniciando PHP-FPM..."
    exec php-fpm
else
    echo "Executando comando customizado: $@"
    exec "$@"
fi
