# Conexão DOI (plugin WordPress)

Páginas permanentes de livro e capítulo da Conexão Editora, destino dos DOIs
registrados na Crossref. O Hub Editora é a fonte da verdade: ele chama a API
deste plugin ao publicar, republicar e despublicar; o WordPress só recebe e
renderiza. Nada é editado no painel.

Contrato: `conexao-editora/docs/conexao-doi-contrato.md` (seções 4 e 5).
Fixture: `conexao-editora/docs/exemplos/publicacao-tratado.json`.

## O que o plugin faz

- CPTs `publicacao` (livro) e `capitulo` (filho do livro por `post_parent`),
  somente leitura no painel (aviso "Editado no Hub Editora").
- Endereços `/livro/{slug}/` e `/livro/{slug}/capitulo/{nn}/` (`nn` = sequência
  com dois dígitos). Slug trocado antes do depósito responde 301 no endereço
  antigo; depois de `doi_status >= sent` o slug não muda mais.
- Pessoas viram termos da taxonomia `autor` do tema (meta
  `_conexao_hub_person_id` e `_conexao_orcid`), reaproveitando termo existente
  pelo id do Hub ou pelo nome.
- Caixa de compra ligada ao produto WooCommerce (`commercial[].woo_product_id`):
  preço e estoque reais, botão que adiciona ao carrinho. Aceita id de produto
  simples, de variação, ou do produto variável (aí escolhe a variação pelo
  `pa_formato`: print → impresso, ebook → e-book, combo → impresso-e-book).
  Sem produto, ou livro despublicado: sem botão, aviso "fora de catálogo".
- Citações ABNT, APA 7, Vancouver, BibTeX e RIS geradas em PHP, com copiar.
- SEO: título, descrição, canonical, Open Graph com a capa do produto,
  `citation_*` (Google Scholar), JSON-LD `Book`/`Chapter`. Nessas páginas o
  Rank Math não imprime nada (título, descrição, OG e schema são do plugin).
- Templates `templates/single-publicacao.php` e `templates/single-capitulo.php`
  com o cabeçalho e o rodapé do tema, CSS em `assets/conexao-doi.css`.

## Instalação

1. Copiar a pasta para `wp-content/plugins/conexao-doi/`.
2. Definir o segredo compartilhado no `wp-config.php` (64 caracteres, o mesmo
   de `CONEXAO_DOI_WP_SECRET` no `.env` do Hub):

   ```php
   define('CONEXAO_DOI_SECRET', '…');
   ```

   Ou pelo WP-CLI, sem imprimir: `wp config set CONEXAO_DOI_SECRET $(openssl rand -hex 32) --type=constant --quiet`.
3. Ativar: `wp plugin activate conexao-doi` (registra os tipos e limpa as
   regras de URL). Sem tabelas novas.

Requisitos: WordPress 6.5+, PHP 8.1+, WooCommerce (para a compra e a capa) e
o tema `conexao-editora` (taxonomia `autor`, `get_header()`/`get_footer()`,
variáveis CSS).

## Autenticação

Cabeçalhos em toda chamada:

- `X-Conexao-Timestamp`: unix timestamp (janela de 5 minutos);
- `X-Conexao-Signature`: `hex(hmac_sha256(timestamp . "\n" . corpo, segredo))`.

Sem usuário do WordPress. Falha → 401 (`conexao_doi_assinatura`,
`conexao_doi_timestamp`, `conexao_doi_sem_assinatura`); segredo não
configurado → 503.

No upload multipart o PHP não entrega o corpo bruto, então o que se assina é
`timestamp . "\n" . sha256hex(arquivo)`.

## Rotas (`/wp-json/conexao-doi/v1/`)

| Método e rota | Função |
|---|---|
| `PUT publicacoes/{hub_book_id}` | cria/atualiza livro, capítulos, pessoas e documentos. Idempotente. Resposta: `{ book: {wp_post_id, url}, chapters: [{hub_chapter_id, wp_post_id, url}], people: [{hub_person_id, wp_term_id}] }` |
| `GET publicacoes/{hub_book_id}` | estado atual (slug, slugs antigos, status, doi_status, documentos, capítulos, pessoas) |
| `POST publicacoes/{hub_book_id}/despublicar` | marca fora de catálogo; a página continua. Resposta igual ao GET |
| `POST documentos` | PDF da ficha. Multipart: campos `type` (`book_sheet`/`chapter_sheet`), `hub_book_id`, `hub_chapter_id`, `version`, `generated_at` e o arquivo em `file`. Ou JSON com os mesmos campos mais `filename` e `content_base64`. Resposta: `{ type, attachment_id, url, version, generated_at, wp_post_id }`. O documento já entra na página correspondente |

Regras do `PUT`:

- O payload é o da seção 4 do contrato; o campo opcional `doi_status` (no
  livro e em cada capítulo) controla a trava do slug e o destino de capítulos
  que deixam de vir.
- Capítulo que o Hub deixa de mandar: com `doi_status` abaixo de `sent` vai
  para a lixeira (volta com o mesmo `wp_post_id` se reaparecer); a partir de
  `sent` a página fica, marcada fora de catálogo.
- `book.status = "unpublished"` equivale a despublicar.
- Datas de publicação no futuro são gravadas como a data atual no post (o
  WordPress agendaria em vez de publicar); o payload guarda a data original.

## Como testar com curl

De dentro do servidor (o staging só responde por IP com `--resolve`):

```bash
cd ~/sites/conexaoeditora-staging
SECRET=$(wp config get CONEXAO_DOI_SECRET)
CORPO=wp-content/plugins/conexao-doi/tests/publicacao-staging.json
TS=$(date +%s)
SIG=$({ printf '%s\n' "$TS"; cat "$CORPO"; } | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //')

curl --resolve staging.conexaoeditora.com.br:80:187.127.42.20 \
  -X PUT http://staging.conexaoeditora.com.br/wp-json/conexao-doi/v1/publicacoes/1 \
  -H "Content-Type: application/json" \
  -H "X-Conexao-Timestamp: $TS" -H "X-Conexao-Signature: $SIG" \
  --data-binary @"$CORPO"
```

Para `GET` e `despublicar` o corpo é vazio: `SIG=$(printf '%s\n' "$TS" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //')`.

A suíte completa (PUT, idempotência, GET, 401, páginas e metatags, documentos,
troca de slug, despublicar, capítulos removidos, termos `autor`):

```bash
su -s /bin/bash hubpubcon -c "bash ~/sites/conexaoeditora-staging/wp-content/plugins/conexao-doi/tests/staging.sh"
```

`tests/publicacao-staging.json` é a fixture do contrato com `woo_product_id`
apontando para produtos do staging (49 = produto variável "Ultrassonografia
Pediátrica", 322 = sua variação e-book).

## Arquivos

```
conexao-doi.php            cabeçalho, includes, ativação
inc/cpt.php                CPTs, URLs, 301 do slug antigo, template_include
inc/dados.php              gravação idempotente, pessoas → termos, leitura dos dados
inc/api.php                rotas REST e HMAC
inc/citacoes.php           ABNT, APA, Vancouver, BibTeX, RIS
inc/seo.php                title/description, OG, citation_*, JSON-LD, Rank Math
inc/compra.php             caixa de compra (WooCommerce) e capa
inc/visual.php             peças dos templates (pessoas, afiliações, compartilhar, abas…)
inc/admin.php              painel somente leitura
templates/                 single-publicacao.php, single-capitulo.php
assets/                    conexao-doi.css, conexao-doi.js
tests/                     staging.sh, termos.php, publicacao-staging.json
```
