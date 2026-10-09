<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Identity\Models\User;
use Modules\Work\Services\WorkSubject;

abstract class WorkController
{
    public function __construct(protected readonly WorkSubject $subject) {}

    /** @return array{0: User, 1: User|null} */
    protected function who(Request $request): array
    {
        return $this->subject->resolve($request);
    }
}
