<?php

namespace App\Services;

use App\Models\ChatLog;
use App\Models\ChatNote;
use App\Models\Contact;
use Illuminate\Support\Facades\Auth;

class ChatNoteService
{
    private $workspaceId;

    public function __construct($workspaceId = null)
    {
        // Backward compatible: fallback to session if not provided
        $this->workspaceId = $workspaceId ?? session('current_workspace');
    }

    public function get(object $request)
    {
        return (new ChatNote)->listAll($request->query('search'));
    }

    public function getByUuid($uuid = null)
    {
        return ChatNote::where('id', $uuid)->first();
    }

    public function store(object $request, $uuid = null)
    {
        $contact = Contact::where('uuid', $request->contact)->first();

        $note = $uuid === null ? new ChatNote() : ChatNote::where('uuid', $uuid)->firstOrFail();
        $note->contact_id = $contact->id;
        $note->content = $request->notes;
        $note->created_by = Auth::id();
        $note->save();

        ChatLog::insert([
            'contact_id' => $contact->id,
            'entity_type' => 'notes',
            'entity_id' => $note->id,
            'created_at' => now()
        ]);

        return $note;
    }

    public function delete($uuid)
    {
        $note = ChatNote::where('uuid', $uuid)->firstOrFail();
        $note->deleted_at = date('Y-m-d H:i:s');
        $note->deleted_by = Auth::id();
        $note->save();
    }
}
