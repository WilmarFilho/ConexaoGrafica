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

local_sql() {
	# </dev/null pelo mesmo motivo do ssh -n: sem isso o docker engole o stdin
	# do script e o read da confirmação recebe EOF.
	docker compose -f "$PROJETO/docker-compose.yml" exec -T db \
		mysql -uconexao -pconexao conexao -N -B -e "$1" </dev/null 2>/dev/null | tr -d '\r'
}

echo "==> Comparando os dois ambientes"

# Contar o que existe em produção não diz nada: a sincronia anterior colocou o
# conteúdo local lá. O que importa é o que EXCEDE o local — isso só pode ter
# nascido em produção, e some se sobrescrevermos.
PROD_PRODUTOS=$(remoto "wp post list --post_type=cnx_produto --post_status=publish --format=count" 2>/dev/null || echo 0)
PROD_PAGINAS=$(remoto "wp post list --post_type=page,post --post_status=publish --format=count" 2>/dev/null || echo 0)
LEADS=$(remoto "wp post list --post_type=cnx_lead --format=count" 2>/dev/null || echo 0)

LOCAL_PRODUTOS=$(local_sql "SELECT COUNT(*) FROM wp_posts WHERE post_type='cnx_produto' AND post_status='publish'")
LOCAL_PAGINAS=$(local_sql "SELECT COUNT(*) FROM wp_posts WHERE post_type IN ('page','post') AND post_status='publish'")

printf '    produtos   local %-4s produção %s\n' "$LOCAL_PRODUTOS" "$PROD_PRODUTOS"
printf '    páginas    local %-4s produção %s\n' "$LOCAL_PAGINAS" "$PROD_PAGINAS"
printf '    leads      produção %s\n' "$LEADS"

MOTIVO=""
[ "$PROD_PRODUTOS" -gt "$LOCAL_PRODUTOS" ] && MOTIVO="produção tem $(( PROD_PRODUTOS - LOCAL_PRODUTOS )) produto(s) que não existem no local"
[ "$PROD_PAGINAS" -gt "$LOCAL_PAGINAS" ] && MOTIVO="${MOTIVO:+$MOTIVO; }produção tem $(( PROD_PAGINAS - LOCAL_PAGINAS )) página(s) a mais"
[ "$LEADS" -gt 0 ] && MOTIVO="${MOTIVO:+$MOTIVO; }$LEADS lead(s) recebido(s) pelo site no ar"

# Leads só nascem de visita real: se há algum, o site está sendo usado de verdade
# e o banco de produção virou a fonte da verdade.
if [ "${1:-}" != "--forcar" ] && [ -n "$MOTIVO" ]; then
	cat <<-AVISO

	  PARADO. Há conteúdo em produção que o local não tem:
	    $MOTIVO

	  Sobrescrever apagaria isso — e a sincronia é de mão única, do local
	  para produção. Se o site já está em uso, o caminho passa a ser o
	  contrário: cadastrar direto em produção.

	  Se ainda assim for isso mesmo:
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
