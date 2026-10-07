<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests (platform and every module) boot the Laravel application with a
| fresh database. Module tests live in modules/<Module>/tests and are picked up automatically.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', '../modules');
