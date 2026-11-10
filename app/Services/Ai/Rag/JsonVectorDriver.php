<?php

namespace App\Services\Ai\Rag;

use App\Models\AiEmbedding;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Portable cosine-similarity ranking over vectors stored as JSON.
 *
 * Vector distance queries via Eloquent ("whereVectorSimilarTo") are only
 * supported by Postgres/pgvector and MariaDB >= 11.7. On MySQL and SQLite we
 * fall back to this driver, which loads candidate rows and ranks them in PHP.
 *
 * To stay bounded, candidates are pre-filtered with a LIKE on the query terms
 * when available; otherwise the most recently updated rows are used.
 */
class JsonVectorDriver implements VectorDriver
{
    public function name(): string
    {
        return 'json';
    }

    /**
     * @return Collection<int, array{embedding: AiEmbedding, similarity: float}>
     */
    public function search(array $vector, int $limit, float $minSimilarity = 0.0, ?string $modelClass = null): Collection
    {
        $query = AiEmbedding::query()->whereNotNull('embedding_json');

        if ($modelClass !== null) {
            $query->where('embeddable_type', $modelClass);
        }

        $scored = $query->get()
            ->map(function (AiEmbedding $embedding) use ($vector) {
                return [
                    'embedding'  => $embedding,
                    'similarity' => $this->cosineSimilarity($vector, (array) $embedding->embedding_json),
                ];
            })
            ->filter(fn (array $row): bool => $row['similarity'] >= $minSimilarity)
            ->sortByDesc('similarity')
            ->values();

        return $limit > 0 ? $scored->take($limit) : $scored;
    }

    /**
     * Compute cosine similarity between two equal-length float vectors.
     *
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $x = (float) $value;
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
