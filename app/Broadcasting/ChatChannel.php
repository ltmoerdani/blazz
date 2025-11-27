<?php

namespace App\Broadcasting;

use App\Models\User;
use App\Models\Contact;

class ChatChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate the user's access to the chat channel.
     *
     * @param User $user
     * @param int $contactId
     * @return array|bool
     */
    public function join(User $user, $contactId): array|bool
    {
        // Check if user has access to this contact's workspace
        $contact = Contact::find($contactId);

        if (!$contact) {
            return false;
        }

        // Check if user belongs to the workspace that owns this contact
        return $user->workspaces()->where('workspace_id', $contact->workspace_id)->exists();
    }
}
