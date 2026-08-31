<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Satu pintu masuk, dua sudut pandang berbeda (§1).
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $target = route(
            $request->user()->seesAllUnits() ? 'admin.overview' : 'operator.accounts',
        );

        // Layar transisi hanya untuk kedatangan pertama sesudah login.
        if ($request->session()->pull('baru_masuk', false)) {
            return view('auth.entering', [
                'target' => $target,
                'greeting' => $request->user()->name,
            ]);
        }

        return redirect()->to($target);
    }
}
