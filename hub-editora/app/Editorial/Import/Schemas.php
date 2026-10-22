<?php

namespace App\Editorial\Import;

/**
 * JSON Schemas da saída estruturada. Todas as propriedades são obrigatórias e o
 * "não achei" é null ou lista vazia: assim o modelo nunca inventa para preencher.
 * A evidência vem como lista {field, pdf_page, excerpt, confidence}; o Analyzer
 * transforma em mapa por campo.
 */
class Schemas
{
    public static function frontMatter(): array
    {
        return self::object([
            'title' => self::nullable('string', 'Título principal da obra, como está na folha de rosto'),
            'subtitle' => self::nullable('string'),
            'alt_title' => self::nullable('string', 'Título em outro idioma, se impresso'),
            'alt_title_lang' => self::nullable('string', 'ISO 639-1 do título alternativo (ex.: en)'),
            'type' => self::nullable('string', 'Um de: edited_book (obra organizada, capítulos de autores diversos), monograph (autoral), reference (obra de referência), other'),
            'edition_number' => self::nullable('integer'),
            'volume' => self::nullable('string'),
            'series_title' => self::nullable('string'),
            'series_issn' => self::nullable('string'),
            'language' => self::nullable('string', 'ISO 639-1 do idioma principal (pt, en, es)'),
            'publisher' => self::nullable('string'),
            'publication_place' => self::nullable('string', 'Cidade da editora'),
            'publication_year' => self::nullable('integer'),
            'publication_date' => self::nullable('string', 'YYYY-MM-DD, só se a data completa estiver impressa'),
            'pages' => self::nullable('integer', 'Total de páginas, se informado na ficha catalográfica'),
            'isbn_print' => self::nullable('string', 'ISBN-13 do impresso, exatamente como impresso'),
            'isbn_ebook' => self::nullable('string', 'ISBN-13 do e-book/PDF/EPUB'),
            'other_isbns' => self::list(self::object([
                'type' => self::str('Rótulo impresso (ex.: EPUB, brochura)'),
                'value' => self::str(),
            ])),
            'subject_area' => self::nullable('string', 'Área do conhecimento, pela CDD/CDU da ficha ou pelo tema'),
            'subject_subarea' => self::nullable('string'),
            'keywords' => self::list(self::str(), 'Palavras-chave da obra só se estiverem impressas (ficha catalográfica ou página de resumo)'),
            'abstract' => self::nullable('string', 'Resumo/apresentação da obra somente se existir no PDF, copiado literalmente'),
            'contributors' => self::list(self::contributor(true), 'Organizadores e autores listados nas páginas iniciais'),
            'toc' => self::list(self::object([
                'section' => self::nullable('string', 'Parte/seção do sumário em que os capítulos estão, ou null'),
                'chapters' => self::list(self::object([
                    'number' => self::nullable('string', 'Número impresso do capítulo'),
                    'title' => self::str(),
                    'authors' => self::list(self::str(), 'Autores do capítulo como aparecem no sumário, se aparecerem'),
                    'printed_page' => self::nullable('integer', 'Página impressa em que o capítulo começa, segundo o sumário'),
                ])),
            ]), 'Sumário completo, na ordem, só com capítulos de conteúdo (sem prefácio, apresentação, índice)'),
            'end_of_chapters_printed_page' => self::nullable('integer', 'Página impressa do primeiro item depois do último capítulo (índice, anexos), se o sumário trouxer'),
            'evidence' => self::evidenceList(),
            'notes' => self::nullable('string', 'Avisos para o revisor humano (dúvidas, dados ilegíveis)'),
        ]);
    }

