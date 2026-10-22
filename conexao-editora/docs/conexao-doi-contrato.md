# Conexão DOI — contrato de dados e integração (fase 0)

Versão 1.0, 22/10/2026. Vale para as quatro frentes (Hub, WordPress, IA, Crossref). Mudança aqui exige avisar as outras frentes. Plano: `conexao-doi-plano.md`.

## 1. Decisões fechadas

| Tema | Decisão |
|---|---|
| Admin | Hub Editora (Laravel 13 + Filament 4), módulo **Editorial**, menu "Publicações" |
| Páginas públicas | WordPress `conexaoeditora.com.br` (`/home/hubpubcon/sites/conexaoeditora`, WP 7.1, Woo 11.2, Rank Math, tema `conexao-editora`), plugin `conexao-doi` |
| Fonte de verdade | sempre o Hub. O WordPress só recebe e renderiza |
| DOI de capítulo | **todo capítulo recebe DOI** |
| Área de conhecimento | texto livre com sugestão das já usadas (área e subárea) |
| Sufixo DOI | gerado pelo sistema: livro `conexao.{ano}.{NNN}` (sequencial por ano, 3 dígitos), capítulo `{sufixo do livro}.{NN}` (ordem no livro, 2 dígitos; 3 a partir de 100). Editável só enquanto rascunho; imutável depois de validado |
| Assistente do livro | **8 etapas**, como nas telas do Diego: Dados da obra → Autores → Publicação → Identificadores e DOI → Resumo → Capítulos → Comercialização → Revisão |
| Caixa de compra | ligada ao **produto WooCommerce pelo ID** (preço e estoque reais, adiciona ao carrinho). Se não houver produto ou ele sair de catálogo, a página fica sem botão e continua no ar |
| Schema Crossref | `crossref5.4.0.xsd` (book / book_metadata / content_item component_type="chapter") |
| IA | Claude API, `claude-opus-5-5`, saída estruturada; nada publica sem aprovação humana |
| Perfis novos no Hub | `editor` (cadastra e importa), `revisor` (aprova sugestões da IA e aprova o livro), `deposito` (envia à Crossref). `admin` faz tudo |

Pendente (não bloqueia): credenciais Crossref, PDF do Tratado, usuários, chave da API, layout da ficha PDF, desligar o `noindex` do site (`blog_public=0` hoje).

## 2. Modelo de dados (Hub, MariaDB)

Tabelas novas, todas com `id`, `created_at`, `updated_at`; cifradas em repouso pelo deploy (`hub:encrypt-tables`).

### `people`
| campo | tipo | obs |
|---|---|---|
| `name` | string | como aparece na obra |
| `sort_name` | string | "SOBRENOME, Nome", para citações; gerado se vazio |
| `orcid` | string null | `0000-0000-0000-000X`, validado (checksum) |
| `email` | string null | interno, nunca publicado |
| `bio` | text null | |
| `wp_term_id` | int null | termo da taxonomia `autor` no WP, preenchido pela publicação |

Regra do Romário: para entrar numa contribuição publicada, a pessoa precisa de **ORCID ou instituição**.

### `institutions`
`name`, `acronym` null, `city`, `state`, `country` (ISO-2, padrão BR), `ror_id` null.

### `books`
| grupo | campos |
|---|---|
| vínculo | `product_id` null → `products` (catálogo do Hub, que já aponta para o produto Woo) |
| obra | `title`, `subtitle` null, `alt_title` null, `alt_title_lang` (padrão `en`), `type` enum `edited_book|monograph|reference|other`, `series_title` null, `series_issn` null, `volume` null, `edition_number` smallint null, `language` (ISO-639-1, padrão `pt`), `extra_languages` json, `cover_path` null (privado; a capa pública vem do WP/produto) |
| publicação | `publisher` (padrão "Conexão Editora"), `publication_place` (padrão "Goiânia"), `publication_year` smallint, `publication_date` date null, `pages` int null, `format` enum `print|ebook|both`, `subject_area`, `subject_subarea` null, `audience` null |
| identificadores | `isbn_print` null, `isbn_ebook` null, `other_isbns` json `[{type,value}]`, `doi_prefix`, `doi_suffix`, `landing_url` null |
| textos | `abstract` (resumo científico), `abstract_en` null, `synopsis` null (texto comercial, não vai no XML), `keywords` json, `keywords_en` json, `references` json `[{text, doi?}]` |
| estado | `status` enum `draft|review|approved|published|unpublished`; `doi_status` enum `none|validated|ready|sent|processing|registered|failed`; `approved_by`, `approved_at`; `wp_post_id` null; `published_at` null; `slug` (gerado do título, imutável após `doi_status >= sent`) |

