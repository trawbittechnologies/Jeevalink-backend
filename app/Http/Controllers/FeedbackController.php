<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * Get all feedback entries with optional filters.
     * Admin only.
     */
    public function index(Request $request)
    {
        $query = DB::table('feedbacks')->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', $search)
                  ->orWhere('subject', 'like', $search)
                  ->orWhere('message', 'like', $search);
            });
        }

        $feedbacks = $query->get()->map(function ($f) {
            $f->_id = $f->id;
            return $f;
        });

        return response()->json([
            'success' => true,
            'message' => 'Feedback retrieved successfully.',
            'data'    => ['feedbacks' => $feedbacks]
        ]);
    }

    /**
     * Submit new feedback from user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_name' => 'required|string|max:255',
            'subject'   => 'required|string|max:255',
            'message'   => 'required|string',
            'rating'    => 'nullable|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $id = DB::table('feedbacks')->insertGetId([
            'user_name'  => $request->user_name,
            'phone'      => $request->phone,
            'subject'    => $request->subject,
            'message'    => $request->message,
            'rating'     => $request->rating ?? 5,
            'status'     => 'open',
            'user_id'    => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $feedback = DB::table('feedbacks')->find($id);

        return response()->json([
            'success' => true,
            'message' => 'Feedback submitted successfully.',
            'data'    => ['feedback' => $feedback]
        ], 201);
    }

    /**
     * Reply to feedback.
     * Admin only.
     */
    public function reply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reply' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $feedback = DB::table('feedbacks')->find((int)$id);
        if (!$feedback) {
            return response()->json(['success' => false, 'message' => 'Feedback not found.'], 404);
        }

        DB::table('feedbacks')->where('id', (int)$id)->update([
            'reply'      => $request->reply,
            'status'     => 'replied',
            'updated_at' => now(),
        ]);

        $this->logActivity('Feedback Replied', "Admin replied to feedback ID: {$id} from {$feedback->user_name}");

        return response()->json(['success' => true, 'message' => 'Reply sent successfully.', 'data' => []]);
    }

    /**
     * Update feedback status (resolve / archive).
     * Admin only.
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,replied,resolved,archived',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        DB::table('feedbacks')->where('id', (int)$id)->update([
            'status'     => $request->status,
            'updated_at' => now(),
        ]);

        $this->logActivity('Feedback Status Updated', "Feedback ID: {$id} status set to {$request->status}");

        return response()->json(['success' => true, 'message' => 'Feedback status updated.', 'data' => []]);
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
                'type'       => 'feedback',
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // non-fatal
        }
    }
}
