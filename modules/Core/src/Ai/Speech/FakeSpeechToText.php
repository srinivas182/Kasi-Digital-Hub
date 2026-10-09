<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Speech;

use Modules\Core\Ai\AiUnavailable;

/** Demo/CI transcription: never calls a provider. Tests can queue texts or a failure. */
final class FakeSpeechToText implements SpeechToText
{
    /** @var list<string|null> */
    private static array $queue = [];

    /** @var list<array{path: string, language: string}> */
    public static array $calls = [];

    public static function respondWith(string ...$texts): void
    {
        array_push(self::$queue, ...$texts);
    }

    public static function fail(): void
    {
        self::$queue[] = null;
    }

    public static function reset(): void
    {
        self::$queue = [];
        self::$calls = [];
    }

    public function transcribe(string $path, string $mimeType, string $language): string
    {
        self::$calls[] = ['path' => $path, 'language' => $language];

        if (self::$queue !== []) {
            $text = array_shift(self::$queue);
            if ($text === null) {
                throw new AiUnavailable('Fake transcription failure');
            }

            return $text;
        }

        return '(Demo transcription) I worked at a spaza shop for two years. I served customers, handled cash and packed the shelves.';
    }

    public function model(): string
    {
        return 'fake-speech';
    }
}
