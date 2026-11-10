<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_embeddings', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner (e.g. Product, Page).
            $table->string('embeddable_type');
            $table->unsignedBigInteger('embeddable_id');

            // Which logical chunk of the source this vector describes.
            $table->unsignedInteger('chunk_index')->default(0);
            $table->longText('content')->nullable();
            $table->unsignedInteger('tokens')->nullable();

            // Provider/model used to produce the vector (mixed vectors are unsafe).
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('dimensions')->nullable();

            // Hash of content, used to skip re-embedding unchanged text.
            $table->string('content_hash', 64)->nullable();

            // Portable JSON copy of the vector. Always populated so the "json"
            // driver works on MySQL; the native vector column is added below
            // only when the connection supports it.
            $table->json('embedding_json')->nullable();

            $table->timestamps();

            $table->index(['embeddable_type', 'embeddable_id'], 'ai_embeddings_embeddable_index');
            $table->index('content_hash');
        });

        // Add a native vector column + index when the database supports it
        // (Postgres/pgvector or MariaDB >= 11.7). On MySQL this is skipped and
        // the JSON column is used for application-side cosine similarity.
        if ($this->supportsNativeVectors()) {
            Schema::table('ai_embeddings', function (Blueprint $table) {
                $table->vector('embedding', config('ai_commerce.embeddings_dimensions', 1536))->nullable();
            });

            Schema::table('ai_embeddings', function (Blueprint $table) {
                $table->vectorIndex('embedding', 'ai_embeddings_embedding_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_embeddings');
    }

    /**
     * Determine if the default connection can store native vector columns.
     */
    protected function supportsNativeVectors(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'mariadb'], true);
    }
};
