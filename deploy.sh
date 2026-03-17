#!/bin/bash
# =============================================================================
# deploy.sh — Deploy seguro com zero downtime para AprenderAI
# =============================================================================
#
# ESTRATÉGIA:
#
#  1. BUILD COMPLETO:
#     - Builda TODAS as imagens (app + nginx) offline, sem parar nada.
#     - O frontend é compilado pelo Vite dentro do Docker build.
#     - A imagem do Nginx recebe os assets novos do frontend.
#
#  2. BLUE-GREEN SWAP (App / PHP-FPM):
#     - Captura o ID dos containers antigos do app.
#     - Sobe um segundo container (Green) com a nova imagem.
#     - Aguarda o novo container ficar saudável (healthcheck).
#     - Para o container antigo (Blue) explicitamente.
#     - Escala de volta para 1 (mantendo apenas o Green).
#
#  3. NGINX RESTART (Frontend):
#     - Após o swap do App, recria o container Nginx com a imagem nova.
#     - Isso dura ~1-2s — o frontend detecta via useDeployDetection e
#       exibe a tela de manutenção automaticamente.
#     - Sessões NÃO são perdidas (vivem no Redis DB 0, que não é tocado).
#
#  4. WORKERS + SCHEDULERS:
#     - Reiniciados com a nova imagem após o App estar saudável.
#
#  5. CACHE CLEANUP:
#     - Limpa Redis DB 1 (cache do Laravel) sem afetar sessões.
#     - Limpa caches do Laravel (config, routes, views).
#
# USO:
#   chmod +x deploy.sh
#   ./deploy.sh
#
# REQUISITOS:
#   - Docker Compose V2 (docker compose, não docker-compose)
#
# =============================================================================

set -euo pipefail

COMPOSE_FILE="docker-compose.prod.yml"
COMPOSE="docker compose -f $COMPOSE_FILE"
APP_SERVICE="app"
TIMEOUT=400  # Timeout para healthcheck (cobre migrations longas)

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
echo -e "${BLUE}  🚀  AprenderAI — Deploy Seguro (v3)${NC}"
echo -e "${BLUE}=================================================${NC}"
echo ""

# =============================================================================
# PASSO 1: Build de TODAS as imagens (sem parar nada)
# =============================================================================
log_info "[1/6] Buildando TODAS as imagens (app + nginx)..."
log_info "      Isso inclui: frontend (Vite) + backend (PHP) + webserver (Nginx)"

$COMPOSE build $APP_SERVICE webserver

log_success "Imagens buildadas: aprender-ai-app + aprender-ai-nginx"
echo ""

# =============================================================================
# PASSO 2: Blue-Green Swap — Subir novo container App
# =============================================================================
# Captura IDs dos containers antigos ANTES de subir os novos
OLD_APP_CONTAINERS=$($COMPOSE ps -q $APP_SERVICE)

log_info "[2/6] Subindo novo container App com a nova imagem (scale: 1 → 2)..."
log_info "      O container atual continua servindo enquanto o novo inicializa."

$COMPOSE up -d --no-recreate --scale $APP_SERVICE=2 $APP_SERVICE

log_success "2 containers App rodando. Nginx balanceia entre eles via Docker DNS."
echo ""

# =============================================================================
# PASSO 3: Aguardar novo container App ficar saudável
# =============================================================================
log_info "[3/6] Aguardando novo container App ficar saudável (máx ${TIMEOUT}s)..."
log_info "      O healthcheck verifica /tmp/app_ready (migrations + optimize + php-fpm)"

