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
CURRENT_SCALE=$($COMPOSE ps --format json $APP_SERVICE 2>/dev/null | \
    python3 -c "import sys,json; data=sys.stdin.read().strip(); print(len(json.loads(data)) if data.startswith('[') else (1 if data else 0))" 2>/dev/null || echo "1")

log_info "[2/5] Subindo segundo container com nova imagem (scale: ${CURRENT_SCALE} → 2)..."
log_info "      O container atual continua servindo enquanto o novo inicializa."

# CRÍTICO: Especificar $APP_SERVICE no final limita o escopo do 'up' APENAS
# ao serviço app. Sem isso, o Docker reconcilia toda a stack — incluindo o
# webserver que depende de service_healthy contra o container antigo (que
# não tem healthcheck) — causando "dependency failed to start".
$COMPOSE up -d --no-recreate --scale $APP_SERVICE=2 $APP_SERVICE

log_success "2 containers rodando. Nginx balanceará entre eles."
echo ""

# =============================================================================
# PASSO 3: Aguardar novo container ficar saudável
# =============================================================================
log_info "[3/5] Aguardando novo container ficar saudável (máx ${TIMEOUT}s)..."
log_info "      Verificando sentinel /tmp/app_ready + healthcheck Docker..."

START=$(date +%s)
while true; do
    NOW=$(date +%s)
    ELAPSED=$((NOW - START))

    if [ $ELAPSED -ge $TIMEOUT ]; then
        log_error "Timeout após ${TIMEOUT}s! Novo container não ficou saudável."
        
        NEW_CONTAINER=$($COMPOSE ps -q $APP_SERVICE | tail -n 1)
        if [ -n "$NEW_CONTAINER" ]; then
            log_info "=== STATUS DO HEALTHCHECK ($NEW_CONTAINER) ==="
            docker inspect --format='{{json .State.Health}}' "$NEW_CONTAINER" || true
            echo ""
            log_info "=== LOGS DO CONTAINER NOVO ==="
            docker logs --tail=60 "$NEW_CONTAINER" || true
            echo ""
        fi

        log_warning "Revertendo para 1 container (o antigo ainda está rodando)..."
        $COMPOSE up -d --no-recreate --scale $APP_SERVICE=1 $APP_SERVICE 2>/dev/null || true
        exit 1
    fi

    # Verifica se algum container está com health=healthy
    HEALTHY_COUNT=$($COMPOSE ps --format json $APP_SERVICE 2>/dev/null | \
        python3 -c "
import sys, json
data = sys.stdin.read().strip()
if not data:
    print(0); exit()
try:
    try:
        items = json.loads(data)
        if isinstance(items, dict): items = [items]
    except json.JSONDecodeError:
        items = [json.loads(line) for line in data.splitlines() if line.strip()]
    healthy = 0
    for i in items:
        status = str(i.get('Status', '')).lower()
        health = str(i.get('Health', '')).lower()
        if health == 'healthy' or 'healthy' in status:
            healthy += 1
    print(healthy)
except:
    print(0)
" 2>/dev/null || echo "0")

    if [ "$HEALTHY_COUNT" -ge 1 ]; then
        # Verifica se temos pelo MENOS 2 upstreams disponíveis (blue + green)
        TOTAL_COUNT=$($COMPOSE ps --format json $APP_SERVICE 2>/dev/null | \
            python3 -c "
import sys, json
data = sys.stdin.read().strip()
if not data:
    print(0); exit()
try:
    try:
        items = json.loads(data)
        if isinstance(items, dict): items = [items]
    except json.JSONDecodeError:
        items = [json.loads(line) for line in data.splitlines() if line.strip()]
    print(len(items))
except:
    print(0)
" 2>/dev/null || echo "0")

        if [ "$TOTAL_COUNT" -ge 2 ]; then
            log_success "Novo container saudável após ${ELAPSED}s! (${HEALTHY_COUNT} healthy de ${TOTAL_COUNT})"
            break
        fi
    fi

    printf "\r${YELLOW}  ⏳ Aguardando... ${ELAPSED}s/${TIMEOUT}s (healthy: ${HEALTHY_COUNT})${NC}"
    sleep 2
done
echo ""

# =============================================================================
# PASSO 4: Reiniciar workers e schedulers com a nova imagem
# =============================================================================
# Fazemos isto ENQUANTO temos 2 containers app rodando para garantir 
# que o pico de CPU do restart dos workers (46+ containers) não comprometa a API.
log_info "[4/5] Reiniciando workers, schedulers e serviços auxiliares..."
$COMPOSE up -d --no-deps worker ai-worker scheduler concursos-sync

echo ""

# =============================================================================
# PASSO 5: Remover container antigo (scale de volta para 1)
# =============================================================================
log_info "[5/5] Removendo container antigo (scale: 2 → 1)..."
log_info "      Requests em andamento no container antigo serão finalizados gracefully."

# Pequena espera para garantir que o nginx propagou o novo upstream
sleep 3

# Novamente: especificar $APP_SERVICE para não tocar em webserver/workers
$COMPOSE up -d --scale $APP_SERVICE=1 $APP_SERVICE

log_success "Container antigo removido. Apenas novo container ativo."

# ----------------------------------------------------------------
# PASSO FINAL: Reload Nginx (DNS Refresh)
# ----------------------------------------------------------------
log_info "      Fazendo reload final no Nginx para limpar DNS..."
$COMPOSE exec -T webserver nginx -s reload || log_warning "Falha ao recarregar Nginx."

log_success "Todos os serviços atualizados."
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
