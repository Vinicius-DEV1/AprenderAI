#!/bin/bash
# =============================================================================
# deploy.sh — Deploy seguro com mínimo downtime para AprenderAI
# =============================================================================
#
# ESTRATÉGIA (Blue-Green via Docker Compose Scale):
#
#  1. SEPARAR BUILD do UP:
#     - Build da nova imagem acontece OFFLINE, sem parar o container atual.
#     - Reduz o período de indisponibilidade de ~90s para ~5-10s.
#
#  2. SCALE PARA 2 CONTAINERS (Blue-Green):
#     - Novo container (verde) sobe com a nova imagem.
#     - Antigo container (azul) continua servindo durante a inicialização do novo.
#     - Nginx (bloco upstream php_fpm) balanceia entre os dois via Docker DNS.
#     - Usuários com requests em andamento terminam no container antigo.
#
#  3. WAIT FOR HEALTHY:
#     - Deploy.sh aguarda o novo container criar /tmp/app_ready.
#     - Só então o antigo é removido.
#
#  4. WORKERS + SCHEDULERS:
#     - Reiniciados APÓS o novo app estar saudável.
#
# USO:
#   chmod +x deploy.sh
#   ./deploy.sh
#
# REQUISITOS:
#   - Docker Compose V2 (docker compose, não docker-compose)
#   - jq instalado (sudo apt install jq) — usado para parsear healthcheck status
#
# =============================================================================

set -euo pipefail

COMPOSE_FILE="docker-compose.prod.yml"
COMPOSE="docker compose -f $COMPOSE_FILE"
APP_SERVICE="app"
TIMEOUT=400  # Aumentado para 400s (para cobrir migrations longas)

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info()    { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error()   { echo -e "${RED}❌ $1${NC}"; }

echo ""
echo -e "${BLUE}=================================================${NC}"
echo -e "${BLUE}  🚀  AprenderAI — Deploy Seguro${NC}"
echo -e "${BLUE}=================================================${NC}"
echo ""

# =============================================================================
# PASSO 1: Build da nova imagem (sem parar nada)
# =============================================================================
log_info "[1/5] Buildando nova imagem (sem parar o container atual)..."
$COMPOSE build $APP_SERVICE

log_success "Nova imagem buildada: aprender-ai-app"
echo ""

# =============================================================================
# PASSO 2: Scale para 2 containers (blue + green)
# =============================================================================
# Captura IDs dos containers antigos antes de subir novos
OLD_APP_CONTAINERS=$($COMPOSE ps -q $APP_SERVICE)

log_info "[2/5] Subindo novo container com nova imagem (scale: 1 → 2)..."
log_info "      O container atual continua servindo enquanto o novo inicializa."

# Sobe o segundo container. O Docker manterá o antigo rodando devido ao status atual.
$COMPOSE up -d --no-recreate --scale $APP_SERVICE=2 $APP_SERVICE

log_success "2 containers rodando. Nginx balanceará entre eles."
echo ""

# =============================================================================
# PASSO 3: Aguardar novo container ficar saudável
# =============================================================================
log_info "[3/5] Aguardando novo container ficar saudável (máx ${TIMEOUT}s)..."

START=$(date +%s)
while true; do
    NOW=$(date +%s)
    ELAPSED=$((NOW - START))

    if [ $ELAPSED -ge $TIMEOUT ]; then
        log_error "Timeout após ${TIMEOUT}s! Novo container não ficou saudável."
        # Cleanup do container que falhou (o que não está na lista de antigos)
        ALL_CONTAINERS=$($COMPOSE ps -q $APP_SERVICE)
        for CID in $ALL_CONTAINERS; do
            if [[ ! " $OLD_APP_CONTAINERS " =~ " $CID " ]]; then
                log_info "Removendo container falho: $CID"
                docker stop "$CID" >/dev/null 2>&1 || true
                docker rm "$CID" >/dev/null 2>&1 || true
            fi
        done
        $COMPOSE up -d --scale $APP_SERVICE=1 $APP_SERVICE 2>/dev/null || true
        exit 1
    fi

    # Verifica se o NOVO container está saudável
    ALL_CONTAINERS=$($COMPOSE ps -q $APP_SERVICE)
    NEW_HEALTHY=0
    for CID in $ALL_CONTAINERS; do
        # Se NÃO é um dos antigos, verificamos a saúde
        if [[ ! " $OLD_APP_CONTAINERS " =~ " $CID " ]]; then
            HEALTH=$(docker inspect --format='{{.State.Health.Status}}' "$CID" 2>/dev/null || echo "unknown")
            if [ "$HEALTH" == "healthy" ]; then
                NEW_HEALTHY=$((NEW_HEALTHY + 1))
            fi
        fi
    done

    if [ "$NEW_HEALTHY" -ge 1 ]; then
        log_success "Novo container saudável após ${ELAPSED}s!"
        break
    fi

    printf "\r${YELLOW}  ⏳ Aguardando novo container... ${ELAPSED}s/${TIMEOUT}s${NC}"
    sleep 2
done
echo ""

# =============================================================================
# PASSO 4: Reiniciar workers e schedulers com a nova imagem
# =============================================================================
log_info "[4/5] Reiniciando workers, schedulers e serviços auxiliares..."
$COMPOSE up -d --no-deps worker ai-worker scheduler concursos-sync

echo ""

# =============================================================================
# PASSO 5: Remover container antigo (swap real)
# =============================================================================
log_info "[5/5] Removendo container antigo para concluir o swap..."

# Pequena espera para garantir que o nginx propagou o novo upstream
sleep 3

# PARAR os containers antigos explicitamente. 
# Isso força o 'scale 1' a manter apenas o novo que sobrou rodando.
for CID in $OLD_APP_CONTAINERS; do
    log_info "      Finalizando container antigo: $CID"
    docker stop "$CID" >/dev/null 2>&1 || true
done

# Escala de volta para 1 (isso removerá os containers parados)
$COMPOSE up -d --scale $APP_SERVICE=1 $APP_SERVICE

log_success "Swap concluído. Apenas novo container ativo."

# ----------------------------------------------------------------
# PASSO FINAL: Reload Nginx (DNS Refresh)
# ----------------------------------------------------------------
log_info "      Fazendo reload final no Nginx para limpar DNS..."
$COMPOSE exec -T webserver nginx -s reload || log_warning "Falha ao recarregar Nginx."

log_success "Todos os serviços atualizados."

# ----------------------------------------------------------------
# PASSO EXTRA: Limpeza agressiva de Cache
# ----------------------------------------------------------------
log_info "[EXTRA] Limpando caches e Redis (DB 1 - Cache)..."
$COMPOSE exec -T redis redis-cli -n 1 FLUSHDB || log_warning "Falha ao limpar Redis DB 1."
$COMPOSE exec -T $APP_SERVICE php artisan cache:clear || true
$COMPOSE exec -T $APP_SERVICE php artisan view:clear || true
$COMPOSE exec -T $APP_SERVICE php artisan route:clear || true

log_success "Cache limpo com sucesso!"
echo ""

# =============================================================================
# STATUS FINAL
# =============================================================================
echo -e "${GREEN}=================================================${NC}"
echo -e "${GREEN}  ✅  Deploy concluído com sucesso!${NC}"
echo -e "${GREEN}=================================================${NC}"
echo ""
$COMPOSE ps
echo ""
log_info "Dica: verifique a saúde do sistema em: https://aprenderai.com.br/api/health"
