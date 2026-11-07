<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Pembatasan hak akses menurut peran, diperiksa di server pada setiap permintaan. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Akun tidak aktif. Hubungi sekretariat.']);
        }

        abort_unless(in_array($user->role, $roles, true), 403);

        return $next($request);
    }
}
