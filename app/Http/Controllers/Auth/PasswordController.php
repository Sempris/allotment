<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Every user changes the own password. The board only sets start passwords.
 */
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(10), 'confirmed'],
        ], [], ['current_password' => 'Bisheriges Passwort', 'password' => 'Neues Passwort']);

        $request->user()->update(['password' => $data['password']]);

        return redirect()->route('dashboard')->with('status', 'Passwort geändert.');
    }
}
