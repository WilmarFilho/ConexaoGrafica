<?php

namespace App\Editorial\Import;

use App\Editorial\EditorialException;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Contribution;
use App\Models\Import;
use App\Models\Institution;
use App\Models\Person;
use App\Models\SyncLog;
use App\Models\User;
use App\Rules\Orcid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aprovação da importação por IA (Conexão DOI, frente E2): copia a proposta
 * aprovada em `imports.result` para o catálogo.
 *
 *  - Livro: cria, ou atualiza o que `imports.book_id` aponta (só preenche o
 *    que veio na proposta; não apaga o que já estava no livro).
 *  - Capítulos: um por `sequence` (reexecutar atualiza em vez de duplicar),
 *    com `abstract_approved` = texto revisado na tela, senão o original, senão
 *    a sugestão da IA; `ai_evidence` = `chapters[i].evidence`.
 *  - Pessoas: reaproveitadas pelo ORCID, depois pelo nome normalizado (sem
 *    acento, caixa, espaços); `sort_name` da proposta. Instituições pelo nome
 *    normalizado. Contribuições únicas por (obra, pessoa, papel).
 *  - Sufixos DOI pelo DoiSuffix (hooks de Book e Chapter).
 *
 * Tudo numa transação. As pendências da proposta (`needs_review`, pessoa sem
 * ORCID nem instituição, resumo vazio...) não travam aqui: viram itens
 * pendentes do checklist do livro (BookWorkflow), que é quem decide a
 * publicação. O livro nasce em rascunho e os capítulos também.
 */
class Integrator
{
    /** @var array<int, Person> índice em book.contributors → pessoa criada/encontrada */
    private array $bookPeople = [];

    /** @var array<string, Person> nome normalizado → pessoa */
    private array $peopleByName = [];

    /** @var array<string, Institution> nome normalizado → instituição */
    private array $institutionsByName = [];

    /** @var array{book: string, chapters: int, people: int, institutions: int, contributions: int} */
    public array $stats = ['book' => '', 'chapters' => 0, 'people' => 0, 'institutions' => 0, 'contributions' => 0];

    public static function integrate(Import $import, ?User $user = null): Book
    {
        return (new self)->run($import, $user ?? auth()->user());
    }

    public function run(Import $import, ?User $user = null): Book
    {
        $result = $import->result;

        if (! is_array($result) || blank($result['book'] ?? null)) {
            throw new EditorialException('A importação ainda não tem proposta para integrar.');
        }

        if (! in_array($import->status, [Import::APPROVED, Import::INTEGRATED], true) || ! $import->isFullyApproved()) {
            throw new EditorialException('Integre só depois de aprovar a obra e todos os capítulos: '.$import->approvalSummary().'.');
        }

        $this->bookPeople = [];
        $this->peopleByName = [];
        $this->institutionsByName = [];
        $this->stats = ['book' => '', 'chapters' => 0, 'people' => 0, 'institutions' => 0, 'contributions' => 0];

        $book = DB::transaction(function () use ($import, $result, $user) {
            $book = $this->book($import, $result['book']);
            $this->bookContributions($book, (array) ($result['book']['contributors'] ?? []));

            foreach (array_values((array) ($result['chapters'] ?? [])) as $i => $data) {
                $this->chapter($book, $data, $i);
            }

            $import->forceFill([
                'book_id' => $book->id,
                'status' => Import::INTEGRATED,
                'reviewed_by' => $user?->id ?? $import->reviewed_by,
                'approved_at' => $import->approved_at ?? now(),
            ])->save();

            return $book;
        });

        SyncLog::record(null, SyncLog::MANUAL, 'import.integrated', $import,
            sprintf('Importação #%d integrada: livro "%s" %s, %d capítulo(s), %d pessoa(s) e %d instituição(ões) nova(s)',
                $import->id, $book->title, $this->stats['book'] === 'created' ? 'criado' : 'atualizado',
                $this->stats['chapters'], $this->stats['people'], $this->stats['institutions']),
            $this->stats + ['book_id' => $book->id, 'user' => $user?->email]);

        return $book->fresh();
    }

    // ------------------------------------------------------------------ livro

