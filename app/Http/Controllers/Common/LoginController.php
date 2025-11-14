<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUser;
use App\Http\Requests\TfaRequest;
use App\Models\Addon;
use App\Models\Setting;
use App\Models\User;
use App\Services\UserService;
use App\Services\SocialLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Illuminate\Support\Facades\Session;

class LoginController extends BaseController
{
    protected $userService;
    protected $role;

    public function __construct(?UserService $userService = null, string $role = 'user')
    {
        // Use appropriate user service based on role
        if ($role === 'admin') {
            $this->userService = app('App\Services\AdminUserService');
        } else {
            $this->userService = $userService ?: app(UserService::class);
        }
        $this->role = $role;
    }

    /**
     * Show the login form
     */
    public function showLoginForm()
    {
        $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials', 'trial_period', 'allow_facebook_login', 'allow_google_login'];
        $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        return Inertia::render('Auth/Login', $data);
    }

    /**
     * Handle login request
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->where('deleted_at', null)->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'Email tidak terdaftar atau password salah.',
            ]);
        }

        $addon = Addon::where('name', 'Google Authenticator')->first();
        $addonIsActive = $addon ? $addon->is_active : 0;
        $remember = $request->remember;

        if ($user->tfa && $addonIsActive == 1) {
            $request->session()->put('tfa', $user->id);
            $request->session()->put('remember', $remember);

            return redirect('/tfa');
        }

        return $this->doLogin($request, $user, $remember);
    }

    /**
     * Show two-factor authentication form
     */
    public function showTfaForm(Request $request)
    {
        if (!$request->session()->has('tfa')) {
            return redirect('/login');
        }

        $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials', 'trial_period', 'allow_facebook_login','allow_google_login'];
        $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        return Inertia::render('Auth/Tfa', $data);
    }

    /**
     * Verify two-factor authentication code
     */
    public function tfaVerify(TfaRequest $request)
    {
        if (!$request->session()->has('tfa')) {
            return redirect('/login');
        }

        $userId = $request->session()->get('tfa');
        $remember = $request->session()->get('remember', false);

        $user = User::find($userId);

        if (!$user) {
            $request->session()->forget(['tfa', 'remember']);
            return redirect('/login')->withErrors([
                'email' => 'Session expired, please login again.',
            ]);
        }

        // Verify TFA code (you'll need to implement this logic based on your TFA system)
        $google2fa = app('pragmarx.google2fa');
        $valid = $google2fa->verifyKey($user->google2fa_secret, $request->code);

        if (!$valid) {
            return back()->withErrors([
                'code' => 'Invalid authentication code.',
            ]);
        }

        $request->session()->forget(['tfa', 'remember']);

        return $this->doLogin($request, $user, $remember);
    }

    /**
     * Handle the actual login process
     */
    private function doLogin(Request $request, $user, $remember = false)
    {
        try {
            // Check if user is active
            if ($user->status !== 1) {
                return back()->withErrors([
                    'email' => 'Account is not active. Please contact administrator.',
                ]);
            }

            // Check if user's subscription is active (if applicable)
            if ($user->subscription && $user->subscription->status !== 'active') {
                return back()->withErrors([
                    'email' => 'Your subscription has expired. Please renew your subscription.',
                ]);
            }

            // Authenticate the user
            Auth::login($user, $remember);

            // Clear login attempts if any
            $request->session()->forget(['login_attempts', 'lockout_time']);

            // Log successful login
            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Set workspace session
            if ($user->teams->isNotEmpty()) {
                $team = $user->teams->first();
                session()->put('current_workspace', $team->workspace_id);
                session()->put('current_team', $team->id);
            }

            // Redirect based on role
            $redirectUrl = $this->role === 'admin' ? '/admin/dashboard' : '/dashboard';

            return redirect()->intended($redirectUrl);

        } catch (\Exception $e) {
            Log::error('Login failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);

            return back()->withErrors([
                'email' => 'Login failed. Please try again.',
            ]);
        }
    }

    /**
     * Handle social login redirect
     */
    public function socialLogin(Request $request, $type)
    {
        try {
            $socialLoginService = app(SocialLoginService::class);

            if (!in_array($type, ['facebook', 'google'])) {
                return redirect('/login')->withErrors([
                    'email' => 'Invalid social login type.',
                ]);
            }

            // Check if social login is enabled
            $settingKey = $type === 'facebook' ? 'allow_facebook_login' : 'allow_google_login';
            $allowSocialLogin = Setting::where('key', $settingKey)->value('value');

            if (!$allowSocialLogin) {
                return redirect('/login')->withErrors([
                    'email' => ucfirst($type) . ' login is not enabled.',
                ]);
            }

            return $socialLoginService->redirect($type);

        } catch (\Exception $e) {
            Log::error('Social login redirect failed', [
                'type' => $type,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return redirect('/login')->withErrors([
                'email' => 'Social login failed. Please try again.',
            ]);
        }
    }

    /**
     * Handle Facebook callback
     */
    public function handleFacebookCallback(Request $request)
    {
        try {
            $socialLoginService = app(SocialLoginService::class);
            $result = $socialLoginService->handleFacebookCallback();

            if ($result->success) {
                Auth::login($result->user);

                // Set workspace session
                if ($result->user->teams->isNotEmpty()) {
                    $team = $result->user->teams->first();
                    session()->put('current_workspace', $team->workspace_id);
                    session()->put('current_team', $team->id);
                }

                Log::info('User logged in via Facebook', [
                    'user_id' => $result->user->id,
                    'email' => $result->user->email,
                    'ip' => $request->ip(),
                ]);

                return redirect()->intended($this->role === 'admin' ? '/admin/dashboard' : '/dashboard');
            } else {
                return redirect('/login')->withErrors([
                    'email' => $result->message ?? 'Facebook login failed.',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Facebook login callback failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return redirect('/login')->withErrors([
                'email' => 'Facebook login failed. Please try again.',
            ]);
        }
    }

    /**
     * Handle Google callback
     */
    public function googleCallback(Request $request)
    {
        try {
            $socialLoginService = app(SocialLoginService::class);
            $result = $socialLoginService->handleGoogleCallback();

            if ($result->success) {
                Auth::login($result->user);

                // Set workspace session
                if ($result->user->teams->isNotEmpty()) {
                    $team = $result->user->teams->first();
                    session()->put('current_workspace', $team->workspace_id);
                    session()->put('current_team', $team->id);
                }

                Log::info('User logged in via Google', [
                    'user_id' => $result->user->id,
                    'email' => $result->user->email,
                    'ip' => $request->ip(),
                ]);

                return redirect()->intended($this->role === 'admin' ? '/admin/dashboard' : '/dashboard');
            } else {
                return redirect('/login')->withErrors([
                    'email' => $result->message ?? 'Google login failed.',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Google login callback failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return redirect('/login')->withErrors([
                'email' => 'Google login failed. Please try again.',
            ]);
        }
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request)
    {
        try {
            if (Auth::check()) {
                Log::info('User logged out', [
                    'user_id' => Auth::id(),
                    'email' => Auth::user()->email,
                    'ip' => $request->ip(),
                ]);

                Auth::logout();
            }

            // Clear all session data
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('status', 'You have been logged out successfully.');

        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            // Force redirect to login even if logout fails
            return redirect('/login');
        }
    }

    /**
     * Check authentication status
     */
    public function checkAuth(Request $request)
    {
        return response()->json([
            'authenticated' => Auth::check(),
            'user' => Auth::check() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
                'role' => Auth::user()->role ?? 'user',
            ] : null,
            'workspace' => session()->get('current_workspace'),
        ]);
    }
}