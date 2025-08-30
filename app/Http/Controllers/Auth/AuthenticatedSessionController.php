<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        // 外部ユーザ取得はここでは実施しない（/api/users をフロントから呼ぶ）
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
        // Sync external users from Project A on login page load
        $users = [];
        try {
            $base = rtrim(env('EXTERNAL_API_BASE', ''), '/');
            $token = env('EXTERNAL_API_TOKEN');
            if ($base) {
                $client = new Client([
                    'base_uri' => $base . (str_ends_with($base, '/') ? '' : '/') ,
                    'timeout' => 10,
                    'http_errors' => false,
                    'verify' => env('SSL_VERIFY', true),
                ]);
                $headers = ['Accept' => 'application/json'];
                if ($token) { $headers['Authorization'] = 'Bearer ' . $token; }
                $resp = $client->get('users', ['headers' => $headers]);
                $arr = json_decode((string) $resp->getBody(), true);
                if (is_array($arr)) {
                    foreach ($arr as $row) {
                        $extId = (string) ($row['id'] ?? $row['external_user_id'] ?? '');
                        $name  = (string) ($row['name'] ?? $row['full_name'] ?? '');
                        $email = (string) ($row['email'] ?? $row['mail'] ?? null);
                        if (!$extId) { continue; }
                        // Link by external_user_id or fallback by email
                        $user = User::where('external_user_id', $extId)->when(!$extId && $email, function($q) use ($email){
                            $q->orWhere('email', $email);
                        })->first();
                        if (!$user && $email) {
                            $user = User::where('email', $email)->first();
                        }
                        if ($user) {
                            $user->external_user_id = $extId;
                            if ($name) $user->name = $name;
                            if ($email) $user->email = $email;
                            $user->save();
                        } else {
                            // Create with random password; user must set password later
                            User::create([
                                'name' => $name ?: 'ユーザー',
                                'email' => $email ?: (Str::uuid().'@example.local'),
                                'external_user_id' => $extId,
                                'password' => Hash::make(Str::random(24)),
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('External user sync failed: '.$e->getMessage());
        }

        $users = User::whereNotNull('external_user_id')
            ->orderBy('name')
            ->get(['name', 'external_user_id']);

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'users' => $users,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
