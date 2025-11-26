<?php

namespace App\Models;

use App\Mail\CustomEmailVerification;
use App\Models\Team;
use App\Models\Role;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
    
    public function listAll($role, $searchTerm, $workspaceId = null)
    {
        $query = $this->where(function ($query) use ($role) {
                if ($role === 'user') {
                    $query->where('users.role', '=', 'user');
                } else {
                    $query->where('users.role', '!=', 'user');
                }
            })
            ->where(function ($query) use ($searchTerm) {
                $query->where('first_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('email', 'like', '%' . $searchTerm . '%')
                    ->orWhere('phone', 'like', '%' . $searchTerm . '%');
            })
            ->latest('users.created_at');

        if ($workspaceId !== null) {
            $query->join('teams', 'teams.user_id', '=', 'users.id')
                ->where('teams.Workspace_id', '=', $workspaceId)
                ->select('users.*', 'teams.role');
        }

        return $query->paginate(10);
    }

    /**
     * Get the teams associated with the user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the teams with their associated workspaces
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function teamsWithWorkspaces(){
        return $this->teams()->with('workspace');
    }

    /**
     * Get the role associated with the user
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role(){
        return $this->belongsTo(Role::class, 'role', 'name');
    }

    /**
     * Send email verification notification to the user
     *
     * @return void
     */
    public function sendEmailVerificationNotification(){
        try {
            Mail::to($this->email)->send(new CustomEmailVerification($this));
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
        }
    }

    /**
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    /**
     * Determine if the user has verified their email address
     *
     * @return bool
     */
    public function hasVerifiedEmail()
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Mark the given user's email as verified.
     *
     * @return bool
     */
    /**
     * Mark the given user's email as verified
     *
     * @return bool
     */
    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    /**
     * Get the email address that should be used for verification
     *
     * @return string
     */
    public function getEmailForVerification()
    {
        return $this->email;
    }
}
