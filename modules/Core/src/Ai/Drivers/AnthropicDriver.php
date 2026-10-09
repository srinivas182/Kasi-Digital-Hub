<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Core\Ai\AiDriver;
use Modules\Core\Ai\AiRequest;
use Modules\Core\Ai\AiResponse;
use Modules\Core\Ai\AiUnavailable;

/** Anthropic Messages API. */
final class AnthropicDriver implements AiDriver
{
    public function complete(AiRequest $request): AiResponse
    {
        $key = (string) config('kasi.ai.keys.anthropic');
        if ($key === '') {
            throw new AiUnavailable('ANTHROPIC_API_KEY is not set.');
        }

        try {
            $response = Http::timeout((int) config('kasi.ai.timeout_seconds'))
                ->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $request->model,
                    'max_tokens' => $request->maxTokens,
                    'system' => $request->system,
                    'messages' => [['role' => 'user', 'content' => $request->user]],
                ]);
        } catch (ConnectionException $e) {
            throw new AiUnavailable($e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new AiUnavailable('Anthropic API returned '.$response->status());
        }

        $text = collect((array) $response->json('content'))->where('type', 'text')->pluck('text')->implode('');

        return new AiResponse($text, (int) $response->json('usage.input_tokens'), (int) $response->json('usage.output_tokens'), (string) $response->json('model', $request->model));
    }
}
