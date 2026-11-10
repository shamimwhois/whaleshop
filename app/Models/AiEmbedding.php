<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiEmbedding extends Model
{
    protected $table = 'ai_embeddings';

    protected $fillable = [
        'embeddable_type',
        'embeddable_id',
        'chunk_index',
        'content',
        'tokens',
        'provider',
        'model',
        'dimensions',
        'content_hash',
        'embedding_json',
    ];

    protected $casts = [
        'id'             => 'integer',
        'embeddable_id'  => 'integer',
        'chunk_index'    => 'integer',
        'tokens'         => 'integer',
        'dimensions'     => 'integer',
        'embedding_json' => 'array',
    ];

    /**
     * The model (Product, Page, ...) this vector describes.
     *
     * @return MorphTo<Model, $this>
     */
    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get a sanitized copy of the row with the vector payload removed.
     *
     * Used when injecting retrieval results into an agent prompt, so we never
     * waste tokens (or leak internals) by shipping raw float arrays.
     *
     * @return array<string, mixed>
     */
    public function toContextArray(): array
    {
        return [
            'id'         => $this->id,
            'source'     => class_basename($this->embeddable_type),
            'source_id'  => $this->embeddable_id,
            'chunk'      => $this->chunk_index,
            'content'    => $this->content,
        ];
    }
}
