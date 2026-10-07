<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Locale\Languages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Saves the chosen interface language for one year.
 */
final class LocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(Languages::available()))],
        ]);

        return back()->withCookie(cookie()->forever(Languages::COOKIE, $validated['locale']));
    }
}
