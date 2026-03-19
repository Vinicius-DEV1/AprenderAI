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
#     - Captura o ID e NAME dos containers antigos do app.
#     - Sobe um segundo container (Green) com a nova imagem.
#     - Aguarda ESPECIFICAMENTE o(s) novo(s) container(s) ficarem saudáveis.
#     - Para e REMOVE os containers antigos explicitamente via docker rm.
#     - Escala de volta para 1 (sem ambiguidade: só o Green sobrou).
#
#  3. NGINX REFRESH (Frontend):
#     - Após o swap do App, usa `docker restart` no container Nginx.
#     - Isso força o Nginx a recarregar sua imagem na RAM sem recriar o container.
#     - Dura ~1s. O frontend detecta via useDeployDetection e exibe manutenção.
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
echo -e "${BLUE}  🚀  AprenderAI — Deploy Seguro (v4.1)${NC}"
echo -e "${BLUE}=================================================${NC}"
echo ""

# Helper para verificar se um ID de container está em uma lista de IDs
is_id_in_list() {
    local target="$1"
    local list="$2"
    for id in $list; do
        if [ "$id" == "$target" ]; then
            return 0
        fi
    done
    return 1
}

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
# Captura IDs E NAMES dos containers antigos ANTES de subir os novos.
# Guardar os NAMES é crítico para o rm depois do stop.
OLD_APP_IDS=$($COMPOSE ps -q $APP_SERVICE)
OLD_APP_NAMES=$($COMPOSE ps --format '{{.Name}}' $APP_SERVICE 2>/dev/null || \
                $COMPOSE ps --format json $APP_SERVICE 2>/dev/null | \
                python3 -c "import sys,json; data=sys.stdin.read().strip(); items=json.loads(data) if data.startswith('[') else [json.loads(data)]; [print(i.get('Name','').lstrip('/')) for i in items]" 2>/dev/null)

log_info "[2/6] Subindo novo container App com a nova imagem (scale: 1 → 2)..."
log_info "      Containers antigos: $OLD_APP_NAMES"
log_info "      O container atual continua servindo enquanto o novo inicializa."

$COMPOSE up -d --no-recreate --scale $APP_SERVICE=2 $APP_SERVICE

log_success "2 containers App rodando."
echo ""

# =============================================================================
# PASSO 3: Aguardar novo container App ficar saudável
# =============================================================================
log_info "[3/6] Aguardando novo container App ficar saudável (máx ${TIMEOUT}s)..."
log_info "      O healthcheck verifica /tmp/app_ready (migrations + optimize + php-fpm)"

START=$(date +%s)
HEALTHY_NEW_CONTAINER=""

while true; do
    NOW=$(date +%s)
    ELAPSED=$((NOW - START))

    if [ $ELAPSED -ge $TIMEOUT ]; then
        log_error "Timeout após ${TIMEOUT}s! Novo container não ficou saudável."

        # Mostra logs do container novo para debug
        ALL_IDS=$($COMPOSE ps -q $APP_SERVICE)
        for CID in $ALL_IDS; do
            if [[ ! " $OLD_APP_IDS " =~ " $CID " ]]; then
                log_info "=== LOGS DO CONTAINER NOVO ($CID) ==="
                docker logs --tail=40 "$CID" 2>&1 || true
                echo ""
            fi
        done

        # Cleanup: remove o container novo que falhou, preserva o antigo
        for CID in $ALL_IDS; do
            if [[ ! " $OLD_APP_IDS " =~ " $CID " ]]; then
                log_info "Removendo container novo falho: $CID"
                docker stop "$CID" >/dev/null 2>&1 || true
                docker rm "$CID" >/dev/null 2>&1 || true
            fi
        done
        # Garante que temos exatamente 1 container (o antigo)
        $COMPOSE up -d --no-recreate --scale $APP_SERVICE=1 $APP_SERVICE 2>/dev/null || true
        exit 1
    fi

    # Verifica se algum container NOVO (não-antigo) está healthy
    ALL_IDS=$($COMPOSE ps -q $APP_SERVICE)
    for CID in $ALL_IDS; do
        if ! is_id_in_list "$CID" "$OLD_APP_IDS"; then
            HEALTH=$(docker inspect --format='{{.State.Health.Status}}' "$CID" 2>/dev/null || echo "unknown")
            if [ "$HEALTH" == "healthy" ]; then
                HEALTHY_NEW_CONTAINER="$CID"
                break 2   # Sai do while e do for
            fi
        fi
    done

    printf "\r${YELLOW}  ⏳ Aguardando novo App... ${ELAPSED}s/${TIMEOUT}s${NC}"
    sleep 2