START=$(date +%s)
while true; do
    NOW=$(date +%s)
    ELAPSED=$((NOW - START))

    if [ $ELAPSED -ge $TIMEOUT ]; then
        log_error "Timeout após ${TIMEOUT}s! Novo container não ficou saudável."

        # Mostra logs do container novo para debug
        ALL_CONTAINERS=$($COMPOSE ps -q $APP_SERVICE)
        for CID in $ALL_CONTAINERS; do
            if [[ ! " $OLD_APP_CONTAINERS " =~ " $CID " ]]; then
                log_info "=== LOGS DO CONTAINER NOVO ($CID) ==="
                docker logs --tail=40 "$CID" 2>&1 || true
                echo ""
                log_info "=== HEALTHCHECK STATUS ==="
                docker inspect --format='{{json .State.Health}}' "$CID" 2>/dev/null || true
                echo ""
            fi
        done

        # Cleanup: remove o container que falhou, mantém o antigo
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

    # Verifica se o NOVO container (não-antigo) está saudável
    ALL_CONTAINERS=$($COMPOSE ps -q $APP_SERVICE)
    NEW_HEALTHY=0
    for CID in $ALL_CONTAINERS; do
        if [[ ! " $OLD_APP_CONTAINERS " =~ " $CID " ]]; then
            HEALTH=$(docker inspect --format='{{.State.Health.Status}}' "$CID" 2>/dev/null || echo "unknown")
            if [ "$HEALTH" == "healthy" ]; then
                NEW_HEALTHY=$((NEW_HEALTHY + 1))
            fi
        fi
    done

    if [ "$NEW_HEALTHY" -ge 1 ]; then
        log_success "Novo container App saudável após ${ELAPSED}s!"
        break
    fi

    printf "\r${YELLOW}  ⏳ Aguardando novo App... ${ELAPSED}s/${TIMEOUT}s${NC}"
    sleep 2
done
echo ""

# =============================================================================
# PASSO 4: Remover container App antigo (swap real)
# =============================================================================
log_info "[4/6] Removendo container App antigo para concluir o swap..."

# Pequena espera para Nginx propagar o novo upstream via DNS
sleep 3

# Parar containers antigos explicitamente
for CID in $OLD_APP_CONTAINERS; do
    log_info "      Finalizando container antigo: $CID"
    docker stop "$CID" >/dev/null 2>&1 || true
done

# Scale de volta para 1 (remove os containers parados)
$COMPOSE up -d --scale $APP_SERVICE=1 $APP_SERVICE

log_success "Swap do App concluído. Apenas novo container ativo."
echo ""

# =============================================================================
# PASSO 5: Recriar Nginx com a nova imagem (frontend atualizado)
# =============================================================================
# Este passo causa ~1-2s de indisponibilidade do webserver.
# O frontend detecta isso via useDeployDetection (polling /api/health)
# e exibe a tela de manutenção automaticamente.
# Sessões NÃO são perdidas — vivem no Redis DB 0.
# =============================================================================
log_info "[5/6] Recriando container Nginx com nova imagem (frontend atualizado)..."
log_info "      ⚡ Isso levará ~1-2s. A tela de manutenção será exibida no frontend."

$COMPOSE up -d --force-recreate --no-deps webserver

log_success "Nginx recriado com assets novos do frontend."
echo ""

# =============================================================================
# PASSO 6: Reiniciar workers e schedulers com a nova imagem
# =============================================================================
log_info "[6/6] Reiniciando workers, schedulers e serviços auxiliares..."
$COMPOSE up -d --no-deps worker ai-worker scheduler concursos-sync

log_success "Workers e schedulers reiniciados."
echo ""

# =============================================================================
# LIMPEZA FINAL: Cache do Laravel + Redis
# =============================================================================
log_info "[CACHE] Limpando caches do Laravel e Redis (DB 1 — Cache)..."

# Flush Redis DB 1 (cache). DB 0 (sessões) NÃO é tocado.
$COMPOSE exec -T redis redis-cli -n 1 FLUSHDB || log_warning "Falha ao limpar Redis DB 1."

# Limpa caches do Laravel (dentro do container novo)
$COMPOSE exec -T $APP_SERVICE php artisan cache:clear   || true
$COMPOSE exec -T $APP_SERVICE php artisan config:clear  || true
$COMPOSE exec -T $APP_SERVICE php artisan route:clear   || true
$COMPOSE exec -T $APP_SERVICE php artisan view:clear    || true

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
