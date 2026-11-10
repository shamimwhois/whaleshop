# AI Agent & Assistance

> Technical specification for the **WhaleShop AI Assistant** vertical slice — an admin-panel AI helper that
> generates product names / descriptions / tags and serves an interactive chat sidebar.
>
> Source of truth: this document was written by reading the actual code in
> `D:\laravel\hrmobilerepair`. Every path, line anchor and code excerpt below was verified.

---

## Table of Contents

1. [Overview & Purpose](#1-overview--purpose)
2. [High-Level Architecture](#2-high-level-architecture)
3. [Component & File Map](#3-component--file-map)
4. [Configuration](#4-configuration)
5. [Data Model](#5-data-model)
6. [API Reference](#6-api-reference)
7. [Service Layer & Extensibility](#7-service-layer--extensibility)
8. [Prompt Engineering (`HasAiPrompt`)](#8-prompt-engineering-hasaiprompt)
9. [Permissions & Gating](#9-permissions--gating)
10. [Frontend Behaviour](#10-frontend-behaviour)
11. [Setup / Activation Runbook](#11-setup--activation-runbook)
12. [Troubleshooting](#12-troubleshooting)
13. [Assumptions & Limitations](#13-assumptions--limitations)
14. [Appendix — File Inventory](#14-appendix--file-inventory)

---

## 1. Overview & Purpose

The **AI Assistant** is a self-contained vertical slice of the admin panel that surfaces LLM
capabilities to authenticated staff. It has two distinct capabilities:

| Capability | Entry point | Purpose |
| --- | --- | --- |
| **Product copywriting helpers** | `POST admin/ai/name`, `/description`, `/tags` | Rewrite/generate a product **name**, an HTML **description**, and a **JSON tag list** — always in the site's default language. |
| **Interactive chat assistant** | `POST admin/ai/chat` + `/chat-response/{id}` | A persisted chat drawer ("AI Helper") mounted in the backend shell, with per-user history. |

**Settings capability** — a separate module (`admin/setting/ai-agent`) lets an administrator enable/disable
the provider and manage its credentials (`openai_api_key`, `openai_status`).

**Key design characteristics** (all verified in code):

- **Provider-agnostic factory** — `AiService::agent($slug)` resolves a concrete agent class by convention
  (`ucfirst($slug)` → `App\Http\AiAgents\Agents\{Name}`), so adding a provider is a drop-in class + validator + seeder row.
- **DB-driven credentials** — API keys/status live in `ai_agents` + `gateway_options` (a polymorphic options table),
  **not** only in `.env`. `DEMO` mode falls back to `env('OPENAI_API_KEY')`.
- **Usage gate** — access is triple-gated: a default agent must be configured, the agent must be active, and the
  user must hold the `ai-assistant` permission.
- **Strict output contracts** — prompts force a single language and a strict format (plain text / HTML / JSON array),
  with `INVALID_INPUT` as the refusal sentinel.



---

## 2. High-Level Architecture

```text
+------------------------------- FRONTEND (Vue 3 + Vuex) --------------------------------+
|  BackendAiSidebarComponent.vue  (chat drawer, mounted in DefaultComponent.vue L23)      |
|      +-- Vuex module  store/modules/ai.js                                               |
|            fetchStatus / fetchName / fetchDescription / fetchTags / sendChatMessage      |
|            setChatResponse / fetchChatHistory                                            |
|  AiAgentComponent.vue           (settings form -> Vuex module store/modules/aiAgent.js)  |
+------------------------------------------+---------------------------------------------+
                                           |  axios  (/admin/...)
+------------------------------------------v---------------------------------------------+
| ROUTES  routes/api.php                                                                  |
|   L397-400  admin/setting/ai-agent  -> AiAgentController (permission:settings)           |
|   L688-696             admin/ai/*   -> AiController      (permission:ai-assistant)       |
+------------------------------------------+---------------------------------------------+
                                           |
+------------------------------------------v---------------------------------------------+
| CONTROLLERS  app/Http/Controllers/Admin/                                                |
|   AiController      -> status | name | description | tags | chat | chatResponse | history |
|   AiAgentController -> index | update                                                    |
+------------------------------------------+---------------------------------------------+
                                           |
+------------------------------------------v---------------------------------------------+
| SERVICES  app/Services/                                                                 |
|   AiService            -> factory: agent($slug) -> name|description|tags|message|status  |
|   AiAbstract           -> abstract contract (status/name/description/tags/message)        |
|   AiUsageService       -> checking(): default agent + active + permission gate            |
|   AiChatHistoryService -> list() / store() / update()                                     |
|   AiAgentService       -> list() / update()   (settings persistence)                      |
+------------------------------------------+---------------------------------------------+
                                           |
+------------------------------------------v---------------------------------------------+
| AGENT  app/Http/AiAgents/Agents/Openai.php   (model: gpt-4o, OpenAI responses() API)     |
|        uses trait  app/Traits/HasAiPrompt.php  (prompt builders + language loader)       |
+------------------------------------------+---------------------------------------------+
                                           |  HTTPS
                                   +-------v--------+
                                   |   OpenAI API   |
                                   +----------------+

PERSISTENCE
  ai_agents            <- provider registry (name, slug, misc, status)
  gateway_options      <- polymorphic options (openai_api_key, openai_status)
                          model_type = App\Models\AiAgent
  ai_chat_histories    <- per-user chat transcript (user_id, ai_agent_id, message, response)
  agent_conversations  <- provided by the `laravel/ai` package migration (see section 13)
```

**Request lifecycle (chat example):**

1. `BackendAiSidebarComponent.sendMessage()` -> `dispatch('ai/sendChatMessage', text)`.
2. `axios.post('/admin/ai/chat', { name: text })` -> `AiController::chat`.
3. Middleware `permission:ai-assistant` must pass; then `AiService->agent($slug)->status()` must be `true`.
4. `AiChatHistoryService::store()` writes a row and returns it -> `AiChatResource`.
5. Frontend pushes the message bubble, sets `isTyping = true`, then calls `/admin/ai/chat-response/{id}`.
6. `AiController::chatResponse` -> `AiService->message(AiRequest)` -> OpenAI `responses()->create([...])`.
7. `AiChatHistoryService::update()` stores `response` (only if empty) -> bubble rendered via `v-html`.

---


## 3. Component & File Map

### 3.1 Backend

| Layer | File (relative to project root) | Notes |
| --- | --- | --- |
| Config | `config/ai.php` | `default => 'openai'`; `default_for_images => 'gemini'`; provider blocks incl. `openai` (`env('OPENAI_API_KEY')`). |
| Models | `app/Models/AiAgent.php` | `gatewayOptions(): MorphMany` -> `GatewayOption`; `fillable = name, slug, misc, status`. |
| | `app/Models/AiChatHistory.php` | `fillable = user_id, ai_agent_id, message, response`; `user(): BelongsTo`. |
| | `app/Models/GatewayOption.php` | Shared polymorphic options (also used by payment / SMS gateways). |
| Trait | `app/Traits/HasAiPrompt.php` | `loadDefaultLanguage()`, `buildProductNamePrompt()`, `buildProductDescriptionPrompt()`, `buildProductTagsPrompt()`. |
| Contracts | `app/Services/AiAbstract.php` | `abstract status(): bool`, plus `name()`, `description()`, `tags()`, `message()`. |
| Services | `app/Services/AiService.php` | `.agent($slug, ...$args)` factory + passthrough `status/name/description/tags/message`. |
| | `app/Services/AiUsageService.php` | `checking()` gate (see section 9). |
| | `app/Services/AiChatHistoryService.php` | `list()`, `store(AiRequest)`, `update(AiChatHistory, $response)`. |
| | `app/Services/AiAgentService.php` | `list(PaginateRequest)`, `update(array $validationRequests)`. |
| Agents | `app/Http/AiAgents/Agents/Openai.php` | `gpt-4o` via `$this->agent->responses()->create([...])`. |
| Requests | `app/Http/Requests/AiRequest.php` | `name` -> `['required','string','max:5000']`. |
| | `app/Http/AiAgents/Requests/Openai.php` | Dynamic rules for `openai_api_key` / `openai_status` keyed on `Activity::ENABLE`. |
| Controllers | `app/Http/Controllers/Admin/AiController.php` | 7 actions; middleware `permission:ai-assistant` (L42). |
| | `app/Http/Controllers/Admin/AiAgentController.php` | 2 actions; middleware `permission:settings` (L27). |
| Resources | `app/Http/Resources/AiChatResource.php` | `id`, `message`, `response`. |
| | `app/Http/Resources/AiAgentResource.php` | `id`, `name`, `slug`, `status`, `options` (`GatewayOptionsResource::collection`). |
| Routes | `routes/api.php` | L397-400 (settings), L688-696 (AI operations). |
| Enums | `app/Enums/{Status,Activity,InputType}.php` | Provider status, enable/disable, input widget type. |

### 3.2 Frontend

| Layer | File | Notes |
| --- | --- | --- |
| Chat drawer | `resources/js/components/layouts/backend/BackendAiSidebarComponent.vue` | Renders `v-if="status"`; toggled via `useCanvas().openCanvas('ai-sidebar')`; loads history on `mounted()`. |
| App shell | `resources/js/components/DefaultComponent.vue` | Import L44, register L59, mount L23 inside `<main class="db-main" v-if="logged">`. |
| Settings UI | `resources/js/components/admin/settings/AiAgent/AiAgentComponent.vue` | Tabbed provider form; hidden `ai_agent_type` drives dynamic validation. |
| Store (chat) | `resources/js/store/modules/ai.js` | `namespaced: true`; state `{ status, name, description, chatHistory, usageLimit }`. |
| Store (settings) | `resources/js/store/modules/aiAgent.js` | `namespaced: true`; `lists()`, `fetch()`, `save()`. |
| Router | `resources/js/router/modules/settingRoutes.js` | `import AiAgentComponent` (L1); route `admin.settings.ai-agent` under `admin/settings`. |
| i18n | `resources/js/languages/{en,ar,bn}.json` | Keys: `ai`, `ai_assistant`, `openai_api_key`, `openai_status`. |

---

## 4. Configuration

### 4.1 `config/ai.php` defaults

```php
'default'                    => 'openai',
'default_for_images'         => 'gemini',
'default_for_audio'          => 'openai',
'default_for_transcription'  => 'openai',
'default_for_embeddings'     => 'openai',
'default_for_reranking'      => 'cohere',
'default_for_classification' => 'typesafe',
```

The `openai` provider block reads `env('OPENAI_API_KEY')`, `env('OPENAI_URL', 'https://api.openai.com/v1')`
and `env('OPENAI_STORE', true)`.

> **Important:** the chat / product-copy flow in this project does **not** use the `laravel/ai` facade.
> It uses `App\Services\AiService` + `App\Http\AiAgents\Agents\Openai`, which talk to the
> `openai-php/client` SDK directly. `config/ai.php` and the package service provider are present
> (the package is a `composer.json` dependency) but only the `agent_conversations` migration is used from it.

### 4.2 Environment variables

| Variable | Used by | Purpose |
| --- | --- | --- |
| `DEMO` | `Agents\Openai::__construct()` | When truthy, the client is built from `env('OPENAI_API_KEY')` instead of the DB value. |
| `OPENAI_API_KEY` | `DEMO` mode + `config/ai.php` | Demo / global API key. |
| `OPENAI_URL`, `OPENAI_STORE` | `config/ai.php` | Endpoint and OpenAI storage flag. |
| `GEMINI_API_KEY`, `AZURE_OPENAI_*`, `XAI_API_KEY`, `VOYAGEAI_API_KEY` | `config/ai.php` | Present in config for other providers; **no agent class implements them today**. |

Seeded demo values live in `database/seeders/AiAgentDataTableSeeder.php` (a placeholder key, applied only when `DEMO`).

### 4.3 Runtime settings and records

| Item | Where | Default |
| --- | --- | --- |
| `site_default_ai_agent` | `site` settings group (`SiteTableSeeder.php` L29, `SiteTableSeederVersionTwo.php` L18) | `$envService->getValue('DEMO') ? 1 : 0` |
| `AiAgent` row | `AiAgentTableSeeder::run()` | `slug = 'openai'`, `status = Status::INACTIVE`, created via `firstOrCreate`. |
| `GatewayOption` rows | `AiAgentTableSeeder::gatewayOption()` | `openai_api_key` (`InputType::TEXT`), `openai_status` (`InputType::SELECT`, value `Activity::DISABLE`). |
| `ai-assistant` permission | `database/seeders/PermissionTableSeeder.php` | `name => 'ai-assistant'`, `url => 'ai-assistant'`. |

### 4.4 Composer / npm dependencies

| Package | Version | Role |
| --- | --- | --- |
| `laravel/ai` | `^1.3` | Framework integration; in this repo it supplies the `agent_conversations` migration. |
| `openai-php/client` | `^0.19.1` | The actual HTTP client used by `Agents\Openai`. |

---


## 5. Data Model

### 5.1 `ai_agents`

Created by `database/migrations/2026_04_08_144553_create_ai_agents_table.php`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `name` | string | Human label, e.g. `Open AI`. |
| `slug` | string | Provider key, e.g. `openai`; resolved to the agent class via `ucfirst`. |
| `misc` | text nullable | Reserved metadata. |
| `status` | tinyint | `App\Enums\Status` (`ACTIVE` / `INACTIVE`). |
| `created_at`, `updated_at` | timestamps | |

**Relationships**

- `AiAgent::gatewayOptions()` — `MorphMany(GatewayOption::class, 'model')`.

### 5.2 `gateway_options` (polymorphic)

Shared options table; for AI the polymorphic `model_type` is `App\Models\AiAgent`.

| Column | Notes |
| --- | --- |
| `model_type`, `model_id` | Polymorphic owner (`App\Models\AiAgent`). |
| `option` | Option key, e.g. `openai_api_key`, `openai_status`. |
| `value` | Option value (API key string or `App\Enums\Activity` value). |
| `type` | `App\Enums\InputType` — widget used by the settings UI (`TEXT`, `SELECT`, ...). |

### 5.3 `ai_chat_histories`

Created by `database/migrations/2026_04_08_163521_create_ai_chat_histories_table.php`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | Used as the `/chat-response/{id}` route parameter. |
| `user_id` | FK -> `users` | Scopes history per staff member. |
| `ai_agent_id` | FK -> `ai_agents` | Which provider answered. |
| `message` | text | The user prompt. |
| `response` | text nullable | Filled asynchronously by `chatResponse`. |
| `created_at`, `updated_at` | timestamps | History ordering. |

**Relationships**

- `AiChatHistory::user()` — `BelongsTo(User::class)`.

### 5.4 `agent_conversations`

Created by `database/migrations/2026_11_10_041115_create_agent_conversations_table.php`, which extends
`Laravel\Ai\Migrations\AiMigration`. This table is provided by the `laravel/ai` package and is **not** used by
the custom chat flow above (which persists to `ai_chat_histories`) — see section 13.

---


## 6. API Reference

All routes live in the `admin` group of `routes/api.php` and therefore require `auth` (Sanctum) plus
the route-level permission middleware described in section 9.

### 6.1 AI operations — `prefix('ai')`, `name('ai.')` (`routes/api.php` L688-696)

| Method | URI | Controller action | Permission | Request payload | Response |
| --- | --- | --- | --- | --- | --- |
| `GET` | `/admin/ai/status` | `AiController::status` | `ai-assistant` | — | `{ status: bool, usage_limit: ... }` |
| `POST` | `/admin/ai/name` | `AiController::name` | `ai-assistant` | `{ name: string(max 5000) }` | `{ name: string, status: ..., message: ... }` |
| `POST` | `/admin/ai/description` | `AiController::description` | `ai-assistant` | `{ name: string(max 5000) }` | `{ description: html, status: ..., message: ... }` |
| `POST` | `/admin/ai/tags` | `AiController::tags` | `ai-assistant` | `{ name: string(max 5000) }` | `{ tags: array, status: ..., message: ... }` |
| `POST` | `/admin/ai/chat` | `AiController::chat` | `ai-assistant` | `{ name: string(max 5000) }` | `AiChatResource` (new history row) |
| `POST` | `/admin/ai/chat-response/{aiChatHistory}` | `AiController::chatResponse` | `ai-assistant` | route model binding `aiChatHistory` | `AiChatResource` with `response` populated |
| `GET` | `/admin/ai/chat-history` | `AiController::chatHistory` | `ai-assistant` | — | `AiChatResource` collection |

Validation is delegated to `App\Http\Requests\AiRequest`, whose only rule is:

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:5000'],
    ];
}
```

> The field is named `name` for all five POST endpoints — even for chat, the user's message text is sent
> as `name`. This is intentional (one validator for every action) and matters when integrating from the frontend.

### 6.2 AI agent settings — `prefix('ai-agent')`, `name('ai-agent.')` (`routes/api.php` L397-400)

Nested inside the `admin/setting` prefix group, so the full URIs are `admin/setting/ai-agent/...`.

| Method | URI | Controller action | Permission | Request payload | Response |
| --- | --- | --- | --- | --- | --- |
| `GET` | `/admin/setting/ai-agent` | `AiAgentController::index` | `settings` | — | `AiAgentResource` collection |
| `POST` | `/admin/setting/ai-agent/update` | `AiAgentController::update` | `settings` | `{ ai_agent_type, openai_api_key, openai_status }` | `{ status, message }` |

`AiAgentController::update` routes the payload through `App\Http\AiAgents\Requests\Openai`, which builds its
rule set dynamically — `openai_api_key` is only `required` when `openai_status === Activity::ENABLE`:

```php
// app/Http/AiAgents/Requests/Openai.php (rules shape)
if (request('openai_status') == Activity::ENABLE) {
    return [ 'openai_api_key' => ['required', ...rules], 'openai_status' => [...] ];
}
return [ 'openai_api_key' => ['nullable', ...rules], 'openai_status' => [...] ];
```

The `ai_agent_type` hidden field selects which provider request class validates the submission, which is the
extension seam for adding providers (section 7).

### 6.3 Resource shapes

```php
// app/Http/Resources/AiChatResource.php
[
    'id'       => $this->id,
    'message'  => $this->message,
    'response' => $this->response,
]

// app/Http/Resources/AiAgentResource.php
[
    'id'      => $this->id,
    'name'    => $this->name,
    'slug'    => $this->slug,
    'status'  => $this->status,
    'options' => GatewayOptionsResource::collection($this->gatewayOptions),
]
```

---


## 7. Service Layer & Extensibility

### 7.1 `AiService` — the provider factory

`app/Services/AiService.php` resolves an agent class by *convention over configuration*:

```php
public function agent(string $slug, ...$args): AiAbstract
{
    $instance = 'App\\Http\\AiAgents\\Agents\\' . ucfirst($slug);
    return new $instance(...$args);
}
```

It also exposes thin passthrough helpers so controllers never touch the agent directly:

| Method | Delegates to |
| --- | --- |
| `status()` | `agent($slug)->status()` |
| `name(AiRequest $request)` | `agent($slug)->name($request)` |
| `description(AiRequest $request)` | `agent($slug)->description($request)` |
| `tags(AiRequest $request)` | `agent($slug)->tags($request)` |
| `message(AiRequest $request)` | `agent($slug)->message($request)` |

The `$slug` comes from the `site_default_ai_agent` setting resolved against the `ai_agents` table, which is
why an admin can switch providers without code changes.

### 7.2 `AiAbstract` — the contract every provider must satisfy

```php
abstract class AiAbstract
{
    abstract public function status(): bool;
    abstract public function name(AiRequest $request);
    abstract public function description(AiRequest $request);
    abstract public function tags(AiRequest $request);
    abstract public function message(AiRequest $request);
}
```

Any new provider (Gemini, Claude, ...) must extend this base and be implemented for all five methods.

### 7.3 `AiChatHistoryService`

| Method | Signature | Behaviour |
| --- | --- | --- |
| `list` | `list()` | Returns the authenticated user's history (ordered for the drawer scroll-back). |
| `store` | `store(AiRequest $request)` | Creates a row for the current user + default agent with `message = $request->name`. |
| `update` | `update(AiChatHistory $aiChatHistory, $response)` | Persists the assistant reply into `response`. |

### 7.4 `AiAgentService`

| Method | Signature | Behaviour |
| --- | --- | --- |
| `list` | `list(PaginateRequest $request)` | Returns the provider registry (paged) for the settings screen. |
| `update` | `update(array $validationRequests)` | Persists `openai_api_key` / `openai_status` into `gateway_options` and flips `AiAgent.status`. |

### 7.5 Adding a new provider — checklist

1. Create `app/Http/AiAgents/Agents/{Name}.php` extending `AiAbstract` and implementing all five methods
   (mirror `Agents/Openai.php`; use the `HasAiPrompt` trait for prompts).
2. Create `app/Http/AiAgents/Requests/{Name}.php` extending `FormRequest` with the credential rules for that provider.
3. Add a row in `AiAgentTableSeeder` (`slug => '{name}'`, desired initial `status`) and its `gateway_options`
   entries with the right `InputType`.
4. Seed the matching permission/i18n keys if the settings screen should surface new labels.
5. No controller or route change is required — `AiService::agent($slug)` discovers the class automatically.

---


## 8. Prompt Engineering (`HasAiPrompt`)

`app/Traits/HasAiPrompt.php` centralises prompt construction so every provider agent produces
consistently-shaped, language-correct results.

### 8.1 Language enforcement

```php
public string $langName = 'English';
public string $langCode = 'EN';

public function loadDefaultLanguage(): void
{
    $defaultLanguage = Settings::group('site')->get('site_default_language');
    $language = Language::find($defaultLanguage);
    if ($language) {
        $this->langName = $language->name;          // e.g. "Arabic"
        $this->langCode = strtoupper($language->code); // e.g. "AR"
    }
}
```

`loadDefaultLanguage()` is called by the agent constructor so every prompt interpolates the
**site's default language** (`site_default_language`), not the admin's UI language. Falls back to
`English / EN` when the setting or `Language` row is missing.

### 8.2 The three prompt builders

| Builder | Input | Output contract |
| --- | --- | --- |
| `buildProductNamePrompt(string $name)` | Product name | Plain text, **3-8 words**, no quotes/slogans/punctuation, 100% in `{$langName}`. |
| `buildProductDescriptionPrompt(string $description)` | Product name | Raw **HTML** starting with `<h2><b>{name}</b></h2>`, then intro paragraph, "Key Highlights" paragraphs with `<b>` titles, then "Details & Specifications" as `<ol>/<li>`. Allowed tags only: `<p> <b> <h1> <h2> <ol>/<li><span>`. **No markdown / code fences / JSON wrappers.** |
| `buildProductTagsPrompt(string $name)` | Product name | A **JSON array of 5-7 strings**, each 1-3 words, covering material / style / purpose / category. Nothing else in the output. |

### 8.3 The `INVALID_INPUT` sentinel

Every builder ends with the same guard clause:

> Only process inputs that are actual products, items, or goods. If the input is unrelated or cannot be
> converted into a product title/description/tags, respond with only `INVALID_INPUT`
> (for tags: `["INVALID_INPUT"]`).

This gives the controller a deterministic signal for off-topic prompts (e.g. a user typing "tell me a joke"),
which it maps to a user-facing error instead of injecting junk into the product record. `AiController::tags`
parses the JSON and replaces an `INVALID_INPUT` result with an empty/flagged payload.

### 8.4 Anti-markdown hardening

The description prompt spends four explicit lines forbidding markdown code fences. This is deliberate:
LLMs habitually wrap HTML in ```html fences, which would render literal backticks inside the product page.
Any change to this prompt should preserve those negative constraints.

---


## 9. Permissions & Gating

### 9.1 Permissions used

| Permission | Seeded in | Guards |
| --- | --- | --- |
| `ai-assistant` | `PermissionTableSeeder` | All 7 `admin/ai/*` routes (`AiController` middleware L42). |
| `settings` | `PermissionTableSeeder` | Both `admin/setting/ai-agent` routes (`AiAgentController` middleware L27). |

### 9.2 `AiUsageService::checking()` — the triple gate

```php
public function checking(): bool
{
    try {
        if (Settings::group('site')->get('site_default_ai_agent') > 0
            && $this->aiService->agent($this->agent)->status()) {
            if (Auth::check() && Auth::user()->hasPermissionTo('ai-assistant')) {
                return true;
            }
        }
        return false;
    } catch (Exception $exception) {
        throw new Exception(QueryExceptionLibrary::message($exception), 422);
    }
}
```

Truth table:

| `site_default_ai_agent > 0` | agent `status()` active | user has `ai-assistant` | result |
| --- | --- | --- | --- |
| any | any | no | `false` |
| no | — | yes | `false` |
| yes | no (INACTIVE) | yes | `false` |
| yes | yes | yes | **`true`** |

The constructor resolves `$this->agent` (the slug) from `site_default_ai_agent` → `AiAgent::find(...)->slug`.
Any exception during the check is re-thrown as a HTTP 422 with a human-readable message via
`QueryExceptionLibrary::message()`.

Any `false` result surfaces to the frontend as `GET /admin/ai/status` returning `status: false`, which is why
`BackendAiSidebarComponent` hides itself (`v-if="status"`).

### 9.3 Agent-level status

Independently of the permission check, the agent constructor and `status()` are DB-driven:

```php
// Agents\Openai
$this->aiAgent = AiAgent::with('gatewayOptions')->where(['slug' => 'openai'])->first();

public function status(): bool
{
    return !blank(AiAgent::where(['slug' => 'openai', 'status' => Status::ACTIVE])->first());
}
```

So toggling `openai_status` to *Disable* on the settings screen (or flipping `ai_agents.status` to
`INACTIVE`) immediately turns the whole assistant off for every user.

---


## 10. Frontend Behaviour

### 10.1 Vuex store — `store/modules/ai.js` (`namespaced`)

**State:** `{ status: false, name: null, description: null, chatHistory: [], usageLimit: null }`
**Getters:** `status`, `name`, `description`, `chatHistory`, `usageLimit`
**Mutations:** `status`, `name`, `description`, `chatHistory`

| Action | HTTP call | Commits |
| --- | --- | --- |
| `fetchStatus()` | `GET /admin/ai/status` | `status` ← `res.data.data` |
| `fetchName(payload)` | `POST /admin/ai/name` | `name` ← `res.data.data.response` |
| `fetchDescription(payload)` | `POST /admin/ai/description` | `description` ← `res.data.data.response` |
| `fetchTags(payload)` | `POST /admin/ai/tags` | **none** — the caller consumes `res.data.data` (the tag array). |
| `sendChatMessage(message)` | `POST /admin/ai/chat` `{name: message}` | **none** — the caller reads the created history row from `res.data.data`. |
| `setChatResponse({id, name})` | `POST /admin/ai/chat-response/{id}` | **none** — the caller reads the updated row. |
| `fetchChatHistory()` | `GET /admin/ai/chat-history` | `chatHistory` ← `res.data.data` (only when truthy). |

Because `fetchTags`, `sendChatMessage` and `setChatResponse` deliberately do not commit, the components
own the transient UI state (in-flight bubbles, typing indicator) and treat the store as the source of
truth only for `status`, `name`, `description` and `chatHistory`.

### 10.2 Settings store — `store/modules/aiAgent.js`

`namespaced: true` with `lists()`, `fetch()` and `save()` actions backing
`admin/settings/AiAgent/AiAgentComponent.vue`. `save()` posts `ai_agent_type` alongside the credential fields so
the backend can pick the right dynamic `FormRequest`.

### 10.3 Chat sidebar — `BackendAiSidebarComponent.vue`

- **Mount point:** `DefaultComponent.vue` L23, inside `<main class="db-main" v-if="logged">` — i.e. it exists
  only for authenticated backend users and is *not* part of any individual admin page.
- **Visibility:** root element is `v-if="status"`, driven by `ai/status` (from `GET /admin/ai/status`).
- **Opening:** triggered by `useCanvas().openCanvas('ai-sidebar')` from the navbar/menu; closing calls the
  matching `closeCanvas`.
- **onMounted flow:** `dispatch('ai/fetchStatus')` first, then `dispatch('ai/fetchChatHistory')` — history is
  only meaningful once the assistant is confirmed enabled.
- **Sending a message:**
  1. Push the user bubble locally and set a typing/loading flag.
  2. `dispatch('ai/sendChatMessage', text)` → row `{ id, message, response: null }`.
  3. `dispatch('ai/setChatResponse', { id, name: text })` → row with `response` filled.
  4. Append the assistant bubble; assistant HTML is rendered with `v-html` (hence the strict HTML-only
     prompt contract in section 8).
- **History rendering:** existing rows from `chatHistory` are shown in order, so `response: null` rows render
  as "pending" bubbles after a page reload.

### 10.4 Settings screen — `AiAgentComponent.vue`

- Registered in `settingRoutes.js` (L1) under the `admin/settings` parent (route name `admin.settings.ai-agent`,
  `permissionUrl: 'settings'`).
- Presents the OpenAI credentials as a tabbed form with a hidden `ai_agent_type` field.
- Labels come from i18n keys `ai`, `ai_assistant`, `openai_api_key`, `openai_status` (all three locales).

### 10.5 Product copy helper actions

`ai/fetchName`, `ai/fetchDescription` and `ai/fetchTags` are the public API surface intended for product
forms (add/edit) to wire "Generate with AI" buttons to. They accept a payload of `{ name: <product name> }`
and resolve with the raw axios response, so a product form can:

```js
// name
this.$store.dispatch('ai/fetchName', { name: this.form.name }).then(() => {
  this.form.name = this.$store.getters['ai/name'];
});
// description
this.$store.dispatch('ai/fetchDescription', { name: this.form.name }).then(() => {
  this.form.description = this.$store.getters['ai/description'];
});
// tags (no commit — read the response directly)
this.$store.dispatch('ai/fetchTags', { name: this.form.name }).then((res) => {
  this.form.tags = res.data.data.response;
});
```

> See section 13: in the current tree these three actions are *not* dispatched by any admin component;
> they are shipped as the integration seam for product screens.

---

## 11. Setup / Activation Runbook

### 11.1 Install and migrate

```bash
composer install          # pulls laravel/ai ^1.3 and openai-php/client ^0.19.1
php artisan migrate       # creates ai_agents, ai_chat_histories, agent_conversations
```

### 11.2 Seed the registry

```bash
php artisan db:seed --class=AiAgentTableSeeder      # AiAgent('openai') + gateway_options
php artisan db:seed --class=PermissionTableSeeder   # creates the 'ai-assistant' permission
php artisan db:seed                                 # or run DatabaseSeeder, which calls both
```

`AiAgentTableSeeder` creates the `openai` agent with `status => Status::INACTIVE` and the two gateway options
`openai_api_key` (text) and `openai_status` (select, default `Activity::DISABLE`).

### 11.3 Activate the assistant

1. Log in as an administrator with the `settings` permission.
2. Go to **Settings -> AI Agent** (`/admin/settings/ai-agent`).
3. Paste the OpenAI key into **OpenAI API Key** and set **OpenAI Status** to *Enable*; save.
   — this writes `gateway_options.openai_api_key` / `openai_status` and sets `ai_agents.status = ACTIVE`.
4. Assign the **`ai-assistant`** permission to the roles/users that should see the helper.
5. On **Settings -> Site**, choose a default agent (`site_default_ai_agent`) — required by the usage gate.
6. Reload the admin; `GET /admin/ai/status` should return `status: true` and the AI sidebar becomes visible.

### 11.4 Alternative: demo / env mode

Set `DEMO=true` and `OPENAI_API_KEY=sk-...` in `.env`. In demo mode:

- `Agents\Openai` builds the client from `env('OPENAI_API_KEY')` and ignores the DB key.
- `AiAgentDataTableSeeder` seeds a placeholder key.
- `SiteTableSeeder` sets `site_default_ai_agent = 1` automatically.

### 11.5 Rebuild assets

The sidebar and settings screen are Vue SFCs; after changing them run:

```bash
npm install
npm run dev      # or: npm run build
```

> **Environment caveat (verified in this workspace):** the local PHP CLI here has no `pdo_pgsql` driver
> enabled, so `php artisan migrate`/`db:seed` against the configured PostgreSQL database will fail until the
> extension is installed. This is an environment limitation, not a code issue.

---


## 12. Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `GET /admin/ai/status` returns `status: false`, sidebar hidden | Any of the three gates fails (section 9.2) | Check `site_default_ai_agent > 0`, `ai_agents.status = ACTIVE`, and that the user has `ai-assistant`. |
| Payload contains `agent_is_not_active` | Agent `status()` is `false` at request time | Enable **OpenAI Status** on the settings screen. |
| `403` on `admin/ai/*` | User lacks `ai-assistant` | Assign the permission to the role. |
| `403` on `admin/setting/ai-agent` | User lacks `settings` | Assign the `settings` permission. |
| `The name field is required.` | The POST body omitted `name` | All five POST endpoints expect the text in `name` (section 6.1). |
| `The name field must not be greater than 5000 characters.` | Prompt longer than 5000 chars | Trim the input; the limit lives in `AiRequest`. |
| Response is literally `INVALID_INPUT` (or `["INVALID_INPUT"]`) | The prompt was not product-related | Expected sentinel behaviour (section 8.3); validate input before calling. |
| Product description renders backticks / `html` | A prompt change broke the anti-markdown constraints | Restore the negative constraints in `HasAiPrompt::buildProductDescriptionPrompt()`. |
| 422 with a DB-ish wrapped message | OpenAI SDK exception wrapped by `QueryExceptionLibrary` (bad key, quota, network) | Verify the key and OpenAI account state; the original message is preserved. |
| Descriptions come back in the wrong language | `site_default_language` unset, or `Language` row missing | Set the site default language; the trait falls back to `English / EN`. |
| Settings save reports the key as required | `openai_status` is *Enable*, which makes `openai_api_key` required | Provide a key or switch status to *Disable*. |

---


## 13. Assumptions & Limitations

These are explicit, code-verified caveats rather than defects — read them before extending the subsystem.

1. **Only OpenAI is implemented.** `config/ai.php` lists many providers (gemini, azure, xai, cohere, ...) and
   `default_for_images` is `gemini`, but the only class in `app/Http/AiAgents/Agents/` is `Openai.php`.
   Selecting another provider is not currently possible.
2. **`laravel/ai` is a partial dependency.** Its service provider and config are installed, and its migration
   creates `agent_conversations`, but the chat flow persists to `ai_chat_histories` instead. Treat
   `agent_conversations` as unused scaffolding unless a future change routes through the package facade.
3. **`site_default_ai_agent` is required.** With the setting at `0` (the non-demo default) the whole assistant
   is unreachable even if an agent is active and permissions are granted. Admins must set it explicitly.
4. **Product helper actions are not wired into any screen.** `ai/fetchName`, `ai/fetchDescription` and
   `ai/fetchTags` exist and work, but no admin component currently dispatches them. They are the intended
   integration point for product add/edit forms; wiring them is a frontend-only change (no backend work needed).
5. **The sidebar is global, not per-page.** `BackendAiSidebarComponent` is mounted once in `DefaultComponent.vue`;
   there is no standalone AI route or page. It is opened through the canvas helper.
6. **`fetchTags` does not commit to the store.** Its result must be read from the promise
   (`res.data.data.response`); unlike `name`/`description` there is no `tags` state slot.
7. **`chat-response` writes are guarded.** `AiChatHistoryService::update()` only fills `response` when it is
   empty, so re-requesting a response for an already-answered row will not overwrite it.
8. **The model is hard-coded.** `Agents\Openai` uses `'model' => 'gpt-4o'` in all five methods; changing the
   model means editing the class (or introducing a setting).
9. **No streaming.** Responses are returned synchronously from `responses()->create()`; the UI fakes
   responsiveness with a typing indicator rather than streaming tokens.
10. **Permissions are route-level only.** There is no policy/gate per capability; anyone holding
    `ai-assistant` can call every AI endpoint.

---


## 14. Appendix — File Inventory

### Backend

```text
app/Enums/Activity.php                                  (Enable/Disable values for gateway options)
app/Enums/InputType.php                                 (TEXT / SELECT widget types)
app/Enums/Status.php                                    (ACTIVE / INACTIVE)
app/Http/AiAgents/Agents/Openai.php                     (provider implementation, gpt-4o)
app/Http/AiAgents/Requests/Openai.php                   (dynamic credential validation)
app/Http/Controllers/Admin/AiController.php             (7 AI actions, permission:ai-assistant)
app/Http/Controllers/Admin/AiAgentController.php        (settings, permission:settings)
app/Http/Requests/AiRequest.php                         (name: required|string|max:5000)
app/Http/Resources/AiAgentResource.php
app/Http/Resources/AiChatResource.php
app/Http/Resources/GatewayOptionsResource.php
app/Models/AiAgent.php
app/Models/AiChatHistory.php
app/Models/GatewayOption.php
app/Services/AiAbstract.php
app/Services/AiAgentService.php
app/Services/AiChatHistoryService.php
app/Services/AiService.php
app/Services/AiUsageService.php
app/Traits/HasAiPrompt.php
config/ai.php
```

### Database

```text
database/migrations/2026_04_08_144553_create_ai_agents_table.php
database/migrations/2026_04_08_163521_create_ai_chat_histories_table.php
database/migrations/2026_11_10_041115_create_agent_conversations_table.php
database/seeders/AiAgentTableSeeder.php
database/seeders/AiAgentDataTableSeeder.php
database/seeders/PermissionTableSeeder.php              (ai-assistant)
database/seeders/SiteTableSeeder.php                    (site_default_ai_agent)
database/seeders/SiteTableSeederVersionTwo.php          (site_default_ai_agent)
database/seeders/DatabaseSeeder.php                     (wiring)
```

### Frontend

```text
resources/js/components/DefaultComponent.vue                              (mounts the sidebar, L23)
resources/js/components/layouts/backend/BackendAiSidebarComponent.vue     (chat drawer)
resources/js/components/admin/settings/AiAgent/AiAgentComponent.vue       (settings screen)
resources/js/store/modules/ai.js                                          (chat + copy helpers)
resources/js/store/modules/aiAgent.js                                     (settings store)
resources/js/router/modules/settingRoutes.js                              (admin.settings.ai-agent)
resources/js/languages/en.json | ar.json | bn.json                        (ai i18n keys)
```

### Routes

```text
routes/api.php  L397-400   admin/setting/ai-agent   -> AiAgentController
routes/api.php  L688-696   admin/ai/{status,name,description,tags,chat,chat-response,chat-history}
```

---

*End of document.*

