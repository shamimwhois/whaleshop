<?php

namespace App\Ai\Tools;

use App\Services\Ai\Rag\RagSearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Retrieval tool: semantic search over indexed store content (products, pages).
 *
 * This is the primary grounding tool for the sales agent. It delegates to the
 * RAG layer so the same retrieval quality is shared between the agent, the
 * storefront widget and any future MCP server.
 */
class ProductSearch implements Tool
{
    public function __construct(protected ?RagSearchService $rag = null)
    {
        $this->rag ??= app(RagSearchService::class);
    }

    public function description(): Stringable|string
    {
        return 'Search the store catalogue semantically. Use this for any question about products, '
            . 'compatibility, features, repair parts, availability or recommendations. Returns the '
            . 'most relevant catalogue entries with their product ids.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate([
            'query' => ['required', 'string', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $results = $this->rag->search(
            $arguments['query'],
            (int) ($arguments['limit'] ?? config('ai_commerce.rag.limit', 10)),
            modelClass: \App\Models\Product::class,
        );

        if ($results->isEmpty()) {
            return 'No matching products were found in the catalogue.';
        }

        $lines = ['Matching products:'];

        foreach ($results as $index => $row) {
            $embedding = $row['embedding'];
            $product = \App\Models\Product::find($embedding->embeddable_id);

            $lines[] = sprintf(
                '[%d] id=%s | %s | similarity=%s',
                $index + 1,
                $embedding->embeddable_id,
                $product?->name ?? 'Unknown product',
                round($row['similarity'], 3),
            );

            if ($embedding->content) {
                $lines[] = '     ' . \Illuminate\Support\Str::limit($embedding->content, 400);
            }
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('The natural language search query.'),
            'limit' => $schema->integer()->min(1)->max(20)->description('Maximum number of results.'),
        ];
    }
}