`doi` é calculado: `{doi_prefix}/{doi_suffix}`. Único.

### `chapters`
`book_id`, `sequence` (ordem), `section` null (parte/seção do sumário), `number` null (número impresso), `title`, `subtitle` null, `title_en` null, `language` null (herda do livro), `first_page`, `last_page` (números impressos), `publication_date` null (herda), `abstract_original` null, `abstract_ai` null, `abstract_approved` null, `keywords` json, `references` json, `doi_suffix`, `landing_url` null, `slug`, `status` enum `draft|review|approved`, `doi_status` (mesmo enum do livro), `wp_post_id` null, `ai_evidence` json (por campo: página e trecho), `approved_by`, `approved_at`.

Capítulo pertence a exatamente um livro. O resumo publicado é sempre `abstract_approved`.

### `contributions`
`contributable_type` (`book|chapter`), `contributable_id`, `person_id`, `role` enum `author|editor|translator|reviewer|other` (organizador = `editor`, que é o papel da Crossref), `sequence`, `institution_id` null, `affiliation_text` null (quando a instituição não está cadastrada). Único por (contributable, person, role).

### `commercial_links`
`book_id`, `modality` enum `print|ebook|combo`, `label`, `woo_product_id` null, `url` null (só se não houver produto Woo; HTTPS, domínio da editora), `enabled` bool, `sort_order`. Os capítulos herdam os links do livro.

### `imports`
`book_id` null, `file_path` (privado, `storage/app/private/imports/`), `original_name`, `size`, `pages` null, `status` enum `received|extracting|segmenting|suggested|review|approved|integrated|error`, `model`, `prompt_version`, `result` json (proposta completa), `error` null, `created_by`, `reviewed_by` null, `approved_at` null, `usage` json (tokens/custo).

### `deposits` e `deposit_items`
`deposits`: `batch_id` (único, vai no XML), `environment` enum `test|production`, `book_id`, `xml_path`, `checksum` (sha256), `status` enum `queued|sent|processing|done|failed`, `http_status` null, `response` text null, `submitted_at`, `result_at`, `created_by`.
`deposit_items`: `deposit_id`, `target_type` (`book|chapter`), `target_id`, `doi`, `status` enum `pending|registered|failed`, `message` null.

### `documents`
`documentable_type/id`, `type` enum `book_sheet|chapter_sheet`, `template_version`, `hash`, `path` (privado), `wp_attachment_id` null, `public_url` null, `generated_at`, `stale` bool.

Auditoria: usar `SyncLog::record()` existente com ações `editorial.*`, `import.*`, `deposit.*`, `document.*`, `publish.*`.

## 3. Estados e transições

**Livro:** `draft` → (checklist OK) `review` → (revisor aprova) `approved` → (publicar no WP) `published`. Só `approved` pode ir ao WP; só `published` com `landing_url` pode ser depositado. `unpublished` tira o botão de compra e marca a página, mas nunca remove a página (regra da Crossref).

**DOI (livro e cada capítulo):** `none` → `validated` (XML passa no XSD) → `ready` → `sent` → `processing` → `registered` | `failed`. HTTP 200 do depósito leva só a `sent`. `registered` vem do relatório de processamento. De `registered`, mudanças de metadados geram novo depósito de atualização (mesmo DOI). Troca de URL idem.

**Importação:** `received` → `extracting` → `segmenting` → `suggested` → `review` → `approved` → `integrated` (cria/atualiza livro, capítulos, pessoas) | `error` (reprocessável).

## 4. Integração Hub → WordPress

Plugin `conexao-doi` no WP expõe uma API REST interna. O Hub chama ao publicar, republicar e despublicar. O WP nunca chama o Hub.

**Autenticação:** cabeçalhos `X-Conexao-Timestamp` (unix) e `X-Conexao-Signature` = `hex(hmac_sha256(timestamp . "\n" . corpo, segredo))`. Segredo compartilhado de 64 caracteres: no Hub em `CONEXAO_DOI_WP_SECRET` (.env), no WP em `wp-config.php` (`CONEXAO_DOI_SECRET`). Rejeitar se o timestamp tiver mais de 5 minutos de diferença. Sem usuário WP envolvido.

