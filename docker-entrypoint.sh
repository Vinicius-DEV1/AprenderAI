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
    # --force é obrigatório em produção
    php artisan migrate --force
    
    echo "Alimentando banco de dados (Seeds)..."
    # Popula planos e configurações iniciais necessárias
    php artisan db:seed --force
    
    echo "Otimizando aplicação..."
    # Cache de configurações e rotas para máxima performance
    php artisan optimize
fi

echo "Iniciando PHP-FPM..."
exec php-fpm
