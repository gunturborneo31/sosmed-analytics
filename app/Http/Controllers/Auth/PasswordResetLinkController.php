<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        // Jawaban selalu sama agar tidak membocorkan surel mana yang terdaftar.
        return back()->with('status', $status === Password::ResetLinkSent
            ? 'Kalau surel itu terdaftar, tautan pengaturan ulang sudah kami kirim.'
            : 'Kalau surel itu terdaftar, tautan pengaturan ulang sudah kami kirim.');
    }
}
