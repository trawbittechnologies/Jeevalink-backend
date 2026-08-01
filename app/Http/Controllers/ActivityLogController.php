<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    /**
     * Get paginated activity logs.
     * Admin only.
     */
    public function index(Request $request)
    {
        $perPage = (int)($request->per_page ?? 50);
        $page    = (int)($request->page ?? 1);
        $offset  = ($page - 1) * $perPage;

        $query = DB::table('activity_logs')->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', $search)
                  ->orWhere('details', 'like', $search)
                  ->orWhere('admin_name', 'like', $search);
            });
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $total = $query->count();
        $logs  = $query->skip($offset)->take($perPage)->get()->map(function ($l) {
            $l->_id = $l->id;
            return $l;
        });

        return response()->json([
            'success' => true,
            'message' => 'Activity logs retrieved successfully.',
            'data'    => [
                'logs'       => $logs,
                'total'      => $total,
                'page'       => $page,
                'per_page'   => $perPage,
                'total_pages'=> (int)ceil($total / $perPage),
            ]
        ]);
    }

    /**
     * Clear all activity logs.
     * Admin only — DANGEROUS.
     */
    public function clear(Request $request)
    {
        DB::table('activity_logs')->truncate();

        return response()->json([
            'success' => true,
            'message' => 'All activity logs cleared.',
            'data'    => []
        ]);
    }

    /**
     * Static helper to log an activity from any controller.
     */
    public static function log(string $action, string $details, string $type = 'general'): void
    {
        try {
            $admin = Auth::user();
            DB::table('activity_logs')->insert([
                'action'     => $action,
                'details'    => $details,
                'admin_name' => $admin ? ($admin->primary_name ?? 'System Admin') : 'System',
                'admin_id'   => $admin ? $admin->id : null,
                'type'       => $type,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // non-fatal logging failure
        }
    }
}
