<?php
/**
 * Health Check / Keep-Alive Endpoint
 * 
 * Lightweight endpoint that responds instantly with a 200 OK.
 * Used by external monitoring services (UptimeRobot, cron-job.org, etc.)
 * to prevent Render free-tier from spinning down after inactivity.
 * 
 * NO database connection — keeps the response fast (~1ms).
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

http_response_code(200);

echo json_encode([
    'status'  => 'ok',
    'service' => 'AttendX For TAASCOR',
    'time'    => date('c'),
]);
