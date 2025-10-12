<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignLog extends Model {
    use HasFactory;

    protected $guarded = [];
    public $timestamps = true;

    /**
     * Convert datetime value to workspace timezone
     */
    private function convertToWorkspaceTimezone($value)
    {
        return DateTimeHelper::convertToWorkspaceTimezone($value)->toDateTimeString();
    }

    public function getCreatedAtAttribute($value)
    {
        return $this->convertToWorkspaceTimezone($value);
    }

    public function getUpdatedAtAttribute($value)
    {
        // Use same conversion logic as created_at for consistency
        // Both created_at and updated_at should use identical timezone conversion
        return $this->convertToWorkspaceTimezone($value);
    }

    public function campaign(){
        return $this->belongsTo(Campaign::class, 'campaign_id', 'id');
    }

    public function contact(){
        return $this->belongsTo(Contact::class, 'contact_id', 'id');
    }

    public function chat(){
        return $this->belongsTo(Chat::class, 'chat_id', 'id');
    }

    public function retries(){
        return $this->hasMany(CampaignLogRetry::class);
    }

    public function whatsappSession(){
        return $this->belongsTo(WhatsAppSession::class, 'whatsapp_session_id', 'id');
    }
}
