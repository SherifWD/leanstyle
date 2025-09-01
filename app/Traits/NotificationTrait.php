<?php

namespace App\Traits;

use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\AnnouncementMail;


trait NotificationTrait
{
    /**
     * Send notification to student with Firebase Cloud Messaging
     *
     * @param string $title
     * @param string $body
     * @param string $type
     * @param Student $student
     * @param array|null $payload
     * @param bool $isSeen
     * @return array
     */
    public function sendNotificationToStudent($title, $body, $type, Student $student, $payload = null, $isSeen = false)
    {
        try {
            // Save notification to database
            $notification = Notification::create([
                'student_id' => $student->id,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'payload' => $payload,
                'is_seen' => $isSeen,
                'sent_at' => now()
            ]);
            if ($type == 'parent') {
                if ($student->parent_email) {
                    $this->sendEmailAnnouncement($title, $body, $student->parent_email);
                }
            } else {
                if ($student->email) {
                    $this->sendEmailAnnouncement($title, $body, $student->email);
                }
            }

            // Get FCM token from student
            $fcmToken = $student->fcmToken;

            if (!$fcmToken || !$fcmToken->fcm_token) {
                Log::warning("No FCM token found for student: {$student->id}");
                return [
                    'success' => false,
                    'message' => 'No FCM token found for student',
                    'notification_saved' => true,
                    'firebase_sent' => false
                ];
            }

            // Send Firebase notification
            $firebaseResponse = $this->sendFirebaseNotification(
                $fcmToken->fcm_token,
                $title,
                $body,
                $payload
            );

            return [
                'success' => true,
                'message' => 'Notification sent successfully',
                'notification_saved' => true,
                'firebase_sent' => $firebaseResponse['success'],
                'notification_id' => $notification->id
            ];
        } catch (\Exception $e) {
            Log::error("Error sending notification to student {$student->id}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'notification_saved' => false,
                'firebase_sent' => false
            ];
        }
    }

    /**
     * Send notification to user (admin, supervisor, coordinator) with Firebase Cloud Messaging
     *
     * @param string $title
     * @param string $body
     * @param string $type
     * @param User $user
     * @param array|null $payload
     * @param bool $isSeen
     * @return array
     */
    public function sendNotificationToUser($title, $body, $type, User $user, $payload = null, $isSeen = false)
    {
        try {
            // Save notification to database
            $notification = Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'payload' => $payload,
                'is_seen' => $isSeen,
                'sent_at' => now()
            ]);
            $this->sendEmailAnnouncement($title, $body, $user->email);
            // Get FCM token from user
            $fcmToken = $user->fcmToken;

            if (!$fcmToken || !$fcmToken->fcm_token) {
                Log::warning("No FCM token found for user: {$user->id}");
                return [
                    'success' => false,
                    'message' => 'No FCM token found for user',
                    'notification_saved' => true,
                    'firebase_sent' => false
                ];
            }

            // Send Firebase notification
            $firebaseResponse = $this->sendFirebaseNotification(
                $fcmToken->fcm_token,
                $title,
                $body,
                $payload
            );

