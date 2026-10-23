# App do leitor Conexão Editora: contrato

Versão 1.1 · 23/10/2026. Documento-fonte das duas frentes (servidor e app). Mudou aqui, muda nos dois.

1.1: confirmação de e-mail antes dos pedidos do Hub, link de entrada de uso único para o leitor do site (flipbook), `leitor_url`, convenções de nulos, codificação da marca d'água, cabeçalhos CORS e códigos de status detalhados (implementação do plugin `conexao-app` 1.1.0).

## Visão geral

- **Público:** leitores (clientes) da Conexão Editora. Mesmo login do site `conexaoeditora.com.br` (usuários do WordPress/WooCommerce).
- **O que o app faz:** estante com os e-books que a pessoa pode ler (mesma regra da biblioteca do site), leitura do PDF com sumário, busca, marcadores e progresso sincronizado, leitura offline do que foi baixado, acompanhamento dos pedidos (físicos e digitais) de todos os canais, gestão dos aparelhos conectados.
- **Plataformas:** um código só (React + Vite + TypeScript + Capacitor + pdf.js):
  - web app (PWA) em `https://conexaoeditora.com.br/app/` (estático, sem Node no servidor);
  - Android e iOS pelo Capacitor (mesmo build web empacotado).
- **Servidor do app:** plugin WordPress `conexao-app` (REST `https://conexaoeditora.com.br/wp-json/conexao-app/v1`). Reaproveita `conexao-biblioteca` (direito de leitura, arquivo do PDF) e `conexao-doi` (metadados do livro). Pedidos vêm do Hub Editora por chamada assinada servidor→servidor.

## Autenticação

- `POST /login` com e-mail (ou usuário) e senha → `wp_authenticate`. Bloqueio após 10 falhas em 15 min por e-mail+IP (transient), mensagem genérica "E-mail ou senha incorretos."
- Sucesso devolve um **token de aparelho**: 32 bytes aleatórios em base64url, prefixo `cxa_`. O servidor guarda só o SHA-256. Cabeçalho nas chamadas: `Authorization: Bearer cxa_...`.
- Cada token é um **aparelho** (tabela `{prefix}conexao_app_aparelhos`): `id`, `user_id`, `token_hash` (único), `nome` (ex.: "iPhone de Ana", "Chrome · Windows"), `plataforma` (`ios`|`android`|`web`), `id_local`, `criado_em`, `usado_em`, `ip`, `revogado_em`.
- `id_local`: identificador que o app gera uma vez por instalação (até 64 caracteres `A-Za-z0-9._:-`). Novo login com o mesmo `id_local` revoga o token antigo daquele aparelho em vez de ocupar outra vaga.
- **Limite de aparelhos:** opção `conexao_app_limite_aparelhos` (padrão 3). No 4º login o servidor responde `409 limite_aparelhos` com a lista dos aparelhos ativos; o app mostra a tela "Aparelhos conectados" para a pessoa desconectar um e tentar de novo (`POST /login` com `substituir: <id do aparelho>` revoga aquele e entra).
- Token não expira por tempo; morre ao sair, ao ser desconectado de outro aparelho, ao trocar a senha (hook `after_password_reset` / `profile_update` com senha nova revoga todos) ou se a conta for removida.
- Esqueci a senha: `POST /senha` com e-mail → `retrieve_password()` (mesmo e-mail do site). Resposta sempre `204` (não revela se o e-mail existe).
- Criar conta: `POST /cadastro` {nome, email, senha} → `wc_create_new_customer` → já devolve token como no login (`201`). Respeita "registro habilitado" do Woo (fechado → `403 sem_acesso`). Senha com no mínimo 8 caracteres (o Woo não valida senha no servidor). Até 10 tentativas por hora por IP. Conta nova começa com o e-mail **não** confirmado.
- Esqueci a senha: até 5 pedidos a cada 15 min por IP (depois `429`).
- Login social (Google/Facebook): fora da versão 1 (precisa das credenciais dos apps OAuth da editora).
- CORS (só no namespace `conexao-app/v1`): liberar `capacitor://localhost`, `https://localhost`, `http://localhost` (apps nativos) e o próprio domínio; métodos GET/POST/PUT/DELETE/OPTIONS; cabeçalhos aceitos `Authorization`, `Content-Type` (corpo JSON) e `Range` (PDF em pedaços); cabeçalhos expostos ao app `X-Conexao-Marca`, `Content-Length`, `Content-Range`, `Accept-Ranges`. Sem cookies (nunca `Allow-Credentials`). Origem fora da lista não recebe cabeçalho CORS.

### Confirmação de e-mail

