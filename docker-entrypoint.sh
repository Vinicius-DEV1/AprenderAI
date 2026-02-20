#!/bin/sh
set -e

# Aguarda o banco de dados ficar disponível (opcional, mas recomendado)
echo "Aguardando inicialização do ambiente..."

# Ajusta permissões iniciais
chown -R 1337:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Roda as migrações se o arquivo .env existir
if [ -f .env ]; then
    echo "Rodando migrações..."
    php artisan migrate --force
    
    echo "Otimizando aplicação..."
    php artisan optimize
fi

echo "Iniciando PHP-FPM..."
exec php-fpm
