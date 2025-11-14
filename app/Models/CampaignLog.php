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
        // Apply workspace timezone conversion for updated_at timestamp
        // Maintains consistency with created_at conversion method
        if ($value) {
            return $this->convertToWorkspaceTimezone($value);
        }
        return $value;
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

    public function whatsappAccount(){
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id', 'id');
    }
}
