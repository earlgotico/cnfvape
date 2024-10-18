<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Logout successfully!');
    }

    protected function redirectBasedOnRole(int $role): RedirectResponse
    {
        if ($role === 2) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        } elseif ($role === 1) {
            return redirect()->intended(route('staff.orders.dashboard', absolute: false));
        } else {
            return redirect()->intended(route('dashboard', absolute: false));
        }
    }
}
