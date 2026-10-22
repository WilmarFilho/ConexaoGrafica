# Schemas da Crossref 5.4.0 (cópia local)

Cópia dos XSDs usados por `App\Crossref\Validator` para validar o `doi_batch`
**sem acesso à rede**. Baixados em 22/10/2026.

| Arquivo | Origem |
|---|---|
| `crossref5.4.0.xsd`, `common5.4.0.xsd`, `languages5.4.0.xsd`, `mediatypes5.4.0.xsd` | https://www.crossref.org/schemas/ |
| `fundref.xsd`, `fundingdata5.4.0.xsd`, `clinicaltrials.xsd`, `AccessIndicators.xsd`, `relations.xsd` | https://www.crossref.org/schemas/ |
| `JATS-journalpublishing1-3d2-mathml3.xsd`, `JATS-journalpublishing1-3d2-mathml3-elements.xsd` | https://www.crossref.org/schemas/ (JATS 1.3d2, redistribuído pela Crossref) |
| `standard-modules/xlink.xsd`, `standard-modules/module-ali.xsd` | https://www.crossref.org/schemas/standard-modules/ |
| `standard-modules/xml.xsd` | https://www.crossref.org/schemas/standard-modules/xml.xsd (idêntico a https://www.w3.org/2009/01/xml.xsd, só muda a instrução de estilo) |
| `standard-modules/mathml3/*.xsd` | https://www.crossref.org/schemas/standard-modules/mathml3/ (cópia da Crossref do MathML 3 do W3C, https://www.w3.org/Math/XMLSchema/mathml3/) |

## Alterações feitas nas cópias

Só dois arquivos foram editados, e só nos `schemaLocation` que apontavam para a
internet (`crossref5.4.0.xsd` e `common5.4.0.xsd`):

- `http://www.w3.org/2009/01/xml.xsd` → `standard-modules/xml.xsd`
- `http://www.w3.org/Math/XMLSchema/mathml3/mathml3.xsd` → `standard-modules/mathml3/mathml3.xsd`

Assim cada namespace (xml, MathML, xlink, ali, JATS) é importado de **um único
arquivo local**, o que evita o aviso do libxml de "namespace já importado" quando o
mesmo namespace vem de dois caminhos diferentes (o JATS já apontava para
`standard-modules/`). Nenhuma regra de validação foi alterada.

Os dois `schemaLocation="http://..."` que restam em `standard-modules/xml.xsd`
estão dentro de comentários de documentação do W3C; não são importados.

## Garantia offline

Além dos caminhos relativos, o `Validator` registra um
`libxml_set_external_entity_loader` que só entrega arquivos de dentro desta
pasta e recusa qualquer URL `http(s)`, e carrega o XML com `LIBXML_NONET`.

## Atualizar

Baixar os mesmos arquivos de novo, reaplicar as duas trocas acima e rodar
`php artisan test --filter=Crossref`.
