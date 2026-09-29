<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    /** Formatos em que um livro é vendido, na ordem em que aparecem no painel. */
    public const FORMATS = [
        'fisico' => 'Físico',
        'ebook' => 'E-book',
        'fisico_ebook' => 'Físico + E-book',
    ];

    /** Formatos que levam livro impresso e, portanto, passam pela expedição. */
    public const SHIPPED_FORMATS = ['fisico', 'fisico_ebook'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'physical' => 'boolean',
            'active' => 'boolean',
            'formats' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // "físico" deixa de ser escolha à parte: vem dos formatos marcados
        static::saving(function (Product $product) {
            if (is_array($product->formats)) {
                $product->formats = self::sortFormats($product->formats);
                $product->physical = (bool) array_intersect($product->formats, self::SHIPPED_FORMATS);
            }
        });
    }

    /** Remove repetidos e desconhecidos e põe na ordem de FORMATS. */
    public static function sortFormats(array $formats): array
    {
        return array_values(array_intersect(array_keys(self::FORMATS), $formats));
    }

    /**
     * Formato a partir do nome da variação na loja ("Impresso", "E-book",
     * "Impresso + E-book"); sem nome reconhecível, decide pelo virtual.
     */
    public static function formatFromLabel(?string $label, bool $virtual = false): string
    {
        $t = strtolower(Str::ascii((string) $label));
        $fisico = str_contains($t, 'impress') || str_contains($t, 'fisic');
        $ebook = str_contains($t, 'book') || str_contains($t, 'digital');

        return match (true) {
            $fisico && $ebook => 'fisico_ebook',
            $fisico => 'fisico',
            $ebook => 'ebook',
            default => $virtual ? 'ebook' : 'fisico',
        };
    }

    /**
     * Título do livro sem o formato: "E-book X", "X - Impresso" e
     * "X — Físico + E-book" viram "X". O formato aparece à parte.
     */
    public static function cleanTitle(string $name): string
    {
        $clean = preg_replace('/^\s*e-?\s?books?\b\s*[:\-–—]?\s*/iu', '', $name);
        $clean = preg_replace('/\s*[-–—]\s*(impresso|f[íi]sico|e-?\s?book)(\s*\+\s*(impresso|f[íi]sico|e-?\s?book))?\s*$/iu', '', (string) $clean);
        $clean = trim((string) $clean);

        return $clean !== '' ? $clean : trim($name);
    }

    public function channelRefs(): HasMany
    {
        return $this->hasMany(ProductChannelRef::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Sem peso e dimensões não dá para cotar frete: a Central de Expedição sinaliza. */
    public function hasShippingDimensions(): bool
    {
        return $this->weight_grams && $this->width_cm && $this->height_cm && $this->depth_cm;
    }

    /** "16 × 23 × 3 cm" ou null quando incompleto. */
    public function getDimensionsLabelAttribute(): ?string
    {
        if (! $this->width_cm || ! $this->height_cm || ! $this->depth_cm) {
            return null;
        }

        return "{$this->width_cm} × {$this->height_cm} × {$this->depth_cm} cm";
    }
}
