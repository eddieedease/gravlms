<?php

// Helper function to log to a web-accessible file
function ltiDebugLog($message)
{
    $logFile = __DIR__ . '/../lti_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}
