<?php

declare(strict_types=1);

namespace App\Octane;

use Modules\Core\Ai\Drivers\FakeAiDriver;
use Modules\Core\Ai\Speech\FakeSpeechToText;
use Modules\Core\Documents\Generation\FakePdfRenderer;

/**
 * Octane keeps the app in memory between requests (S22). The fake drivers used in demos keep their state in
 * static arrays; reset them after every request so nothing carries over from one person to the next and
 * memory doesn't grow.
 */
final class ResetFakeDrivers
{
    public function handle(object $event): void
    {
        FakeAiDriver::reset();
        FakeSpeechToText::reset();
        FakePdfRenderer::reset();
    }
}