| Método e rota | Função |
|---|---|
| `PUT /wp-json/conexao-doi/v1/publicacoes/{hub_book_id}` | cria ou atualiza o livro, os capítulos, as pessoas (termos `autor`) e os documentos. Idempotente |
| `POST /wp-json/conexao-doi/v1/publicacoes/{hub_book_id}/despublicar` | marca `fora de catálogo` (página continua) |
| `GET /wp-json/conexao-doi/v1/publicacoes/{hub_book_id}` | estado atual no WP (para conferência) |
| `POST /wp-json/conexao-doi/v1/documentos` | upload do PDF da ficha (multipart) → devolve `attachment_id` e URL |

**Resposta do `PUT`:**
```json
{ "book": { "wp_post_id": 123, "url": "https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/" },
  "chapters": [ { "hub_chapter_id": 7, "wp_post_id": 124, "url": "https://conexaoeditora.com.br/livro/tratado-de-doencas-raras/capitulo/01/" } ],
  "people": [ { "hub_person_id": 3, "wp_term_id": 55 } ] }
```
O Hub grava `wp_post_id`, `landing_url` e `wp_term_id`. **Essas URLs são as que vão para a Crossref.**

**Payload do `PUT`:** ver `exemplos/publicacao-tratado.json`. Resumo da estrutura:

```
book: { hub_id, slug, title, subtitle, alt_title, type, series{title, issn}, volume, edition_number,
        language, publisher, publication_place, publication_year, publication_date, pages, format,
        subject_area, subject_subarea, audience, isbn_print, isbn_ebook, doi, keywords[], keywords_en[],
        abstract, synopsis, references[], contributors[], toc[], commercial[], documents[], status }
contributors[]: { hub_person_id, name, sort_name, orcid, role, sequence, affiliation{ name, city, state, country } }
toc[]: { section, chapters[hub_chapter_id] }           (hierarquia do sumário)
chapters[]: { hub_id, slug, sequence, number, section, title, subtitle, title_en, language, first_page, last_page,
              publication_date, doi, abstract, keywords[], references[], contributors[], documents[], status }
commercial[]: { modality, label, woo_product_id, url, enabled, sort_order }
documents[]: { type, attachment_id, url, version, generated_at }
```

Datas em ISO-8601. Texto em UTF-8, sem HTML (o WP escapa na saída). O Hub envia sempre o registro completo; o WP substitui, não mescla.

## 5. Lado WordPress (plugin `conexao-doi`)

- CPTs `publicacao` (livro) e `capitulo` (filho de `publicacao` por `post_parent`), sem editor no admin (somente leitura; aviso "editado no Hub"), meta `_conexao_hub_id`, `_conexao_doi`, `_conexao_dados` (o payload inteiro em JSON, para renderizar sem dezenas de metas) e metas separadas só para o que precisa de consulta: `_conexao_isbn_print`, `_conexao_isbn_ebook`, `_conexao_doi`.
- URLs: `/livro/{slug}/` e `/livro/{slug}/capitulo/{nn}/` (`nn` = sequência com 2 dígitos). Slug imutável depois que o Hub informa `doi_status >= sent`; se mudar antes, o plugin registra o antigo e responde 301.
- Pessoas viram termos da taxonomia `autor` já existente no tema (meta `_conexao_hub_person_id`, ORCID em meta), para a página `/autores/` e os filtros continuarem valendo.
- Caixa de compra: `wc_get_product(woo_product_id)` → preço, estoque, botão `add-to-cart`. Produto ausente ou `unpublished` → sem botão, com aviso "fora de catálogo".
- Citações (ABNT, APA, Vancouver, BibTeX, RIS) geradas em PHP a partir de `_conexao_dados`, com botão copiar.
- SEO: título, descrição, Open Graph (capa do produto), `citation_*` meta tags (Google Scholar: `citation_title`, `citation_author`, `citation_publication_date`, `citation_isbn`, `citation_doi`, `citation_firstpage`, `citation_lastpage`), JSON-LD `Book` / `Chapter`. Compatível com o Rank Math (desligar o título dele nessas páginas).
- Templates: `single-publicacao.php` e `single-capitulo.php` no plugin, seguindo os mockups do Diego, usando o header/footer do tema.
- Nada de edição manual no WP: tudo que for salvo pelo admin é sobrescrito no próximo `PUT`.

## 6. Mapeamento para o XML da Crossref (5.4.0)

