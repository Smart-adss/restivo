<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(): View
    {
        $this->authorizeSupport();

        return view('platform.support.index', [
            'tickets' => SupportTicket::withoutGlobalScope('tenant')->with('user:id,name')->latest()->get(),
        ]);
    }

    public function show(string $publicId): View
    {
        $this->authorizeSupport();

        $ticket = SupportTicket::withoutGlobalScope('tenant')
            ->with(['user:id,name', 'messages.user:id,name'])
            ->where('public_id', $publicId)
            ->firstOrFail();

        return view('platform.support.show', [
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, string $publicId): RedirectResponse
    {
        $this->authorizeSupport();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $ticket = SupportTicket::withoutGlobalScope('tenant')->where('public_id', $publicId)->firstOrFail();

        SupportMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $ticket->update(['status' => 'pending']);

        return redirect()->route('platform.support.show', $ticket->public_id)->with('status', __('app.reply_saved'));
    }

    private function authorizeSupport(): void
    {
        abort_unless(auth()->user()->hasPermission('platform.support.manage'), 403);
    }
}
