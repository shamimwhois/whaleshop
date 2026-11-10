<?php

namespace App\Services\Ai\Rag;

use App\Models\AiEmbedding;
use Illuminate\Support\Collection;

/**
 * Native database-side vector similarity.
 *
 * Uses Eloquent's "whereVectorSimilarTo" macro, which requires a real vector
 * column and is only supported by Postgres/pgvector and MariaDB >= 11.7.
 * Laravel throws a RuntimeException elsewhere, which the factory catches to
 * fall back to the JSON driver.
 */
class NativeVectorDriver implements VectorDriver
{
    public function name(): string
    {
        return 'native';
    }

    /**
     * @return Collection<int, array{embedding: AiEmbedding, similarity: float}>
     */
    public function search(array $vector, int $limit, float $minSimilarity = 0.0, ?string $modelClass = null): Collection
    {
        $query = AiEmbedding::query()->whereNotNull('embedding');

        if ($modelClass !== null) {
            $query->where('embeddable_type', $modelClass);
        }

        $similarity = $minSimilarity > 0.0 ? $minSimilarity : (float) config('ai_commerce.rag.min_similarity', 0.5);

        $query->whereVectorSimilarTo('embedding', $vector, $similarity);

        if ($limit > 0) {
            $query->limit($limit);
        }

        // The driver returns rows already ordered by distance (ascending), so
        // the ordering maps to descending similarity. We synthesize the
        // similarity value from the row position for a consistent return shape.
        return $query->get()
            ->values()
            ->map(function (AiEmbedding $embedding, int $index) {
                return [
                    'embedding'  => $embedding,
                    'similarity' => max(0.0, 1 - ($index / 100)),
                ];
            });
    }
}
