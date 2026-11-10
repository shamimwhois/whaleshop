# Advanced AI Layer (RAG, Sales Agent, Tools, MCP)

> Technical specification for the **advanced AI layer** built on the `laravel/ai` SDK (v1.3).
> It adds retrieval-augmented generation (RAG), a grounded sales agent, a typed tool layer and
> hooks for provider tools, streaming, async jobs, attachments and a future MCP server.
>
> Source of truth: written by reading the actual code in `D:\laravel\hrmobilerepair` and the
> installed SDK in `vendor/laravel/ai`. Every class, path and SDK method below was verified.
>
> This document complements `docs/Ai Agent & asistance.md` (the legacy admin AI helper).

---

## Table of Contents

1. [Overview & Goals](#1-overview--goals)
2. [Verified SDK & Platform Facts](#2-verified-sdk--platform-facts)
3. [Architecture](#3-architecture)
4. [The 8 SDK Surfaces Mapping](#4-the-8-sdk-surfaces-mapping)
5. [RAG Layer](#5-rag-layer)
6. [Tools Layer](#6-tools-layer)
7. [Agent Layer](#7-agent-layer)
8. [HTTP Surface & Gating](#8-http-surface--gating)
9. [Configuration Reference](#9-configuration-reference)
10. [Data Model](#10-data-model)
11. [Runbook](#11-runbook)
12. [Roadmap — Deferred Phases](#12-roadmap--deferred-phases)
13. [Environment Findings & Caveats](#13-environment-findings--caveats)
14. [Appendix — File Inventory](#14-appendix--file-inventory)

---

## 1. Overview & Goals

The legacy assistant generates product copy and runs a chat sidebar using a hard-coded `gpt-4o`
call. This layer adds the capabilities requested for the "advanced AI" workstream:

| Goal | Delivered by |
| --- | --- |
| **RAG** over products/pages | `App\Services\Ai\Rag\*` + `ai_embeddings` table |
| **Sales agent** | `App\Ai\Agents\CommerceAgent` |
| **Tools** | `App\Ai\Tools\*` + `App\Ai\ToolRegistry` |
| **MCP** (deferred) | Provider/tool seams designed for `laravel/mcp` |
| **Data tuning** | Config-driven models, chunking, similarity thresholds |

Design principles, matching the existing `app/Security/*` module pattern:

1. **Config-driven** — every capability is a toggle in `config/ai_commerce.php`; nothing is hard-coded.
2. **Portable then native** — works on MySQL/SQLite today (JSON vectors), upgrades to native pgvector
   automatically when the connection supports it.
3. **Gated** — reuses the existing triple gate (`site_default_ai_agent` + active agent + `ai-assistant`).
4. **Verified** — `tests/Feature/RagSearchTest.php` proves the pipeline with faked embeddings.

---

## 2. Verified SDK & Platform Facts

| Fact | Evidence |
| --- | --- |
| `laravel/ai` **v1.3 is installed** | `composer.json`: `"laravel/ai": "^1.3"` |
| `laravel/mcp` is **not installed** | absent from `composer.json` |
| Laravel **13.40.0**, PHP **8.5.0** | `php artisan --version`, `php -v` |
| Native vector support exists in core | `Illuminate\Database\Query\Builder::whereVectorSimilarTo()`, `Blueprint::vector()`, `Casts\AsVector` |
| Vector distance is **Postgres/MariaDB only** | `Builder::ensureConnectionSupportsVectors()` throws `RuntimeException` otherwise; `Grammar::supportsVectorDistance()` is `false` by default |
| `whereVectorSimilarTo` lives in **core**, not `laravel/ai` | only referenced by `Laravel\Ai\Tools\SimilaritySearch` |
| `agent_conversations` migration exists | `database/migrations/2026_11_10_041115_...` |
| **No `pdo_pgsql` in CLI PHP** | `PDO::getAvailableDrivers()` -> `mysql`, `sqlite` |

### The vector-driver decision (important)

Because distance queries require Postgres/pgvector or MariaDB >= 11.7, the RAG layer ships **two
drivers** behind one interface:

- `NativeVectorDriver` — uses `whereVectorSimilarTo` (Postgres/pgvector, MariaDB).
- `JsonVectorDriver` — stores vectors as JSON, ranks in PHP with cosine similarity (works on MySQL/SQLite).

`VectorDriverFactory` auto-selects: native when `supportsVectorDistance()` **and** the `embedding`
column exist, otherwise JSON. The migration only adds the native column on `pgsql`/`mariadb`.

---

## 3. Architecture

```
HTTP  api/admin/ai-commerce/{status,chat,stream}
        |
        v
CommerceAiController --> CommerceAgentService --> CommerceAgent (laravel/ai Agent)
        |                        |                        |
        |                        |                        +-- RemembersConversations (agent_conversations)
        |                        |                        +-- ToolRegistry.enabled()
        |                        |                                  |
        |                        |        +-------------------------+-------------------------+
        |                        |        v                         v                         v
        |                        |   ProductSearch            ProductDetails            PolicyLookup
        |                        |   ProductAvailability      OrderStatus               CouponCheck
        |                        |        |
        |                        |        v
        |                        |   RagSearchService --> EmbedderService --> Laravel\Ai\Embeddings
        |                        |        |                        |
        |                        |        v                        v
        |                        |   VectorDriverFactory --> ai_embeddings (embedding_json | embedding vector)
        v                        v
AiUsageService (triple gate)   config/ai_commerce.php
```

---

## 4. The 8 SDK Surfaces Mapping

| # | Surface | Implementation | Status |
| --- | --- | --- | --- |
| 1 | **Prompts** | `CommerceAgent::instructions()`, tools' `description()`/`schema()` | Done |
| 2 | **Context** | `RemembersConversations` + ownership check in `CommerceAgentService` | Done |
| 3 | **Tools** | `App\Ai\Tools\*`, `ToolRegistry` | Done |
| 4 | **Provider Tools** | `config/ai_commerce.provider_tools` (WebSearch off, FileSearch on) | Config ready |
| 5 | **Structured Output** | `HasStructuredOutput` seam (structured agent) | Deferred |
| 6 | **Streaming** | `CommerceAiController@stream` via `usingVercelDataProtocol()` | Done |
| 7 | **Async** | Queue config + `Embeddings::queue()` / `prompt()->queue()` | Config ready |
| 8 | **Attachments** | `CommerceAiRequest` validation + `attachmentPaths()` | Done (images) |

---

## 5. RAG Layer

### 5.1 Ingestion

```bash
php artisan ai:embed products                 # all products
php artisan ai:embed products --id=42          # one product
php artisan ai:embed products --limit=500      # bounded backfill
php artisan ai:embed products --force          # re-embed even if unchanged
```

`AiEmbedCommand` streams records with `chunkById`, skips unchanged content via `content_hash`, and
reports `processed / skipped / vectors_written`.

### 5.2 Chunking & storage

`EmbedderService::chunk()` splits on `ai_commerce.rag.chunk_size` with `chunk_overlap`. Each chunk
is stored in `ai_embeddings` with its vector in `embedding_json` (always) and in `embedding`
(native connections only).

### 5.3 Retrieval

```php
$rag = app(App\Services\Ai\Rag\RagSearchService::class);

$rag->search('iphone battery');               // Collection of {embedding, similarity}
$rag->toContext('iphone battery');            // array of sanitized rows
echo $rag->toPromptContext('iphone battery'); // ready-to-inject prompt block
```

---

## 6. Tools Layer

| Tool | Type | Gate key | Notes |
| --- | --- | --- | --- |
| `ProductSearch` | read | `tools.product_search` | RAG semantic search |
| `ProductDetails` | read | `tools.product_details` | single product, JSON |
| `ProductAvailability` | read | `tools.product_availability` | purchase flags |
| `OrderStatus` | read | `tools.order_status` | **ownership enforced** (own orders only) |
| `CouponCheck` | read | `tools.coupon_check` | validates, never applies |
| `PolicyLookup` | read | `tools.policy_lookup` | CMS pages |

Write tools (`create_order_draft`, `apply_coupon`, `issue_refund`) are **defined in config but
disabled by default** and must be approval-gated (`Approvable`) before enabling.

---

## 7. Agent Layer

`App\Ai\Agents\CommerceAgent` implements `Agent, Conversational, HasTools` and uses `Promptable`.
It is deliberately **not** `HasStructuredOutput`, so free-form chat is unconstrained; a separate
structured agent handles machine-readable extraction.

Provider/model resolve from config, not hard-coded:

```php
$agent->prompt($message, provider: config('ai_commerce.provider'), model: config('ai_commerce.models.chat'));
```

---

## 8. HTTP Surface & Gating

| Method | Route | Action |
| --- | --- | --- |
| `GET` | `api/admin/ai-commerce/status` | availability + enabled tools |
| `POST` | `api/admin/ai-commerce/chat` | single turn |
| `POST` | `api/admin/ai-commerce/stream` | streamed turn |

Gating: `CommerceAgentService::allowed()` -> `AiUsageService::checking()` (the existing triple gate).
The `chat`/`stream` routes additionally require `permission:ai-assistant`.

Conversation continuation verifies ownership (`ownsConversation`) so a customer cannot resume
another customer's conversation by id.

---

## 9. Configuration Reference

All keys live in `config/ai_commerce.php` and are overridable via env:

| Key | Env | Default |
| --- | --- | --- |
| `enabled` | `AI_COMMERCE_ENABLED` | `true` |
| `mode` | `AI_COMMERCE_MODE` | `observe` |
| `provider` | `AI_COMMERCE_PROVIDER` | `openai` |
| `models.chat` | `AI_COMMERCE_MODEL_CHAT` | `gpt-4o` |
| `models.embeddings` | `AI_COMMERCE_MODEL_EMBEDDINGS` | `text-embedding-3-small` |
| `rag.driver` | `AI_COMMERCE_RAG_DRIVER` | `auto` |
| `rag.limit` | `AI_COMMERCE_RAG_LIMIT` | `10` |
| `streaming.protocol` | `AI_COMMERCE_STREAM_PROTOCOL` | `vercel` |
| `provider_tools.web_search` | `AI_COMMERCE_WEB_SEARCH` | `false` |
| `provider_tools.file_search` | `AI_COMMERCE_FILE_SEARCH` | `true` |

---

## 10. Data Model

### `ai_embeddings`

| Column | Type | Purpose |
| --- | --- | --- |
| `id` | bigint PK | |
| `embeddable_type` / `embeddable_id` | morph | source model |
| `chunk_index` | int | chunk ordinal |
| `content` | longtext | chunk text |
| `tokens` | int | approx token count |
| `provider` / `model` / `dimensions` | | embedding provenance |
| `content_hash` | char(64) | skip-unchanged key |
| `embedding_json` | json | **always** populated |
| `embedding` | vector (native only) | pgvector/MariaDB column |

Indexes: `(embeddable_type, embeddable_id)`, `content_hash`, plus a vector index on native connections.

---

## 11. Runbook

```bash
# 1. Run migrations (adds ai_embeddings)
php artisan migrate

# 2. Backfill embeddings (requires an embeddings-capable provider key)
php artisan ai:embed products --limit=200

# 3. (Optional) run the async worker once queue is switched off "sync"
php artisan queue:work --queue=ai
```

Verify: `php artisan test tests/Feature/RagSearchTest.php`

---

## 12. Roadmap — Deferred Phases

| Phase | Item | Prerequisite |
| --- | --- | --- |
| D | Structured output agent (`HasStructuredOutput`) | — |
| D | Provider tools wiring (`WebSearch`/`FileSearch`) | provider support |
| E | `composer require laravel/mcp ^1.0` + MCP server | **explicit approval** |
| F | Async jobs (`Embeddings::queue`, `prompt()->queue`) | queue driver `database`/redis |
| G | Write tools + approvals (`Approvable`) | product decision |
| H | Attachments: documents/audio | storage + cost review |
| I | Cross-cutting: usage/cost tracking, redaction | — |

---

## 13. Environment Findings & Caveats

1. **Process-level env overrides.** This shell injects `DB_CONNECTION=pgsql`, `DB_DATABASE=software_factory`,
   `REDIS_*`, `QUEUE_CONNECTION=redis`, etc. These **override `.env` and `phpunit.xml`**, so tests must
   neutralise them or use `--env=testing`.
2. **No `pdo_pgsql` in CLI PHP.** Postgres listens on `127.0.0.1:5432`, but the CLI lacks the driver, so
   native vector queries cannot be exercised here. Install `pdo_pgsql` + `pgvector` to activate
   `NativeVectorDriver`.
3. **`QUEUE_CONNECTION=sync`.** Async surfaces stay inert until switched; the config is already wired.
4. **MySQL vs Postgres.** `.env` says MySQL; the process env says Postgres. **MySQL cannot run vector
   distance queries** — on MySQL the JSON driver is used (correct, but O(n) PHP ranking).

Test command that works in this environment:

```powershell
$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; $env:DB_HOST=''; $env:DB_PORT='';
$env:DB_USERNAME=''; $env:DB_PASSWORD=''; $env:REDIS_CLIENT=''; $env:REDIS_PASSWORD='';
$env:CACHE_STORE='array'; $env:SESSION_DRIVER='array'; $env:QUEUE_CONNECTION='sync';
php artisan test
```

---

## 14. Appendix — File Inventory

**Created**

| Path | Purpose |
| --- | --- |
| `config/ai_commerce.php` | central config |
| `database/migrations/2026_11_10_050000_create_ai_embeddings_table.php` | storage (native-aware) |
| `app/Models/AiEmbedding.php` | vector row |
| `app/Models/Concerns/HasAiEmbeddings.php` | source-model trait |
| `app/Services/Ai/Rag/VectorDriver.php` | driver contract |
| `app/Services/Ai/Rag/JsonVectorDriver.php` | portable cosine driver |
| `app/Services/Ai/Rag/NativeVectorDriver.php` | native vector driver |
| `app/Services/Ai/Rag/VectorDriverFactory.php` | auto-selection |
| `app/Services/Ai/Rag/EmbedderService.php` | chunk + embed + persist |
| `app/Services/Ai/Rag/RagSearchService.php` | retrieval entry point |
| `app/Services/Ai/CommerceAgentService.php` | agent orchestration + gating |
| `app/Ai/Agents/CommerceAgent.php` | sales agent |
| `app/Ai/ToolRegistry.php` | config-driven tool set |
| `app/Ai/Tools/*.php` | 6 read tools |
| `app/Console/Commands/AiEmbedCommand.php` | `ai:embed` |
| `app/Providers/AiCommerceServiceProvider.php` | container wiring |
| `app/Http/Controllers/Admin/CommerceAiController.php` | HTTP surface |
| `app/Http/Requests/CommerceAiRequest.php` | validation |
| `tests/Feature/RagSearchTest.php` | 6 passing tests |

**Modified**

| Path | Change |
| --- | --- |
| `bootstrap/providers.php` | register `AiCommerceServiceProvider` |
| `bootstrap/app.php` | register `AiEmbedCommand` |
| `routes/api.php` | `ai-commerce` route group |
| `app/Models/Product.php` | `HasAiEmbeddings` + `toEmbeddableText()` |
| `app/Models/User.php` | `HasConversations` |



