# Importação de livros em PDF com IA (Conexão DOI, frente C)

Lê o PDF de um livro com a API da OpenAI (Responses API, saída estruturada) e monta uma **proposta** de cadastro: dados da obra, pessoas com instituição, capítulos com páginas impressas, resumos e palavras-chave. A proposta fica em `imports.result` e **nunca** grava em `books`/`chapters`/`people`: isso é a aprovação, feita na tela de revisão (frente A + integração).

## Peças

| Peça | Papel |
|---|---|
| `App\Models\Import` + migration `create_imports_table` | tabela `imports` do contrato; estados `received → extracting → segmenting → suggested → review → approved → integrated \| error`; `reprocess()` volta a `received` |
| `App\Editorial\Import\PdfText` | texto **por página** (`smalot/pdfparser`) + número impresso lido no cabeçalho/rodapé → `[{pdf_page, printed_page, text}]`. Sem OCR |
| `App\Editorial\Import\PdfSlicer` | recorta as primeiras N páginas com Ghostscript (`/usr/bin/gs` no servidor) para enviar como **arquivo PDF**; sem gs, PDF até 100 páginas vai inteiro, maior vai como texto |
| `App\Editorial\Import\Segmenter` | determinístico: deslocamento "PDF − impressa" mais frequente, busca do título de cada capítulo numa janela em torno da página esperada (depois no livro todo, pulando páginas de sumário), fim = véspera do capítulo seguinte |
| `App\Editorial\Import\Analyzer` | orquestra as chamadas à IA (obra; capítulo; capítulo longo em partes + consolidação), valida ISBN-13/ORCID, liga autores dos capítulos às pessoas da obra, monta o `result` |
| `App\Editorial\Import\Prompts` / `Schemas` | instruções fixas e JSON Schemas (strict: tudo obrigatório, `additionalProperties: false`, nulo = "não achei"). Mudou algo → sobe `Analyzer::PROMPT_VERSION` |
| `App\Editorial\Import\Ai\AiGateway` | interface única de saída; `OpenAiGateway` usa o SDK oficial `openai-php/client` (`responses()->create`) com `text.format` `json_schema` strict, `instructions`, `reasoning.effort`, `store: false`; `UsageMeter`/`Pricing` somam tokens e custo |
| `App\Jobs\ProcessImport` | fila `database`; avança os estados, grava `result` a cada capítulo, `error` em falha, `SyncLog` (`import.*`) em cada etapa |
| `hub:importar-pdf {arquivo} [--livro=] [--sincrono]` | ponta a ponta pela linha de comando; sem `OPENAI_API_KEY` só avisa "não configurado" |

Config em `config/hub.php` → `ai`: `OPENAI_API_KEY`, `HUB_AI_MODEL` (padrão `gpt-6-astra`), `HUB_AI_EFFORT` (`medium`), `HUB_AI_FRONT_MATTER_PAGES` (30), `HUB_AI_MAX_CHAPTER_TOKENS` (60000), `HUB_AI_MAX_OUTPUT_TOKENS` (32000, inclui raciocínio), `HUB_GS_BIN`, tabela de preços.

## Fluxo do job

1. **extracting** — `PdfText::extract()`; grava `pages`; sem texto → `error` ("PDF sem camada de texto"). Depois a chamada **obra**: as primeiras 30 páginas como `input_file` (PDF em base64 inline, `data:application/pdf;base64,…`; a OpenAI lê texto e imagem de cada página) → título, subtítulo, tipo, edição, idioma, editora, cidade, ano, ISBNs, pessoas com instituição ("Nome, Instituição"), sumário com seções/capítulos/páginas impressas, `end_of_chapters_printed_page`. Log `import.extracted`, `import.front_matter`.
2. **segmenting** — `Segmenter` mapeia o sumário às páginas. Log `import.segmented`. Em seguida **um capítulo por chamada**: texto com marcadores `[[p. 21 | PDF 31]]` como `input_text`, lista das pessoas da obra nas `instructions` → título confirmado, autores (ligados por nome exato à lista), `abstract_original` literal **ou** `abstract_ai` (120–250 palavras, pt, só texto), palavras-chave originais ou sugeridas, referências, evidência por campo. Capítulo acima de `max_chapter_tokens` vai em partes (`key_points` por parte) e uma chamada consolida. Recusa (`refusal`), resposta cortada (`incomplete` por `max_output_tokens`) ou JSON inválido num capítulo **não** derrubam a importação: o capítulo fica `status: failed` com `needs_review`, log `import.chapter` (warning). O `result` é salvo após cada capítulo (progresso visível).
3. **suggested** — `result` e `usage` finais, log `import.suggested` com custo.
4. **error** — qualquer exceção fora das previstas (rede, chave inválida, PDF sem texto, sumário vazio): `error` com a mensagem, `usage` do que gastou, log `import.failed`; `Import::reprocess()` + `ProcessImport::dispatch()` refaz do zero.

