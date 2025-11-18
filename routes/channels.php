<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chats', function ($user) {
    return true; // Adjust authentication logic if needed
});

// Authorization for workspace chat list channel (used by NewChatEvent)
Broadcast::channel('chats.ch{workspaceId}', function ($user, $workspaceId) {
    // Check if user belongs to the workspace via teams
    if ($user->teams()->where('workspace_id', $workspaceId)->exists()) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});

/*
|--------------------------------------------------------------------------
| Real-time Chat Channels (Following Riset Pattern)
|--------------------------------------------------------------------------
|
| Private workspace channels untuk real-time messaging
| Based on best practice from riset documentation Section 4.2
|
*/

// Primary workspace channel - all users in workspace see new messages
Broadcast::channel('workspace.{workspaceId}', function ($user, $workspaceId) {
    // Check if user belongs to the workspace via teams
    if ($user->teams()->where('workspace_id', $workspaceId)->exists()) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar ?? null,
        ];
    }
    return false;
});

// Specific contact chat channel - for users viewing specific contact
Broadcast::channel('workspace.{workspaceId}.chat.{contactId}', function ($user, $workspaceId, $contactId) {
    // Verify user belongs to workspace
    if (!$user->teams()->where('workspace_id', $workspaceId)->exists()) {
        return false;
    }
    
    // Verify contact exists in workspace
    $contact = \App\Models\Contact::where('workspace_id', $workspaceId)
        ->where('id', $contactId)
        ->first();
    
    if (!$contact) {
        return false;
    }
    
    return [
        'id' => $user->id,
        'name' => $user->name,
        'viewing_contact_id' => $contactId,
    ];
});

// Legacy chat channel for backward compatibility
Broadcast::channel('chat.{contactId}', function ($user, $contactId) {
    $contact = \App\Models\Contact::find($contactId);

    if (!$contact) {
        return false;
    }

    // Check if user has access to the contact's workspace via teams
    return $user->teams()->where('workspace_id', $contact->workspace_id)->exists();
});

// User presence channels
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Workspace presence for typing indicators
Broadcast::channel('workspace.{workspaceId}.presence', function ($user, $workspaceId) {
    return $user->teams()->where('workspace_id', $workspaceId)->exists();
});

// Message status updates
Broadcast::channel('message.{messageId}.status', function ($user, $messageId) {
    $chat = \App\Models\Chat::find($messageId);

    if (!$chat) {
        return false;
    }

    // Check if user has access to the chat's workspace via teams
    return $user->teams()->where('workspace_id', $chat->workspace_id)->exists();
});

// Contact presence channels (for individual contact updates)
Broadcast::channel('contact.{contactId}.presence', function ($user, $contactId) {
    $contact = \App\Models\Contact::find($contactId);

    if (!$contact) {
        return false;
    }

    // Check if user has access to the contact's workspace via teams
    return $user->teams()->where('workspace_id', $contact->workspace_id)->exists();
});
