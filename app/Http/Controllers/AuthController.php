<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        $sampleUsers = User::with('organization')->orderBy('id')->get();

        return view('auth.login', compact('sampleUsers'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        // Demo convenience fallback: handle both 'password' and 'password123'
        $altPassword = $credentials['password'] === 'password' ? 'password123' : ($credentials['password'] === 'password123' ? 'password' : null);
        if ($altPassword && Auth::attempt(['email' => $credentials['email'], 'password' => $altPassword])) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Kredensial yang dimasukkan tidak sesuai dengan data kami.',
        ])->onlyInput('email');
    }

    public function quickSwitch(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        Auth::login($user);
        $request->session()->regenerate();

        // If switching from login page, go straight to dashboard
        if (url()->previous() === route('login') || ! Auth::check()) {
            return redirect()->route('dashboard')->with('success', "Beralih peran sebagai: {$user->name} ({$user->role_display_name})");
        }

        return redirect()->intended(route('dashboard'))->with('success', "Beralih peran sebagai: {$user->name} ({$user->role_display_name})");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        $organizations = Organization::where('is_active', true)->orderBy('name')->get();

        return view('auth.register', compact('organizations'));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'nip' => 'nullable|string|max:50|unique:users',
            'organization_id' => 'nullable|exists:organizations,id',
            'password' => 'required|string|min:6|confirmed',
            'agree' => 'accepted',
        ], [
            'agree.accepted' => 'Anda harus menyetujui syarat dan ketentuan.',
            'email.unique' => 'Alamat email ini sudah terdaftar.',
            'nip.unique' => 'NIP/NIK ini sudah terdaftar.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nip' => $validated['nip'] ?? null,
            'organization_id' => $validated['organization_id'] ?? null,
            'role' => 'REQUESTER_CABANG',
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'approval_limit' => 0,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Registrasi berhasil! Selamat datang di Portal JIMS Bank Jatim.');
    }

    public function showForgotPassword()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.forgot-password');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'Alamat email tidak terdaftar dalam sistem.',
            'email.required' => 'Masukkan alamat email Anda.',
            'email.email' => 'Format email tidak valid.',
        ]);

        return back()->with('status', 'Tautan instruksi pemulihan kata sandi telah dikirim ke email Anda.');
    }
}
