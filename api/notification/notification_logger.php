<?php
// api/notification/notification_logger.php

function log_notification_attempt($templateId, $recipientEmail, $success, $errorMessage = '') {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'template_id' => $templateId,
        'recipient' => $recipientEmail,
        'success' => $success,
        'error' => $errorMessage,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/logs/notifications.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function get_notification_status($templateId) {
    // This can be enhanced to check NotificationAPI status if needed
    return [
        'template_id' => $templateId,
        'last_sent' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];
}
?>