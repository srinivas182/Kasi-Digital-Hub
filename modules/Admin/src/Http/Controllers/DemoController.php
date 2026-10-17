<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Support\DemoStories;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * Demo tools (ADR-029): guided stories, the role switcher and demo reset. All three refuse unless demo mode
 * is on AND the app is not running as production; the switcher only signs in as demo accounts.
 */
final class DemoController
{
    public static function allowed(): bool
    {
        return config('kasi.demo.enabled') === true && ! app()->isProduction();
    }

    public static function isDemoAccount(User $user): bool
    {
        return str_starts_with($user->phone, '+2772000') || str_starts_with($user->phone, '+2784000');
    }

    public function stories(): Response
    {
        abort_unless(self::allowed(), 404);

        $ids = User::query()->whereIn('phone', array_column(DemoStories::personas(), 'phone'))->pluck('id', 'phone');

        return Inertia::render('Admin/Demo', ['stories' => array_map(static fn (array $s): array => [...$s, 'userId' => $ids[$s['phone']] ?? null], DemoStories::all()),
            'personas' => array_values(array_filter(array_map(static fn (array $p): array => [...$p, 'userId' => $ids[$p['phone']] ?? null], DemoStories::personas()), static fn (array $p): bool => $p['userId'] !== null))]);
    }

    public function switch(Request $request, string $user, AuditLogger $audit): RedirectResponse
    {
        abort_unless(self::allowed(), 404);
        $target = User::query()->whereKey($user)->first();
        abort_unless($target instanceof User && self::isDemoAccount($target), 404);
        $from = $request->user();
        Auth::login($target);
        $request->session()->regenerate();
        $audit->record('demo.switched', $target, meta: ['from' => $from instanceof User ? $from->id : null]);

        return redirect()->to((string) $request->query('to', '/home'));
    }

    public function reset(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless(self::allowed(), 404);
        $user = $request->user();
        abort_unless($user instanceof User && app(AccessResolver::class)->hasPermission($user, 'admin.system.view'), 403);
        $audit->record('demo.reset', actor: $user);
        Artisan::call('kasi:demo:reset', ['--force' => true]);

        return redirect('/login')->with('status', __('admin.demo.reset_done'));
    }
}
