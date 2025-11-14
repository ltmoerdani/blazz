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

Broadcast::channel('workspace.{workspaceId}', function ($user, $workspaceId) {
    // Check if user belongs to the workspace
    return $user->workspaces()->where('id', $workspaceId)->exists();
});

/*
|--------------------------------------------------------------------------
| Real-time Chat Channels
|--------------------------------------------------------------------------
|
| Real-time messaging channels for WhatsApp Web-like experience
|
*/

// Chat channels for individual contacts
Broadcast::channel('chat.{contactId}', function ($user, $contactId) {
    $contact = \App\Models\Contact::find($contactId);

    if (!$contact) {
        return false;
    }

    return $user->workspaces()->where('workspace_id', $contact->workspace_id)->exists();
});

// User presence channels
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Workspace presence for typing indicators
Broadcast::channel('workspace.{workspaceId}.presence', function ($user, $workspaceId) {
    return $user->workspaces()->where('workspace_id', $workspaceId)->exists();
});

// Message status updates
Broadcast::channel('message.{messageId}.status', function ($user, $messageId) {
    $chat = \App\Models\Chat::find($messageId);

    if (!$chat) {
        return false;
    }

    return $user->workspaces()->where('workspace_id', $chat->workspace_id)->exists();
});
