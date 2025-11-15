<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\SignupRequest;
use App\Http\Requests\StoreUserInvite;
use App\Models\Addon;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;
use App\Models\workspace;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class RegistrationController extends BaseController
{
    protected $userService;

    public function __construct()
    {
        $this->userService = new UserService('user');
    }

    /**
     * Show the registration form
     */
    public function showRegistrationForm()
    {
        // Check if registration is enabled
        $allowRegistration = Setting::where('key', 'allow_registration')->value('value');
        if ($allowRegistration !== '1') {
            return redirect('/login')->withErrors([
                'email' => 'Registration is currently disabled.',
            ]);
        }

        $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials', 'trial_period'];
        $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        return Inertia::render('Auth/Register', $data);
    }

    /**
     * Handle registration request
     */
    public function handleRegistration(SignupRequest $request)
    {
        try {
            DB::beginTransaction();

            // Check if registration is enabled
            $allowRegistration = Setting::where('key', 'allow_registration')->value('value');
            if ($allowRegistration !== '1') {
                return back()->withErrors([
                    'email' => 'Registration is currently disabled.',
                ]);
            }

            // Create user
            $user = User::create([
                'first_name' => $request->name,
                'last_name' => '',
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'phone' => $request->phone ?? null,
                'email_verified_at' => now(),
            ]);

            // Create workspace for the user
            $workspace = $this->createWorkspace($user, $request);

            // Create team membership
            $this->createTeamMembership($user, $workspace);

            // Log registration
            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'workspace_id' => $workspace->id,
                'ip' => $request->ip(),
            ]);

            DB::commit();

            // Auto-login the user
            Auth::login($user);

            // Set workspace session
            session()->put('current_workspace', $workspace->id);
            if ($user->teams->isNotEmpty()) {
                session()->put('current_team', $user->teams->first()->id);
            }

            return redirect('/dashboard')->with('success', 'Registration successful! Welcome to ' . config('app.name') . '.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);

            return back()->withErrors([
                'email' => 'Registration failed. Please try again.',
            ])->withInput();
        }
    }

    /**
     * View team invitation
     */
    public function viewInvite($uuid)
    {
        try {
            $invite = TeamInvite::where('uuid', $uuid)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->firstOrFail();

            $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials'];
            $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
            $data['invite'] = $invite;
            $data['team'] = $invite->team;
            $data['workspace'] = $invite->team->workspace;

            return Inertia::render('Auth/Invite', $data);

        } catch (\Exception $e) {
            Log::warning('Invalid or expired team invite', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return redirect('/login')->withErrors([
                'email' => 'Invalid or expired invitation link.',
            ]);
        }
    }

    /**
     * Handle team invitation acceptance
     */
    public function invite(StoreUserInvite $request, $inviteCode)
    {
        try {
            DB::beginTransaction();

            $invite = TeamInvite::where('uuid', $inviteCode)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->firstOrFail();

            // Check if email matches the invitation
            if ($invite->email !== $request->email) {
                return back()->withErrors([
                    'email' => 'This invitation was sent to a different email address.',
                ]);
            }

            // Create user if doesn't exist
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                $user = User::create([
                    'first_name' => $request->name,
                    'last_name' => '',
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                    'phone' => $request->phone ?? null,
                    'email_verified_at' => now(),
                ]);
            }

            // Create team membership
            $this->createTeamMembership($user, $invite->team->workspace, $invite->team, $invite->role);

            // Mark invite as accepted
            $invite->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            Log::info('Team invite accepted', [
                'user_id' => $user->id,
                'email' => $user->email,
                'invite_id' => $invite->id,
                'team_id' => $invite->team_id,
                'ip' => $request->ip(),
            ]);

            DB::commit();

            // Auto-login the user
            Auth::login($user);

            // Set workspace session
            session()->put('current_workspace', $invite->team->workspace_id);
            session()->put('current_team', $invite->team_id);

            return redirect('/dashboard')->with('success', 'Welcome to the team!');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Team invite acceptance failed', [
                'error' => $e->getMessage(),
                'invite_code' => $inviteCode,
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);

            return back()->withErrors([
                'email' => 'Failed to accept invitation. Please try again.',
            ])->withInput();
        }
    }

    /**
     * Create workspace for new user
     */
    private function createWorkspace($user, $request)
    {
        $trialPeriod = Setting::where('key', 'trial_period')->value('value') ?? 7;

        $workspace = workspace::create([
            'name' => $request->company_name ?? $user->name . "'s Workspace",
            'address' => $request->country ?? 'Unknown',
            'timezone' => $request->timezone ?? 'UTC',
            'created_by' => $user->id,
            'metadata' => [
                'trial_expires_at' => now()->addDays($trialPeriod)->toISOString(),
                'registration_source' => 'web',
                'default_whatsapp_accounts_limit' => 1,
            ]
        ]);

        return $workspace;
    }

    /**
     * Create team membership for user
     */
    private function createTeamMembership($user, $workspace, $team = null, $role = 'owner')
    {
        // Create team if not provided
        if (!$team) {
            $team = Team::create([
                'workspace_id' => $workspace->id,
                'name' => 'Default Team',
                'description' => 'Default team for workspace',
            ]);
        }

        // Create team membership
        $team->users()->attach($user->id, [
            'role' => $role,
            'joined_at' => now(),
        ]);

        return $team;
    }

    /**
     * Check registration availability
     */
    public function checkRegistrationAvailability(Request $request)
    {
        $allowRegistration = Setting::where('key', 'allow_registration')->value('value');
        $emailExists = User::where('email', $request->email)->exists();

        return response()->json([
            'registration_enabled' => $allowRegistration === '1',
            'email_available' => !$emailExists,
            'social_login_enabled' => [
                'facebook' => Setting::where('key', 'allow_facebook_login')->value('value') === '1',
                'google' => Setting::where('key', 'allow_google_login')->value('value') === '1',
            ],
        ]);
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to resend verification email.',
            ], 401);
        }

        $user = Auth::user();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Your email is already verified.',
            ]);
        }

        try {
            // Send verification email manually
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\CustomEmailVerification($user));

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to resend verification email', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email. Please try again.',
            ], 500);
        }
    }
}