<?php

namespace App\Console\Commands;

use App\Models\AiEmbedding;
use App\Models\Product;
use App\Services\Ai\Rag\EmbedderService;
use Illuminate\Console\Command;

/**
 * Backfill (or refresh) embedding vectors for RAG-enabled source models.
 *
 * Examples:
 *   php artisan ai:embed products
 *   php artisan ai:embed products --id=42 --force
 *   php artisan ai:embed products --limit=500
 */
class AiEmbedCommand extends Command
{
    protected $signature = 'ai:embed
                            {source=products : The source to embed (products)}
                            {--id= : Embed a single record id}
                            {--limit=0 : Maximum records to process (0 = all)}
                            {--force : Re-embed even when the content hash is unchanged}
                            {--chunk=100 : Records processed per batch}';

    protected $description = 'Generate RAG embedding vectors for store content (products, pages)';

    public function handle(EmbedderService $embedder): int
    {
        if (! config('ai_commerce.rag.enabled', true)) {
            $this->warn('RAG is disabled (ai_commerce.rag.enabled). Enable it before embedding.');

            return self::FAILURE;
        }

        $source = strtolower((string) $this->argument('source'));

        $query = match ($source) {
            'products' => Product::query(),
            default    => null,
        };

        if ($query === null) {
            $this->error("Unsupported source [{$source}]. Supported: products.");

            return self::FAILURE;
        }

        if ($id = $this->option('id')) {
            $query->whereKey($id);
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('No records matched the given filters.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Embedding %d record(s) from [%s] using driver [%s]...', $total, $source, app(\App\Services\Ai\Rag\VectorDriverFactory::class)->make()->name()));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $skipped = 0;
        $vectors = 0;

        $query->with(['category', 'brand'])
            ->chunkById((int) $this->option('chunk'), function ($records) use ($embedder, $bar, &$processed, &$skipped, &$vectors) {
                foreach ($records as $record) {
                    $text = method_exists($record, 'toEmbeddableText') ? (string) $record->toEmbeddableText() : '';

                    if ($text === '') {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    if (! $this->option('force') && $this->isUnchanged($record, $text)) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    try {
                        $vectors += $embedder->embedModel($record);
                    } catch (\Throwable $exception) {
                        $this->newLine();
                        $this->error(sprintf('Failed to embed #%s: %s', $record->getKey(), $exception->getMessage()));
                    }

                    $processed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf('Done. processed=%d skipped=%d vectors_written=%d', $processed, $skipped, $vectors));

        return self::SUCCESS;
    }

    /**
     * Determine if the stored vectors already reflect the current content.
     */
    protected function isUnchanged(object $record, string $text): bool
    {
        $hash = hash('sha256', trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text));

        $existing = AiEmbedding::query()
            ->where('embeddable_type', $record::class)
            ->where('embeddable_id', $record->getKey())
            ->limit(1)
            ->value('content_hash');

        if ($existing === null) {
            return false;
        }

        // Rebuild the same chunk hashes the embedder would produce.
        $embedder = app(EmbedderService::class);
        $chunkHashes = array_map(
            static fn (string $chunk): string => hash('sha256', $chunk),
            $embedder->chunk($text),
        );

        return in_array($existing, $chunkHashes, true);
    }
}
