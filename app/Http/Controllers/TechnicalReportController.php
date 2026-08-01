<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TechnicalReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = DB::table('technical_reports')->orderBy('created_at', 'desc')->get()->map(function ($r) {
            $r->id = 'TR-' . str_pad($r->id, 4, '0', STR_PAD_LEFT);
            return $r;
        });

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        
        $id = DB::table('technical_reports')->insertGetId([
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category ?? 'General',
            'priority' => $request->priority ?? 'Medium',
            'reporter_role' => $user ? $user->role : 'User',
            'status' => 'Open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = DB::table('technical_reports')->find($id);
        $report->id = 'TR-' . str_pad($report->id, 4, '0', STR_PAD_LEFT);

        return response()->json([
            'success' => true,
            'message' => 'Technical report created successfully.',
            'data' => $report
        ], 201);
    }

    public function reply(Request $request, $id)
    {
        $numericId = (int) str_replace('TR-', '', $id);
        
        $validator = Validator::make($request->all(), [
            'reply' => 'required|string',
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        DB::table('technical_reports')->where('id', $numericId)->update([
            'reply' => $request->reply,
            'status' => $request->status,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Reply sent.', 'data' => []]);
    }
}
