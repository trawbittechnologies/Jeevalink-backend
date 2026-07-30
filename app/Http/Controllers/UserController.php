<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BloodRequest;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\EmergencyController;

class UserController extends Controller
{
    /**
     * Get user profile.
     */
    public function getProfile(Request $request)
    {
        $auth = new AuthController();
        return $auth->me($request);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $auth = new AuthController();
        return $auth->profile($request);
    }

    /**
     * Create blood request for user.
     */
    public function createBloodRequest(Request $request)
    {
        $reqController = new RequestController();
        return $reqController->create($request);
    }

    /**
     * Get blood requests for user.
     */
    public function getBloodRequests(Request $request)
    {
        $reqController = new RequestController();
        return $reqController->index($request);
    }

    /**
     * Trigger SOS emergency request.
     */
    public function createSos(Request $request)
    {
        $emergency = new EmergencyController();
        return $emergency->createRequest($request);
    }
}
