<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    /**
     * List all support tickets with optional filters.
     * Admin only.
     */
    public function index(Request $request)
    {
        $query = DB::table('support_tickets')->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', $search)
                  ->orWhere('ticket_id', 'like', $search)
                  ->orWhere('issue_type', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        $tickets = $query->get()->map(function ($t) {
            $t->_id = $t->id;
            return $t;
        });

        return response()->json([
            'success' => true,
            'message' => 'Tickets retrieved successfully.',
            'data'    => ['tickets' => $tickets]
        ]);
    }

    /**
     * Submit a new support ticket.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_name'    => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'issue_type'   => 'required|string|max:255',
            'description'  => 'required|string',
            'priority'     => 'nullable|in:Low,Medium,High,Critical',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $ticketId = 'TKT-' . strtoupper(Str::random(6));

        $id = DB::table('support_tickets')->insertGetId([
            'ticket_id'    => $ticketId,
            'user_name'    => $request->user_name,
            'phone_number' => $request->phone_number,
            'issue_type'   => $request->issue_type,
            'description'  => $request->description,
            'priority'     => $request->priority ?? 'Medium',
            'status'       => 'open',
            'user_id'      => Auth::id(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $ticket = DB::table('support_tickets')->find($id);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully.',
            'data'    => ['ticket' => $ticket]
        ], 201);
    }

    /**
     * Reply to a support ticket.
     * Admin only.
     */
    public function reply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_reply' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $ticket = DB::table('support_tickets')->find((int)$id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        DB::table('support_tickets')->where('id', (int)$id)->update([
            'admin_reply' => $request->admin_reply,
            'status'      => 'in_progress',
            'updated_at'  => now(),
        ]);

        $this->logActivity('Ticket Replied', "Admin replied to ticket {$ticket->ticket_id} from {$ticket->user_name}");

        return response()->json(['success' => true, 'message' => 'Reply sent.', 'data' => []]);
    }

    /**
     * Update ticket status.
     * Admin only.
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        DB::table('support_tickets')->where('id', (int)$id)->update([
            'status'     => $request->status,
            'updated_at' => now(),
        ]);

        $this->logActivity('Ticket Status Changed', "Ticket ID: {$id} status updated to {$request->status}");

        return response()->json(['success' => true, 'message' => 'Ticket status updated.', 'data' => []]);
    }

    private function logActivity(string $action, string $details): void
    {
        try {
            $admin = Auth::user();
            DB::table('activity_logs')->insert([
                'action'     => $action,
                'details'    => $details,
                'admin_name' => $admin ? $admin->primary_name : 'System Admin',
                'admin_id'   => $admin ? $admin->id : null,
                'type'       => 'support',
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // non-fatal
        }
    }
}
