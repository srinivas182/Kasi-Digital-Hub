<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Core\Ai\Drivers\FakeAiDriver;
use Modules\Core\Ai\Speech\FakeSpeechToText;
use Modules\Core\Documents\Generation\FakePdfRenderer;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests assert on Inertia responses; built assets are not needed.
        $this->withoutVite();

        // Fake AI and PDF drivers keep state between calls; start every test clean.
        FakeAiDriver::reset();
        FakeSpeechToText::reset();
        FakePdfRenderer::$rendered = [];
    }
}
