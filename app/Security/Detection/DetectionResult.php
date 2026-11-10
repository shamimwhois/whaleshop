<?php

namespace App\Security\Detection;

use JsonSerializable;

/**
 * A single signature hit. Immutable value object.
 */
final class DetectionResult implements JsonSerializable
{
    public function __construct(
        public readonly string $category,
        public readonly string $rule,
        public readonly string $field,
        public readonly int $weight,
        public readonly string $excerpt = '',
    ) {}

    public function key(): string
    {
        return $this->category.':'.$this->rule.':'.$this->field;
    }

    public function jsonSerialize(): array
    {
        return [
            'category' => $this->category,
            'rule'     => $this->rule,
            'field'    => $this->field,
            'weight'   => $this->weight,
            'excerpt'  => $this->excerpt,
        ];
    }
}
