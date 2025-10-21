<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\User;
use app\Services\SubscriptionService;

class CannedReplyLimit implements Rule
{
    private $workspaceId;
    protected $ignoreId;

    public function __construct($workspaceId, $ignoreId = null)
    {
        $this->workspaceId = $workspaceId;
        $this->ignoreId = $ignoreId;
    }
    
    public function passes($attribute, $value)
    {
        return !SubscriptionService::isSubscriptionFeatureLimitReached($this->workspaceId, 'canned_replies_limit');
    }

    public function message()
    {
        return __('You have reached your limit. Please upgrade your account!');
    }
}
