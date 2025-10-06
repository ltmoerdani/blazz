<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingPayment extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = false;

    public function listAll($searchTerm, $workspaceId = null)
    {
        return $this->with(['workspace'])
                    ->when($workspaceId !== null, function ($query) use ($workspaceId) {
                        return $query->where('workspace_id', $workspaceId);
                    })
                    ->latest()
                    ->paginate(10);
    }

    public function workspace()
    {
        return $this->belongsTo(workspace::class, 'workspace_id', 'id');
    }
}
