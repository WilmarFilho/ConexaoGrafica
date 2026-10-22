# Conexão DOI — estudo técnico e plano em fases

Versão 1.0, 22/10/2026. **Aprovado pelo Wilmar em 22/10/2026:** arquitetura Hub + WordPress, página DOI com caixa de compra ligada ao produto Woo, páginas em conexaoeditora.com.br (a virada Pubcon → Conexão Editora vai acontecer em breve). Meta declarada: site da editora no WP + Hub + app até o fim do mês; o app e o multivendor no Hub são projetos à parte, fora deste plano. Fontes: vídeo do Romário (transcrição em `conexao-doi-transcricao-video.md`), `Conexao_DOI_Especificacao_Responsiva.html` (v2.0) e `Conexao_DOI_Prototipo_Funcional.html`, mais os três mockups do Diego embutidos na especificação.

## 1. O que o projeto é

Um sistema editorial da Conexão Editora que:

1. cadastra **livros, capítulos, autores e instituições** com os metadados que a Crossref exige;
2. **lê o PDF do livro com IA** e pré-preenche tudo isso (inclusive resumo e palavras-chave por capítulo), com revisão humana antes de aprovar;
3. **registra o DOI** na Crossref (XML por HTTPS POST), acompanha o processamento e permite corrigir;
4. publica **páginas permanentes** de livro e de capítulo no site da editora, que são o destino do DOI e também a vitrine de venda;
5. gera **fichas em PDF** (livro e capítulo) e **citações** ABNT/APA/Vancouver/BibTeX/RIS;
6. recadastra o **acervo atual** nesse padrão.

A escala que importa: livros com 70–90 capítulos e 70+ autores (ex.: Tratado de Doenças Raras, 74 capítulos). O sistema tem de ser bom em lote, não em cadastro unitário.

## 2. Pontos em que o vídeo e a especificação divergem (precisam de decisão)

| # | Tema | Especificação (v2.0) | Vídeo / mockups | Recomendação |
|---|---|---|---|---|
| 1 | Página DOI e loja | "Decisão irrevogável": a loja não muda; a página DOI só exibe **links** para a loja | "Essa página já vai ser a nossa página de venda"; o mockup do livro tem preço, estoque e carrinho | Página DOI própria e permanente (não é a página do produto Woo), **com caixa de compra ligada ao produto WooCommerce pelo ID**: preço e estoque reais, botão que adiciona ao carrinho. A loja e o checkout não mudam. Atende aos dois. |
| 2 | Onde fica o admin | "Plugin WordPress próprio" | Romário pergunta: WP, externo injetando no WP, ou sair do WP? | **Admin no Hub Editora** (Laravel + Filament), **páginas públicas no WordPress** via plugin leve. Justificativa na seção 3. |
| 3 | Domínio das páginas | não trata | não trata, mas o DOI é permanente | Páginas em **conexaoeditora.com.br**. A virada Pubcon → Conexão Editora está adiada e o site novo está oculto dos buscadores (`blog_public=0`); as páginas DOI exigem decidir isso antes do primeiro registro real, porque a URL depositada não deve mudar. |
| 4 | Acervo atual | só cita "manutenção" | "vou ter que cadastrar todos os livros de novo" | Importação em lote pela mesma IA, a partir dos PDFs originais (a biblioteca da Pubcon tem 56 títulos, 53 com camada de texto). Os 66 produtos da loja nova já estão no Hub (tabela `products`, com ISBN) e viram a base do catálogo. |
| 5 | Ficha PDF | templates configuráveis, QR, versões | layout ainda não existe | Implementar o gerador com um template inicial; trocar pelo projeto gráfico do Diego quando chegar. Não bloqueia o resto. |
| 6 | Afiliação nos autores | sobrescrito por instituição | Diego esqueceu o sobrescrito no capítulo | Implementar já com sobrescrito (padrão editorial). |

Fora dessas, especificação e vídeo coincidem.

## 3. Arquitetura recomendada

