<?php

namespace App\Http\Middleware;

use App\Models\Addon;
use App\Models\Chat;
use App\Models\Language;
use App\Models\workspace;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Middleware;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use Illuminate\Support\Facades\App;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $language = session('locale') ?? 'en';

        $tfaData = $this->getTwoFactorAuthData($user);
        $workspaceData = $this->getWorkspaceData($user);
        $appConfig = $this->getApplicationConfig();

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'applicationVersion' => fn () => Config::get('version.version'),
            'applicationReleaseDate' => fn () => Config::get('version.release_date'),
            'config' => $appConfig['config'],
            'auth' => [
                'user' => $user ?: null,
            ],
            'workspace' => $workspaceData['workspace'],
            'workspaceId' => session('current_workspace'),
            'workspaces' => $workspaceData['workspaces'],
            'flash' => [
                'status'=> session('status')
            ],
            'response_data' => fn () => $request->session()->get('response_data'),
            'languages' => $appConfig['languages'],
            'unreadMessages' => $workspaceData['unreadMessages'],
            'currentLanguage' => $language,
            'tfa' => [
                'status' => $tfaData['active'],
                'secret' => $tfaData['secret'],
                'qrcode' => $tfaData['qrcode'],
                'enabled' => $user ? $user->tfa : false,
            ],
            'isRtl' => $appConfig['isRtl'],
        ]);
    }

    private function getTwoFactorAuthData($user): array
    {
        $tfaActive = false;
        $secret = '';
        $qrcode = '';

        if (!$user) {
            return ['active' => $tfaActive, 'secret' => $secret, 'qrcode' => $qrcode];
        }

        $googleAuth = Addon::where('name', 'Google Authenticator')->first()->is_active;
        $tfaActive = $googleAuth == 1;

        if ($googleAuth == 1) {
            $secret = $user->tfa_secret;
            $tfa = new TwoFactorAuth(new BaconQrCodeProvider());

            if (!$secret) {
                $secret = $tfa->createSecret();
                User::where('id', $user->id)->update(['tfa_secret' => $secret]);
            }

            $qrcode = $tfa->getQRCodeImageAsDataUri(
                preg_replace('#^https?://#', '', config('app.url')),
                $secret
            );
        }

        return ['active' => $tfaActive, 'secret' => $secret, 'qrcode' => $qrcode];
    }

    private function getWorkspaceData($user): array
    {
        $workspace = [];
        $workspaces = [];
        $unreadMessages = 0;

        if (!$user || $user->role !== 'user') {
            return ['workspace' => $workspace, 'workspaces' => $workspaces, 'unreadMessages' => $unreadMessages];
        }

        $workspaceId = session('current_workspace');
        
        $user->load(['teams' => function ($query) use ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }]);

        $workspaces = Team::with('workspace')->where('user_id', $user->id)->get();
        $workspace = workspace::where('id', $workspaceId)->first();
        $unreadMessages = Chat::where('workspace_id', $workspaceId)
            ->where('type', 'inbound')
            ->where('deleted_at', null)
            ->where('is_read', 0)
            ->count();

        return ['workspace' => $workspace, 'workspaces' => $workspaces, 'unreadMessages' => $unreadMessages];
    }

    private function getApplicationConfig(): array
    {
        if (!$this->isInstalled()) {
            return ['config' => [], 'languages' => [], 'isRtl' => false];
        }

        $keys = [
            'favicon', 'logo', 'company_name', 'address', 'currency', 'email', 'phone',
            'socials', 'trial_period', 'recaptcha_site_key', 'recaptcha_active',
            'google_analytics_tracking_id', 'google_maps_api_key', 'pusher_app_key',
            'pusher_app_cluster', 'google_auth_active', 'enable_api_key_input',
            'enable_model_selection', 'default_open_ai_text_model', 'default_open_ai_audio_model',
            // Laravel Reverb configuration keys
            'broadcast_driver', 'reverb_app_id', 'reverb_app_key', 'reverb_app_secret',
            'reverb_host', 'reverb_port', 'reverb_scheme'
        ];

        $config = Setting::whereIn('key', $keys)->get();
        $languages = Language::where('deleted_at', null)
            ->where('status', 'active')
            ->get();
        
        $currentLanguage = Language::where('code', session('locale') ?? App::getLocale())->first();
        $isRtl = $currentLanguage ? $currentLanguage->is_rtl : false;

        return ['config' => $config, 'languages' => $languages, 'isRtl' => $isRtl];
    }

    /**
     * Checks if the application has been installed.
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }
}
