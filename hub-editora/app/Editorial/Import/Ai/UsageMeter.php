<?php

namespace App\Editorial\Import\Ai;

/**
 * Soma o uso de tokens e o custo estimado de todas as chamadas de uma importação,
 * por etapa e no total. Vira o json `imports.usage`.
 */
class UsageMeter
{
    private array $calls = [];

    public function __construct(private readonly string $model) {}

    public function add(string $stage, StructuredResult|array $usage, ?int $chapter = null, ?string $status = null): void
    {
        if ($usage instanceof StructuredResult) {
            $status = $usage->status;
            $usage = $usage->usage;
        }
        $u = array_merge(StructuredResult::emptyUsage(), array_intersect_key($usage, StructuredResult::emptyUsage()));
        $this->calls[] = [
            'stage' => $stage,
            'chapter' => $chapter,
            'input_tokens' => $u['input_tokens'],
            'output_tokens' => $u['output_tokens'],
            'cached_input_tokens' => $u['cached_input_tokens'],
            'reasoning_tokens' => $u['reasoning_tokens'],
            'cost_usd' => Pricing::cost($this->model, $u),
            'status' => $status,
        ];
    }

    public function calls(): int
    {
        return count($this->calls);
    }

    /** Uso de um capítulo só (para gravar junto do capítulo na proposta). */
    public function forChapter(int $sequence): array
    {
        return $this->sum(array_filter($this->calls, fn ($c) => $c['chapter'] === $sequence));
    }

    public function toArray(): array
    {
        $stages = [];
        foreach (array_unique(array_column($this->calls, 'stage')) as $stage) {
            $stages[$stage] = $this->sum(array_filter($this->calls, fn ($c) => $c['stage'] === $stage));
        }

        return array_merge($this->sum($this->calls), [
            'model' => $this->model,
            'price_known' => Pricing::known($this->model),
            'by_stage' => $stages,
            'calls_log' => array_values($this->calls),
        ]);
    }

    private function sum(array $calls): array
    {
        $out = StructuredResult::emptyUsage() + ['cost_usd' => 0.0, 'calls' => 0];
        foreach ($calls as $c) {
            foreach (array_keys(StructuredResult::emptyUsage()) as $k) {
                $out[$k] += $c[$k];
            }
            $out['cost_usd'] += $c['cost_usd'];
            $out['calls']++;
        }
        $out['cost_usd'] = round($out['cost_usd'], 4);

        return $out;
    }
}