```
PDF original (privado) ──► Hub Editora: módulo Editorial (Filament)
                             ├─ livros, capítulos, pessoas, instituições
                             ├─ importação com IA (Claude) + revisão lado a lado
                             ├─ XML Crossref → validação XSD → depósito (fila) → resultado
                             ├─ fichas PDF + citações
                             └─ publicar ──► WordPress conexaoeditora.com.br
                                              plugin conexao-doi: páginas /livro/{slug} e /capitulo/{slug}
                                              (layouts do Diego, SEO/OG, 301, caixa de compra via produto Woo)
```

**Por que o admin no Hub e não um plugin WordPress completo**

- O Hub já tem o que a especificação exige e que num plugin WP teríamos de construir do zero: login individual com 2FA, perfis (Administrador/Expedição, fácil acrescentar Editor/Revisor/Depósito), trilha de auditoria, fila de jobs, agendador, backup cifrado fora do servidor, pentest feito, deploy com testes obrigatórios. Isso foi tudo exigido pela Amazon e já está pago.
- Filament entrega assistente em etapas, tabelas com busca/filtro, repetidores de capítulos e autores, relações, importação em lote — o assistente de 7 etapas do mockup é um `Wizard` do Filament.
- O Hub já é o cadastro mestre dos livros (`products` com ISBN, formatos e vínculo com os produtos Woo). O módulo Editorial completa esse cadastro em vez de duplicar.
- O roteiro aprovado do site novo (fase 2, biblioteca digital) já prevê que o **direito de leitura mora no Hub**. Catálogo, capítulos e autores no Hub são o mesmo caminho.
- Prazo: é a opção mais rápida porque reaproveita a base pronta. Um plugin WP com painel próprio para 74 capítulos, revisão de IA e fila de depósitos custaria mais e ficaria preso ao admin do WordPress.

**Por que as páginas públicas no WordPress**

- Ficam no mesmo domínio da loja, com o tema que já fiz (`conexao-editora`), menu, busca, SEO, e o botão de compra usa o WooCommerce de verdade (preço, estoque, carrinho). Não precisa de proxy nem de duplicar o checkout.
- O WordPress **não é fonte de verdade**: recebe do Hub, por REST assinado, o registro aprovado (livro + capítulos + autores + links + ficha PDF) e só renderiza. Se o produto sair de catálogo, a página continua no ar sem o botão, como a Crossref exige.
- Plugin pequeno: dois tipos de conteúdo (`publicacao`, `capitulo`), templates seguindo os mockups, gerador de citações, metatags, redirecionamento 301 ao mudar slug.

**IA (API da OpenAI — trocado em 22/10/2026 a pedido do Wilmar; o texto abaixo descreve o desenho, que é o mesmo)**

- Extração de texto do PDF em PHP (`smalot/pdfparser`; os originais têm camada de texto). As primeiras ~30 páginas (capa, ficha catalográfica, autores, sumário) vão também como **PDF** para o modelo, porque o layout importa; os capítulos vão como **texto** com marcação de página, um capítulo por chamada.
- Saída estruturada (JSON validado) com, para cada campo, a página e o trecho que o justificam. Resumo e palavras-chave só são gerados quando o PDF não traz, e sempre marcados como "sugestão da IA".
- Modelo: GPT-5 (o mais capaz adequado a documentos longos, escolhido pela frente C na documentação atual da OpenAI). Volume por livro de 500 páginas e 74 capítulos: ≈ 370 mil tokens de entrada e 40 mil de saída, isto é, poucos dólares por livro; a frente C registra o custo medido. Chave `OPENAI_API_KEY` no `.env` do Hub.
- OCR de livros digitalizados fica fora da primeira versão (3 títulos do acervo). Os demais têm texto.

**Crossref**

- XML no schema de livros (`doi_batch` → `book` → `book_metadata` + `content_item` por capítulo), validado pelo XSD **antes** de enviar.
- Depósito por `POST multipart` em `test.crossref.org` (homologação) e `doi.crossref.org` (produção), com `operation=doMDUpload`. A fila do Hub envia, guarda o XML e o hash, e um job consulta o relatório de processamento até cada DOI constar como registrado ou com erro. HTTP 200 nunca marca como registrado.
- Estados por DOI: rascunho → validado → pronto → enviado → processando → registrado / falhou. Reenvio idempotente; correção de metadados e troca de URL sem gerar DOI novo.
- Sufixo gerado pelo sistema: `conexao.<ano>.<sequencial>` para o livro e `.<nn>` para o capítulo, com checagem de unicidade (o padrão exato é decisão editorial).
- A Crossref cobra por DOI registrado; a editora já tem conta e prefixo. Preciso das credenciais (vão direto no `.env`, nunca no chat).

