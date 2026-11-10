<?php

namespace App\Security\Detection;

use JsonSerializable;

/**
 * Aggregated outcome of inspecting one HTTP request.
 */
final class DetectionReport implements JsonSerializable
{
    /** @param DetectionResult[] $matches */
    public function __construct(
        public array $matches = [],
        public int $score = 0,
        public string $severity = 'info',
        public string $action = 'allow',
        public array $categories = [],
    ) {}

    public function add(DetectionResult $match): void
    {
        foreach ($this->matches as $existing) {
            if ($existing->key() === $match->key()) {
                return; // one hit per rule per field
            }
        }

        $this->matches[] = $match;
        $this->categories[$match->category] = true;
    }

    public function hasMatches(): bool
    {
        return $this->matches !== [];
    }

    public function categoryList(): array
    {
        return array_keys($this->categories);
    }

    public function jsonSerialize(): array
    {
        return [
            'score'      => $this->score,
            'severity'   => $this->severity,
            'action'     => $this->action,
            'categories' => $this->categoryList(),
            'matches'    => array_map(static fn (DetectionResult $m) => $m->jsonSerialize(), $this->matches),
        ];
    }
}
