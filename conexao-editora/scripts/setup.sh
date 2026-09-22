#!/usr/bin/env bash
# Deixa o ambiente local pronto: WordPress, WooCommerce, tema, páginas, menus e
# conteúdo de exemplo. Pode rodar de novo sem duplicar nada.
set -euo pipefail

CLI="docker compose exec -T -u 33 cli wp"
URL="http://localhost:8092"
ADMIN_USER="conexao"
ADMIN_PASS="conexao-local-2026"
ADMIN_MAIL="dev@conexaoeditora.local"

echo "== WordPress"
if ! $CLI core is-installed 2>/dev/null; then
  $CLI core install --url="$URL" --title="Conexão Editora" \
    --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email="$ADMIN_MAIL" --skip-email
fi

$CLI language core install pt_BR --activate >/dev/null 2>&1 || true
$CLI option update timezone_string "America/Sao_Paulo"
$CLI option update date_format "j \\d\\e F, Y"
$CLI rewrite structure '/%postname%/' --hard

echo "== tema e plugins"
$CLI theme activate conexao-editora
$CLI plugin is-installed woocommerce >/dev/null 2>&1 || $CLI plugin install woocommerce --activate
$CLI plugin is-active woocommerce >/dev/null 2>&1 || $CLI plugin activate woocommerce
$CLI plugin delete akismet hello 2>/dev/null || true

echo "== loja"
$CLI option update woocommerce_store_address "Rua 227 A, 20"
$CLI option update woocommerce_store_city "Goiânia"
$CLI option update woocommerce_default_country "BR:GO"
$CLI option update woocommerce_store_postcode "74610-060"
$CLI option update woocommerce_currency "BRL"
$CLI option update woocommerce_price_thousand_sep "."
$CLI option update woocommerce_price_decimal_sep ","
$CLI option update woocommerce_currency_pos "left_space"
$CLI option update woocommerce_enable_reviews "yes"

echo "== páginas"
criar_pagina() {
  local titulo="$1" slug="$2"
  if [ -z "$($CLI post list --post_type=page --name="$slug" --field=ID)" ]; then
    $CLI post create --post_type=page --post_status=publish --post_title="$titulo" --post_name="$slug" >/dev/null
  fi
}

criar_pagina "Home" "home"
criar_pagina "Conteúdos" "conteudos"
criar_pagina "A Editora" "a-editora"
criar_pagina "Quem somos" "quem-somos"
criar_pagina "Nossa missão" "nossa-missao"
criar_pagina "Conselho editorial" "conselho-editorial"
criar_pagina "Prêmios e selos" "premios-e-selos"
criar_pagina "Trabalhe conosco" "trabalhe-conosco"
criar_pagina "Imprensa" "imprensa"
criar_pagina "Publique conosco" "publique-conosco"
criar_pagina "Vendas corporativas" "vendas-corporativas"
criar_pagina "Contato" "contato"
criar_pagina "Eventos" "eventos"
criar_pagina "F.A.Q" "faq"
criar_pagina "Central de ajuda" "central-de-ajuda"
criar_pagina "Como comprar" "como-comprar"
criar_pagina "Formas de pagamento" "formas-de-pagamento"
criar_pagina "Entrega e prazos" "entrega-e-prazos"
criar_pagina "Trocas e devoluções" "trocas-e-devolucoes"
criar_pagina "Acompanhe seu pedido" "acompanhe-seu-pedido"
criar_pagina "Política de privacidade" "politica-de-privacidade"
criar_pagina "Termos de uso" "termos-de-uso"
criar_pagina "Política de cookies" "politica-de-cookies"
criar_pagina "Direitos autorais" "direitos-autorais"
criar_pagina "Código de conduta" "codigo-de-conduta"
criar_pagina "Lançamentos" "lancamentos"
criar_pagina "Mais vendidos" "mais-vendidos"
criar_pagina "Pré-vendas" "pre-vendas"
criar_pagina "Coleções e séries" "colecoes-e-series"

HOME_ID=$($CLI post list --post_type=page --name=home --field=ID)
BLOG_ID=$($CLI post list --post_type=page --name=conteudos --field=ID)
$CLI option update show_on_front page
$CLI option update page_on_front "$HOME_ID"
$CLI option update page_for_posts "$BLOG_ID"

echo "== categorias de livro"
for cat in "Biografia" "Crônica" "Direito" "Educação Familiar" "História" "Medicina" "Literatura" "Religião"; do
  $CLI term create product_cat "$cat" >/dev/null 2>&1 || true
done

