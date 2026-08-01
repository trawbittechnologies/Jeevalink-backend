<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BloodRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RequestController extends Controller
{
    /**
     * Create a new blood request.
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'errors' => []
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'patient_name' => 'required|string|max:255',
            'blood_group' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'units_required' => 'required|numeric',
            'hospital_name' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'contact_number' => 'required|string|max:20',
            'required_by_date' => 'required|date',
            'urgency_level' => 'nullable|in:Normal,Urgent,Emergency SOS',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['requested_by'] = $user->id;
        $data['verified'] = ($user->role === 'admin' || $user->role === 'volunteer');

        $bloodRequest = BloodRequest::create($data);

        // Fetch matched arrays to pass to notifier
        $bloodRequestArr = BloodRequest::findById($bloodRequest->id);

        if (!$bloodRequestArr) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create blood request.',
                'errors' => []
            ], 500);
        }

        // Trigger asynchronous matching notifications
        NotificationService::notifyMatchingDonors($bloodRequestArr);

        return response()->json([
            'success' => true,
            'message' => 'Blood request created successfully.',
            'data' => [
                'request' => $bloodRequestArr
            ]
        ], 201);
    }

    /**
     * Fetch requests with filters.
     */
    public function index(Request $request)
    {
        $filters = [
            'bloodGroup' => $request->query('bloodGroup', ''),
            'district' => $request->query('district', ''),
            'city' => $request->query('city', ''),
            'urgencyLevel' => $request->query('urgencyLevel', ''),
            'status' => $request->query('status', ''),
            'verified' => $request->query('verified', '')
        ];

        $requests = BloodRequest::getAll($filters);

        return response()->json([
            'success' => true,
            'message' => 'Requests retrieved successfully.',
            'data' => [
                'requests' => $requests
            ]
        ]);
    }

    /**
     * Mark a request as fulfilled.
     */
    public function fulfill(Request $request, $id)
    {
        $requestId = (int)$id;
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'errors' => []
            ], 401);
        }

        $bloodRequestArr = BloodRequest::findById($requestId);

        if (!$bloodRequestArr) {
            return response()->json([
                'success' => false,
                'message' => 'Blood request not found.',
                'errors' => []
            ], 404);
        }

        if ($bloodRequestArr['status'] === 'Fulfilled') {
            return response()->json([
                'success' => false,
                'message' => 'This blood request has already been fulfilled.',
                'errors' => []
            ], 400);
        }

        $fulfilled = BloodRequest::fulfill($requestId, $user->id);

        if (!$fulfilled) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update blood request status.',
                'errors' => []
            ], 500);
        }

        // Notify the requester
        $donor = User::findById($user->id);
        NotificationService::notifyFulfillment($bloodRequestArr, $donor);

        // Reward the donor
        User::incrementStats($user->id, 'reward_points', 100);
        User::incrementStats($user->id, 'lives_saved', 3);
        User::incrementStats($user->id, 'total_donations', 1);

        // Update user's last donated date to today
        User::updateProfile($user->id, ['last_donated_date' => date('Y-m-d')]);

        // Send confirmation reward points notification
        NotificationService::notifyRewardPoints($user->id, 100, "Donating blood and saving 3 lives!");

        $updatedRequest = BloodRequest::findById($requestId);

        return response()->json([
            'success' => true,
            'message' => 'Blood request marked as fulfilled successfully.',
            'data' => [
                'request' => $updatedRequest
            ]
        ]);
    }

    /**
     * Verify a blood request.
     */
    public function verify(Request $request, $id)
    {
        $requestId = (int)$id;

        $bloodRequestArr = BloodRequest::findById($requestId);

        if (!$bloodRequestArr) {
            return response()->json([
                'success' => false,
                'message' => 'Blood request not found.',
                'errors' => []
            ], 404);
        }

        $verified = BloodRequest::verify($requestId);

        if (!$verified) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify blood request.',
                'errors' => []
            ], 500);
        }

        $updatedRequest = BloodRequest::findById($requestId);

        return response()->json([
            'success' => true,
            'message' => 'Blood request verified successfully.',
            'data' => [
                'request' => $updatedRequest
            ]
        ]);
    }

    /**
     * Delete/Reject a blood request.
     */
    public function destroy(Request $request, $id)
    {
        $requestId = (int)$id;
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'errors' => []
            ], 401);
        }

        $bloodRequestArr = BloodRequest::findById($requestId);

        if (!$bloodRequestArr) {
            return response()->json([
                'success' => false,
                'message' => 'Blood request not found.',
                'errors' => []
            ], 404);
        }

        // Only request creator or admin can delete a request
        if ($bloodRequestArr['requested_by'] !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this request.',
                'errors' => []
            ], 403);
        }

        $deleted = BloodRequest::deleteRequest($requestId);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete blood request.',
                'errors' => []
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Blood request removed successfully.',
            'data' => []
        ]);
    }

    /**
     * Accept a pending blood request.
     */
    public function accept(Request $request, $id)
    {
        $requestId = (int)$id;
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'errors' => []
            ], 401);
        }

        $bloodRequest = BloodRequest::find($requestId);

        if (!$bloodRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Blood request not found.',
                'errors' => []
            ], 404);
        }

        if ($bloodRequest->status === 'Fulfilled') {
            return response()->json([
                'success' => false,
                'message' => 'This blood request has already been fulfilled.',
                'errors' => []
            ], 400);
        }

        if ($bloodRequest->accepted_by !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This blood request has already been accepted by another donor.',
                'errors' => []
            ], 400);
        }

        $bloodRequest->accepted_by = $user->id;
        $bloodRequest->save();

        $freshRequest = BloodRequest::findById($requestId);
        $donor = User::findById($user->id);

        // Notify the creator of the request
        NotificationService::notifyAcceptance($freshRequest, $donor);

        return response()->json([
            'success' => true,
            'message' => 'Blood request accepted successfully.',
            'data' => [
                'request' => $freshRequest
            ]
        ]);
    }

    /**
     * Get top 5 recommended donors for a blood request.
     */
    public function topDonors(Request $request, $id)
    {
        $requestId = (int)$id;
        $bloodRequest = BloodRequest::find($requestId);

        if (!$bloodRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Blood request not found.',
                'errors' => []
            ], 404);
        }

        $compatibilityMap = [
            'A+' => ['A+', 'A-', 'O+', 'O-'],
            'A-' => ['A-', 'O-'],
            'B+' => ['B+', 'B-', 'O+', 'O-'],
            'B-' => ['B-', 'O-'],
            'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'AB-' => ['A-', 'B-', 'AB-', 'O-'],
            'O+' => ['O+', 'O-'],
            'O-' => ['O-'],
        ];

        $compatibleGroups = $compatibilityMap[$bloodRequest->blood_group] ?? [$bloodRequest->blood_group];

        $donors = User::whereIn('blood_group', $compatibleGroups)
            ->where('available_for_donation', true)
            ->where('status', 'Active')
            ->where('role', 'user')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Top recommended donors retrieved successfully.',
            'data' => $donors
        ]);
    }
}
