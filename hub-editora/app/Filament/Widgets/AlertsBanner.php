<?php

namespace App\Filament\Widgets;

use App\Support\IntegrationAlerts;
use Filament\Widgets\Widget;

/** Faixa no topo da Visão geral: só aparece quando alguma integração precisa de atenção. */
class AlertsBanner extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.alerts-banner';

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return IntegrationAlerts::openIssues() !== [];
    }

    public function getIssues(): array
    {
        return IntegrationAlerts::openIssues();
    }
}