echo "== categorias do blog"
for cat in "Educação Familiar" "Literatura" "Mercado"; do
  $CLI term create category "$cat" >/dev/null 2>&1 || true
done

echo "== livros de exemplo"
bash "$(dirname "$0")/seed-produtos.sh"

echo "== menus"
menu_item_pagina() {
  local menu="$1" slug="$2" titulo="${3:-}"
  local id
  id=$($CLI post list --post_type=page --name="$slug" --field=ID)
  [ -n "$id" ] || return 0
  if [ -n "$titulo" ]; then
    $CLI menu item add-post "$menu" "$id" --title="$titulo" >/dev/null
  else
    $CLI menu item add-post "$menu" "$id" >/dev/null
  fi
}

for menu in principal topo rodape-catalogo rodape-editora rodape-ajuda rodape-politicas; do
  $CLI menu delete "$menu" >/dev/null 2>&1 || true
  $CLI menu create "$menu" >/dev/null
done

LOJA_ID=$($CLI option get woocommerce_shop_page_id)
$CLI menu item add-post principal "$LOJA_ID" --title="Livros" >/dev/null
$CLI menu item add-custom principal "Categorias" "/loja/" >/dev/null
$CLI menu item add-custom principal "Autores" "/autores/" >/dev/null
menu_item_pagina principal "publique-conosco" "Publique conosco"
menu_item_pagina principal "conteudos" "Conteúdos"
menu_item_pagina principal "a-editora" "A Editora"
menu_item_pagina principal "contato" "Contato"

menu_item_pagina topo "a-editora" "Sobre a Conexão Editora"
menu_item_pagina topo "eventos" "Eventos"
menu_item_pagina topo "faq" "F.A.Q"

for slug in quem-somos nossa-missao conselho-editorial premios-e-selos trabalhe-conosco imprensa; do
  menu_item_pagina rodape-catalogo "$slug"
done
for slug in lancamentos mais-vendidos pre-vendas colecoes-e-series; do
  menu_item_pagina rodape-editora "$slug"
done
$CLI menu item add-custom rodape-editora "Autores" "/autores/" >/dev/null
$CLI menu item add-post rodape-editora "$LOJA_ID" --title="Categorias" >/dev/null
for slug in central-de-ajuda como-comprar formas-de-pagamento entrega-e-prazos trocas-e-devolucoes acompanhe-seu-pedido; do
  menu_item_pagina rodape-ajuda "$slug"
done
for slug in politica-de-privacidade termos-de-uso politica-de-cookies direitos-autorais codigo-de-conduta; do
  menu_item_pagina rodape-politicas "$slug"
done

$CLI menu location assign principal principal
$CLI menu location assign topo topo
$CLI menu location assign rodape-catalogo rodape-catalogo
$CLI menu location assign rodape-editora rodape-editora
$CLI menu location assign rodape-ajuda rodape-ajuda
$CLI menu location assign rodape-politicas rodape-politicas

# a chamada "Publique conosco" ganha o selo de novidade do layout
PUBLIQUE_ITEM=$($CLI menu item list principal --fields=db_id,title --format=csv | awk -F, '$2=="\"Publique conosco\"" || $2=="Publique conosco" {print $1}')
[ -n "$PUBLIQUE_ITEM" ] && $CLI post term add "$PUBLIQUE_ITEM" nav_menu_item >/dev/null 2>&1 || true
[ -n "$PUBLIQUE_ITEM" ] && $CLI post meta update "$PUBLIQUE_ITEM" _menu_item_classes '["destacado"]' --format=json >/dev/null

echo "== posts de exemplo"
criar_post() {
  local titulo="$1" categoria="$2"
  if [ -z "$($CLI post list --post_type=post --title="$titulo" --field=ID 2>/dev/null)" ]; then
    $CLI post create --post_type=post --post_status=publish --post_title="$titulo" \
      --post_category="$($CLI term list category --name="$categoria" --field=term_id | head -1)" \
      --post_content="Conteúdo de exemplo enquanto o time editorial não escreve o texto definitivo." >/dev/null
  fi
}
criar_post "A importância da leitura na educação familiar" "Educação Familiar"
criar_post "Do manuscrito ao livro: como funciona o processo editorial" "Literatura"
criar_post "Livros de saúde: conhecimento que transforma o cuidado" "Mercado"

$CLI rewrite flush --hard
$CLI cache flush >/dev/null 2>&1 || true

echo
echo "Pronto."
echo "  Loja:   $URL"
echo "  Painel: $URL/wp-admin  ($ADMIN_USER / $ADMIN_PASS — só vale neste ambiente local)"