O Hub acha pedidos de outros canais pelo e-mail, e o WordPress não confirma o e-mail no cadastro. Por isso o app só vê pedidos do Hub com o e-mail **confirmado**:

- Meta do usuário `conexao_app_email_verificado` = o e-mail confirmado (minúsculas). Vale só enquanto for igual ao e-mail atual da conta: trocou o e-mail, deixa de valer sozinho.
- `POST /email/codigo` (com token) → manda um código de 6 dígitos para o e-mail da conta (assunto "Seu código de confirmação · Conexão Editora"), válido por 15 min. O servidor guarda só o hash. Limite: 1 por minuto e 5 por hora (`429`). Já confirmado → `204` sem enviar. Falha no envio → `503`.
- `POST /email/confirmar` `{codigo}` → `204` e marca o e-mail como confirmado. Código errado, vencido, emitido para outro e-mail ou já usado → `422 invalido` "Código incorreto ou expirado." (com `campos.codigo`). Na 5ª tentativa errada o código morre (pedir outro).
- Também confirma: redefinir a senha pelo link do e-mail do site (prova o acesso à caixa de entrada). Nada mais conta.

## Erros

Corpo `{ "codigo": "texto_curto", "mensagem": "frase para a pessoa" }`. Códigos: `nao_autenticado` (401), `sem_acesso` (403), `nao_encontrado` (404), `limite_aparelhos` (409, com `aparelhos`), `muitas_tentativas` (429), `invalido` (422, com `campos`: objeto campo → frase; campos do aparelho como `aparelho.plataforma`), `indisponivel` (503).

- Erros do próprio WordPress no namespace saem no mesmo formato: rota inexistente ou id fora do padrão → `404 nao_encontrado`; qualquer 5xx → `503 indisponivel`.
- Sucessos: `200` em geral; `201` em `POST /cadastro` e no marcador novo (a mesma página marcada de novo devolve o marcador existente com `200`); `204` sem corpo em `/senha`, `/sair`, `/email/*` e nos `DELETE`.
- Aparelho ou marcador de outra pessoa responde `404`, nunca `403`.

## Endpoints

Todos sob `/wp-json/conexao-app/v1`. Datas em ISO 8601 com fuso. Valores em centavos (`*_centavos`).

### Conta e aparelhos
| Método | Rota | Descrição |
|---|---|---|
| POST | `/login` | `{email, senha, aparelho: {nome, plataforma, id_local}, substituir?}` → `{token, usuario, aparelho}` |
| POST | `/cadastro` | `{nome, email, senha, aparelho}` → igual ao login |
| POST | `/senha` | `{email}` → 204 |
| POST | `/sair` | revoga o token atual → 204 |
| GET | `/eu` | `{usuario: {id, nome, email, iniciais, email_verificado}, aparelho_atual_id}` (o `usuario` do login e do cadastro tem o mesmo formato) |
| POST | `/email/codigo` | manda o código de confirmação → 204 (ver "Confirmação de e-mail") |
| POST | `/email/confirmar` | `{codigo}` → 204 |
| GET | `/aparelhos` | `{limite, aparelhos: [{id, nome, plataforma, usado_em, atual}]}` |
| DELETE | `/aparelhos/{id}` | revoga (só aparelhos do próprio usuário) → 204 |

### Estante e livros
| Método | Rota | Descrição |
|---|---|---|
| GET | `/estante` | livros que a pessoa pode ler: `{livros: [Livro], continuar: Livro\|null}` (continuar = último lido com progresso < 100%) |
| GET | `/livros/{id}` | `Livro` completo (com `descricao`, `ficha`) |
| GET/HEAD | `/livros/{id}/pdf` | o PDF (stream, `Accept-Ranges`, `Content-Range` no 206, 416 para intervalo inválido; mesma entrega da biblioteca). Só com acesso. Cabeçalho `X-Conexao-Marca`: texto da marca d'água (nome + e-mail parcial, ex. "Ana Souza · an***@gmail.com") **codificado em URL (UTF-8, como `encodeURIComponent`)**; o app faz `decodeURIComponent`. Livro sem PDF → 404 |
| POST | `/livros/{id}/acesso-web` | link de entrada de uso único no site: `{url}` (ver "Entrada no site"). Só com acesso e com arquivo (`modelo` ≠ `""`) |
| GET | `/livros/{id}/leitura` | `{pagina, total_paginas, atualizado_em, marcadores: [{id, pagina, criado_em}]}` (sem leitura registrada: `pagina`, `total_paginas` e `atualizado_em` vêm `null`; marcadores em ordem de página) |
| PUT | `/livros/{id}/leitura` | `{pagina, total_paginas}` inteiros, `1 ≤ pagina ≤ total_paginas` (o app manda no máximo a cada 5 s ou ao sair) → leitura |
| POST | `/livros/{id}/marcadores` | `{pagina}` (até o total de páginas registrado) → marcador |
| DELETE | `/livros/{id}/marcadores/{marcador}` | → 204 |

