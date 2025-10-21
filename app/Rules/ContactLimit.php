<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\User;
use app\Services\SubscriptionService;

class ContactLimit implements Rule
{
    protected $ignoreId;

    public function __construct($ignoreId = null)
    {
        $this->ignoreId = $ignoreId;
    }
    
    public function passes($attribute, $value)
    {
        $workspaceId = session()->get('current_workspace');

        return !SubscriptionService::isSubscriptionFeatureLimitReached($workspaceId, 'contacts_limit');
    }

    public function message()
    {
        return __('You have reached your limit of contacts. Please upgrade your account to add more!');
    }
}