**Fichas PDF e citações**

- PDF gerado no servidor com `dompdf` (HTML/CSS, sem Node) e QR Code (`bacon/qr-code`) apontando para a página permanente. Template versionado; a ficha pública é regenerada quando o registro muda.
- Citações ABNT, APA, Vancouver, BibTeX e RIS geradas por código a partir dos metadados (determinístico, sem IA), com botão copiar.

**Segurança e dados**

- PDFs originais em pasta privada do Hub, fora do docroot, nunca publicados. Credenciais Crossref e chave de IA só no `.env`. Perfis novos: Editor (cadastra), Revisor (aprova IA), Operador de depósito (envia à Crossref). Tudo na trilha de auditoria existente.

## 4. O que já existe e será reaproveitado

| Já pronto | Uso no Conexão DOI |
|---|---|
| Hub Editora (Laravel 13, Filament 4, MariaDB cifrado, fila, cron, auditoria, backup, deploy com testes) | base do módulo Editorial |
| `products` do Hub com ISBN, formatos e vínculo com produtos Woo | ponto de partida do catálogo e da caixa de compra |
| Tema `conexao-editora` (WordPress) com templates Woo próprios | páginas públicas no mesmo visual |
| Plugin `conexao-biblioteca` | referência de como o tema conversa com CPTs |
| Biblioteca da Pubcon (56 títulos em PDF/flipbook) | fonte para o recadastro do acervo |
| Servidor: PHP 8.3 com dom/xml/xsl/gd/intl, Python 3.9, ImageMagick, Ghostscript | validação XSD, geração de PDF e QR sem instalar nada novo |

## 5. Fases

Durações em dias úteis, considerando as frentes paralelas da seção 6. "Aceite" é o que o Romário consegue ver e testar.

| Fase | Entrega | Aceite | Duração |
|---|---|---|---|
| **0. Decisões e contrato** | Decisões da seção 2 fechadas; modelo de dados e API Hub→WP definidos; credenciais Crossref de teste; PDF do Tratado; perfis de usuário | documento assinado por você e pelo Romário | 2–3 dias |
| **1. Cadastro editorial (Hub)** | Livros, capítulos, pessoas e instituições; assistente de 7 etapas; validação de ISBN-13, ORCID e unicidade de sufixo; links comerciais; perfis Editor/Revisor/Depósito; auditoria | cadastrar o Tratado à mão, com 74 capítulos, sem travar | 5–7 dias |
| **2. Páginas permanentes (WP)** | Plugin `conexao-doi`; páginas de livro e capítulo nos layouts do Diego, responsivas; citações nos 5 formatos; SEO/OG; 301; caixa de compra pelo produto Woo; sincronização Hub→WP | abrir a página do Tratado e de um capítulo no celular e no desktop, comprar pelo botão | 6–8 dias, em paralelo com a 1 |
| **3. Importação com IA** | Upload privado; extração; segmentação de capítulos com páginas impressas; autores e afiliações; resumos e palavras-chave sugeridos; tela de revisão lado a lado (PDF × formulário) com evidências; aprovação individual e em lote | subir o PDF do Tratado e aprovar os 74 capítulos em menos de uma tarde | 7–10 dias, em paralelo após a fase 0 |
| **4. Crossref** | Gerador de XML; validação XSD; depósito em teste e produção; fila; leitura do resultado; estados por DOI; correção e reenvio; troca de URL | DOI do Tratado e de um capítulo registrados no sandbox da Crossref e resolvendo para as páginas da fase 2 | 5–7 dias, em paralelo |
| **5. Fichas PDF** | Gerador com template inicial; QR; versão/hash; download nas páginas públicas; troca pelo layout do Diego | baixar a ficha do livro e de um capítulo pela página pública | 3–4 dias, depende do layout |
| **6. Acervo e homologação** | Importação em lote do acervo; testes automatizados dos cenários da especificação; revisão de segurança; documentação; treinamento; primeiro depósito em produção | acervo cadastrado, primeiro DOI real registrado, equipe treinada | 5–7 dias |