    public static function chapter(): array
    {
        return self::object([
            'title' => self::nullable('string', 'Título do capítulo confirmado no texto'),
            'subtitle' => self::nullable('string'),
            'title_en' => self::nullable('string', 'Título em inglês só se impresso no capítulo'),
            'number' => self::nullable('string'),
            'language' => self::nullable('string', 'ISO 639-1'),
            'authors' => self::list(self::contributor(false), 'Autores do capítulo na ordem impressa'),
            'abstract_original' => self::nullable('string', 'Resumo impresso no capítulo, copiado literalmente; null se não houver'),
            'abstract_ai' => self::nullable('string', 'Resumo redigido por você, 120 a 250 palavras, português, só texto, APENAS se abstract_original for null'),
            'keywords_original' => self::list(self::str(), 'Palavras-chave impressas no capítulo'),
            'keywords_ai' => self::list(self::str(), '3 a 6 palavras-chave sugeridas APENAS se não houver impressas'),
            'references' => self::list(self::object([
                'text' => self::str('Referência completa como impressa'),
                'doi' => self::nullable('string', 'DOI só se estiver impresso na referência'),
            ]), 'Referências bibliográficas do capítulo, se houver lista'),
            'evidence' => self::evidenceList(),
            'notes' => self::nullable('string'),
        ]);
    }

    /** Parte de um capítulo longo: só o que serve para consolidar depois. */
    public static function chapterPart(): array
    {
        return self::object([
            'title' => self::nullable('string', 'Título do capítulo, se esta parte o contém'),
            'authors' => self::list(self::contributor(false)),
            'abstract_original' => self::nullable('string', 'Resumo impresso, literal, se estiver nesta parte'),
            'keywords_original' => self::list(self::str()),
            'references' => self::list(self::object(['text' => self::str(), 'doi' => self::nullable('string')])),
            'key_points' => self::list(self::str(), '5 a 12 frases com o conteúdo essencial desta parte, para redigir o resumo final'),
            'evidence' => self::evidenceList(),
        ]);
    }

    private static function contributor(bool $withRole): array
    {
        $props = [
            'name' => self::str('Nome completo como impresso'),
            'affiliation' => self::nullable('string', 'Instituição resumida (ex.: Universidade Federal de Goiás); null se não impressa'),
            'city' => self::nullable('string'),
            'state' => self::nullable('string', 'UF com 2 letras, se impressa'),
            'country' => self::nullable('string', 'ISO-2, se impresso ou óbvio pela instituição'),
            'orcid' => self::nullable('string', 'Só se o ORCID estiver impresso (0000-0000-0000-000X)'),
            'book_contributor' => self::nullable('string', 'Nome EXATO da lista de pessoas da obra a que este autor corresponde, ou null'),
            'evidence' => self::evidence(),
            'confidence' => self::num('0 a 1'),
        ];
        if ($withRole) {
            $props['role'] = ['type' => 'string', 'enum' => ['editor', 'author', 'translator', 'other'],
                'description' => 'editor = organizador/coordenador/editor; author = autor'];
            unset($props['book_contributor']);
        }

        return self::object($props);
    }

    private static function evidence(): array
    {
        return self::object([
            'pdf_page' => self::nullable('integer', 'Número da página do PDF (o marcador [[... | PDF n]] ou a posição no documento enviado)'),
            'excerpt' => self::str('Trecho literal curto (até 200 caracteres) que comprova o dado'),
        ]);
    }

    private static function evidenceList(): array
    {
        return self::list(self::object([
            'field' => self::str('Nome do campo (title, isbn_print, publication_year, authors, abstract_original...)'),
            'pdf_page' => self::nullable('integer'),
            'excerpt' => self::str(),
            'confidence' => self::num('0 a 1'),
        ]), 'Uma entrada por campo preenchido, com página e trecho que o justificam');
    }

    private static function object(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    private static function list(array $items, ?string $description = null): array
    {
        return array_filter(['type' => 'array', 'items' => $items, 'description' => $description]);
    }

    private static function str(?string $description = null): array
    {
        return array_filter(['type' => 'string', 'description' => $description]);
    }

    private static function num(?string $description = null): array
    {
        return array_filter(['type' => 'number', 'description' => $description]);
    }

    private static function nullable(string $type, ?string $description = null): array
    {
        return array_filter(['type' => [$type, 'null'], 'description' => $description]);
    }
}
