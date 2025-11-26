<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use App\Models\Team;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model {
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'workspaces';
    protected $guarded = [];
    public $timestamps = true;

    public function listAll($searchTerm, $userId = null)
    {
        $query = $this->with(['teams.user', 'owner.user', 'subscription.plan'])
            ->when($userId !== null, function ($query) use ($userId) {
                $query->whereHas('teams', function ($teamsQuery) use ($userId) {
                    $teamsQuery->where('user_id', $userId);
                });
            })
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', '%' . $searchTerm . '%');
            })
            ->withCount('teams')
            ->latest()
            ->paginate(10);

        return $query;
    }

    /**
     * Get the teams associated with the workspace
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function teams()
    {
        return $this->hasMany(Team::class, 'workspace_id');
    }

    /**
     * Get the owner team of the workspace
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo(Team::class, 'id', 'workspace_id')->where('role', 'owner');
    }

    /**
     * Get the subscription associated with the workspace
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'id', 'workspace_id');
    }
}
