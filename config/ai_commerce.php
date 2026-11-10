<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Commerce Layer
    |--------------------------------------------------------------------------
    |
    | Configuration for the advanced AI layer (RAG, sales agent, MCP, data
    | tuning) built on top of the "laravel/ai" SDK. This file follows the same
    | config-driven module pattern used by "config/security.php": every
    | capability is a toggle, so features can be rolled out ("observe") and
    | then enforced ("enforce") without code changes.
    |
    */

    'enabled' => env('AI_COMMERCE_ENABLED', true),

    /*
    | Observe  : generate + log results, never auto-apply to domain data.
    | Enforce  : results may be applied to domain data (still permission gated).
    */
    'mode' => env('AI_COMMERCE_MODE', 'observe'),

    /*
    |--------------------------------------------------------------------------
    | Provider / Model Selection
    |--------------------------------------------------------------------------
    |
    | All agents resolve their provider and model from here instead of being
    | hard-coded (the legacy OpenAI agent hard-codes "gpt-4o").
    |
    */

    'provider' => env('AI_COMMERCE_PROVIDER', 'openai'),

    'models' => [
        'chat'       => env('AI_COMMERCE_MODEL_CHAT', 'gpt-4o'),
        'fast'       => env('AI_COMMERCE_MODEL_FAST', 'gpt-4o-mini'),
        'embeddings' => env('AI_COMMERCE_MODEL_EMBEDDINGS', 'text-embedding-3-small'),
    ],

    'embeddings_dimensions' => env('AI_COMMERCE_EMBEDDING_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Async AI workloads (embedding backfills, bulk enrichment, reporting)
    | should not run on the request cycle.
    |
    */

    'queue' => [
        'connection' => env('AI_COMMERCE_QUEUE_CONNECTION', null),
        'name'       => env('AI_COMMERCE_QUEUE_NAME', 'ai'),
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG (Retrieval Augmented Generation)
    |--------------------------------------------------------------------------
    |
    | The vector driver is auto-detected:
    |   - native : Postgres/pgvector or MariaDB >= 11.7 vector columns, queried
    |              via Eloquent "whereVectorSimilarTo".
    |   - json   : portable fallback storing vectors as JSON and ranking them
    |              in PHP with cosine similarity (works on MySQL).
    |
    */

    'rag' => [
        'enabled'        => env('AI_COMMERCE_RAG_ENABLED', true),
        'driver'         => env('AI_COMMERCE_RAG_DRIVER', 'auto'), // auto|native|json
        'table'          => 'ai_embeddings',
        'min_similarity' => 0.5,
        'limit'          => env('AI_COMMERCE_RAG_LIMIT', 10),
        'chunk_size'     => env('AI_COMMERCE_RAG_CHUNK_SIZE', 800),
        'chunk_overlap'  => env('AI_COMMERCE_RAG_CHUNK_OVERLAP', 100),
        'sources' => [
            'product' => ['enabled' => true, 'model' => \App\Models\Product::class],
            'page'    => ['enabled' => true, 'model' => \App\Models\Page::class],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools
    |--------------------------------------------------------------------------
    |
    | Per-tool enablement. Write tools are additionally approval gated and are
    | disabled by default so v1 of the sales agent is read-only.
    |
    */

    'tools' => [
        'product_search'       => true,
        'product_details'      => true,
        'product_availability' => true,
        'order_status'         => true,
        'coupon_check'         => true,
        'shipping_estimate'    => true,
        'policy_lookup'        => true,
        'recommendation'       => true,

        // Write tools (approval gated, opt-in).
        'create_order_draft'   => env('AI_COMMERCE_WRITE_TOOLS', false),
        'apply_coupon'         => env('AI_COMMERCE_WRITE_TOOLS', false),
        'issue_refund'         => env('AI_COMMERCE_WRITE_TOOLS', false),
    ],

    'limits' => [
        'max_steps'  => env('AI_COMMERCE_MAX_STEPS', 8),
        'timeout'    => env('AI_COMMERCE_TIMEOUT', 60),
        'max_tokens' => env('AI_COMMERCE_MAX_TOKENS', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaming
    |--------------------------------------------------------------------------
    */

    'streaming' => [
        'enabled'  => env('AI_COMMERCE_STREAMING', true),
        'protocol' => env('AI_COMMERCE_STREAM_PROTOCOL', 'vercel'), // vercel|agui
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | v1 defaults to images only; documents/audio are opt-in.
    |
    */

    'attachments' => [
        'enabled'     => env('AI_COMMERCE_ATTACHMENTS', true),
        'images'      => true,
        'documents'   => env('AI_COMMERCE_ATTACHMENTS_DOCUMENTS', false),
        'audio'       => env('AI_COMMERCE_ATTACHMENTS_AUDIO', false),
        'max_size_kb' => env('AI_COMMERCE_ATTACHMENT_MAX_KB', 5120),
        'mimes'       => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Tools
    |--------------------------------------------------------------------------
    |
    | Server-side tools executed by the provider. WebSearch is billed per call
    | and sends queries off-site, so it is disabled by default.
    |
    */

    'provider_tools' => [
        'web_search'     => env('AI_COMMERCE_WEB_SEARCH', false),
        'web_fetch'      => env('AI_COMMERCE_WEB_FETCH', false),
        'file_search'    => env('AI_COMMERCE_FILE_SEARCH', true),
        'code_execution' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Gating
    |--------------------------------------------------------------------------
    |
    | Mirrors the existing triple gate in App\Services\AiUsageService.
    |
    */

    'gate' => [
        'permission'       => 'ai-assistant',
        'admin_permission' => 'settings',
    ],

];
