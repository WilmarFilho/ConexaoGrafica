#!/usr/bin/env bash
#
# Copia o banco e os uploads do ambiente local para produção.
#
# NÃO roda no GitHub Actions de propósito: o runner não enxerga o MySQL do seu
# Docker, e commitar um dump neste repositório (que é público) exporia hashes de
# senha e e-mails. Então a sincronia é local e manual — um comando.
#
#   bash scripts/sincronizar-producao.sh
#
# ATENÇÃO: substitui o banco de produção inteiro. É seguro enquanto o site está
# em construção e não há conteúdo real do cliente. Depois do lançamento, pare de
# usar: o banco de produção passa a ser a fonte da verdade.

set -euo pipefail

SSH_USER=clientescx
SSH_HOST=187.127.42.20
CHAVE=~/.ssh/conexao_deploy
REMOTO=/home/clientescx/public_html/graficaconexao.com.br
URL_LOCAL=http://localhost:8080
URL_PROD=https://graficaconexao.com.br
PROJETO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

remoto() {
	# -n é essencial: sem ele o ssh engole o stdin do script e o read da
	# confirmação recebe EOF, abortando tudo antes de começar.
	ssh -n -i "$CHAVE" -o BatchMode=yes -o ConnectTimeout=20 "$SSH_USER@$SSH_HOST" "cd $REMOTO && $*"
}

echo "==> Conferindo o estado de produção"

PRODUTOS=$(remoto "wp post list --post_type=cnx_produto --post_status=publish --format=count" 2>/dev/null || echo 0)
LEADS=$(remoto "wp post list --post_type=cnx_lead --format=count" 2>/dev/null || echo 0)

echo "    produtos publicados: $PRODUTOS"
echo "    leads recebidos:     $LEADS"

# Trava de segurança: se já há conteúdo real lá, sobrescrever destrói trabalho
# de outra pessoa. A partir do lançamento, este script deve parar de ser usado.
if [ "${1:-}" != "--forcar" ] && { [ "$PRODUTOS" -gt 0 ] || [ "$LEADS" -gt 0 ]; }; then
	cat <<-AVISO

	  PARADO. Produção já tem conteúdo próprio:
	    $PRODUTOS produto(s) e $LEADS lead(s).

	  Sobrescrever o banco apagaria isso. Se o site já foi ao ar, o caminho
	  passa a ser o contrário: cadastrar direto em produção.

	  Se ainda assim for isso mesmo que você quer:
	    bash scripts/sincronizar-producao.sh --forcar

	AVISO
	exit 1
fi

echo
echo "    Isto vai SUBSTITUIR o banco de produção pelo local."
read -r -p "    Digite 'sim' para continuar: " RESPOSTA
[ "$RESPOSTA" = "sim" ] || { echo "    cancelado."; exit 0; }

echo
echo "==> 1/6  Backup do banco de produção"
CARIMBO=$(date +%Y%m%d-%H%M%S)
remoto "mkdir -p ~/backups && wp db export ~/backups/prod-$CARIMBO.sql --quiet"
echo "    salvo em ~/backups/prod-$CARIMBO.sql"

echo "==> 2/6  Exportando o banco local"
docker compose -f "$PROJETO/docker-compose.yml" exec -T db \
	mysqldump -uconexao -pconexao --no-tablespaces --default-character-set=utf8mb4 conexao \
	> "$PROJETO/.tmp/local.sql" 2>/dev/null
echo "    $(wc -c < "$PROJETO/.tmp/local.sql" | tr -d ' ') bytes"

echo "==> 3/6  Importando em produção"
# reset dropa TODAS as tabelas, inclusive as do prefixo antigo. Sem isso as
# tabelas YitgiJs_ ficariam órfãs no banco.
remoto "wp db reset --yes --quiet"
ssh -i "$CHAVE" -o BatchMode=yes "$SSH_USER@$SSH_HOST" \
	"cd $REMOTO && wp db import - --quiet" < "$PROJETO/.tmp/local.sql"

echo "==> 4/6  Alinhando o prefixo das tabelas"
# O dump traz tabelas wp_ e chaves como wp_capabilities/wp_user_roles, que são
# prefixadas. Trocar o prefixo no wp-config é mais seguro do que reescrever o
# dump: um sed em 'wp_' corromperia essas chaves dentro dos campos serializados.
remoto "wp config set table_prefix wp_ --type=variable --quiet"

echo "==> 5/6  Trocando as URLs"
remoto "wp search-replace '$URL_LOCAL' '$URL_PROD' --all-tables --precise --quiet"
remoto "wp cache flush --quiet; wp rewrite flush --quiet"

echo "==> 6/6  Enviando os uploads"
# rsync não existe no Git Bash do Windows; roda num container.
MSYS_NO_PATHCONV=1 docker run --rm \
	-v "$PROJETO/wp-content/uploads:/src:ro" \
	-v "$HOME/.ssh/conexao_deploy:/tmp/key_ro:ro" \
	alpine:3.20 sh -c '
		apk add --no-cache rsync openssh-client >/dev/null 2>&1
		cp /tmp/key_ro /tmp/key && chmod 600 /tmp/key
		rsync -az --chmod=D755,F644 --exclude=".gitkeep" \
			-e "ssh -i /tmp/key -o BatchMode=yes -o StrictHostKeyChecking=accept-new" \
			/src/ '"$SSH_USER@$SSH_HOST:$REMOTO"'/wp-content/uploads/
	'

rm -f "$PROJETO/.tmp/local.sql"

cat <<-FIM

	Pronto. Produção agora espelha o ambiente local.

	  $URL_PROD

	Duas coisas para conferir agora:

	  1. O login de produção passou a ser o do ambiente local. Troque a senha
	     imediatamente — o dump local usa credenciais de desenvolvimento:
	       ssh $SSH_USER@$SSH_HOST "cd $REMOTO && wp user update admin --user_pass='<senha forte>'"

	  2. Se algo quebrou, o backup está em ~/backups/prod-$CARIMBO.sql:
	       ssh $SSH_USER@$SSH_HOST "cd $REMOTO && wp db import ~/backups/prod-$CARIMBO.sql"

FIM
