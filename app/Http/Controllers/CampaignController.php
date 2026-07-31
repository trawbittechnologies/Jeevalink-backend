<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Display a listing of the campaigns.
     */
    public function index(Request $request)
    {
        $query = Campaign::query();

        if ($request->has('category') && $request->category !== 'all' && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('venue', 'like', "%{$search}%")
                  ->orWhere('organizer_name', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%");
            });
        }

        $campaigns = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $campaigns
        ]);
    }

    /**
     * Store a newly created campaign in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'required|string',
            'venue' => 'nullable|string|max:255',
            'event_date' => 'nullable|string|max:100',
            'event_time' => 'nullable|string|max:100',
            'organizer_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'image_url' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'block' => 'nullable|string|max:100',
            'author_name' => 'nullable|string|max:255',
            'author_role' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        if ($user) {
            $validated['user_id'] = $user->id;
            if (empty($validated['author_name'])) {
                $validated['author_name'] = $user->full_name ?? $user->name ?? 'User';
            }
            if (empty($validated['author_role'])) {
                $validated['author_role'] = $user->role ?? 'user';
            }
        }

        $campaign = Campaign::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Campaign created successfully',
            'data' => $campaign
        ], 201);
    }

    /**
     * Display the specified campaign.
     */
    public function show($id)
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $campaign
        ]);
    }

    /**
     * Update the specified campaign in storage.
     */
    public function update(Request $request, $id)
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|required|string',
            'venue' => 'nullable|string|max:255',
            'event_date' => 'nullable|string|max:100',
            'event_time' => 'nullable|string|max:100',
            'organizer_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'image_url' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'block' => 'nullable|string|max:100',
            'author_name' => 'nullable|string|max:255',
            'author_role' => 'nullable|string|max:100',
        ]);

        $campaign->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Campaign updated successfully',
            'data' => $campaign
        ]);
    }

    /**
     * Remove the specified campaign from storage.
     */
    public function destroy($id)
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully'
        ]);
    }

    /**
     * Toggle like for the specified campaign.
     */
    public function toggleLike($id)
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }

        $campaign->increment('likes_count');

        return response()->json([
            'success' => true,
            'data' => $campaign
        ]);
    }

    /**
     * Increment share count for the specified campaign.
     */
    public function incrementShare($id)
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found'
            ], 404);
        }

        $campaign->increment('shares_count');

        return response()->json([
            'success' => true,
            'data' => $campaign
        ]);
    }
}
