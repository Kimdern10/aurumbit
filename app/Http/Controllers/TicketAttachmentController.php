<?php

namespace App\Http\Controllers;

use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    /**
     * Show a file attached to a support message. Customers only see files on
     * their own tickets; admins (on the admin route) can open any.
     */
    public function __invoke(Request $request, TicketMessage $message): StreamedResponse
    {
        abort_unless($request->user()->is_admin || $message->ticket->user_id === $request->user()->id, 404);
        abort_unless($message->attachment_path !== null && Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
