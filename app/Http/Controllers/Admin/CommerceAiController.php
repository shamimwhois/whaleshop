<?php

namespace App\Http\Controllers\Admin;

use App\Ai\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\CommerceAiRequest;
use App\Services\Ai\CommerceAgentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

/**
 * HTTP surface for the commerce sales agent (chat, streaming, status).
 *
 * Billing/conversational state is delegated to CommerceAgentService and the
 * existing AiUsageService gate; this class only adapts HTTP in and out.
 */
class CommerceAiController extends Controller implements HasMiddleware
{
    public function __construct(protected CommerceAgentService $service) {}

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:' . config('ai_commerce.gate.permission', 'ai-assistant'), only: ['chat', 'stream']),
        ];
    }

    /**
     * Report whether the assistant is available to the current user.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data'   => [
                'enabled'  => $this->service->allowed(),
                'tools'    => app(ToolRegistry::class)->names(),
                'streaming'=> (bool) config('ai_commerce.streaming.enabled', true),
            ],
        ]);
    }

    /**
     * Handle a single (non-streamed) chat turn.
     */
    public function chat(CommerceAiRequest $request): JsonResponse
    {
        try {
            $response = $this->service->ask(
                $request->string('message')->toString(),
                $request->input('conversation_id'),
                $this->attachmentPaths($request),
            );

            return response()->json([
                'status'          => true,
                'data'            => [
                    'message'         => $response->text,
                    'conversation_id' => $response->conversationId,
                ],
            ]);
        } catch (Exception $exception) {
            return response()->json(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    /**
     * Stream a chat turn using the configured protocol (Vercel by default).
     */
    public function stream(CommerceAiRequest $request)
    {
        try {
            $stream = $this->service->stream(
                $request->string('message')->toString(),
                $request->input('conversation_id'),
                $this->attachmentPaths($request),
            );

            $protocol = (string) config('ai_commerce.streaming.protocol', 'vercel');

            $stream = $protocol === 'agui'
                ? $stream->usingAgentUserInteractionProtocol()
                : $stream->usingVercelDataProtocol();

            return $stream->toResponse($request);
        } catch (Exception $exception) {
            return response()->json(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    /**
     * Persist uploaded attachments and return their stored paths.
     *
     * Only enabled attachment kinds are accepted, matching the config gate.
     *
     * @return array<int, string>
     */
    protected function attachmentPaths(CommerceAiRequest $request): array
    {
        if (! config('ai_commerce.attachments.enabled', true) || ! $request->hasFile('attachments')) {
            return [];
        }

        $paths = [];

        foreach ($request->file('attachments') as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $paths[] = Storage::disk(config('filesystems.default'))->putFile('ai-attachments', $file);
        }

        return array_filter($paths);
    }
}