Regras que o código impõe sobre a IA: ISBN só entra se passar no dígito verificador (senão `null` + `needs_review` + aviso); ORCID só com checksum válido; `abstract_ai` só quando não há original (se vier com original, é descartado); fonte marcada (`pdf`/`ai`) em resumo e palavras-chave; pessoa sem ORCID **e** sem instituição recebe `needs_review: ['affiliation']` (regra do Romário).

## Formato de `imports.result`

```jsonc
{
  "format": "conexao-doi/import-proposal@1",
  "generated_at": "2026-10-22T14:03:00-03:00",
  "model": "gpt-6-astra",
  "prompt_version": "2026-10-22.1",
  "source": {
    "file": "tratado.pdf", "pages": 993, "pages_with_text": 990,
    "front_matter": { "pages": 30, "mode": "pdf" },      // pdf (recorte gs) | whole_pdf | text
    "page_offset": 10,                                   // PDF = impressa + 10; null se nenhum número foi lido
    "anchors": 912                                       // páginas cujo número impresso bate com o deslocamento
  },
  "book": {                                              // mesmas chaves do payload Hub→WP (publicacao-tratado.json)
    "title": "Tratado de Doenças Raras", "subtitle": "...", "alt_title": null, "alt_title_lang": "en",
    "type": "edited_book", "series": null, "volume": null, "edition_number": 1, "language": "pt",
    "publisher": "Conexão Editora", "publication_place": "Goiânia", "publication_year": 2026, "publication_date": null,
    "pages": 993, "format": null, "subject_area": "Medicina", "subject_subarea": "Genética médica", "audience": null,
    "isbn_print": "978-...", "isbn_ebook": "978-...", "other_isbns": [{ "type": "EPUB", "value": "978-..." }],
    "keywords": ["..."], "keywords_en": [], "abstract": null, "abstract_source": null, "synopsis": null, "references": [],
    "contributors": [
      { "name": "Maria Exemplo da Silva", "sort_name": "SILVA, Maria Exemplo da", "orcid": "0000-0002-1825-0097",
        "role": "editor", "sequence": 1,
        "affiliation": { "name": "Universidade Federal de Goiás", "city": "Goiânia", "state": "GO", "country": "BR" },
        "affiliation_text": "Universidade Federal de Goiás",
        "evidence": { "pdf_page": 5, "excerpt": "Organizadores: Maria Exemplo da Silva..." },
        "confidence": 0.95, "needs_review": [] }                     // ["affiliation"] se não tem ORCID nem instituição
    ],
    "toc": [ { "section": "Seção 1 — Fundamentos", "chapters": [1, 2] } ],   // sequences dos capítulos
    "evidence": { "title": { "pdf_page": 3, "excerpt": "...", "confidence": 0.98 }, "isbn_print": { ... } },
    "needs_review": ["abstract", "keywords"],            // campos vazios, com confiança < 0,6 ou inválidos
    "notes": null                                        // avisos da IA para o revisor
  },
  "chapters": [
    {
      "sequence": 1, "number": "1", "section": "Seção 1 — Fundamentos",
      "title": "Uma breve história das doenças raras", "subtitle": null, "title_en": null, "language": "pt",
      "first_page": 21, "last_page": 34,                 // impressas (vão para a Crossref)
      "pdf_first_page": 31, "pdf_last_page": 44,         // para abrir o PDF na página certa na revisão
      "abstract_original": "...", "abstract_ai": null, "abstract_source": "pdf",   // ou original null + abstract_ai + "ai"
      "keywords": ["..."], "keywords_source": "pdf",     // "ai" quando sugeridas
      "references": [ { "text": "BRASIL. Ministério da Saúde...", "doi": null } ],
      "contributors": [
        { "name": "Ana Exemplo Rocha", "sort_name": "ROCHA, Ana Exemplo", "orcid": null, "role": "author", "sequence": 1,
          "book_contributor_index": 2,                   // índice em book.contributors; null = pessoa não listada na obra
          "affiliation_text": "Universidade Federal de Goiás",
          "evidence": { "pdf_page": 31, "excerpt": "Ana Exemplo Rocha¹" }, "confidence": 0.95,
          "needs_review": [] }                           // ["person"] sem vínculo com a obra, ["affiliation"] sem ORCID/instituição
      ],
      "toc_authors": ["Ana Exemplo Rocha"],              // como o sumário listou, para comparar
      "evidence": {
        "pages":    { "pdf_page": 31, "excerpt": "Capítulo 1 Uma breve história...", "confidence": 0.95, "source": "segmenter" },
        "title":    { "pdf_page": 31, "excerpt": "...", "confidence": 0.98, "source": "pdf" },
        "authors":  { ... }, "abstract": { ..., "source": "pdf" | "ai" }, "keywords": { ... }, "references": { ... }
      },
      "confidence": 0.93,                                // média das evidências
      "needs_review": [],                                // pages | title | authors | abstract | keywords
      "notes": null,
      "status": "suggested",                             // pending | suggested | failed
      "error": null,                                     // mensagem quando failed (recusa, cortada, JSON inválido, não localizado)
      "usage": { "input_tokens": 8120, "output_tokens": 1240, "cached_input_tokens": 2900, "reasoning_tokens": 400, "cost_usd": 0.117, "calls": 1 }
    }
  ],
  "people": [                                            // pessoas únicas (obra + capítulos), para a etapa "Autores"
    { "name": "Maria Exemplo da Silva", "sort_name": "SILVA, Maria Exemplo da", "orcid": "0000-...",
      "affiliation_text": "Universidade Federal de Goiás", "roles": ["editor", "author"], "chapters": [1, 7], "needs_review": [] }
  ],
  "warnings": ["2 capítulo(s) do sumário não foram localizados no texto."],
  "summary": { "chapters": 74, "analyzed": 72, "failed": 2, "needs_review": 9, "abstracts_from_pdf": 10, "abstracts_from_ai": 62 }
}
```

