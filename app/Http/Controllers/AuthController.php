<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function create()
    {
        return Inertia::render('Login');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::attempt([...$data, 'active' => true], false)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The credentials are invalid or this account is inactive.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        Audit::record($request->user(), null, 'auth.login', 'user:'.$request->user()->id);

        return redirect()->intended('/');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function forgot()
    {
        return Inertia::render('Password', ['mode' => 'forgot']);
    }

    public function email(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'If this account exists, a reset link has been sent.');
    }

    public function reset(string $token, Request $request)
    {
        return Inertia::render('Password', ['mode' => 'reset', 'token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()]]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect('/login')->with('success', 'Password reset. Please sign in.');
    }

    public function change(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()]]);
        $user = $request->user();
        $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success','Password changed. Please sign in.');
    }
}
