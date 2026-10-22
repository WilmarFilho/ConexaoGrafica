<?php

namespace App\Crossref;

use DOMDocument;
use LibXMLError;

/**
 * Valida um XML contra o `crossref5.4.0.xsd` local (resources/crossref/5.4.0),
 * sem rede: os schemas importados estão na mesma pasta e um carregador de
 * entidades recusa qualquer URL http(s). Os erros saem como texto em português,
 * com a linha, prontos para a tela ("Linha 12: ...").
 */
class Validator
{
    public function __construct(private readonly ?string $schemaPath = null) {}

    public static function schemaDir(): string
    {
        return resource_path('crossref/5.4.0');
    }

    public function schema(): string
    {
        return $this->schemaPath ?? self::schemaDir().'/crossref5.4.0.xsd';
    }

    public function isValid(string $xml): bool
    {
        return $this->errors($xml) === [];
    }

    /**
     * Lista de erros legíveis; vazia quando o XML é válido.
     *
     * @return list<string>
     */
    public function errors(string $xml): array
    {
        $previousUse = libxml_use_internal_errors(true);
        libxml_clear_errors();
        libxml_set_external_entity_loader($this->offlineLoader());

        try {
            $doc = new DOMDocument;
            $doc->preserveWhiteSpace = false;

            if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                $parse = $this->collect();

                return ['XML malformado'.($parse !== [] ? ': '.$parse[0] : '.')];
            }

            return $doc->schemaValidate($this->schema()) ? [] : $this->collect();
        } finally {
            libxml_set_external_entity_loader(null); // volta ao carregador padrão
            libxml_clear_errors();
            libxml_use_internal_errors($previousUse);
        }
    }

    /**
     * Só entrega arquivos de dentro da pasta dos schemas; nada de rede.
     * Devolve o caminho (string) ou null, que faz o libxml falhar o include.
     */
    private function offlineLoader(): callable
    {
        $dir = realpath(dirname($this->schema())) ?: dirname($this->schema());

        return function (?string $public, string $system) use ($dir) {
            if (preg_match('#^https?://#i', $system)) {
                return null;
            }

            $path = $system;
            if (str_starts_with($path, 'file://')) {
                $path = urldecode(substr($path, 7));
                $path = preg_replace('#^/([A-Za-z]:)#', '$1', $path) ?? $path; // Windows: /C:/x → C:/x
            }

            $real = realpath($path);
            if ($real === false || ! str_starts_with($real, $dir)) {
                return null;
            }

            return $real;
        };
    }

    /** @return list<string> */
    private function collect(): array
    {
        $errors = [];

        foreach (libxml_get_errors() as $e) {
            if ($e->level === LIBXML_ERR_WARNING) {
                continue;
            }
            $errors[] = $this->format($e);
        }

        if ($errors === []) {
            $errors[] = 'O XML não passou na validação, mas o libxml não detalhou o motivo.';
        }

        return array_values(array_unique($errors));
    }

    private function format(LibXMLError $e): string
    {
        $msg = trim($e->message);
        $msg = preg_replace('/\{[^}]*\}/', '', $msg) ?? $msg; // tira os namespaces {http://...}

        $msg = $this->translate($msg);

        return ($e->line > 0 ? "Linha {$e->line}: " : '').rtrim($msg, '. ').'.';
    }

    /** Traduz as mensagens mais comuns do libxml; o que não reconhecer fica como veio. */
    private function translate(string $msg): string
    {
        $prefix = '';
        if (preg_match("/^Element '([^']+)', attribute '([^']+)': (.*)$/s", $msg, $m)) {
            $prefix = "Elemento '{$m[1]}', atributo '{$m[2]}': ";
            $msg = $m[3];
        } elseif (preg_match("/^Element '([^']+)': (.*)$/s", $msg, $m)) {
            $prefix = "Elemento '{$m[1]}': ";
            $msg = $m[2];
        }

        $rules = [
            "/^This element is not expected\. Expected is one of \( (.+) \)\.?$/" => 'não é esperado aqui; esperado um de: $1',
            "/^This element is not expected\. Expected is \( (.+) \)\.?$/" => 'não é esperado aqui; esperado: $1',
            "/^This element is not expected\.?$/" => 'não é esperado aqui',
            "/^Missing child element\(s\)\. Expected is one of \( (.+) \)\.?$/" => 'está incompleto; falta um de: $1',
            "/^Missing child element\(s\)\. Expected is \( (.+) \)\.?$/" => 'está incompleto; falta: $1',
            "/^The attribute '([^']+)' is required but missing\.?$/" => "o atributo '$1' é obrigatório",
            "/^The attribute '([^']+)' is not allowed\.?$/" => "o atributo '$1' não é permitido",
            "/^\[facet 'enumeration'\] The value '([^']*)' is not an element of the set \{(.+)\}\.?$/" => "o valor '$1' não está entre os permitidos ($2)",
            "/^\[facet 'pattern'\] The value '([^']*)' is not accepted by the pattern '(.+)'\.?$/" => "o valor '$1' não segue o formato exigido ($2)",
            "/^\[facet 'minLength'\] The value has a length of '(\d+)'; this underruns the allowed minimum length of '(\d+)'\.?$/" => 'o valor tem $1 caractere(s); o mínimo é $2',
            "/^\[facet 'maxLength'\] The value has a length of '(\d+)'; this exceeds the allowed maximum length of '(\d+)'\.?$/" => 'o valor tem $1 caractere(s); o máximo é $2',
            "/^\[facet 'maxInclusive'\] The value '([^']*)' is greater than the maximum value allowed \('([^']*)'\)\.?$/" => "o valor '$1' passa do máximo ($2)",
            "/^\[facet 'minInclusive'\] The value '([^']*)' is less than the minimum value allowed \('([^']*)'\)\.?$/" => "o valor '$1' é menor que o mínimo ($2)",
            "/^\[facet 'totalDigits'\] The value '([^']*)' has more digits than are allowed \('([^']*)'\)\.?$/" => "o valor '$1' tem mais dígitos que o permitido ($2)",
            "/^'([^']*)' is not a valid value of the (?:atomic|local atomic|union|list) type '?([^'.]*)'?\.?$/" => "'$1' não é um valor válido ($2)",
            "/^No matching global declaration available for the validation root\.?$/" => 'elemento raiz não é reconhecido pelo schema (esperado doi_batch da Crossref 5.4.0)',
            "/^Character content other than whitespace is not allowed because the content type is 'element-only'\.?$/" => 'não aceita texto solto, só elementos filhos',
            "/^Character content is not allowed, because the content type is empty\.?$/" => 'deve ficar vazio',
            "/^Element content is not allowed, because the type definition is simple\.?$/" => 'aceita só texto, não elementos filhos',
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $msg)) {
                return $prefix.(preg_replace($pattern, $replacement, $msg) ?? $msg);
            }
        }

        return $prefix.$msg;
    }
}
