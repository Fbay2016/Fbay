<?php
header('Content-Type: application/json');

// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

$currentHour = (int)date('H');
$currentMinute = (int)date('i');
$currentTimeDecimal = $currentHour + ($currentMinute / 60);

// Check Time: 09:00 AM (9.0) to 11:00 PM (23.0) based on your message logic
$isWithinHours = ($currentTimeDecimal >= 09.0 && $currentTimeDecimal < 23.0);

// Plan-specific expiry timestamp
$expiryTimestamp = strtotime('2026-09-25 12:00:00');
$currentTimestamp = time();

$isExpired = ($currentTimestamp >= $expiryTimestamp);
$timeLeft = max(0, $expiryTimestamp - $currentTimestamp);

/* 
  FIXED LOGIC: 
  - '$success' should only check if the portal operating hours are active.
  - '$expired' and '$time_left' should strictly handle the plan's countdown timer.
  This prevents the plan timer from breaking or showing expired just because the portal closes.
*/

$message = '';
if (!$isWithinHours) {
    $message = 'Portal is closed. Operating hours are 09:00 AM to 11:00 PM (IST).';
} elseif ($isExpired) {
    $message = 'Plan has expired.';
} else {
    $message = 'Portal is open and plan is active.';
}

echo json_encode([
    'success' => $isWithinHours, // Portal status only
    'expired' => $isExpired,     // Plan expiry only
    'time_left' => $timeLeft,    // Plan countdown only
    'expiry_time' => $expiryTimestamp,
    'message' => $message
]);
?>