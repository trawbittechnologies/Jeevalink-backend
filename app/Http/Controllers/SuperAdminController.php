<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BloodRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    /**
     * Get Super Admin dashboard.
     */
    public function dashboard(Request $request)
    {
        return $this->metrics($request);
    }

    /**
     * Get Super Admin metrics and district analytics.
     */
    public function metrics(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        $district = $user ? $user->district : null;

        $userQuery = User::query();
        $requestQuery = BloodRequest::query();

        if ($district) {
            $userQuery->where('district', $district);
            $requestQuery->where('district', $district);
        }

        $totalBlockAdmins = User::where('role', 'block_admin')
            ->when($district, function($q) use ($district) {
                return $q->where('district', $district);
            })->count();

        $totalVolunteers = User::where('role', 'volunteer')
            ->when($district, function($q) use ($district) {
                return $q->where('district', $district);
            })->count();

        $totalUsers = (clone $userQuery)->where('role', 'user')->count();
        $totalRequests = $requestQuery->count();
        $fulfilledRequests = (clone $requestQuery)->where('status', 'fulfilled')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'district' => $district ?? 'Global',
                'total_block_admins' => $totalBlockAdmins,
                'total_volunteers' => $totalVolunteers,
                'total_users' => $totalUsers,
                'total_requests' => $totalRequests,
                'fulfilled_requests' => $fulfilledRequests,
                'fulfillment_rate' => $totalRequests > 0 ? round(($fulfilledRequests / $totalRequests) * 100, 1) : 100,
            ]
        ]);
    }

    /**
     * Get list of Block Admins.
     */
    public function getBlockAdmins(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        $district = $user ? $user->district : null;

        $blockAdmins = User::where('role', 'block_admin')
            ->when($district, function($q) use ($district) {
                return $q->where('district', $district);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $blockAdmins->map(function ($ba) {
            return [
                'id' => $ba->id,
                'primary_name' => $ba->primary_name ?? $ba->name,
                'email' => $ba->email,
                'mobile' => $ba->mobile ?? 'N/A',
                'district' => $ba->district ?? 'N/A',
                'city' => $ba->city ?? 'N/A',
                'role' => $ba->role,
                'status' => $ba->status ?? 'Active',
                'secondary_name' => $ba->secondary_name ?? '',
                'secondary_name' => $ba->secondary_name ?? '',
                'secondaryContactNumber' => $ba->secondary_phone ?? '',
                'secondary_contact_number' => $ba->secondary_phone ?? '',
                'secondary_phone' => $ba->secondary_phone ?? '',
                'whatsapp_number' => $ba->whatsapp_number ?? '',
                'created_at' => $ba->created_at ? $ba->created_at->toIso8601String() : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Create a new Block Admin.
     */
    public function createBlockAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'primary_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|unique:users,mobile',
            'district' => 'required|string',
            'city' => 'required|string',
        ]);

        if ($validator->fails()) {
            \Log::error('Block admin validation failed: ', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors()
            ], 422);
        }

        $password = 'BA@' . Str::random(8);

        $blockAdmin = User::create([
            'primary_name' => $request->primary_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'secondary_name' => $request->secondary_name ?? $request->secondary_name ?? null,
            'secondary_phone' => $request->secondaryContactNumber ?? $request->secondary_contact_number ?? $request->secondary_phone ?? null,
            'whatsapp_number' => $request->whatsapp_number ?? $request->whatsapp ?? null,
            'district' => $request->district,
            'city' => $request->city,
            'role' => 'block_admin',
            'status' => 'Active',
            'is_verified' => true,
            'password_hash' => Hash::make($password),
        ]);

        try {
            Mail::raw(
                "Hello {$request->primary_name},\n\nYour Block Admin account for {$request->city}, {$request->district} has been created.\n\nEmail: {$request->email}\nPassword: {$password}\n\nLogin at: https://jeevalink-frontend.vercel.app/login",
                function ($mail) use ($request) {
                    $mail->to($request->email)->subject('JeevaLink - Block Admin Account Created');
                }
            );
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Block Admin created successfully.',
            'data' => [
                'user' => User::findById($blockAdmin->id),
                'generated_password' => $password
            ]
        ], 201);
    }

    /**
     * Update Block Admin.
     */
    public function updateBlockAdmin(Request $request, $id)
    {
        $blockAdmin = User::where('role', 'block_admin')->find($id);

        if (!$blockAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Block Admin not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'primary_name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'mobile' => 'sometimes|string|unique:users,mobile,' . $id,
            'district' => 'sometimes|string',
            'city' => 'sometimes|string',
            'status' => 'sometimes|string|in:Active,Inactive,Suspended',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        \Log::info('updateBlockAdmin called', ['id' => $id, 'payload' => $request->all()]);

        if ($request->has('primary_name')) {
            \Log::info('updating primary_name to: ' . $request->primary_name);
            $blockAdmin->primary_name = $request->primary_name;
        }
        if ($request->has('email')) $blockAdmin->email = $request->email;
        if ($request->has('mobile')) $blockAdmin->mobile = $request->mobile;
        if ($request->has('district')) $blockAdmin->district = $request->district;
        if ($request->has('city')) $blockAdmin->city = $request->city;
        if ($request->has('secondary_name') || $request->has('secondary_name')) {
            $blockAdmin->secondary_name = $request->secondary_name ?? $request->secondary_name;
        }
        if ($request->has('secondaryContactNumber') || $request->has('secondary_contact_number') || $request->has('secondary_phone')) {
            $blockAdmin->secondary_phone = $request->secondaryContactNumber ?? $request->secondary_contact_number ?? $request->secondary_phone;
        }
        if ($request->has('whatsapp_number')) $blockAdmin->whatsapp_number = $request->whatsapp_number;
        if ($request->has('status')) $blockAdmin->status = $request->status;

        if ($request->filled('password')) {
            $blockAdmin->password = Hash::make($request->password);
        }

        \Log::info('Before save', ['attributes' => $blockAdmin->getAttributes()]);
        
        $blockAdmin->save();
        
        \Log::info('After save', ['attributes' => $blockAdmin->fresh()->getAttributes()]);

        return response()->json([
            'success' => true,
            'message' => 'Block Admin updated successfully.',
            'data' => User::findById($blockAdmin->id)
        ]);
    }

    /**
     * Delete Block Admin.
     */
    public function deleteBlockAdmin(Request $request, $id)
    {
        $blockAdmin = User::where('role', 'block_admin')->find($id);

        if (!$blockAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Block Admin not found.'
            ], 404);
        }

        $blockAdmin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Block Admin deleted successfully.'
        ]);
    }
}
