<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Core\Ai\AiDriver;
use Modules\Core\Ai\AiRequest;
use Modules\Core\Ai\AiResponse;
use Modules\Core\Ai\AiUnavailable;

/** OpenAI Chat Completions API (the switchable fallback provider). */
final class OpenAiDriver implements AiDriver
{
    public function complete(AiRequest $request): AiResponse
    {
        $key = (string) config('kasi.ai.keys.openai');
        if ($key === '') {
            throw new AiUnavailable('OPENAI_API_KEY is not set.');
        }

        try {
            $response = Http::timeout((int) config('kasi.ai.timeout_seconds'))->withToken($key)
                ->post('https://api.openai.com/v1/chat/completions', array_filter([
                    'model' => $request->model,
                    'max_tokens' => $request->maxTokens,
                    'messages' => [['role' => 'system', 'content' => $request->system], ['role' => 'user', 'content' => $request->user]],
                    'response_format' => $request->outputFields !== [] ? ['type' => 'json_object'] : null,
                ]));
        } catch (ConnectionException $e) {
            throw new AiUnavailable($e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new AiUnavailable('OpenAI API returned '.$response->status());
        }

        return new AiResponse((string) $response->json('choices.0.message.content'), (int) $response->json('usage.prompt_tokens'), (int) $response->json('usage.completion_tokens'), (string) $response->json('model', $request->model));
    }
}
