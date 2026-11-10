<?php

namespace App\Ai\Tools;

use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Retrieval tool for store policy / informational pages.
 *
 * Answers questions about shipping, returns, warranty and terms by reading the
 * CMS pages, which keeps policy wording under the store owner's control rather
 * than baked into the prompt.
 */
class PolicyLookup implements Tool
{
    public function description(): Stringable|string
    {
        return 'Look up store policies and informational pages (shipping, returns, refunds, '
            . 'warranty, terms, privacy) by keyword. Use this to answer policy questions accurately.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate([
            'topic' => ['required', 'string', 'max:200'],
        ]);

        $term = '%' . $arguments['topic'] . '%';

        $pages = Page::query()
            ->where(function ($query) use ($term) {
                $query->where('title', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })
            ->limit(3)
            ->get();

        if ($pages->isEmpty()) {
            return 'No policy page matched "' . $arguments['topic'] . '".';
        }

        $lines = [];

        foreach ($pages as $page) {
            $lines[] = '### ' . $page->title;
            $lines[] = \Illuminate\Support\Str::limit(strip_tags((string) $page->description), 1200);
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->required()->description('Policy topic or keyword, e.g. "shipping" or "returns".'),
        ];
    }
}
