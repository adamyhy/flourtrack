<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route("dashboard");
        }
        return view("auth.login");
    }

    public function login(Request $request)
    {
        $request->validate([
            "email" => "required|email",
            "password" => "required",
        ]);

        $throttleKey = Str::lower($request->input("email")) . "|" . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                "email" => "Terlalu banyak percobaaan login gagal. Akun/IP dikunci sementara selama {$seconds} detik.",
            ])->onlyInput("email");
        }

        $credentials = $request->only("email", "password");
        $credentials["is_active"] = true;

        if (Auth::attempt($credentials, $request->boolean("remember"))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();
            return redirect()->intended(route("dashboard"))->with("success", "Selamat datang kembali, " . Auth::user()->name);
        }

        RateLimiter::hit($throttleKey, 60);

        $remaining = RateLimiter::remaining($throttleKey, 3);
        $message = "Email atau password salah.";
        if ($remaining > 0) {
            $message .= " Sisa percobaan login: {$remaining}x lagi sebelum dikunci.";
        } else {
            $message .= " Percobaan habis! Terkunci 60 detik.";
        }

        return back()->withErrors(["email" => $message])->onlyInput("email");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route("login")->with("info", "Anda telah keluar dari sistem.");
    }
}
