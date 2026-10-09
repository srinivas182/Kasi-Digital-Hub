<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Speech;

use Illuminate\Http\UploadedFile;
use Modules\Core\Ai\AiBudget;
use Modules\Core\Ai\AiResult;
use Modules\Core\Ai\AiUnavailable;
use Modules\Core\Ai\Models\AiRequestLog;
use Modules\Core\Identity\Models\User;

/**
 * Voice notes to text. The audio is never stored: it is sent for transcription from the upload's
 * temporary file and that file is deleted straight afterwards. Only switched-on languages are
 * accepted (each language is enabled after testing with real recordings from the hubs).
 */
final readonly class VoiceNotes
{
    public const FEATURE = 'core.voice_note';

    public function __construct(private SpeechToText $speech, private AiBudget $budget) {}

    /** @return list<string> */
    public static function languages(): array
    {
        return array_values((array) config('kasi.speech.languages'));
    }

    public function transcribe(UploadedFile $audio, string $language, int $seconds, User $for, ?User $by = null): AiResult
    {
        $path = (string) $audio->getRealPath();

        try {
            if (! in_array($language, self::languages(), true)) {
                return new AiResult(false, reason: 'language');
            }

            $log = ['feature' => self::FEATURE, 'prompt_key' => self::FEATURE, 'prompt_version' => 1, 'model' => $this->speech->model(), 'tier' => 'speech',
                'user_id' => $for->id, 'actor_id' => $by?->id, 'hub_id' => ($by ?? $for)->home_hub_id];

            $today = AiRequestLog::query()->where('feature', self::FEATURE)->where('user_id', $for->id)->where('created_at', '>=', now()->subDay())->count();
            $refusal = $today >= (int) config('kasi.speech.per_person_day') ? 'budget' : $this->budget->refusal(self::FEATURE, null, $log['hub_id']);
            if ($refusal !== null) {
                AiRequestLog::query()->create([...$log, 'outcome' => $refusal]);

                return new AiResult(false, reason: $refusal);
            }

            $started = hrtime(true);
            try {
                $text = $this->speech->transcribe($path, (string) $audio->getMimeType(), $language);
                $outcome = trim($text) === '' ? 'invalid' : 'ok';
            } catch (AiUnavailable) {
                $text = '';
                $outcome = 'unavailable';
            }

            AiRequestLog::query()->create([...$log, 'outcome' => $outcome, 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'cost_cents' => (int) ceil(max(1, $seconds) / 60 * (int) config('kasi.speech.cost_cents_per_minute')), 'output' => $text === '' ? null : mb_substr($text, 0, 8000)]);

            return $outcome === 'ok' ? new AiResult(true, ['text' => $text], $text) : new AiResult(false, reason: $outcome);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
