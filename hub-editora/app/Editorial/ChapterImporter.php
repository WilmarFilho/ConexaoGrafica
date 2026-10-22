<?php

namespace App\Editorial;

use App\Models\Book;
use App\Models\Contribution;
use App\Models\Institution;
use App\Models\Person;
use App\Models\SyncLog;
use App\Rules\Orcid;
use Illuminate\Support\Facades\DB;

/**
 * "Importar JSON" da etapa 6: recebe uma lista de capítulos no formato do
 * contrato (o mesmo `chapters[]` do payload do WordPress, sem os campos que
 * só o Hub gera) e cria capítulos, pessoas e instituições que faltarem.
 * Aceita tanto `[...]` quanto `{"chapters": [...]}`.
 */
class ChapterImporter
{
    /** @return array{chapters: int, people: int, institutions: int} */
    public static function import(Book $book, array|string $payload): array
    {
        if (is_string($payload)) {
            try {
                $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new EditorialException('JSON inválido: '.$e->getMessage());
            }
        }

        $items = is_array($payload) && array_is_list($payload) ? $payload : ($payload['chapters'] ?? null);

        if (! is_array($items) || $items === []) {
            throw new EditorialException('Esperada uma lista de capítulos ou um objeto {"chapters": [...]}.');
        }

        $stats = ['chapters' => 0, 'people' => 0, 'institutions' => 0];

        DB::transaction(function () use ($book, $items, &$stats) {
            $next = ((int) $book->chapters()->max('sequence')) + 1;

            foreach ($items as $i => $item) {
                if (! is_array($item) || blank($item['title'] ?? null)) {
                    throw new EditorialException('Capítulo '.($i + 1).' sem título.');
                }

                $sequence = (int) ($item['sequence'] ?? $next);
                $next = max($next, $sequence) + 1;

                $chapter = $book->chapters()->create([
                    'sequence' => $sequence,
                    'number' => isset($item['number']) ? (string) $item['number'] : (string) $sequence,
                    'section' => $item['section'] ?? null,
                    'title' => trim((string) $item['title']),
                    'subtitle' => $item['subtitle'] ?? null,
                    'title_en' => $item['title_en'] ?? null,
                    'language' => $item['language'] ?? null,
                    'first_page' => (int) ($item['first_page'] ?? 0),
                    'last_page' => (int) ($item['last_page'] ?? ($item['first_page'] ?? 0)),
                    'publication_date' => $item['publication_date'] ?? null,
                    'abstract_original' => $item['abstract_original'] ?? $item['abstract'] ?? null,
                    'abstract_ai' => $item['abstract_ai'] ?? null,
                    'keywords' => array_values(array_filter((array) ($item['keywords'] ?? []))),
                    'references' => self::references($item['references'] ?? []),
                    'doi_suffix' => $item['doi_suffix'] ?? null,
                    'slug' => $item['slug'] ?? null,
                ]);
                $stats['chapters']++;

                foreach (array_values((array) ($item['contributors'] ?? [])) as $n => $c) {
                    if (! is_array($c) || blank($c['name'] ?? null)) {
                        continue;
                    }

                    $person = self::person($c, $stats);
                    $institution = self::institution($c['affiliation'] ?? null, $stats);

                    $role = array_key_exists($c['role'] ?? '', Contribution::ROLES) ? $c['role'] : 'author';
                    $exists = $chapter->contributions()->where('person_id', $person->id)->where('role', $role)->exists();

                    if ($exists) {
                        continue;
                    }

                    $chapter->contributions()->create([
                        'person_id' => $person->id,
                        'role' => $role,
                        'sequence' => (int) ($c['sequence'] ?? $n + 1),
                        'institution_id' => $institution?->id,
                        'affiliation_text' => $institution ? null : self::affiliationText($c['affiliation'] ?? null),
                    ]);
                }
            }
        });

        SyncLog::record(null, SyncLog::MANUAL, 'editorial.chapters_imported', $book,
            "{$stats['chapters']} capítulo(s) importado(s) por JSON", $stats);

        return $stats;
    }

    /** Pessoa pelo id do Hub, pelo ORCID ou pelo nome; cria se não existir. */
    private static function person(array $c, array &$stats): Person
    {
        if (! empty($c['hub_person_id']) && ($p = Person::find($c['hub_person_id']))) {
            return $p;
        }

        $orcid = filled($c['orcid'] ?? null) ? Orcid::normalize($c['orcid']) : null;

        if ($orcid && ($p = Person::where('orcid', $orcid)->first())) {
            return $p;
        }

        $name = trim((string) $c['name']);
        $p = Person::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        if ($p) {
            if ($orcid && ! $p->orcid) {
                $p->update(['orcid' => $orcid]);
            }

            return $p;
        }

        $stats['people']++;

        return Person::create(['name' => $name, 'sort_name' => $c['sort_name'] ?? null, 'orcid' => $orcid, 'email' => $c['email'] ?? null]);
    }

    private static function institution(mixed $affiliation, array &$stats): ?Institution
    {
        if (! is_array($affiliation) || blank($affiliation['name'] ?? null)) {
            return null;
        }

        $name = trim((string) $affiliation['name']);
        $found = Institution::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        if ($found) {
            return $found;
        }

        $stats['institutions']++;

        return Institution::create([
            'name' => $name,
            'acronym' => $affiliation['acronym'] ?? null,
            'city' => (string) ($affiliation['city'] ?? ''),
            'state' => (string) ($affiliation['state'] ?? ''),
            'country' => (string) ($affiliation['country'] ?? 'BR'),
            'ror_id' => $affiliation['ror_id'] ?? null,
        ]);
    }

    private static function affiliationText(mixed $affiliation): ?string
    {
        return is_string($affiliation) && filled($affiliation) ? trim($affiliation) : null;
    }

    /** `[{text, doi?}]`; strings viram {text}. */
    private static function references(mixed $refs): array
    {
        return collect(is_array($refs) ? $refs : [])
            ->map(fn ($r) => is_string($r) ? ['text' => $r, 'doi' => null] : ['text' => (string) ($r['text'] ?? ''), 'doi' => $r['doi'] ?? null])
            ->filter(fn ($r) => filled($r['text']))
            ->values()->all();
    }
}
