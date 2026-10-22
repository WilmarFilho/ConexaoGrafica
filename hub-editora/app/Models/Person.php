<?php

namespace App\Models;

use App\Rules\Orcid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pessoa que contribui numa obra (autor, organizador, tradutor...). O ORCID
 * é guardado na forma canônica e o nome de ordenação ("SOBRENOME, Nome") é
 * gerado quando não informado. O e-mail é interno e nunca sai do Hub.
 */
class Person extends Model
{
    protected $table = 'people';

    protected $guarded = [];

    /** Sufixos que ficam junto do sobrenome na ordenação ("FERREIRA NETO, Pedro"). */
    private const SURNAME_SUFFIXES = ['filho', 'neto', 'júnior', 'junior', 'jr', 'jr.', 'sobrinho', 'segundo', 'terceiro'];

    protected static function booted(): void
    {
        static::saving(function (Person $person) {
            $person->name = trim((string) $person->name);
            $person->orcid = filled($person->orcid) ? Orcid::normalize($person->orcid) : null;

            if (blank($person->sort_name)) {
                $person->sort_name = self::sortNameFor($person->name);
            }
        });
    }

    /** "Maria Exemplo da Silva" → "SILVA, Maria Exemplo da". Partículas ficam com o prenome. */
    public static function sortNameFor(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];

        if (count($words) < 2) {
            return mb_strtoupper(trim($name));
        }

        $surname = array_pop($words);

        if (count($words) >= 2 && in_array(mb_strtolower($surname), self::SURNAME_SUFFIXES, true)) {
            $surname = array_pop($words).' '.$surname;
        }

        return mb_strtoupper($surname).', '.implode(' ', $words);
    }

    /** Sobrenome (parte antes da vírgula do nome de ordenação): `surname` na Crossref. */
    public function surname(): string
    {
        return trim(Str::before((string) $this->sort_name, ','));
    }

    /** Prenome(s): `given_name` na Crossref. */
    public function givenName(): string
    {
        return str_contains((string) $this->sort_name, ',') ? trim(Str::after((string) $this->sort_name, ',')) : '';
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /** Instituições com que a pessoa aparece nas contribuições (sem repetir). */
    public function institutions(): Collection
    {
        return $this->contributions()->with('institution')->get()
            ->pluck('institution')->filter()->unique('id')->values();
    }

    public function getOrcidUrlAttribute(): ?string
    {
        return $this->orcid ? 'https://orcid.org/'.$this->orcid : null;
    }
}