`imports.usage`: `{ input_tokens, output_tokens, cached_input_tokens, reasoning_tokens, cost_usd, calls, model, price_known, by_stage: { front_matter: {...}, chapter: {...}, chapter_part, chapter_consolidate }, calls_log: [ { stage, chapter, ...tokens, cost_usd, status } ] }`. `input_tokens` inclui os `cached_input_tokens` (cobrados mais barato); `output_tokens` inclui os `reasoning_tokens`.

## O que a tela de revisão lado a lado precisa

- Esquerda: o PDF (`imports.file_path`, disco `local`), aberto em `chapters[i].pdf_first_page` ou em `evidence.<campo>.pdf_page` ao clicar num campo. Direita: o formulário do livro/capítulo pré-preenchido com os valores acima.
- Destaques: `needs_review` (lista por campo), `confidence` < 0,6, `abstract_source`/`keywords_source` = `ai` (rótulo "sugestão da IA"), `contributors[].book_contributor_index === null` (pessoa nova ou grafia diferente), `status: failed` (capítulo para preencher à mão), `warnings`.
- Aprovação: por capítulo (`chapters[i]`) ou em lote; a cada aprovação o integrador copia os campos para `chapters` (`abstract_approved` = `abstract_original` ou `abstract_ai` editado; `ai_evidence` = `chapters[i].evidence`), `contributions` (via `book_contributor_index` → `people`) e `books`. Estados `review`/`approved`/`integrated` e `reviewed_by`/`approved_at` são da tela.
- Reprocessar: `Import::reprocess()` + `ProcessImport::dispatch($id)`; o PDF continua no disco.

## Como testar

Não há PHP local. De `hub-editora/`:

```bash
FRENTE=c bash deploy/testar-wip.sh ImportTest     # só esta suíte
FRENTE=c bash deploy/testar-wip.sh                # tudo
```

A API é simulada: `tests/Support/FakeAiGateway` responde pelo contrato e `tests/Support/PdfFixture` gera um PDF de 6 páginas (capa, ficha, sumário, dois capítulos com números impressos 9–11). O `OpenAiGateway` é testado com o `OpenAI\Testing\ClientFake` do próprio SDK (`CreateResponse::fake`), conferindo o corpo enviado (schema strict, `input_file`, `instructions`, `store: false`) e os casos de recusa e resposta cortada. Nenhuma chamada real sai nos testes.

Ponta a ponta com chave (quando houver, no `.env` de `~/hub-wip-c`): `php artisan hub:importar-pdf /caminho/livro.pdf --sincrono`.

## Modelo e custo estimado

Preços da tabela da OpenAI em 22/10/2026 (padrão, contexto ≤ 272K), por milhão de tokens:

| Modelo | Entrada | Entrada em cache | Saída (inclui raciocínio) | Perfil |
|---|---|---|---|---|
| `gpt-6-astra` (padrão) | US$ 10,00 | US$ 1,00 | US$ 50,00 | o mais capaz |
| `gpt-6.1-sol` | US$ 2,00 | US$ 0,10 | US$ 10,00 | "desempenho próximo do Astra a custo menor" |
| `gpt-5.5` | US$ 5,00 | US$ 0,50 | US$ 30,00 | geração anterior |

Livro de 500 páginas e 74 capítulos:

