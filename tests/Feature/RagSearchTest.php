<?php

namespace Tests\Feature;

use App\Models\AiEmbedding;
use App\Models\Product;
use App\Services\Ai\Rag\EmbedderService;
use App\Services\Ai\Rag\JsonVectorDriver;
use App\Services\Ai\Rag\RagSearchService;
use App\Services\Ai\Rag\VectorDriverFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

/**
 * Verifies the portable (JSON) RAG pipeline end to end on SQLite, where native
 * vector distance queries are unavailable and the JSON driver must be used.
 */
class RagSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_driver_is_selected_when_vectors_are_not_native(): void
    {
        config()->set('ai_commerce.rag.driver', 'auto');

        $driver = app(VectorDriverFactory::class)->make();

        $this->assertInstanceOf(JsonVectorDriver::class, $driver);
        $this->assertSame('json', $driver->name());
    }

    public function test_cosine_similarity_ranks_identical_vectors_highest(): void
    {
        $driver = new JsonVectorDriver();

        $this->assertEqualsWithDelta(1.0, $driver->cosineSimilarity([1.0, 0.0], [1.0, 0.0]), 0.0001);
        $this->assertEqualsWithDelta(0.0, $driver->cosineSimilarity([1.0, 0.0], [0.0, 1.0]), 0.0001);
        $this->assertEqualsWithDelta(0.0, $driver->cosineSimilarity([1.0], [1.0, 2.0]), 0.0001);
    }

    public function test_embedding_a_product_persists_chunks_and_vectors(): void
    {
        Embeddings::fake([
            [[1.0, 0.0, 0.0]],
        ]);

        $product = Product::factory()->create([
            'name'        => 'iPhone 12 Battery',
            'description' => 'Replacement battery for iPhone 12.',
        ]);

        $count = app(EmbedderService::class)->embedModel($product);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('ai_embeddings', [
            'embeddable_type' => Product::class,
            'embeddable_id'   => $product->getKey(),
            'chunk_index'     => 0,
        ]);

        $stored = AiEmbedding::where('embeddable_id', $product->getKey())->first();
        $this->assertEquals([1.0, 0.0, 0.0], $stored->embedding_json);
    }

    public function test_rag_search_returns_the_closest_product(): void
    {
        $product = Product::factory()->create([
            'name'        => 'iPhone 12 Battery',
            'description' => 'Replacement battery for iPhone 12.',
        ]);

        // Query vector matches the product vector exactly on the first call;
        // the fake returns one response per generate() invocation.
        Embeddings::fake([
            [[1.0, 0.0, 0.0]], // embedModel()
            [[1.0, 0.0, 0.0]], // embedQuery()
        ]);

        app(EmbedderService::class)->embedModel($product);

        $results = app(RagSearchService::class)->search('iphone battery', limit: 5, minSimilarity: 0.0);

        $this->assertCount(1, $results);
        $this->assertSame($product->getKey(), $results->first()['embedding']->embeddable_id);
        $this->assertEqualsWithDelta(1.0, $results->first()['similarity'], 0.0001);
    }

    public function test_prompt_context_is_rendered_for_grounding(): void
    {
        $product = Product::factory()->create([
            'name'        => 'Samsung Screen Protector',
            'description' => 'Tempered glass screen protector.',
        ]);

        Embeddings::fake([
            [[0.0, 1.0, 0.0]],
            [[0.0, 1.0, 0.0]],
        ]);

        app(EmbedderService::class)->embedModel($product);

        $context = app(RagSearchService::class)->toPromptContext('screen protector', limit: 5);

        $this->assertStringContainsString('Relevant store knowledge', $context);
        $this->assertStringContainsString('Product', $context);
    }

    public function test_chunking_splits_long_text_with_overlap(): void
    {
        config()->set('ai_commerce.rag.chunk_size', 50);
        config()->set('ai_commerce.rag.chunk_overlap', 10);

        $chunks = app(EmbedderService::class)->chunk(str_repeat('word ', 60));

        $this->assertGreaterThan(1, count($chunks));
    }
}