| Hub | XML |
|---|---|
| `type` | `book@book_type` |
| contributors role=`editor` / `author` | `book_metadata/contributors/person_name@contributor_role="editor|author"`, `@sequence="first|additional"`, `given_name`, `surname`, `affiliations/institution/institution_name`, `ORCID` |
| `title`, `subtitle` | `titles/title`, `titles/subtitle` |
| `edition_number` | `edition_number` |
| `publication_date` ou `publication_year` | `publication_date@media_type="print|online"` (um por formato) |
| `isbn_print`, `isbn_ebook` | `isbn@media_type="print"`, `isbn@media_type="electronic"` |
| `publisher`, `publication_place` | `publisher/publisher_name`, `publisher/publisher_place` |
| `doi`, `landing_url` | `doi_data/doi`, `doi_data/resource` |
| cada capítulo | `content_item@component_type="chapter"` com `contributors`, `titles`, `component_number` (número impresso), `pages/first_page|last_page`, `publication_date`, `doi_data`, `citation_list` (quando houver referências) |
| `series_title`, `series_issn` | `book_series_metadata` em vez de `book_metadata` (regra: livro de série usa outra estrutura; o gerador escolhe pelo preenchimento de `series_issn`) |
| resumos | `jats:abstract` (opcional, aceito pelo schema) |

Envio: `POST https://test.crossref.org/servlet/deposit` (homologação) ou `https://doi.crossref.org/servlet/deposit` com `operation=doMDUpload`, `login_id`, `login_passwd`, `fname` (arquivo XML). Resultado: `https://doi.crossref.org/servlet/submissionDownload?usr=…&pwd=…&doi_batch_id=…&file_name=…&type=result`. Credenciais em `CROSSREF_LOGIN`, `CROSSREF_PASSWORD`, `CROSSREF_PREFIX`, `CROSSREF_ENV` no `.env` do Hub.

## 7. Frentes e critérios de aceite

| Frente | Entrega | Aceite |
|---|---|---|
| **A — Hub: módulo Editorial** | migrations e models da seção 2; perfis; recursos Filament "Publicações" (livros com assistente de 8 etapas), "Capítulos", "Pessoas", "Instituições"; validações (ISBN-13, ORCID, sufixo único, páginas); geração de sufixo; publicação no WP (cliente HTTP assinado, job em fila); testes | cadastrar livro com 74 capítulos à mão; publicar e receber URLs |
| **B — WordPress: plugin `conexao-doi`** | seção 5 completa; fixture = `exemplos/publicacao-tratado.json`; testes com WP-CLI | `PUT` do exemplo gera as páginas nos layouts, com compra funcionando, citações e metatags; `despublicar` mantém a página |
| **C — IA: importação** | serviço de extração (texto por página + PDF do início), segmentação de capítulos com páginas impressas, pessoas e afiliações, resumos/palavras-chave sugeridos com evidência; job em fila; tela de revisão lado a lado no Filament; aprovação individual e em lote; custo registrado | PDF do Tratado → proposta com 74 capítulos revisável; nada vai ao livro sem aprovar |
| **D — Crossref** | gerador de XML (livro avulso e série), validação contra o XSD local (com os schemas importados), cliente de depósito, fila, leitura de resultado, estados por DOI, reenvio, atualização de URL; testes com XML válido/inválido e respostas simuladas | XML do exemplo passa no XSD; depósito no `test.crossref.org` com DOIs `registered` |
| **E — Documentos** (depois de A e B) | fichas PDF (dompdf + QR), versão/hash, envio ao WP | ficha do livro e de um capítulo baixável pela página pública |

Regras para todas as frentes: PHP 8.3, sem Node no servidor, segredos só no `.env`, testes em `~/hub-wip-{frente}` no servidor (sem PHP local), nada de alterar tabelas existentes do Hub sem avisar, mensagens e rótulos em português.

## 8. Referências
- Crossref schema 5.4.0: https://www.crossref.org/schemas/crossref5.4.0.xsd (importa common, fundref, AccessIndicators, relations, JATS, MathML)
- Books markup guide: https://www.crossref.org/documentation/schema-library/markup-guide-record-types/books-and-chapters/
- HTTPS POST deposit: https://www.crossref.org/documentation/register-maintain-records/direct-deposit-xml/https-post/
- Google Scholar meta tags: https://scholar.google.com/intl/en/scholar/inclusion.html#indexing