| Etapa | Entrada | Saída | `gpt-6-astra` | `gpt-6.1-sol` |
|---|---|---|---|---|
| Obra: 30 páginas de PDF (texto + imagem, ~2.000 tokens/página) + instruções | ~65 mil | ~12 mil (JSON do sumário e das pessoas + raciocínio) | US$ 1,25 | US$ 0,25 |
| 74 capítulos: ~6,3 páginas × ~650 tokens + instruções/lista de pessoas (~3 mil; o prefixo repetido entra em cache) | ~520 mil (≈ 200 mil em cache) | ~130 mil (~1,2 mil de JSON + ~0,5 mil de raciocínio em `medium` por capítulo) | US$ 9,90 | US$ 2,00 |
| **Total** | **~585 mil** | **~140 mil** | **≈ US$ 11** (faixa 8–15) | **≈ US$ 2,25** (faixa 1,60–3) |

Trocar de modelo é só `HUB_AI_MODEL` no `.env`. O custo real fica em `imports.usage.cost_usd` e na Auditoria (`import.suggested`).

## Tela de revisão e integração (frente E2)

Menu **Publicações → Importações (IA)** (`/admin/importacoes`), para admin, editor e revisor.

| Peça | Papel |
|---|---|
| `ImportResource` + `ListImports` | lista (arquivo, livro, situação, capítulos/analisados/a revisar, aprovados, custo, data); ações Revisar, Reprocessar, Excluir (admin, apaga o PDF) |
| `CreateImport` | upload do PDF (disco `local`, pasta `imports`, só `application/pdf`, até 200 MB — `config/livewire.php` sobe o limite do upload temporário) + livro opcional; cria o `Import` em `received`, log `import.received` e despacha `ProcessImport`. `?book=ID` pré-seleciona o livro (atalho da etapa 6) |
| `ReviewImport` + `resources/views/filament/resources/imports/review.blade.php` | tela lado a lado: à esquerda o PDF num `<iframe>` (rota `hub.imports.pdf`, `ImportPdfController`, exige login + perfil editorial; `#page=N` pelos botões "ver p. N" de cada evidência); à direita o formulário por abas (`Schemas/ReviewForm`): **Obra** (campos de `book` com evidência/confiança, amarelo em `needs_review` ou confiança < 0,6), **Pessoas** (`book.contributors` editável + lista `people`), **Capítulos** (lista lateral com estado, anterior/próximo, título/autores/páginas, resumo original × sugestão da IA e `abstract_approved` editável, palavras-chave, referências; `failed` em vermelho) |
| Ações da tela | Salvar · Aprovar obra · Aprovar capítulo N · Aprovar todos sem pendência (só `suggested` e sem `needs_review`) · Integrar ao catálogo · Reprocessar · Abrir livro |
| `App\Editorial\Import\Integrator` | a aprovação que cria (ou atualiza, se `book_id`) o livro, capítulos, pessoas, instituições e contribuições; idempotente; transação; `status = integrated`, `reviewed_by`, `approved_at`, log `import.integrated` |

Estados: abrir uma proposta `suggested` → `review` (log `import.review`); obra + todos os capítulos aprovados → `approved` (log `import.approved`); reabrir algo volta a `review`; integrar → `integrated`. `Reprocessar` vale em `error`, `received`, `suggested` e `review` (descarta a revisão em curso).

O que a tela grava em `imports.result`: os campos editados (sobre o original, preservando `evidence`, `confidence`, `needs_review`), `book.approved`, `chapters[i].approved` e `chapters[i].abstract_approved`. Listas (contribuidores) são casadas pela posição original (`_idx` no formulário); por isso a lista de pessoas da obra não permite excluir (o índice é o vínculo `book_contributor_index` dos capítulos) — para descartar alguém, deixe o nome em branco.

Regras do Integrator:
- Livro novo exige título e ano; `subject_area`/`abstract` vazios entram como `''` e ficam pendentes no checklist. Livro existente: só os campos que a proposta trouxe são atualizados; DOI/slug/situação não mudam.
- Pessoa: por ORCID válido, depois por nome normalizado (sem acento/caixa); cria com `sort_name` da proposta. Instituição: criada a partir de `affiliation {name, city, state, country}` da obra; `affiliation_text` dos capítulos só é procurado — se não casar, fica como texto livre na contribuição.
- Capítulo: um por `sequence` (`firstOrNew`); contribuições únicas por (capítulo, pessoa, papel); `book_contributor_index` → pessoa da obra, senão resolve/cria pela mesma regra. Nada é apagado numa reexecução.
- Os capítulos nascem `draft`; a aprovação editorial (com a regra ORCID-ou-instituição) continua no fluxo do `BookWorkflow`.

Testes: `FRENTE=e2 bash deploy/testar-wip.sh ImportReviewTest` (`tests/Feature/ImportReviewTest.php`).