`Livro`:
```json
{
  "id": 249,
  "titulo": "Atualidades em Ultrassonografia",
  "subtitulo": "Da Propedêutica ao Relatório",
  "autores": ["Fulano de Tal", "Ciclano"],
  "capa": "https://conexaoeditora.com.br/wp-content/uploads/...-600x900.jpg",
  "modelo": "pdf",
  "disponivel": true,
  "tamanho_bytes": 18234567,
  "atualizado_em": "2026-10-20T10:00:00-03:00",
  "leitor_url": null,
  "progresso": { "pagina": 118, "total_paginas": 312, "percentual": 37.8, "atualizado_em": "..." },
  "descricao": "texto puro (só no detalhe)",
  "ficha": { "paginas": 312, "idioma": "Português", "editora": "Conexão Editora", "ano": 2024, "isbn": "978-...", "doi": "10.68564/...", "dimensoes": "16 × 23 cm", "pagina_doi": "https://conexaoeditora.com.br/livro/..." },
  "avaliacao": { "media": 4.5, "quantidade": 12 }
}
```
- `modelo`: `pdf` (lê no app) ou `flipbook` (legado: o app abre o leitor do site no navegador interno) ou `""` (sem arquivo ainda: aparece com "em breve").
- `disponivel`: a pessoa pode ler **e** há arquivo. `/livros/{id}` também responde para livro digital publicado fora da estante (metadados públicos), com `disponivel: false`; leitura, marcadores e PDF exigem acesso (`403 sem_acesso`).
- `leitor_url`: endereço do leitor do site, só em `flipbook` (senão `null`). Não abrir direto: o leitor exige o login do site. O app pede `POST /livros/{id}/acesso-web` e abre a `url` devolvida no navegador interno.
- Nulos: `progresso` é `null` até o livro ser aberto; `tamanho_bytes` só em `pdf`; campos desconhecidos da `ficha` vêm `null` (`idioma` e `editora` têm padrão); `subtitulo` sem valor é `""`; `capa` sem imagem é `""`; `avaliacao` sem avaliações é `{media: 0, quantidade: 0}`.
- `atualizado_em`: data do arquivo PDF (serve para saber se o livro baixado mudou); sem PDF, a do produto.
- `Content-Length`: a hospedagem atual (Apache + PHP) tira o `Content-Length` das respostas do PHP e responde em `chunked`. Até isso ser resolvido no servidor, o app passa `length: tamanho_bytes` ao pdf.js e usa o total de `Content-Range` das respostas 206.

### Entrada no site (leitor de flipbook)

`POST /livros/{id}/acesso-web` → `{ "url": "https://conexaoeditora.com.br/?cxa_entrada=<token>" }`. O link vale **120 s** e **uma vez** (o servidor guarda só o hash). Aberto no navegador interno, o site cria uma sessão de navegador (sem "lembrar de mim") para a conta do token e redireciona (302, `Referrer-Policy: no-referrer`) para o leitor do livro. Link usado, vencido ou inválido → redireciona para o login do site, sem detalhe. Só por GET.
- Autores/ficha vêm da publicação DOI ligada ao produto quando existir; senão da taxonomia `autor` do tema e dos campos do produto.

### Pedidos
| Método | Rota | Descrição |
|---|---|---|
| GET | `/pedidos` | `{pedidos: [Pedido], fonte: "hub"\|"loja", email_verificado}` dos últimos 24 meses, mais recentes primeiro |
| GET | `/pedidos/{ref}` | `Pedido` com `itens` e `eventos` |

O WordPress só chama o Hub com o e-mail confirmado (ver "Confirmação de e-mail"); sem isso, responde `fonte: "loja"` com os pedidos da própria conta na loja e `email_verificado: false` (o app oferece confirmar o e-mail para ver os pedidos de outros canais). `/pedidos/{ref}` segue a mesma regra. O WordPress chama o Hub: `GET https://hub.pubcon.com.br/api/app/pedidos?email=<email>` assinado (`X-Conexao-Timestamp` em segundos unix, `X-Conexao-Signature` = HMAC-SHA256 em hex minúsculo de `timestamp\nGET\n/api/app/pedidos\nemail=<email>`, com o e-mail em minúsculas e sem codificar na string assinada e codificado normalmente na URL, com o segredo `CONEXAO_APP_HUB_SECRET` no wp-config e `HUB_APP_SECRET` no .env do Hub). Janela de 5 min. Cache de 2 min por usuário. Se o Hub falhar, cai nos pedidos do WooCommerce do próprio site (`fonte: "loja"`, pela conta, não pelo e-mail); a falha também fica 2 min em cache. O WordPress repassa só as chaves do `Pedido`.

