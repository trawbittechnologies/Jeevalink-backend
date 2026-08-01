<?php

namespace App\Services;

use App\Models\Notification;
use App\Services\MatchingService;
use App\Services\FCMService;

class NotificationService
{
    /**
     * Notify all matching donors about a new blood request.
     *
     * @param array $requestData Created blood request data
     * @return void
     */
    public static function notifyMatchingDonors(array $requestData): void
    {
        $matchingDonors = MatchingService::findMatches($requestData);
        $urgency = $requestData['urgency_level'] ?? 'Normal';
        $bloodGroup = $requestData['blood_group'];
        $hospital = $requestData['hospital_name'];
        $city = $requestData['city'];

        foreach ($matchingDonors as $donor) {
            $title = ($urgency === 'Emergency SOS') ? '🚨 Emergency Blood Required' : '🩸 Compatible Blood Request Nearby';
            $message = "A patient needs {$bloodGroup} blood at {$hospital}, {$city}. Your contribution can save a life.";

            // 1. Create In-App Notification record
            Notification::create([
                'recipient_id' => $donor['id'],
                'title' => $title,
                'message' => $message,
                'type' => ($urgency === 'Emergency SOS') ? 'SOS' : 'Match'
            ]);

            // 2. Dispatch push notification via FCM / Expo if urgency is critical or SOS
            if (($urgency === 'Emergency SOS' || $urgency === 'Urgent') && !empty($donor['fcm_token'])) {
                FCMService::sendPushNotification(
                    $donor['fcm_token'],
                    $title,
                    "Immediate donor needed for {$bloodGroup} at {$hospital}.",
                    [
                        'requestId' => $requestData['id'],
                        'type' => 'SOS',
                        'patientName' => $requestData['patient_name'] ?? '',
                        'bloodGroup' => $requestData['blood_group'] ?? '',
                        'unitsRequired' => $requestData['units_required'] ?? '1',
                        'hospitalName' => $requestData['hospital_name'] ?? '',
                        'contactNumber' => $requestData['contact_number'] ?? '',
                        'location' => $requestData['location'] ?? '',
                        'additionalNotes' => $requestData['additional_notes'] ?? ''
                    ]
                );
            }
        }
    }

    /**
     * Notify a user about earning reward points.
     *
     * @param int $userId
     * @param int $points
     * @param string $reason
     * @return void
     */
    public static function notifyRewardPoints(int $userId, int $points, string $reason): void
    {
        $title = "🎉 Reward Points Earned!";
        $message = "You earned {$points} JeevaPoints for: {$reason}. Thank you for your support!";

        Notification::create([
            'recipient_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'Reward'
        ]);
    }

    /**
     * Notify requester that their blood request is fulfilled.
     *
     * @param array $requestData
     * @param array $donorData
     * @return void
     */
    public static function notifyFulfillment(array $requestData, array $donorData): void
    {
        $title = "❤️ Blood Request Fulfilled";
        $message = "Your request for patient {$requestData['patient_name']} has been marked as fulfilled by {$donorData['primary_name']}.";

        Notification::create([
            'recipient_id' => $requestData['requested_by'],
            'title' => $title,
            'message' => $message,
            'type' => 'Fulfilled'
        ]);
    }

    /**
     * Send system warning alert.
     *
     * @param int $userId
     * @param string $message
     * @return void
     */
    public static function sendSystemWarning(int $userId, string $message): void
    {
        $title = "⚠️ System Warning Notification";

        Notification::create([
            'recipient_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'Warning'
        ]);
    }

    /**
     * Notify requester that a donor accepted their request.
     *
     * @param array $requestData
     * @param array $donorData
     * @return void
     */
    public static function notifyAcceptance(array $requestData, array $donorData): void
    {
        $title = "🚨 Emergency Request Accepted";
        $message = "Donor {$donorData['primary_name']} has accepted your emergency request for patient {$requestData['patient_name']}.";

        Notification::create([
            'recipient_id' => $requestData['requested_by'],
            'title' => $title,
            'message' => $message,
            'type' => 'SOS'
        ]);

        $requester = \App\Models\User::find($requestData['requested_by']);
        if ($requester && !empty($requester->fcm_token)) {
            FCMService::sendPushNotification(
                $requester->fcm_token,
                $title,
                "Donor {$donorData['primary_name']} has accepted your emergency request.",
                ['requestId' => $requestData['id'], 'type' => 'Acceptance']
            );
        }
    }
}
