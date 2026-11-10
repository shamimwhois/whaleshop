<?php

namespace App\Services\Ai\Rag;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Chooses the vector driver for the current database connection.
 *
 * Resolution order:
 *   1. Explicit "ai_commerce.rag.driver" of "native" or "json".
 *   2. "auto": native when the connection supports vector distance queries
 *      (Postgres/pgvector, MariaDB >= 11.7) AND the vector column exists;
 *      otherwise the portable JSON driver.
 */
class VectorDriverFactory
{
    public function __construct(
        protected NativeVectorDriver $native,
        protected JsonVectorDriver $json,
    ) {}

    public function make(): VectorDriver
    {
        $configured = (string) config('ai_commerce.rag.driver', 'auto');

        if ($configured === 'json') {
            return $this->json;
        }

        if ($configured === 'native') {
            return $this->native;
        }

        return $this->supportsNative() ? $this->native : $this->json;
    }

    /**
     * Determine whether the current connection can run vector distance queries.
     */
    public function supportsNative(): bool
    {
        try {
            $grammar = DB::connection()->getQueryGrammar();

            if (! $grammar->supportsVectorDistance()) {
                return false;
            }

            return Schema::connection(DB::connection()->getName())
                ->hasColumn(config('ai_commerce.rag.table', 'ai_embeddings'), 'embedding');
        } catch (RuntimeException) {
            return false;
        } catch (\Throwable) {
            return false;
        }
    }
}