            return [
                'success' => true,
                'message' => 'Notification sent successfully',
                'notification_saved' => true,
                'firebase_sent' => $firebaseResponse['success'],
                'notification_id' => $notification->id
            ];
        } catch (\Exception $e) {
            Log::error("Error sending notification to user {$user->id}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'notification_saved' => false,
                'firebase_sent' => false
            ];
        }
    }
    /**
     * Send Firebase Cloud Messaging notification using HTTP v1 API
     *
     * @param string $fcmToken
     * @param string $title
     * @param string $body
     * @param array|null $data
     * @return array
     */
    private function sendFirebaseNotification($fcmToken, $title, $body, $data = null)
    {
        try {
            $projectId = env('FIREBASE_PROJECT_ID');

            if (!$projectId) {
                Log::error('Firebase project ID not configured');
                return [
                    'success' => false,
                    'message' => 'Firebase project ID not configured'
                ];
            }

            // Get access token for Firebase
            $accessToken = $this->getFirebaseAccessToken();

            if (!$accessToken) {
                Log::error('Failed to get Firebase access token');
                return [
                    'success' => false,
                    'message' => 'Failed to get Firebase access token'
                ];
            }

            // Build the message payload for FCM HTTP v1 API
            $message = [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'android' => [
                    'notification' => [
                        'sound' => 'default',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default'
                        ]
                    ]
                ]
            ];

            // Add data payload if provided
            if ($data && is_array($data)) {
                // FCM v1 requires all data values to be strings
                $message['data'] = array_map('strval', $data);
            }

            $payload = ['message' => $message];

            // Use the FCM HTTP v1 API endpoint
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info("Firebase notification sent successfully to token: " . substr($fcmToken, 0, 20) . "...");
                return [
                    'success' => true,
                    'message' => 'Firebase notification sent successfully',
                    'response' => $responseData
                ];
            } else {
                $errorBody = $response->body();
                Log::error("Firebase HTTP request failed: " . $errorBody);
                return [
                    'success' => false,
                    'message' => 'Firebase HTTP request failed',
                    'status' => $response->status(),
                    'response' => $errorBody
                ];
            }
        } catch (\Exception $e) {
            Log::error("Firebase notification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Firebase notification error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Firebase access token using service account key
     *
     * @return string|null
     */    private function getFirebaseAccessToken()
    {
        try {
            $serviceAccountPath = env('FIREBASE_SERVICE_ACCOUNT_KEY_PATH');

            // Convert relative path to absolute path
            if ($serviceAccountPath && !str_starts_with($serviceAccountPath, '/') && !str_contains($serviceAccountPath, ':\\')) {
                $serviceAccountPath = storage_path('app/firebase/service-account-key.json');
            }
            if (!$serviceAccountPath || !file_exists($serviceAccountPath)) {
                Log::error('Firebase service account key file not found: ' . $serviceAccountPath);
                return null;
            }

            Log::info('Loading Firebase service account from: ' . $serviceAccountPath);

            $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

            if (!$serviceAccount) {
                Log::error('Invalid Firebase service account key file');
                return null;
            }

            // Create JWT for Google OAuth 2.0
            $now = time();
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT'
            ];

            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600
            ];

            // Create JWT token
            $jwt = $this->createJWT($header, $payload, $serviceAccount['private_key']);

            if (!$jwt) {
                Log::error('Failed to create JWT token');
                return null;
            }

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            } else {
                Log::error('Failed to get access token: ' . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Error getting Firebase access token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create JWT token for Firebase authentication
     *
     * @param array $header
     * @param array $payload
     * @param string $privateKey
     * @return string|null
     */
    private function createJWT($header, $payload, $privateKey)
    {
        try {
            $headerEncoded = $this->base64UrlEncode(json_encode($header));
            $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

            $dataToSign = $headerEncoded . '.' . $payloadEncoded;

            $signature = '';
            if (!openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $signatureEncoded = $this->base64UrlEncode($signature);

            return $dataToSign . '.' . $signatureEncoded;
        } catch (\Exception $e) {
            Log::error("Error creating JWT: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Base64 URL encode
     *
     * @param string $data
     * @return string
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send notification to multiple students
     *
     * @param string $title
     * @param string $body
     * @param string $type
     * @param array $students
     * @param array|null $payload
     * @param bool $isSeen
     * @return array
     */
    public function sendNotificationToMultipleStudents($title, $body, $type, array $students, $payload = null, $isSeen = false)
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($students as $student) {
            $result = $this->sendNotificationToStudent($title, $body, $type, $student, $payload, $isSeen);
            $results[] = $result;

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        return [
            'success' => $successCount > 0,
            'message' => "Notifications sent: {$successCount} successful, {$failureCount} failed",
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * Send notification to multiple users
     *
     * @param string $title
     * @param string $body
     * @param string $type
     * @param array $users
     * @param array|null $payload
     * @param bool $isSeen
     * @return array
     */
    public function sendNotificationToMultipleUsers($title, $body, $type, array $users, $payload = null, $isSeen = false)
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($users as $user) {
            $result = $this->sendNotificationToUser($title, $body, $type, $user, $payload, $isSeen);
            $results[] = $result;

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        return [
            'success' => $successCount > 0,
            'message' => "Notifications sent: {$successCount} successful, {$failureCount} failed",
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ];
    }

    public function sendEmailAnnouncement($title, $messageBody, $email)
    {
        try {
            Mail::to($email)->send(new AnnouncementMail($title, $messageBody));

            return [
                'success' => true,
                'email' => $email,
                'status' => 'sent',
            ];
        } catch (\Exception $e) {
            Log::error("Failed to send email to {$email}: " . $e->getMessage());

            return [
                'success' => false,
                'email' => $email,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
