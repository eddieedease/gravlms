<?php
// Test if logging works
require_once __DIR__ . '/api/lti_debug.php';

ltiDebugLog("=== TEST LOG ENTRY ===");
ltiDebugLog("If you see this, logging is working!");
ltiDebugLog("Time: " . date('Y-m-d H:i:s'));

echo "Test log written. Check the debug viewer at: lti_debug_viewer.php";
?>