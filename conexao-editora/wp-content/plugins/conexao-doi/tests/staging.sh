#!/usr/bin/env bash
#
# Testa o plugin no staging, de dentro do servidor (o site só responde por IP
# com --resolve). Rodar como o usuário da conta:
#
#   su -s /bin/bash hubpubcon -c "bash ~/sites/conexaoeditora-staging/wp-content/plugins/conexao-doi/tests/staging.sh"
#
# Lê CONEXAO_DOI_SECRET do wp-config pelo WP-CLI, assina cada chamada com
# openssl e confere as respostas. O segredo nunca é impresso.
#
set -uo pipefail

SITE=${SITE:-$HOME/sites/conexaoeditora-staging}
HOST=${HOST:-staging.conexaoeditora.com.br}
IP=${IP:-187.127.42.20}
PLUGIN="$SITE/wp-content/plugins/conexao-doi"
FIXTURE=${1:-$PLUGIN/tests/publicacao-staging.json}
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

cd "$SITE" || exit 1

SECRET=$(wp config get CONEXAO_DOI_SECRET 2>/dev/null)

if [ -z "$SECRET" ]; then
    echo "CONEXAO_DOI_SECRET não está no wp-config.php do staging." >&2
    exit 1
fi

falhas=0
ok() { echo "  [ok] $1"; }
erro() { echo "  [ERRO] $1"; falhas=$((falhas + 1)); }
confere() { # descrição, condição (0 = passou)
    if [ "$2" -eq 0 ]; then ok "$1"; else erro "$1"; fi
}

# assina timestamp + "\n" + corpo (o corpo exatamente como o curl envia)
assinar() { # timestamp [arquivo]
    if [ -n "${2:-}" ]; then
        { printf '%s\n' "$1"; cat "$2"; } | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //'
    else
        printf '%s\n' "$1" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //'
    fi
}

# chamar MÉTODO ROTA [ARQUIVO-CORPO] [TIMESTAMP] [ASSINATURA]
# Grava o corpo da resposta em $TMP/resposta e devolve o código HTTP.
chamar() {
    local metodo=$1 rota=$2 corpo=${3:-} ts=${4:-$(date +%s)} sig=${5:-}
    [ -z "$sig" ] && sig=$(assinar "$ts" "$corpo")

    curl -s --resolve "$HOST:80:$IP" -X "$metodo" "http://$HOST/wp-json/conexao-doi/v1/$rota" \
        -H "Content-Type: application/json" \
        -H "X-Conexao-Timestamp: $ts" -H "X-Conexao-Signature: $sig" \
        ${corpo:+--data-binary @"$corpo"} \
        -o "$TMP/resposta" -w '%{http_code}'
}

pagina() { # URL → grava em $TMP/pagina e devolve o código
    curl -s --resolve "$HOST:80:$IP" "$1" -o "$TMP/pagina" -w '%{http_code}'
}

json() { # expressão PHP sobre $r (resposta decodificada)
    php -r '$r = json_decode(file_get_contents($argv[1]), true); echo '"$1"';' "$TMP/resposta"
}

echo "== PUT da fixture ($FIXTURE)"
codigo=$(chamar PUT publicacoes/1 "$FIXTURE")
confere "PUT responde 200 (veio $codigo)" $([ "$codigo" = 200 ]; echo $?)
cat "$TMP/resposta"; echo
LIVRO_ID=$(json '$r["book"]["wp_post_id"] ?? 0')
LIVRO_URL=$(json '$r["book"]["url"] ?? ""')
CAP_URL=$(json '$r["chapters"][0]["url"] ?? ""')
CAP_ID=$(json '$r["chapters"][0]["wp_post_id"] ?? 0')
PESSOAS=$(json 'count($r["people"] ?? [])')
confere "resposta traz book.url, 2 capítulos e 4 pessoas" $([ -n "$LIVRO_URL" ] && [ "$(json 'count($r["chapters"] ?? [])')" = 2 ] && [ "$PESSOAS" = 4 ]; echo $?)

echo "== PUT repetido não duplica"
codigo=$(chamar PUT publicacoes/1 "$FIXTURE")
confere "segundo PUT responde 200" $([ "$codigo" = 200 ]; echo $?)
confere "mesmo wp_post_id do livro ($LIVRO_ID)" $([ "$(json '$r["book"]["wp_post_id"]')" = "$LIVRO_ID" ]; echo $?)
confere "mesmo wp_post_id do capítulo 1 ($CAP_ID)" $([ "$(json '$r["chapters"][0]["wp_post_id"]')" = "$CAP_ID" ]; echo $?)
quantos=$(wp post list --post_type=publicacao --post_status=any --meta_key=_conexao_hub_id --meta_value=1 --format=count)
confere "só um post publicacao com hub_id 1 (há $quantos)" $([ "$quantos" = 1 ]; echo $?)
quantos=$(wp post list --post_type=capitulo --post_status=any --post_parent="$LIVRO_ID" --format=count)
confere "dois capítulos filhos do livro (há $quantos)" $([ "$quantos" = 2 ]; echo $?)

