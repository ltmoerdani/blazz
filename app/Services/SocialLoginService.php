<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use DB;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginService
{
    public static function makeGoogleDriver()
    {
        $metadata = Setting::where('key', 'google_login')->first()->value;
        $metadata = json_decode($metadata);
        $app_id = ($metadata !== false) ? (isset($metadata->client_id) ? $metadata->client_id : '') : '';
        $app_secret = ($metadata !== false) ? (isset($metadata->client_secret) ? $metadata->client_secret : '') : '';

        $config = [
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'redirect' => url('google/callback'),
        ];

        return Socialite::buildProvider('\Laravel\Socialite\Two\GoogleProvider', $config);
    }

    public static function makeFacebookDriver()
    {
        $metadata = Setting::where('key', 'facebook_login')->first()->value;
        $metadata = json_decode($metadata);
        $app_id = ($metadata !== false) ? (isset($metadata->client_id) ? $metadata->client_id : '') : '';
        $app_secret = ($metadata !== false) ? (isset($metadata->client_secret) ? $metadata->client_secret : '') : '';

        $config = [
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'redirect' => url('facebook/callback'),
        ];

        return Socialite::buildProvider('\Laravel\Socialite\Two\FacebookProvider', $config);
    }

    public function redirect(string $type)
    {
        try {
            if ($type === 'google') {
                return self::makeGoogleDriver()->redirect();
            } elseif ($type === 'facebook') {
                return self::makeFacebookDriver()->redirect();
            }

            throw new \Exception("Unsupported social login type: {$type}");
        } catch (\Exception $e) {
            Log::error('Social login redirect failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function handleFacebookCallback()
    {
        try {
            $socialUser = self::makeFacebookDriver()->user();

            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'first_name' => $socialUser->getName() ?? 'Facebook User',
                    'last_name' => '',
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(uniqid()),
                    'status' => 1,
                    'email_verified_at' => now(),
                ]);
            }

            return (object)[
                'success' => true,
                'user' => $user,
                'message' => 'Login successful'
            ];

        } catch (\Exception $e) {
            Log::error('Facebook login callback failed', [
                'error' => $e->getMessage(),
            ]);

            return (object)[
                'success' => false,
                'message' => 'Facebook login failed: ' . $e->getMessage()
            ];
        }
    }

    public function handleGoogleCallback()
    {
        try {
            $socialUser = self::makeGoogleDriver()->user();

            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'first_name' => $socialUser->getName() ?? 'Google User',
                    'last_name' => '',
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(uniqid()),
                    'status' => 1,
                    'email_verified_at' => now(),
                ]);
            }

            return (object)[
                'success' => true,
                'user' => $user,
                'message' => 'Login successful'
            ];

        } catch (\Exception $e) {
            Log::error('Google login callback failed', [
                'error' => $e->getMessage(),
            ]);

            return (object)[
                'success' => false,
                'message' => 'Google login failed: ' . $e->getMessage()
            ];
        }
    }
}