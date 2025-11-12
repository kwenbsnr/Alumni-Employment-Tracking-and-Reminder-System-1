<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use NotificationAPI\NotificationAPI;

class NotificationHelper {
    private $notificationapi;

    public function __construct() {
        $this->notificationapi = new NotificationAPI(
            "ls4kt1i6t2hhh7rxd51k00rjj3", // Client ID
            "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av" // Client Secret
        );
    }

    public function sendNotification($templateId, $type, $toEmail, $parameters = []) {
        try {
            $this->notificationapi->send([
                'type' => $type,
                'to' => [
                    'id' => $toEmail,
                    'email' => $toEmail
                ],
                'parameters' => $parameters,
                'templateId' => $templateId
            ]);
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
        }
    }
}
