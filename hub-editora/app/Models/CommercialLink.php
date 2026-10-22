<?php

namespace App\Models;

use App\Editorial\EditorialException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Caixa de compra da página pública: uma linha por modalidade, ligada ao
 * produto WooCommerce pelo ID (preço e estoque reais). URL só quando não
 * há produto. Os capítulos herdam os links do livro.
 */
class CommercialLink extends Model
{
    protected $guarded = [];

    public const MODALITIES = [
        'print' => 'Livro impresso',
        'ebook' => 'E-book',
        'combo' => 'Impresso + e-book',
    ];

    public const DEFAULT_LABELS = [
        'print' => 'Comprar livro impresso',
        'ebook' => 'Comprar e-book',
        'combo' => 'Comprar impresso + e-book',
    ];

    /** URL externa: só HTTPS no domínio da editora (o WP recusa o resto). */
    public const URL_PATTERN = '~^https://([a-z0-9-]+\.)*conexaoeditora\.com\.br(/|$)~i';

    public static function isAcceptableUrl(?string $url): bool
    {
        return blank($url) || preg_match(self::URL_PATTERN, (string) $url) === 1;
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'woo_product_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CommercialLink $link) {
            if (blank($link->label)) {
                $link->label = self::DEFAULT_LABELS[$link->modality] ?? 'Comprar';
            }
            $link->woo_product_id = $link->woo_product_id ?: null;
            $link->url = filled($link->url) ? trim((string) $link->url) : null;

            if (! self::isAcceptableUrl($link->url)) {
                throw new EditorialException('Link de compra: só HTTPS no domínio conexaoeditora.com.br.');
            }
        });
    }

    /** As três modalidades, na ordem, para o assistente começar preenchido. */
    public static function defaults(): array
    {
        $rows = [];
        $i = 1;
        foreach (self::MODALITIES as $modality => $label) {
            $rows[] = ['modality' => $modality, 'label' => self::DEFAULT_LABELS[$modality], 'woo_product_id' => null, 'url' => null, 'enabled' => $modality !== 'combo', 'sort_order' => $i++];
        }

        return $rows;
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** Tem para onde mandar o leitor (produto Woo ou URL)? */
    public function hasDestination(): bool
    {
        return $this->woo_product_id !== null || filled($this->url);
    }
}
