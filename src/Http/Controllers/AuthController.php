<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use MahmoudMhamed\DbLens\Http\Middleware\AuthorizeDbLens;
use MahmoudMhamed\DbLens\Services\ConnectionManager;

class AuthController extends Controller
{
    public function showLogin(ConnectionManager $cm)
    {
        if (! $this->passwordConfigured()) {
            return redirect()->route('dblens.database.show', ['connection' => $cm->default()]);
        }
        return view('dblens::login');
    }

    public function authenticate(Request $request, ConnectionManager $cm)
    {
        $request->validate(['password' => 'required|string']);

        $expected = (string) config('dblens.viewer.password');
        $given = (string) $request->input('password');

        // Hashed password (preferred): constant-time Hash::check.
        // Plain password (back-compat): hash_equals for constant-time compare.
        $matches = (Hash::info($expected)['algoName'] !== 'unknown')
            ? Hash::check($given, $expected)
            : hash_equals($expected, $given);

        if (! $matches) {
            return back()->withErrors(['password' => 'Invalid password.']);
        }

        $request->session()->regenerate();
        $request->session()->put(AuthorizeDbLens::SESSION_KEY, true);

        return redirect()->route('dblens.database.show', ['connection' => $cm->default()]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget(AuthorizeDbLens::SESSION_KEY);
        return redirect()->route('dblens.login');
    }

    protected function passwordConfigured(): bool
    {
        $password = config('dblens.viewer.password');
        return $password !== null && $password !== '';
    }
}