Na reserva da loja: pendente/aguardando → `aguardando_pagamento`; processando → `concluido_digital` (só e-book), `a_caminho` (com código de rastreio) ou `em_separacao`; concluído → `entregue` ou `concluido_digital`; cancelado/reembolsado/falhou → `cancelado`. `pagamento`: `pago`, `pendente`, `reembolsado` ou `cancelado`. `envio` é `null` em pedido só digital; datas desconhecidas (`postado_em`, `previsao`, `em` de "Pedido enviado") vêm `null`.

`Pedido`:
```json
{
  "ref": "hub-1234",
  "numero": "#10482",
  "canal": "Loja Conexão Editora",
  "feito_em": "2026-10-22T14:58:00-03:00",
  "total_centavos": 8990,
  "situacao": "a_caminho",
  "situacao_rotulo": "A caminho",
  "pagamento": "pago",
  "digital": false,
  "itens": [{ "nome": "Direito Digital e Sociedade", "quantidade": 1, "formato": "Impresso", "livro_id": null }],
  "envio": { "transportadora": "Correios", "servico": "PAC", "codigo": "AA123456789BR", "url_rastreio": "https://...", "postado_em": "...", "entregue_em": null, "previsao": null },
  "eventos": [{ "situacao": "pago", "rotulo": "Pagamento confirmado", "em": "..." }]
}
```
`situacao` (linha do tempo do app): `aguardando_pagamento` → `pago` → `em_separacao` → `a_caminho` → `entregue`; também `cancelado`, `problema` (rótulo "Precisa de atenção", sem detalhe interno), `concluido_digital` (só e-book: "Disponível na estante"). Mapa a partir do `OrderStatus` do Hub:
`new/awaiting_payment`→aguardando_pagamento; `paid`→pago; `picking/checked/label_generated/ready_to_ship`→em_separacao; `shipped`→a_caminho; `delivered`→entregue; `cancelled`→cancelado; `problem`→problema; `fulfilled`→concluido_digital.
Nada de dados internos: sem custos de frete pagos pela editora, sem observações internas, sem dados de outros clientes. Pedidos da Amazon aparecem só se o e-mail bater (a Amazon mascara e-mails; normalmente não aparecem).

## Leitura offline e segurança do arquivo

- "Baixar livro" guarda o PDF no aparelho: no app nativo com o Filesystem do Capacitor (pasta de dados do app), no web app em IndexedDB. Nada em armazenamento público.
- O arquivo baixado só abre com sessão válida naquele aparelho; ao sair ou ao ser desconectado, o app apaga os livros baixados no próximo uso.
- Marca d'água na tela de leitura (nome + e-mail parcial), como no site.
- O app revalida o acesso a cada abertura online (`GET /estante`); offline, lê o que baixou.

## Telas (versão 1)

Abas: **Estante**, **Pedidos**, **Conta**.
1. Abertura (marca), Login, Criar conta, Esqueci a senha, Limite de aparelhos.
2. Estante: continuar lendo (capa, progresso, botão), busca, grade "Meus livros" (selo "baixado"), estado vazio com link para a loja.
3. Livro: capa, título, autores, avaliação, descrição, ficha (páginas, idioma, ISBN, DOI, ano, dimensões), Ler / Baixar (com progresso e cancelar) / Remover do aparelho.
4. Leitor: página do PDF, toque nas bordas vira página (configurável), barra superior (voltar, título, sumário, buscar, marcador, ajustes), barra inferior (página x de y + régua), marca d'água.
5. Sumário (outline do PDF), Buscar no livro (texto do PDF, trecho com o termo e página), Páginas marcadas.
6. Ajustes de leitura: tema (claro, sépia, escuro), virar por toque, uma ou duas páginas (tablet/paisagem), travar orientação.
7. Pedidos: lista com situação e linha do tempo; detalhe com itens, rastreio (código copiável, abrir rastreio).
8. Conta: nome e e-mail, aparelhos conectados, espaço usado pelos downloads (remover todos), ajuda e suporte (WhatsApp/e-mail da editora), sair.
9. Sem internet: faixa no topo, estante só com os baixados.

## Identidade

Marca Conexão Editora: azul `#0096D6` (primária), azul escuro `#0B4F8A`, fundo `#FAFAFA`, texto `#1F2933`; fonte Nunito (da marca). Tema claro e escuro do app; leitor com claro, sépia e escuro. Cantos arredondados, botões em pílula como no layout enviado, mas com hierarquia (um botão primário por tela), área de toque ≥ 44 px, contraste AA.
