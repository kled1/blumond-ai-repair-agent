<?php

namespace App\Http\Controllers;

use App\Agent\SupportAgent;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;

class TicketController extends Controller
{
    public function index()
    {
        return Ticket::latest()->limit(20)->get(['id', 'channel', 'customer_phone', 'status', 'assigned_queue', 'created_at']);
    }

    /** Create a ticket and run the agent on it (synchronously, to keep the demo simple). */
    public function store(StoreTicketRequest $request, SupportAgent $agent)
    {
        $data = $request->safe()->except('photo');
        // Private disk (storage/app/private): photos are never publicly reachable by URL.
        $data['photo_path'] = $request->file('photo')?->store('ticket-photos', 'local');

        $ticket = Ticket::create($data);

        return response()->json($agent->handle($ticket), 201);
    }

    public function show(Ticket $ticket)
    {
        return $ticket->load('steps');
    }
}
