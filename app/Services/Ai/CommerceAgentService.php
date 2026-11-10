<?php

namespace App\Services\Ai;

use App\Ai\Agents\CommerceAgent;
use App\Services\AiUsageService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;

/**
 * Orchestrates commerce agent runs for the HTTP layer.
 *
 * Centralizes: access gating (reuses the existing triple gate), provider/model
 * resolution from config, conversation continuation and usage recording, so the
 * controller stays thin and the same logic can back MCP or queued jobs later.
 */
class CommerceAgentService
{
    public function __construct(
        protected AiUsageService $gate,
        protected CommerceAgent $agent,
    ) {}

    /**
     * Whether the current user may use the commerce assistant at all.
     */
    public function allowed(): bool
    {
        return config('ai_commerce.enabled', true) && $this->gate->checking();
    }

    /**
     * Run a single, non-streamed prompt.
     *
     * @throws Exception when the assistant is not available to the user.
     */
    public function ask(string $message, ?string $conversationId = null, array $attachments = []): AgentResponse
    {
        if (! $this->allowed()) {
            throw new Exception(trans('all.message.agent_is_not_active'), 422);
        }

        $agent = $this->forCurrentUser($conversationId);

        return $agent->prompt(
            $message,
            attachments: $attachments,
            provider: $this->provider(),
            model: $this->model(),
            timeout: (int) config('ai_commerce.limits.timeout', 60),
        );
    }

    /**
     * Start a streamed response for the current customer.
     */
    public function stream(string $message, ?string $conversationId = null, array $attachments = []): StreamableAgentResponse
    {
        if (! $this->allowed()) {
            throw new Exception(trans('all.message.agent_is_not_active'), 422);
        }

        return $this->forCurrentUser($conversationId)->stream(
            $message,
            attachments: $attachments,
            provider: $this->provider(),
            model: $this->model(),
            timeout: (int) config('ai_commerce.limits.timeout', 60),
        );
    }

    /**
     * Bind the agent to the authenticated customer, continuing a conversation
     * when an id is supplied and the conversation belongs to that customer.
     */
    protected function forCurrentUser(?string $conversationId): CommerceAgent
    {
        $user = Auth::user();

        if ($user === null) {
            return $this->agent;
        }

        if ($conversationId !== null && $this->ownsConversation($user, $conversationId)) {
            return $this->agent->continue($conversationId, as: $user);
        }

        return $this->agent->forUser($user);
    }

    /**
     * Verify the conversation belongs to the user before resuming it, so one
     * customer cannot read another customer's history by guessing an id.
     */
    protected function ownsConversation(object $user, string $conversationId): bool
    {
        if (! method_exists($user, 'conversations')) {
            return false;
        }

        return $user->conversations()
            ->where('id', $conversationId)
            ->exists();
    }

    protected function provider(): string
    {
        return (string) config('ai_commerce.provider', 'openai');
    }

    protected function model(): string
    {
        return (string) config('ai_commerce.models.chat', 'gpt-4o');
    }
}