**Marcos**

- **Semana 3 — MVP utilizável:** cadastro manual, páginas públicas e depósito no sandbox da Crossref.
- **Semana 4 — IA e fichas** integradas; depósito em produção do primeiro livro.
- **Semana 5–6 — acervo migrado** e homologação concluída.

Esforço total estimado: **180 a 240 horas**. Com as frentes em paralelo, calendário de **5 a 6 semanas** a partir da fase 0 fechada. Sem paralelismo seriam 9 a 10 semanas.

## 6. Vários agentes ao mesmo tempo

Sim, e este projeto se presta bem a isso, porque as frentes são independentes depois que o contrato de dados (fase 0) existe:

| Frente | Agente | Depende de |
|---|---|---|
| A. Módulo Editorial no Hub (fases 1 e 5) | 1 | contrato |
| B. Plugin WordPress e páginas públicas (fase 2) | 1 | contrato + JSON de exemplo |
| C. Importação com IA (fase 3) | 1 | contrato + modelo da fase 1 (pode começar pelo serviço de extração) |
| D. Crossref: XML, XSD, depósito, resultado (fase 4) | 1 | contrato |

Como funciona na prática: eu escrevo o contrato e os JSONs de exemplo na fase 0, abro um *worktree* git por frente, dou a cada agente uma tarefa fechada com critérios de aceite e testes, e faço a integração e a revisão de cada entrega antes de publicar. Em vez de um desenvolvedor de cada vez, são 3 ou 4 trabalhando em paralelo e eu no papel de líder técnico.

Limites honestos:

- **Ambiente de teste do Hub é um só** (`~/hub-wip` no servidor, sem PHP local). Vou criar uma pasta de teste por frente para os agentes não se atropelarem.
- **Integração é serial:** quando as frentes se encontram (ex.: a IA preenchendo o modelo da fase 1), eu faço a junção.
- **Qualidade exige revisão minha.** Agentes aceleram o código, não a decisão editorial nem a validação com a Crossref.
- O que **não paraleliza**: as decisões da seção 2, as credenciais, o layout do PDF do Diego e a homologação com o Romário.

## 7. O que preciso do Romário para começar

1. As **6 decisões** da seção 2 (a 1, a 2 e a 3 travam o início).
2. **Credenciais da Crossref** (login do depósito e prefixo), colocadas por você no `.env` do Hub, e confirmação de que o sandbox `test.crossref.org` aceita a mesma conta.
3. O **PDF do Tratado de Doenças Raras** (ou outro livro grande) como caso de teste da IA.
4. O **padrão do sufixo DOI** que a editora quer (ex.: `conexao.2026.001` e `.01` por capítulo).
5. O **layout da ficha PDF** do Diego, quando existir, e a correção do sobrescrito de afiliação no mockup do capítulo.
6. Quem são os **usuários** e seus perfis (quem cadastra, quem revisa a IA, quem envia à Crossref).
7. A **chave da API da Claude** (crio a conta ou você cria; vai no `.env`).

## 8. Riscos e como tratamos

| Risco | Tratamento |
|---|---|
| Página DOI com URL que muda (virada de domínio, slug) | páginas só em conexaoeditora.com.br; slug imutável após registro; 301 automático; troca de URL na Crossref pelo sistema |
| IA errar paginação impressa (PDF × número impresso), autores ou afiliações | evidência por página em cada campo, confiança, revisão obrigatória, nada publica sem aprovação; a IA não inventa ORCID/ISBN |
| XML rejeitado pela Crossref (série, ISSN, datas) | validação XSD antes do envio; cenários de teste da especificação (livro avulso, série com ISSN, 40+ capítulos) no sandbox antes da produção |
| Livros digitalizados sem texto | OCR fica para uma fase 7; cadastro manual nesses 3 títulos |
| Custo da Crossref por DOI (livro + dezenas de capítulos) | DOI de capítulo é opcional por capítulo no cadastro; relatório de custo antes de enviar |
| Prazo apertado | MVP na semana 3 com cadastro manual + páginas + sandbox; IA e fichas entram sem bloquear o uso |
| Mockup do Diego diverge da especificação em detalhes | os mockups mandam no visual; a especificação manda nas regras; divergências registradas na seção 2 |
