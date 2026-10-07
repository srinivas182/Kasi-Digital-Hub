<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Feature tests (platform and every module) boot the Laravel application.
| Module tests live in modules/<Module>/tests and are picked up automatically.
*/

pest()->extend(TestCase::class)->in('Feature', '../modules');