done
echo ""
log_success "Novo container App saudável: $HEALTHY_NEW_CONTAINER"
echo ""

# =============================================================================
# PASSO 4: Remover containers App antigos (swap real)
# =============================================================================
log_info "[4/6] Removendo containers App antigos (swap definitivo)..."

# Pequena espera para o Nginx propagar o novo upstream via DNS
sleep 3

# PARA e REMOVE os containers antigos explicitamente, 
# EXCETO o que acabamos de marcar como saudável (caso ele tenha sido reusado por algum motivo).
for CID in $OLD_APP_IDS; do
    if [ "$CID" == "$HEALTHY_NEW_CONTAINER" ]; then
        log_warning "      Pulando remoção do container $CID (marcado como novo saudável)"
        continue
    fi
    log_info "      Parando container antigo: $CID"
    docker stop "$CID" >/dev/null 2>&1 || true
    log_info "      Removendo container antigo: $CID"
    docker rm   "$CID" >/dev/null 2>&1 || true
done

# RE-SINCRONIZAÇÃO: Após remover osIDs antigos via docker-direct, o Compose pode ficar "perdido".
# Forçamos um scale 1 agora para garantir que ele entenda que só o GREEN sobrou.
$COMPOSE up -d --no-recreate --scale $APP_SERVICE=1 $APP_SERVICE >/dev/null 2>&1

log_success "Swap concluído. Apenas novo container ativo."
echo ""

# =============================================================================
# PASSO 5: Recarregar Nginx com a nova imagem (frontend atualizado)
# =============================================================================
# Como os assets do frontend estão DENTRO da nova imagem do webserver,
# DEVEMOS forçar a recriação do container (com `--force-recreate`).
# O downtime do Nginx aqui é < 1 segundo, sendo infinitamente mais escalável e
# menos suscetível a bugs de cache de imagem do que persistir o container.
# Usamos `--no-deps` para que ele não verifique as dependências do app (já resolvidas).
# =============================================================================
log_info "[5/6] Recarregando Nginx com nova imagem (frontend atualizado)..."
log_info "      ⚡ Recriando container webserver (Downtime rápido < 1s)..."

$COMPOSE up -d --force-recreate --no-deps webserver

log_success "Nginx recriado com assets novos do frontend com sucesso."
echo ""

# =============================================================================
# PASSO 6: Reiniciar workers e schedulers com a nova imagem
# =============================================================================
log_info "[6/6] Reiniciando workers, schedulers e serviços auxiliares..."
$COMPOSE up -d --force-recreate --no-deps worker ai-triage-worker ai-embeddings-worker scheduler concursos-sync

log_success "Workers e schedulers reiniciados."
echo ""

# =============================================================================
# LIMPEZA FINAL: Cache do Laravel + Redis
# =============================================================================
log_info "[CACHE] Limpando caches do Laravel e Redis (DB 1 — Cache)..."

# Flush Redis DB 1 (cache). DB 0 (sessões) NÃO é tocado.
$COMPOSE exec -T redis redis-cli -n 1 FLUSHDB || log_warning "Falha ao limpar Redis DB 1."

# Aguarda o app estar pronto antes de limpar via artisan
sleep 2
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
