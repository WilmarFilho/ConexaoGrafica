#!/usr/bin/env bash
# Roda a suíte do Hub no servidor com o código local ainda não commitado.
# Não há PHP na máquina local: o código vai por scp para ~/hub-wip-<frente> na conta
# hubpubcon e os testes rodam lá (vendor copiado do ~/hub-ci na primeira vez).
#
#   bash deploy/testar-wip.sh [filtro] [linhas]
#   FRENTE=a bash deploy/testar-wip.sh Editorial     # pasta ~/hub-wip-a (uma por frente/agente)
#
# Nunca publica nada: produção só pelo deploy.sh.
set -euo pipefail
FILTRO="${1:-}"
LINHAS="${2:-40}"
FRENTE="${FRENTE:-}"
HOST="${HUB_SSH:-root@187.127.42.20}"
AQUI="$(cd "$(dirname "$0")" && pwd)"
APP="$(cd "$AQUI/.." && pwd)"
TAG="$(date +%s)-$$"

cd "$APP"
tar -czf "/tmp/hub-wip-$TAG.tgz" app bootstrap/app.php bootstrap/providers.php config database lang resources routes tests artisan composer.json composer.lock phpunit.xml 2>/dev/null
scp -q "/tmp/hub-wip-$TAG.tgz" "$AQUI/wip-remoto.sh" "$HOST:/tmp/"
rm -f "/tmp/hub-wip-$TAG.tgz"
ssh -o ConnectTimeout=20 "$HOST" "chmod 644 /tmp/hub-wip-$TAG.tgz /tmp/wip-remoto.sh; su -s /bin/bash hubpubcon -c 'bash /tmp/wip-remoto.sh \"$TAG\" \"$FRENTE\" \"$FILTRO\" $LINHAS'; rm -f /tmp/hub-wip-$TAG.tgz"
