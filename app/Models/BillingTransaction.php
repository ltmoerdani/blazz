<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingTransaction extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = true;

    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToOrganizationTimezone($value)->toDateTimeString();
    }

    public function getUpdatedAtAttribute($value)
    {
        return DateTimeHelper::convertToOrganizationTimezone($value)->toDateTimeString();
    }

    public function listAll($searchTerm, $workspaceId = null)
    {
        return $this->whereHas('workspace', function ($query) {
                        $query->whereNull('deleted_at');
                    })
                    ->with(['workspace' => function ($query) {
                        $query->whereNull('deleted_at');
                    }])
                    ->when($workspaceId !== null, function ($query) use ($workspaceId) {
                        return $query->where('workspace_id', $workspaceId);
                    })
                    ->where(function ($query) use ($searchTerm) {
                        $query->where('description', 'like', '%' . $searchTerm . '%');
                    })
                    ->latest()
                    ->paginate(10);
    }

    public function workspace()
    {
        return $this->belongsTo(workspace::class, 'workspace_id', 'id');
    }
}
