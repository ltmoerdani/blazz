<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TicketService
{
    private $workspaceId;

    public function __construct($workspaceId = null)
    {
        // Backward compatible: fallback to session if not provided
        $this->workspaceId = $workspaceId ?? session('current_workspace');
    }

    public function store(object $request){
        Ticket::create([
            'reference' => 'SUP-' . sprintf('%06d', Ticket::count() + 1) . '-' . now()->format('ymd'),
            'user_id' => Auth::user()->role === 'user' ? Auth::user()->id : $request->user(),
            'category_id' => $request->category,
            'subject' => $request->subject,
            'message' => $request->message,
            'assigned_to' => Auth::user()->role === 'user' ? null : Auth::user()->id,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function assignTicket(object $request, $ticketUuid){
        $ticket = Ticket::where('uuid', $ticketUuid)->first();
        $ticket->update([
            'assigned_to' => $request->user(),
            'updated_at' => now()
        ]);
    }

    public function changeStatus(object $request, $ticketUuid){
        $ticket = Ticket::where('uuid', $ticketUuid)->first();
        $ticket->update([
            'status' => $request->status,
            'updated_at' => now()
        ]);
    }

    public function changePriority(object $request, $ticketUuid){
        $ticket = Ticket::where('uuid', $ticketUuid)->first();
        $ticket->update([
            'priority' => $request->priority,
            'updated_at' => now()
        ]);
    }

    public function comment(object $request, $ticketUuid){
        $ticket = Ticket::where('uuid', $ticketUuid)->first();
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::user()->id,
            'message' => $request->message,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function markAsRead($ticketUuid){
        $ticket = Ticket::where('uuid', $ticketUuid)->first();
        TicketComment::where('ticket_id', $ticket->id)->update([
            'seen' => 1
        ]);
    }
}