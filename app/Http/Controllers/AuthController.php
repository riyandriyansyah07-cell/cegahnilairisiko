<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Contracts\View\View;
use App\Models\User;

class AuthController extends Controller
{
    public function show(): View
    {
        // Akun contoh hanya tampil di lingkungan lokal, untuk memudahkan uji alur tiap peran.
        $demo = collect();
        if (app()->environment('local')) {
            $demo = $demo
                ->concat(User::where('role', 'mahasiswa')->orderBy('id')->limit(3)->get())
                ->concat(User::where('role', 'dpa')->orderBy('id')->limit(1)->get())
                ->concat(User::where('role', 'admin')->orderBy('id')->limit(1)->get());
        }

        return view('auth.login', ['demo' => $demo]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($data['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withInput($request->only('username'))
                ->withErrors(['username' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik."]);
        }

        $ok = Auth::attempt(['username' => $data['username'], 'password' => $data['password'], 'is_active' => true]);
        if (! $ok) {
            RateLimiter::hit($key, 60);

            return back()->withInput($request->only('username'))
                ->withErrors(['username' => 'NIM atau kata sandi tidak cocok.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('beranda'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }
}
