<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(): View
    {
        return view('app.support.index', [
            'tickets' => SupportTicket::query()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $ticket = SupportTicket::query()->create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'status' => 'open',
            'priority' => 'normal',
        ]);

        SupportMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return redirect()->route('app.support.index')->with('status', __('app.ticket_saved'));
    }
}