    private function book(Import $import, array $b): Book
    {
        $book = $import->book_id ? Book::find($import->book_id) : null;
        $creating = $book === null;
        $book ??= new Book;

        if ($creating && blank($b['title'] ?? null)) {
            throw new EditorialException('A obra precisa de título (aba Obra).');
        }
        if ($creating && (int) ($b['publication_year'] ?? 0) < 1900) {
            throw new EditorialException('A obra precisa do ano de publicação (aba Obra).');
        }

        $values = [
            'title' => $this->text($b['title'] ?? null),
            'subtitle' => $this->text($b['subtitle'] ?? null),
            'alt_title' => $this->text($b['alt_title'] ?? null),
            'alt_title_lang' => $this->text($b['alt_title_lang'] ?? null),
            'type' => array_key_exists($b['type'] ?? '', Book::TYPES) ? $b['type'] : null,
            'series_title' => $this->text($b['series']['title'] ?? null),
            'series_issn' => $this->text($b['series']['issn'] ?? null),
            'volume' => $this->text($b['volume'] ?? null),
            'edition_number' => filled($b['edition_number'] ?? null) ? (int) $b['edition_number'] : null,
            'language' => $this->text($b['language'] ?? null),
            'publisher' => $this->text($b['publisher'] ?? null),
            'publication_place' => $this->text($b['publication_place'] ?? null),
            'publication_year' => filled($b['publication_year'] ?? null) ? (int) $b['publication_year'] : null,
            'publication_date' => $this->text($b['publication_date'] ?? null),
            'pages' => filled($b['pages'] ?? null) ? (int) $b['pages'] : null,
            'format' => array_key_exists($b['format'] ?? '', Book::FORMATS) ? $b['format'] : null,
            'subject_area' => $this->text($b['subject_area'] ?? null),
            'subject_subarea' => $this->text($b['subject_subarea'] ?? null),
            'audience' => $this->text($b['audience'] ?? null),
            'isbn_print' => $this->text($b['isbn_print'] ?? null),
            'isbn_ebook' => $this->text($b['isbn_ebook'] ?? null),
            'other_isbns' => $this->otherIsbns($b['other_isbns'] ?? []),
            'keywords' => $this->strings($b['keywords'] ?? []),
            'keywords_en' => $this->strings($b['keywords_en'] ?? []),
            'abstract' => $this->text($b['abstract'] ?? null),
            'synopsis' => $this->text($b['synopsis'] ?? null),
            'references' => $this->references($b['references'] ?? []),
        ];

        foreach ($values as $field => $value) {
            $empty = $value === null || $value === [];
            if ($creating) {
                // Colunas obrigatórias do esquema recebem o padrão do modelo (Book::$attributes) ou vazio.
                if ($empty && in_array($field, ['subject_area', 'abstract'], true)) {
                    $book->{$field} = '';
                } elseif (! $empty) {
                    $book->{$field} = $value;
                }
            } elseif ($empty) {
                continue; // atualiza só o que a proposta trouxe
            } elseif (in_array($field, ['abstract', 'keywords'], true) && ($b[$field.'_source'] ?? null) === 'ai' && filled($book->{$field})) {
                continue; // sugestão da IA não troca o que já estava cadastrado no livro
            } else {
                $book->{$field} = $value;
            }
        }

        $book->save();
        $this->stats['book'] = $creating ? 'created' : 'updated';

        return $book;
    }

    private function bookContributions(Book $book, array $contributors): void
    {
        foreach (array_values($contributors) as $i => $c) {
            if (! is_array($c) || blank($c['name'] ?? null)) {
                continue; // nome em branco na revisão = pessoa descartada
            }

            $person = $this->person($c);
            $this->bookPeople[$i] = $person;

            $institution = $this->institution($c['affiliation'] ?? null) ?? $this->findInstitution($c['affiliation_text'] ?? null);
            $role = array_key_exists($c['role'] ?? '', Contribution::ROLES) ? $c['role'] : 'author';

            // Ordem global pela posição na lista (a proposta numera por papel: organizador 1, 2; autor 1, 2).
            $this->contribution($book, $person, $role, $i + 1, $institution,
                $institution ? null : $this->text($c['affiliation_text'] ?? null));
        }
    }

    // -------------------------------------------------------------- capítulos

    private function chapter(Book $book, array $data, int $index): void
    {
        $sequence = (int) ($data['sequence'] ?? $index + 1);
        $title = $this->text($data['title'] ?? null);
        if ($title === null) {
            throw new EditorialException("Capítulo {$sequence} sem título.");
        }

        /** @var Chapter $chapter */
        $chapter = $book->chapters()->firstOrNew(['sequence' => $sequence]);
        $chapter->fill([
            'number' => $this->text($data['number'] ?? null) ?? (string) $sequence,
            'section' => $this->text($data['section'] ?? null),
            'title' => $title,
            'subtitle' => $this->text($data['subtitle'] ?? null),
            'title_en' => $this->text($data['title_en'] ?? null),
            'language' => $this->text($data['language'] ?? null),
            'first_page' => (int) ($data['first_page'] ?? 0),
            'last_page' => (int) ($data['last_page'] ?? ($data['first_page'] ?? 0)),
            'abstract_original' => $this->text($data['abstract_original'] ?? null),
            'abstract_ai' => $this->text($data['abstract_ai'] ?? null),
            'abstract_approved' => $this->text($data['abstract_approved'] ?? null)
                ?? $this->text($data['abstract_original'] ?? null)
                ?? $this->text($data['abstract_ai'] ?? null),
            'keywords' => $this->strings($data['keywords'] ?? []),
            'references' => $this->references($data['references'] ?? []),
            'ai_evidence' => is_array($data['evidence'] ?? null) ? $data['evidence'] : null,
        ]);
        $chapter->save();
        $this->stats['chapters']++;

        foreach (array_values((array) ($data['contributors'] ?? [])) as $n => $c) {
            if (! is_array($c) || blank($c['name'] ?? null)) {
                continue;
            }

            $idx = $c['book_contributor_index'] ?? null;
            $person = $idx !== null && isset($this->bookPeople[(int) $idx]) ? $this->bookPeople[(int) $idx] : $this->person($c);
            $institution = $this->findInstitution($c['affiliation_text'] ?? null);
            $role = array_key_exists($c['role'] ?? '', Contribution::ROLES) ? $c['role'] : 'author';

            $this->contribution($chapter, $person, $role, $n + 1, $institution,
                $institution ? null : $this->text($c['affiliation_text'] ?? null));
        }
    }

