<?php

namespace App\Filament\Resources\Books;

use App\Editorial\BookWorkflow;
use App\Editorial\EditorialException;
use App\Models\Book;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Ações de situação do livro (seção 3 do contrato), usadas na etapa 8 do
 * assistente e no cabeçalho da tela de resumo. Quem decide é o BookWorkflow;
 * aqui só se mostra o botão certo para o perfil certo e se traduz o erro.
 */
class BookActions
{
    /**
     * @param  bool  $saveFirst  na edição, grava o formulário antes (o checklist vê o que está na tela)
     * @return array<Action>
     */
    public static function transitions(bool $saveFirst = false): array
    {
        $user = fn () => auth()->user();

        return [
            Action::make('enviar_revisao')
                ->label('Enviar para revisão')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->visible(fn (Book $record) => $record->status === 'draft' && $user()?->canEditEditorial())
                ->requiresConfirmation()
                ->modalHeading('Enviar o livro para revisão?')
                ->modalDescription('O checklist é conferido agora. Em revisão, o revisor aprova ou devolve.')
                ->action(self::run($saveFirst, fn (Book $b) => BookWorkflow::submitForReview($b), 'Livro enviado para revisão')),

            Action::make('aprovar')
                ->label('Aprovar')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn (Book $record) => $record->status === 'review' && $user()?->canApproveEditorial())
                ->requiresConfirmation()
                ->modalHeading('Aprovar o livro?')
                ->modalDescription('Todos os capítulos precisam estar aprovados. Depois de aprovado, o livro pode ir para o site.')
                ->action(self::run($saveFirst, fn (Book $b) => BookWorkflow::approve($b), 'Livro aprovado')),

            Action::make('devolver')
                ->label('Devolver ao rascunho')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (Book $record) => in_array($record->status, ['review', 'approved'], true) && $user()?->canEditEditorial())
                ->schema([
                    Textarea::make('reason')->label('Motivo (opcional)')->rows(2)->maxLength(500),
                ])
                ->action(fn (Book $record, array $data, Component $livewire) => self::run(
                    $saveFirst,
                    fn (Book $b) => BookWorkflow::backToDraft($b, null, $data['reason'] ?? null),
                    'Livro devolvido ao rascunho',
                )($record, $livewire)),

            Action::make('publicar')
                ->label(fn (Book $record) => $record->status === 'approved' ? 'Publicar no site' : 'Republicar no site')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('primary')
                ->visible(fn (Book $record) => $record->canBePublished() && $user()?->canEditEditorial())
                ->requiresConfirmation()
                ->modalHeading('Publicar no site?')
                ->modalDescription('O registro completo (livro, capítulos, pessoas e links) vai para o WordPress pela fila. As URLs devolvidas são as que vão para a Crossref.')
                ->action(self::run($saveFirst, fn (Book $b) => BookWorkflow::requestPublish($b), 'Publicação enfileirada')),

            Action::make('despublicar')
                ->label('Tirar de catálogo')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->visible(fn (Book $record) => $record->status === 'published' && $user()?->canEditEditorial())
                ->requiresConfirmation()
                ->modalHeading('Tirar o livro de catálogo?')
                ->modalDescription('A página continua no ar, marcada como fora de catálogo e sem o botão de compra (regra da Crossref).')
                ->action(self::run($saveFirst, fn (Book $b) => BookWorkflow::requestUnpublish($b), 'Retirada de catálogo enfileirada')),
        ];
    }

    /** Executa a transição com o registro atualizado e traduz a EditorialException em aviso. */
    private static function run(bool $saveFirst, callable $transition, string $success): \Closure
    {
        return function (Book $record, Component $livewire) use ($saveFirst, $transition, $success): void {
            if ($saveFirst && method_exists($livewire, 'save')) {
                $livewire->save(shouldRedirect: false, shouldSendSavedNotification: false);
            }

            $record->refresh();

            try {
                $transition($record);
                Notification::make()->title($success)->success()->send();
            } catch (EditorialException $e) {
                Notification::make()->title('Não foi possível')->body($e->getMessage())->danger()->persistent()->send();
            }
        };
    }
}
