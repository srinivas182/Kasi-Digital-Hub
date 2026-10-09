<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Speech;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Core\Ai\AiUnavailable;

/** OpenAI audio transcription. Other providers (e.g. Google, Lelapa AI) are added after the pilot recording test. */
final class OpenAiSpeechToText implements SpeechToText
{
    public function transcribe(string $path, string $mimeType, string $language): string
    {
        $key = (string) config('kasi.ai.keys.openai');
        if ($key === '') {
            throw new AiUnavailable('OPENAI_API_KEY is not set.');
        }

        try {
            $response = Http::timeout(60)->withToken($key)
                ->attach('file', (string) file_get_contents($path), 'voice-note.'.$this->extension($mimeType), ['Content-Type' => $mimeType])
                ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => $this->model(), 'language' => $language, 'response_format' => 'json']);
        } catch (ConnectionException $e) {
            throw new AiUnavailable($e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new AiUnavailable('Transcription API returned '.$response->status());
        }

        return trim((string) $response->json('text'));
    }

    public function model(): string
    {
        return (string) config('kasi.speech.openai_model');
    }

    private function extension(string $mimeType): string
    {
        return match (true) {
            str_contains($mimeType, 'webm') => 'webm',
            str_contains($mimeType, 'ogg') => 'ogg',
            str_contains($mimeType, 'mp4'), str_contains($mimeType, 'm4a'), str_contains($mimeType, 'aac') => 'm4a',
            str_contains($mimeType, 'wav') => 'wav',
            default => 'mp3',
        };
    }
}