    // ------------------------------------------------- pessoas e instituições

    private function person(array $c): Person
    {
        $orcid = filled($c['orcid'] ?? null) ? Orcid::normalize($c['orcid']) : null;
        if ($orcid && ! Orcid::isValid($orcid)) {
            $orcid = null;
        }

        if ($orcid && ($p = Person::query()->where('orcid', $orcid)->first())) {
            return $this->remember($p);
        }

        $name = trim((string) $c['name']);
        $key = self::normalize($name);
        $this->loadPeople();

        if ($p = $this->peopleByName[$key] ?? null) {
            if ($orcid && ! $p->orcid) {
                $p->update(['orcid' => $orcid]);
            }

            return $p;
        }

        $p = Person::create([
            'name' => $name,
            'sort_name' => $this->text($c['sort_name'] ?? null),
            'orcid' => $orcid,
        ]);
        $this->stats['people']++;

        return $this->remember($p);
    }

    private function remember(Person $p): Person
    {
        $this->loadPeople();
        $this->peopleByName[self::normalize($p->name)] ??= $p;

        return $p;
    }

    private function loadPeople(): void
    {
        if ($this->peopleByName !== []) {
            return;
        }
        foreach (Person::query()->orderBy('id')->get() as $p) {
            $this->peopleByName[self::normalize($p->name)] ??= $p;
        }
    }

    /** Instituição a partir de `affiliation: {name, city, state, country}`; cria se não existir. */
    private function institution(mixed $affiliation): ?Institution
    {
        if (! is_array($affiliation) || blank($affiliation['name'] ?? null)) {
            return null;
        }

        $name = trim((string) $affiliation['name']);
        if ($found = $this->findInstitution($name)) {
            return $found;
        }

        $i = Institution::create([
            'name' => $name,
            'acronym' => $this->text($affiliation['acronym'] ?? null),
            'city' => trim((string) ($affiliation['city'] ?? '')),
            'state' => trim((string) ($affiliation['state'] ?? '')),
            'country' => trim((string) ($affiliation['country'] ?? '')) ?: 'BR',
            'ror_id' => $this->text($affiliation['ror_id'] ?? null),
        ]);
        $this->stats['institutions']++;
        $this->institutionsByName[self::normalize($name)] = $i;

        return $i;
    }

    /** Só procura (pelo nome normalizado); texto livre que não bate com nada fica em `affiliation_text`. */
    private function findInstitution(mixed $name): ?Institution
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        if ($this->institutionsByName === []) {
            foreach (Institution::query()->orderBy('id')->get() as $i) {
                $this->institutionsByName[self::normalize($i->name)] ??= $i;
            }
            $this->institutionsByName[''] ??= new Institution; // marca "carregado"
        }

        return $this->institutionsByName[self::normalize($name)] ?? null;
    }

    private function contribution(Model $target, Person $person, string $role, int $sequence, ?Institution $institution, ?string $affiliationText): void
    {
        /** @var Contribution $contribution */
        $contribution = $target->contributions()->firstOrNew(['person_id' => $person->id, 'role' => $role]);
        $contribution->fill([
            'sequence' => $sequence,
            'institution_id' => $institution?->id ?? $contribution->institution_id,
            'affiliation_text' => $institution ? null : ($affiliationText ?? $contribution->affiliation_text),
        ]);
        $contribution->save();
        $this->stats['contributions']++;
    }

    // --------------------------------------------------------------- utilitários

    /** Sem acento, minúsculas, só letras/dígitos separados por um espaço. */
    public static function normalize(?string $text): string
    {
        $t = mb_strtolower(Str::ascii((string) $text));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $t) ?? $t);
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function strings(mixed $list): array
    {
        return \App\Editorial\Keywords::normalize($list);
    }

    private function otherIsbns(mixed $list): array
    {
        return collect(is_array($list) ? $list : [])
            ->filter(fn ($o) => is_array($o) && filled($o['value'] ?? null))
            ->map(fn ($o) => ['type' => $this->text($o['type'] ?? null), 'value' => trim((string) $o['value'])])
            ->values()->all();
    }

    private function references(mixed $refs): array
    {
        return collect(is_array($refs) ? $refs : [])
            ->map(fn ($r) => is_string($r) ? ['text' => trim($r), 'doi' => null] : ['text' => trim((string) ($r['text'] ?? '')), 'doi' => $this->text($r['doi'] ?? null)])
            ->filter(fn ($r) => $r['text'] !== '')
            ->values()->all();
    }
}
