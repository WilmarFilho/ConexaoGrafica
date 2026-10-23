<?php

namespace App\Models;

use App\Vendors\Commissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            'vendor_commission_pct' => 'decimal:2',
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

        // Trocar o vendedor ou o % refaz as comissões ainda não repassadas dos pedidos do livro.
        static::updated(function (Product $product) {
            if ($product->wasChanged(['vendor_id', 'vendor_commission_pct'])) {
                $product->syncCommissions();
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** Refaz as comissões dos pedidos deste livro; devolve quantos pedidos. */
    public function syncCommissions(): int
    {
        $orders = Order::query()
            ->whereHas('items', fn (Builder $q) => $q->where('product_id', $this->id))
            ->orWhereIn('id', VendorCommission::query()->where('product_id', $this->id)->select('order_id'))
            ->get();
        $orders->each(fn (Order $order) => Commissions::make()->syncOrder($order));

        return $orders->count();
    }

    /** % do vendedor neste livro: o do produto, senão o do vendedor. */
    public function effectiveCommissionPct(): ?float
    {
        return $this->vendor ? (float) ($this->vendor_commission_pct ?? $this->vendor->commission_pct) : null;
    }

    /** Livros do Editorial (DOI) ligados a este produto; na prática, um. */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /** O livro DOI do produto (o mais recente, se houver mais de um). */
    public function doiBook(): ?Book
    {
        return $this->relationLoaded('books')
            ? $this->books->sortByDesc('id')->first()
            : $this->books()->latest('id')->first();
    }

    /**
     * Situação DOI do produto para filtro e coluna: sem livro, livro cadastrado
     * (DOI ainda não registrado) ou DOI registrado na Crossref.
     */
    public const DOI_FILTERS = [
        'sem' => 'Sem livro DOI',
        'cadastrado' => 'Livro cadastrado, DOI não registrado',
        'registrado' => 'DOI registrado',
    ];

    public function scopeDoiSituation(Builder $query, ?string $situation): void
    {
        match ($situation) {
            'sem' => $query->whereDoesntHave('books'),
            'cadastrado' => $query->whereHas('books')->whereDoesntHave('books', fn (Builder $b) => $b->where('doi_status', 'registered')),
            'registrado' => $query->whereHas('books', fn (Builder $b) => $b->where('doi_status', 'registered')),
            default => null,
        };
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
