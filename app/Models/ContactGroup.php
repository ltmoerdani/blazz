<?php

namespace App\Models;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactGroup extends Model {
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [];
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    public function contacts(){
        return $this->belongsToMany(Contact::class, 'contact_contact_group', 'contact_group_id', 'contact_id')
            ->using(ContactContactGroup::class)
            ->withTimestamps();
    }

    public function countAllContacts($workspaceId){
        return $this->contacts->where('workspace_id', $workspaceId)->count();
    }

    public function getAll($workspaceId, $searchTerm)
    {
        return $this->where('workspace_id', $workspaceId)
            ->where('deleted_at', null)
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', '%' . $searchTerm . '%');
            })
            ->latest()
            ->paginate(10);
    }

    public function getRow($uuid, $workspaceId)
    {
        return $this->withCount(['contacts as contact_count' => function ($query) use ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }])
        ->where('uuid', $uuid)
        ->where('deleted_at', null)
        ->first();
    }

    public function countAll($workspaceId)
    {
        return $this->where('workspace_id', $workspaceId)->where('deleted_at', null)->count();
    }
}
