<?php

namespace App\Models\Concerns;

use App\Models\AiEmbedding;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAiEmbeddings
{
    /**
     * The stored vectors for this model.
     *
     * @return MorphMany<AiEmbedding, $this>
     */
    public function aiEmbeddings(): MorphMany
    {
        return $this->morphMany(AiEmbedding::class, 'embeddable');
    }

    /**
     * Build the plain text that should be embedded for this record.
     *
     * Models using this trait should override this to expose the searchable
     * fields (title, description, attributes...). The default keeps the
     * implementation safe for any model.
     */
    public function toEmbeddableText(): string
    {
        $parts = [];

        foreach (['name', 'title', 'description', 'sku'] as $field) {
            $value = $this->getAttribute($field);
            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        return implode("\n\n", $parts);
    }
}