echo "== GET"
codigo=$(chamar GET publicacoes/1)
confere "GET responde 200 (veio $codigo)" $([ "$codigo" = 200 ]; echo $?)
confere "GET traz slug e status published" $([ "$(json '$r["book"]["status"]')" = published ]; echo $?)

echo "== autenticação"
codigo=$(chamar GET publicacoes/1 "" "$(date +%s)" "deadbeef")
confere "assinatura errada → 401 (veio $codigo)" $([ "$codigo" = 401 ]; echo $?)
codigo=$(chamar GET publicacoes/1 "" "$(( $(date +%s) - 600 ))")
confere "timestamp de 10 min atrás → 401 (veio $codigo)" $([ "$codigo" = 401 ]; echo $?)
codigo=$(curl -s --resolve "$HOST:80:$IP" "http://$HOST/wp-json/conexao-doi/v1/publicacoes/1" -o /dev/null -w '%{http_code}')
confere "sem cabeçalhos → 401 (veio $codigo)" $([ "$codigo" = 401 ]; echo $?)
codigo=$(chamar GET publicacoes/999999)
confere "livro inexistente → 404 (veio $codigo)" $([ "$codigo" = 404 ]; echo $?)

echo "== página do livro: $LIVRO_URL"
codigo=$(pagina "$LIVRO_URL")
confere "responde 200 (veio $codigo)" $([ "$codigo" = 200 ]; echo $?)
confere "título no <title>" $(grep -q '<title>Tratado de Doenças Raras: Diagnóstico, manejo e políticas públicas | ' "$TMP/pagina"; echo $?)
confere "h1 com o título" $(grep -q 'class="doi-titulo">Tratado de Doenças Raras<' "$TMP/pagina"; echo $?)
confere "DOI com link para doi.org" $(grep -q 'href="https://doi.org/10.00000/conexao.2026.001"' "$TMP/pagina"; echo $?)
confere "organizadores no cabeçalho" $(grep -q 'Maria Exemplo da Silva' "$TMP/pagina" && grep -q 'João Exemplo Pereira' "$TMP/pagina"; echo $?)
confere "ORCID só para quem tem" $([ "$(grep -o 'https://orcid.org/0000-0002-1825-0097' "$TMP/pagina" | wc -l)" -ge 1 ] && ! grep -q 'orcid.org/" ' "$TMP/pagina"; echo $?)
confere "seis abas" $([ "$(grep -o 'class="doi-abas__botao' "$TMP/pagina" | wc -l)" = 6 ]; echo $?)
confere "citation_title / citation_isbn / citation_doi" $(grep -q 'name="citation_title"' "$TMP/pagina" && grep -q 'name="citation_isbn" content="978-65-00000-00-1"' "$TMP/pagina" && grep -q 'name="citation_doi" content="10.00000/conexao.2026.001"' "$TMP/pagina"; echo $?)
confere "JSON-LD Book com isbn" $(grep -q '"@type":"Book"' "$TMP/pagina" && grep -q '"isbn":\["978-65-00000-00-1","978-65-00000-01-8"\]' "$TMP/pagina"; echo $?)
confere "og:title e og:image" $(grep -q 'property="og:title"' "$TMP/pagina" && grep -q 'property="og:image"' "$TMP/pagina"; echo $?)
confere "Rank Math sem título/descrição próprios (um só <title>, uma só description)" $([ "$(grep -o '<title>' "$TMP/pagina" | wc -l)" = 1 ] && [ "$(grep -o 'name="description"' "$TMP/pagina" | wc -l)" = 1 ]; echo $?)
confere "botão de compra com add-to-cart e preço" $(grep -q 'add-to-cart=' "$TMP/pagina" && grep -q 'woocommerce-Price-amount' "$TMP/pagina"; echo $?)
confere "cinco formatos de citação" $(grep -q 'id="citar-livro-abnt"' "$TMP/pagina" && grep -q 'id="citar-livro-ris"' "$TMP/pagina" && grep -q 'SILVA, Maria Exemplo da; PEREIRA, João Exemplo (org.)' "$TMP/pagina"; echo $?)
confere "sumário com a seção" $(grep -q 'Seção 1 — Fundamentos' "$TMP/pagina"; echo $?)
confere "CSS e JS do plugin" $(grep -q 'conexao-doi.css' "$TMP/pagina" && grep -q 'conexao-doi.js' "$TMP/pagina"; echo $?)

echo "== página do capítulo: $CAP_URL"
codigo=$(pagina "$CAP_URL")
confere "responde 200 (veio $codigo)" $([ "$codigo" = 200 ]; echo $?)
confere "título do capítulo" $(grep -q 'class="doi-titulo">Uma breve história das doenças raras<' "$TMP/pagina"; echo $?)
confere "DOI do capítulo" $(grep -q 'https://doi.org/10.00000/conexao.2026.001.01' "$TMP/pagina"; echo $?)
confere "autores com sobrescrito de afiliação" $(grep -q 'doi-autores-linha__num' "$TMP/pagina" && grep -q 'id="doi-afiliacao-1"' "$TMP/pagina" && grep -q 'Universidade Federal de Goiás, Goiânia, GO, Brasil' "$TMP/pagina"; echo $?)
confere "citation_firstpage / lastpage / inbook_title" $(grep -q 'name="citation_firstpage" content="21"' "$TMP/pagina" && grep -q 'name="citation_lastpage" content="34"' "$TMP/pagina" && grep -q 'name="citation_inbook_title"' "$TMP/pagina"; echo $?)
confere "JSON-LD Chapter" $(grep -q '"@type":"Chapter"' "$TMP/pagina"; echo $?)
confere "seis abas" $([ "$(grep -o 'class="doi-abas__botao' "$TMP/pagina" | wc -l)" = 6 ]; echo $?)
confere "ABNT do capítulo com In:" $(grep -q 'ROCHA, Ana Exemplo; SILVA, Maria Exemplo da. Uma breve história das doenças raras. In: SILVA' "$TMP/pagina"; echo $?)
confere "PDF sem link (documents vazio)" $(grep -q 'doi-acao--inativa' "$TMP/pagina"; echo $?)
confere "compra do livro na lateral" $(grep -q 'doi-compra--compacta' "$TMP/pagina" && grep -q 'add-to-cart=' "$TMP/pagina"; echo $?)
confere "navegação para o próximo capítulo" $(grep -q 'rel="next"' "$TMP/pagina"; echo $?)
codigo=$(pagina "${LIVRO_URL}capitulo/02/")
confere "capítulo 02 responde 200 (veio $codigo)" $([ "$codigo" = 200 ]; echo $?)
codigo=$(pagina "${LIVRO_URL}capitulo/99/")
confere "capítulo inexistente → 404 (veio $codigo)" $([ "$codigo" = 404 ]; echo $?)

echo "== documento (PDF da ficha)"
printf '%%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%%%EOF\n' > "$TMP/ficha.pdf"
ts=$(date +%s)
sig=$(printf '%s\n%s' "$ts" "$(sha256sum "$TMP/ficha.pdf" | cut -d' ' -f1)" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //')
codigo=$(curl -s --resolve "$HOST:80:$IP" -X POST "http://$HOST/wp-json/conexao-doi/v1/documentos" \
    -H "X-Conexao-Timestamp: $ts" -H "X-Conexao-Signature: $sig" \
    -F type=chapter_sheet -F hub_book_id=1 -F hub_chapter_id=10 -F version=1 -F "file=@$TMP/ficha.pdf" \
    -o "$TMP/resposta" -w '%{http_code}')
confere "multipart responde 200 (veio $codigo)" $([ "$codigo" = 200 ]; echo $?)
cat "$TMP/resposta"; echo
PDF_URL=$(json '$r["url"] ?? ""')
confere "devolve attachment_id e url" $([ -n "$PDF_URL" ] && [ "$(json '$r["attachment_id"] ?? 0')" -gt 0 ]; echo $?)
codigo=$(pagina "$CAP_URL")
confere "capítulo passa a ter o link do PDF" $(grep -q "href=\"$PDF_URL\"" "$TMP/pagina" && grep -q 'name="citation_pdf_url"' "$TMP/pagina"; echo $?)
php -r 'echo json_encode(["type" => "book_sheet", "hub_book_id" => 1, "filename" => "ficha-livro.pdf", "version" => "1", "content_base64" => base64_encode(file_get_contents($argv[1]))]);' "$TMP/ficha.pdf" > "$TMP/doc.json"
codigo=$(chamar POST documentos "$TMP/doc.json")
confere "JSON base64 responde 200 (veio $codigo)" $([ "$codigo" = 200 ]; echo $?)

echo "== slug antes e depois do envio do DOI"
sed 's/"slug": "tratado-de-doencas-raras"/"slug": "tratado-doencas-raras-novo"/' "$FIXTURE" > "$TMP/slug2.json"
codigo=$(chamar PUT publicacoes/1 "$TMP/slug2.json")
NOVA_URL=$(json '$r["book"]["url"] ?? ""')
confere "slug muda enquanto doi_status < sent ($NOVA_URL)" $([ "$NOVA_URL" = "http://$HOST/livro/tratado-doencas-raras-novo/" ]; echo $?)
codigo=$(pagina "$LIVRO_URL")
confere "endereço antigo responde 301 (veio $codigo)" $([ "$codigo" = 301 ]; echo $?)
destino=$(curl -s --resolve "$HOST:80:$IP" "${LIVRO_URL}capitulo/01/" -o /dev/null -w '%{redirect_url}')
confere "capítulo no endereço antigo redireciona para o novo ($destino)" $([ "$destino" = "http://$HOST/livro/tratado-doencas-raras-novo/capitulo/01/" ]; echo $?)
codigo=$(chamar PUT publicacoes/1 "$FIXTURE")
confere "volta ao slug original" $([ "$(json '$r["book"]["url"]')" = "$LIVRO_URL" ]; echo $?)
sed 's/"slug": "tratado-de-doencas-raras"/"slug": "tratado-travado"/; s/"status": "published"/"status": "published", "doi_status": "sent"/' "$FIXTURE" > "$TMP/slug3.json"
codigo=$(chamar PUT publicacoes/1 "$TMP/slug3.json")
confere "com doi_status sent o slug não muda ($(json '$r["book"]["url"]'))" $([ "$(json '$r["book"]["url"]')" = "$LIVRO_URL" ]; echo $?)
codigo=$(chamar PUT publicacoes/1 "$TMP/slug2.json")
confere "depois de sent, nem com doi_status none o slug muda" $([ "$(json '$r["book"]["url"]')" = "$LIVRO_URL" ]; echo $?)
chamar PUT publicacoes/1 "$FIXTURE" > /dev/null

echo "== despublicar"
codigo=$(chamar POST publicacoes/1/despublicar)
confere "responde 200 (veio $codigo)" $([ "$codigo" = 200 ]; echo $?)
confere "estado unpublished" $([ "$(json '$r["book"]["status"]')" = unpublished ]; echo $?)
codigo=$(pagina "$LIVRO_URL")
confere "página continua no ar (veio $codigo)" $([ "$codigo" = 200 ]; echo $?)
confere "sem botão de compra, com aviso fora de catálogo" $(! grep -q 'add-to-cart=' "$TMP/pagina" && grep -q 'Fora de catálogo' "$TMP/pagina"; echo $?)
codigo=$(pagina "$CAP_URL")
confere "capítulo também sem compra" $(! grep -q 'add-to-cart=' "$TMP/pagina" && grep -q 'fora de catálogo' "$TMP/pagina"; echo $?)
codigo=$(chamar PUT publicacoes/1 "$FIXTURE")
codigo=$(pagina "$LIVRO_URL")
confere "novo PUT republica (botão volta)" $(grep -q 'add-to-cart=' "$TMP/pagina"; echo $?)

echo "== capítulo que o Hub deixa de mandar"
php -r '$d = json_decode(file_get_contents($argv[1]), true); array_pop($d["chapters"]); echo json_encode($d);' "$FIXTURE" > "$TMP/sem-ultimo.json"
codigo=$(chamar PUT publicacoes/1 "$TMP/sem-ultimo.json")
codigo=$(pagina "${LIVRO_URL}capitulo/02/")
confere "com doi_status none vai para a lixeira: capítulo 02 → 404 (veio $codigo)" $([ "$codigo" = 404 ]; echo $?)
codigo=$(chamar PUT publicacoes/1 "$FIXTURE")
confere "volta com o mesmo wp_post_id" $([ "$(json '$r["chapters"][1]["wp_post_id"]')" = "$(wp post list --post_type=capitulo --post_status=any --meta_key=_conexao_hub_id --meta_value=11 --field=ID)" ]; echo $?)
php -r '$d = json_decode(file_get_contents($argv[1]), true); $d["chapters"][1]["doi_status"] = "sent"; echo json_encode($d);' "$FIXTURE" > "$TMP/sent.json"
chamar PUT publicacoes/1 "$TMP/sent.json" > /dev/null
chamar PUT publicacoes/1 "$TMP/sem-ultimo.json" > /dev/null
codigo=$(pagina "${LIVRO_URL}capitulo/02/")
confere "com doi_status sent a página fica (veio $codigo), sem compra e com aviso" $([ "$codigo" = 200 ] && ! grep -q 'add-to-cart=' "$TMP/pagina" && grep -q 'não faz mais parte da edição' "$TMP/pagina"; echo $?)
chamar PUT publicacoes/1 "$FIXTURE" > /dev/null

echo "== termos autor"
wp eval-file "$PLUGIN/tests/termos.php"

echo
if [ "$falhas" -eq 0 ]; then
    echo "Tudo passou. Livro: $LIVRO_URL  Capítulo: $CAP_URL"
else
    echo "$falhas verificação(ões) falharam."
    exit 1
fi
