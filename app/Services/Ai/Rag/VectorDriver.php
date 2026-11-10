<?php

namespace App\Services\Ai\Rag;

use App\Models\AiEmbedding;
use Illuminate\Support\Collection;

/**
 * Driver contract for vector retrieval.
 *
 * Implementations decide how similarity is executed: natively in the database
 * (Postgres/pgvector, MariaDB >= 11.7) or in PHP (JSON fallback).
 */
interface VectorDriver
{
    /**
     * Human readable driver identifier ("native" or "json").
     */
    public function name(): string;

    /**
     * Rank stored embeddings against the given query vector.
     *
     * @param  array<int, float>  $vector
     * @return Collection<int, array{embedding: AiEmbedding, similarity: float}>
     */
    public function search(array $vector, int $limit, float $minSimilarity = 0.0, ?string $modelClass = null): Collection;
}
