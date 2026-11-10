<?php

namespace App\Services\Ai\Rag;

use App\Models\AiEmbedding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

/**
 * Generates and persists embedding vectors for polymorphic source models.
 *
 * The vector is always written to "embedding_json" (portable), and also to the
 * native "embedding" column when the connection supports vector types so that
 * database-side similarity search stays available.
 */
class EmbedderService
{
    public function __construct(protected VectorDriverFactory $drivers) {}

    /**
     * Embed a single model, replacing any previous chunks.
     *
     * @return int Number of chunks written.
     */
    public function embedModel(Model $model): int
    {
        $text = method_exists($model, 'toEmbeddableText')
            ? (string) $model->toEmbeddableText()
            : (string) ($model->getAttribute('description') ?? '');

        if (trim($text) === '') {
            return 0;
        }

        $chunks = $this->chunk($text);
        $vectors = $this->generate($chunks);
        $native = $this->drivers->supportsNative();

        DB::transaction(function () use ($model, $chunks, $vectors, $native) {
            $model->aiEmbeddings()->delete();

            foreach ($chunks as $index => $chunk) {
                $vector = $vectors[$index] ?? null;
                if ($vector === null) {
                    continue;
                }

                $attributes = [
                    'chunk_index'    => $index,
                    'content'        => $chunk,
                    'tokens'         => (int) ceil(Str::length($chunk) / 4),
                    'provider'       => config('ai_commerce.provider'),
                    'model'          => config('ai_commerce.models.embeddings'),
                    'dimensions'     => count($vector),
                    'content_hash'   => hash('sha256', $chunk),
                    'embedding_json' => $vector,
                ];

                if ($native) {
                    // The AsVector cast, registered on the column via the
                    // migration, serializes this array for the vector column.
                    $attributes['embedding'] = $vector;
                }

                $model->aiEmbeddings()->create($attributes);
            }
        });

        return count($chunks);
    }

    /**
     * Generate vectors for a list of text chunks.
     *
     * @param  array<int, string>  $inputs
     * @return array<int, array<int, float>>
     */
    public function generate(array $inputs): array
    {
        if ($inputs === []) {
            return [];
        }

        $response = Embeddings::for(array_values($inputs))
            ->dimensions((int) config('ai_commerce.embeddings_dimensions', 1536))
            ->timeout((int) config('ai_commerce.limits.timeout', 60))
            ->generate(
                provider: config('ai_commerce.provider'),
                model: config('ai_commerce.models.embeddings'),
            );

        return $response->embeddings;
    }

    /**
     * Generate a single query vector.
     *
     * @return array<int, float>
     */
    public function embedQuery(string $text): array
    {
        $vectors = $this->generate([$text]);

        return $vectors[0] ?? [];
    }

    /**
     * Split text into overlapping chunks suitable for embedding.
     *
     * @return array<int, string>
     */
    public function chunk(string $text): array
    {
        $size = (int) config('ai_commerce.rag.chunk_size', 800);
        $overlap = (int) config('ai_commerce.rag.chunk_overlap', 100);
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        if ($text === '') {
            return [];
        }

        if (Str::length($text) <= $size) {
            return [$text];
        }

        $chunks = [];
        $offset = 0;
        $length = Str::length($text);
        $step = max(1, $size - $overlap);

        while ($offset < $length) {
            $chunks[] = Str::substr($text, $offset, $size);
            $offset += $step;
        }

        return $chunks;
    }
}
