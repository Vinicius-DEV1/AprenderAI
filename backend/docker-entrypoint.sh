#!/bin/sh
set -e

echo "Aguardando inicialização do ambiente..."

# Remove sentinel de boots anteriores para evitar falsos positivos
# (caso o container tenha sido reiniciado sem rebuild)
rm -f /tmp/app_ready

# Ajusta permissões iniciais (Silencia erros se não for root)
chown -R 1337:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Garante que as pastas de storage existem internamente
# (Já devem existir pela imagem, mas o || true garante que o boot não trave)
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/app/public/imports_tmp 2>/dev/null || true
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

if [ -f .env ]; then
    # ----------------------------------------------------------------
    # Aguarda o MySQL estar PRONTO para aceitar conexões.
    # Aplicado a todos os serviços (app, worker, scheduler)
    # ----------------------------------------------------------------
    echo "Aguardando o banco de dados ficar disponível..."
    MAX_TRIES=30
    COUNT=0
    until php artisan db:show; do
        COUNT=$((COUNT + 1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            echo "ERRO: Banco de dados não ficou disponível após ${MAX_TRIES} tentativas. Abortando."
            php artisan db:show # Mostra o erro um última vez antes de sair totalmente
            exit 1
        fi
        echo "  Banco não está pronto ainda (veja erro acima). Tentativa ${COUNT}/${MAX_TRIES}. Aguardando 3s..."
        sleep 3
    done
    echo "Banco de dados disponível!"
fi

# ----------------------------------------------------------------
# Garantir link de storage antes de qualquer comando
# Se public/storage for um diretório real (e não um link), removemos.
# ----------------------------------------------------------------
if [ -d "public/storage" ] && [ ! -L "public/storage" ]; then
    echo "⚠️  AVISO: public/storage é um diretório real. Reconvertendo para link simbólico..."
    rm -rf public/storage
fi
echo "🔗 Criando link simbólico de storage..."
php artisan storage:link --force || echo "⚠️  Falha ao criar link de storage (pode já existir)"

if [ "$1" = "php-fpm" ] || [ -z "$1" ]; then
    if [ -f .env ]; then
        echo "🚀 Iniciando rotinas de produção..."
        echo "📂 Rodando migrações de banco..."
        # Executa a migração sem o "set -e" interromper imediatamente
        set +e
        php artisan migrate --force
        MIGRATE_STATUS=$?
        set -e
        
        if [ $MIGRATE_STATUS -ne 0 ]; then
            echo "❌ ERRO CRÍTICO: Falha ao rodar as migrações (php artisan migrate --force)."
            echo "⚠️ O container não será finalizado imediatamente para evitar RESTART LOOP."
            echo "⏳ Aguardando 10 minutos para debug antes de encerrar o container..."
            sleep 600
            exit 1
        fi
        
        echo "⚡ Otimizando cache do Laravel..."
        # Limpa caches antigos antes de otimizar para evitar TypeErrors (comum em roteamento)
        php artisan route:clear
        php artisan config:clear
        php artisan cache:clear
        php artisan optimize
    fi

    echo "✅ Pronto! Iniciando PHP-FPM..."
    # ----------------------------------------------------------------
    # SENTINEL FILE — Criado AQUI, imediatamente antes de exec php-fpm.
    # O Docker healthcheck verifica este arquivo para confirmar que o
    # container concluiu toda a inicialização (migrate + optimize).
    # Só após este arquivo existir, o webserver/nginx recebe tráfego.
    # ----------------------------------------------------------------
    touch /tmp/app_ready
    exec php-fpm
else
    echo "Executando comando customizado: $@"
    # Workers e schedulers também criam o sentinel para que seus
    # próprios healthchecks (se adicionados no futuro) funcionem.
    touch /tmp/app_ready
    exec "$@"
fi
