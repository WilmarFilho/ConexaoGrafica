<?php

namespace App\Filament\Resources\Imports\Pages;

use RuntimeException;

/**
 * O formulário da revisão está mais velho que o `result` gravado (a IA terminou a
 * leitura ou outra janela salvou). A tela já foi recarregada e avisada; a ação que
 * ia gravar é abandonada.
 */
class StaleFormException extends RuntimeException {}
