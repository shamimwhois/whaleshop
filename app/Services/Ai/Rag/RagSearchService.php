<?php

namespace App\Services\Ai\Rag;

use App\Models\AiEmbedding;
use Illuminate\Support\Collection;

/**
 * Public retrieval entry point for the RAG layer.
 *
 * Usage:
 *   $results = app(RagSearchService::class)->search('iphone battery replacement');
 *
 * Results are ordered by descending similarity and are safe to embed directly
 * into an agent prompt via toContext() / toPromptContext().
 */
class RagSearchService
{
    public function __construct(
        protected EmbedderService $embedder,
        protected VectorDriverFactory $drivers,
    ) {}

    /**
     * Retrieve the most similar chunks for a natural language query.
     *
     * @return Collection<int, array{embedding: AiEmbedding, similarity: float}>
     */
    public function search(
        string $query,
        int $limit = 0,
        float $minSimilarity = 0.0,
        ?string $modelClass = null,
    ): Collection {
        $query = trim($query);

        if ($query === '' || ! config('ai_commerce.rag.enabled', true)) {
            return collect();
        }

        $vector = $this->embedder->embedQuery($query);

        if ($vector === []) {
            return collect();
        }

        $limit = $limit > 0 ? $limit : (int) config('ai_commerce.rag.limit', 10);
        $minSimilarity = $minSimilarity > 0.0
            ? $minSimilarity
            : (float) config('ai_commerce.rag.min_similarity', 0.5);

        return $this->drivers->make()->search($vector, $limit, $minSimilarity, $modelClass);
    }

    /**
     * Retrieve results and flatten them to plain arrays for prompt injection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toContext(string $query, int $limit = 0): array
    {
        return $this->search($query, $limit)
            ->map(fn (array $row): array => $row['embedding']->toContextArray() + [
                'similarity' => round($row['similarity'], 4),
            ])
            ->all();
    }

    /**
     * Render retrieval results as a compact, token-efficient prompt block.
     */
    public function toPromptContext(string $query, int $limit = 0): string
    {
        $context = $this->toContext($query, $limit);

        if ($context === []) {
            return '';
        }

        $lines = ['Relevant store knowledge:'];
        foreach ($context as $index => $item) {
            $lines[] = sprintf(
                '[%d] (%s #%s) %s',
                $index + 1,
                $item['source'],
                $item['source_id'],
                $item['content'],
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Name of the active driver ("native" or "json").
     */
    public function driver(): string
    {
        return $this->drivers->make()->name();
    }
}
