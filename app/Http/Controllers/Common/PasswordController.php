<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\PasswordValidateResetRequest;
use App\Models\PasswordResetToken;
use App\Models\Setting;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordController extends BaseController
{
    protected $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * Show forgot password form
     */
    public function showForgotForm(Request $request)
    {
        $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials'];
        $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        return Inertia::render('Auth/ForgotPassword', $data);
    }

    /**
     * Create password reset token and send email
     */
    public function createPasswordResetToken(PasswordResetRequest $request)
    {
        try {
            $user = User::where('email', $request->email)
                ->where('deleted_at', null)
                ->first();

            if (!$user) {
                // Don't reveal if email exists or not for security
                return back()->with('status', 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.');
            }

            // Delete any existing tokens for this user
            PasswordResetToken::where('email', $user->email)->delete();

            // Create new token
            $token = Str::random(60);
            PasswordResetToken::create([
                'email' => $user->email,
                'token' => $token,
                'created_at' => now(),
                'expires_at' => now()->addHours(config('auth.passwords.users.expire', 60)),
            ]);

            // Send reset email
            $resetUrl = URL::temporarySignedRoute(
                'password.reset',
                now()->addHours(config('auth.passwords.users.expire', 60)),
                ['token' => $token]
            );

            $this->passwordResetService->sendResetEmail($user, $resetUrl, $token);

            Log::info('Password reset token created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return back()->with('status', 'We have e-mailed your password reset link!');

        } catch (\Exception $e) {
            Log::error('Failed to create password reset token', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);

            return back()->withErrors([
                'email' => 'Failed to send password reset email. Please try again.',
            ]);
        }
    }

    /**
     * Show password reset form
     */
    public function showResetForm(Request $request)
    {
        $token = $request->token;

        // Validate token
        $resetToken = PasswordResetToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetToken) {
            return redirect('/login')->withErrors([
                'email' => 'Invalid or expired password reset token.',
            ]);
        }

        $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials'];
        $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
        $data['token'] = $token;
        $data['email'] = $resetToken->email;

        return Inertia::render('Auth/ResetPassword', $data);
    }

    /**
     * Reset user password
     */
    public function resetPassword(PasswordValidateResetRequest $request)
    {
        try {
            DB::beginTransaction();

            $resetToken = PasswordResetToken::where('token', $request->token)
                ->where('email', $request->email)
                ->where('expires_at', '>', now())
                ->first();

            if (!$resetToken) {
                return back()->withErrors([
                    'email' => 'Invalid or expired password reset token.',
                ]);
            }

            $user = User::where('email', $request->email)
                ->where('deleted_at', null)
                ->first();

            if (!$user) {
                return back()->withErrors([
                    'email' => 'User not found.',
                ]);
            }

            // Update user password
            $user->update([
                'password' => bcrypt($request->password),
                'password_changed_at' => now(),
            ]);

            // Delete the reset token
            $resetToken->delete();

            // Log password reset
            Log::info('Password reset successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            DB::commit();

            return redirect('/login')->with('status', 'Your password has been reset successfully!');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to reset password', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);

            return back()->withErrors([
                'email' => 'Failed to reset password. Please try again.',
            ]);
        }
    }

    /**
     * Show change password form (for authenticated users)
     */
    public function showChangePasswordForm()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        return Inertia::render('Auth/ChangePassword');
    }

    /**
     * Change authenticated user's password
     */
    public function changePassword(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to change password.',
            ], 401);
        }

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verify current password
        if (!password_verify($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        try {
            $user->update([
                'password' => bcrypt($request->password),
                'password_changed_at' => now(),
            ]);

            Log::info('User changed password successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return back()->with('success', 'Password changed successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to change password', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return back()->withErrors([
                'password' => 'Failed to change password. Please try again.',
            ]);
        }
    }

    /**
     * Verify if reset token is valid
     */
    public function verifyResetToken(Request $request)
    {
        $token = $request->token;

        $resetToken = PasswordResetToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetToken) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired token.',
            ]);
        }

        return response()->json([
            'valid' => true,
            'email' => $resetToken->email,
            'expires_at' => $resetToken->expires_at,
        ]);
    }

    /**
     * Check password strength
     */
    public function checkPasswordStrength(Request $request)
    {
        $password = $request->password;

        if (!$password) {
            return response()->json([
                'strength' => 0,
                'message' => 'Password is required.',
            ]);
        }

        $strength = $this->calculatePasswordStrength($password);

        return response()->json([
            'strength' => $strength['score'],
            'message' => $strength['message'],
            'suggestions' => $strength['suggestions'],
        ]);
    }

    /**
     * Calculate password strength
     */
    private function calculatePasswordStrength($password)
    {
        $score = 0;
        $suggestions = [];

        // Length check
        if (strlen($password) >= 8) {
            $score += 25;
        } else {
            $suggestions[] = 'Use at least 8 characters';
        }

        // Uppercase letters
        if (preg_match('/[A-Z]/', $password)) {
            $score += 25;
        } else {
            $suggestions[] = 'Include uppercase letters';
        }

        // Lowercase letters
        if (preg_match('/[a-z]/', $password)) {
            $score += 25;
        } else {
            $suggestions[] = 'Include lowercase letters';
        }

        // Numbers
        if (preg_match('/[0-9]/', $password)) {
            $score += 12.5;
        } else {
            $suggestions[] = 'Include numbers';
        }

        // Special characters
        if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $score += 12.5;
        } else {
            $suggestions[] = 'Include special characters';
        }

        $message = 'Weak';
        if ($score >= 87.5) {
            $message = 'Very Strong';
        } elseif ($score >= 75) {
            $message = 'Strong';
        } elseif ($score >= 50) {
            $message = 'Medium';
        } elseif ($score >= 25) {
            $message = 'Weak';
        }

        return [
            'score' => $score,
            'message' => $message,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Clean expired tokens (maintenance task)
     */
    public function cleanExpiredTokens()
    {
        try {
            $deleted = PasswordResetToken::where('expires_at', '<', now())->delete();

            Log::info('Cleaned expired password reset tokens', [
                'deleted_count' => $deleted,
            ]);

            return response()->json([
                'success' => true,
                'deleted_count' => $deleted,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clean expired tokens', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clean expired tokens.',
            ], 500);
        }
    }
}