<?php

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Simple keyword search over local markdown service documents (resources/policies).
 * Splits docs into paragraphs and returns the best-matching ones.
 * (In production this would be a vector / full-text search.)
 */
class SearchServiceInfoTool extends Tool
{
    private const STOP_WORDS = ['the', 'a', 'an', 'is', 'are', 'can', 'i', 'my', 'to', 'of', 'for', 'and', 'or', 'in', 'on', 'what', 'how', 'do', 'it', 'be'];

    public function name(): string
    {
        return 'search_service_info';
    }

    protected function description(): string
    {
        return 'Search the Blumond service documents (warranty, pricing and diagnostics, opening hours, data privacy, what we do not do). Returns the most relevant paragraphs with their source file. Use it before answering any general question about the shop.';
    }

    protected function properties(): array
    {
        return [
            'query' => ['type' => 'string', 'description' => 'Short search query, e.g. "warranty water damage"'],
        ];
    }

    protected function rules(): array
    {
        return ['query' => ['required', 'string', 'min:2', 'max:200']];
    }

    protected function handle(array $input, AgentContext $ctx): array
    {
        return ['results' => $this->search($input['query'])];
    }

    /** @return array<int, array{source: string, text: string, score: int}> */
    public function search(string $query, int $limit = 3): array
    {
        $terms = collect(preg_split('/\W+/', Str::lower($query)))
            ->filter(fn ($t) => strlen($t) > 1 && ! in_array($t, self::STOP_WORDS, true))
            ->unique();

        $matches = [];
        foreach (File::glob(resource_path('policies/*.md')) as $file) {
            foreach (preg_split('/\n\s*\n/', File::get($file)) as $paragraph) {
                $text = trim($paragraph);
                if ($text === '' || str_starts_with($text, '#')) {
                    continue;
                }
                $lower = Str::lower($text);
                $score = $terms->sum(fn ($t) => substr_count($lower, $t));
                if ($score > 0) {
                    $matches[] = ['source' => basename($file), 'text' => $text, 'score' => $score];
                }
            }
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, $limit);
    }
}
