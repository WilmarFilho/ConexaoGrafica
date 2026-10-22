<?php

namespace App\Filament\Resources\Chapters\Pages;

use App\Filament\Resources\Chapters\ChapterResource;
use Filament\Resources\Pages\ListRecords;

class ListChapters extends ListRecords
{
    protected static string $resource = ChapterResource::class;

    protected static ?string $title = 'Capítulos';

    public function getSubheading(): ?string
    {
        return 'Todos os capítulos, de todos os livros. Capítulo novo nasce dentro do livro (etapa 6).';
    }
}
