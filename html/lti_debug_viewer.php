<?php
// Simple debug log viewer
// Access via: https://yourdomain.com/backend/lti_debug_viewer.php

$logFile = __DIR__ . '/lti_debug.log';

// Clear log if requested
if (isset($_GET['clear'])) {
    file_put_contents($logFile, '');
    header('Location: lti_debug_viewer.php');
    exit;
}

?>
<!DOCTYPE html>
<html>

<head>
    <title>LTI Debug Log</title>
    <meta http-equiv="refresh" content="5">
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }

        h1 {
            color: #4ec9b0;
        }

        pre {
            background: #252526;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
        }

        .buttons {
            margin: 20px 0;
        }

        button,
        a {
            background: #0e639c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }

        button:hover,
        a:hover {
            background: #1177bb;
        }

        .info {
            background: #264f78;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <h1>🔍 LTI Debug Log Viewer</h1>

    <div class="info">
        ⏱️ Auto-refreshes every 5 seconds<br>
        📝 Log file:
        <?php echo $logFile; ?><br>
        📊 File size:
        <?php echo file_exists($logFile) ? number_format(filesize($logFile)) . ' bytes' : 'File not created yet'; ?>
    </div>

    <div class="buttons">
        <button onclick="location.reload()">🔄 Refresh Now</button>
        <a href="?clear=1" onclick="return confirm('Clear all logs?')">🗑️ Clear Log</a>
    </div>

    <h2>Log Contents:</h2>
    <pre><?php
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if (empty($content)) {
            echo "📭 No log entries yet.\n\n";
            echo "Waiting for LTI outcomes requests...\n";
            echo "Complete an activity in the external tool to see logs here.";
        } else {
            echo htmlspecialchars($content);
        }
    } else {
        echo "📭 Log file not created yet.\n\n";
        echo "The log file will be created when the first LTI outcomes request is received.";
    }
    ?></pre>

    <div style="margin-top: 20px; color: #858585;">
        <strong>How to use:</strong><br>
        1. Launch an LTI tool from GravLMS<br>
        2. Complete the activity in the external tool<br>
        3. Watch this page for incoming grade passback requests<br>
        4. If nothing appears, the external tool is not sending grade passback
    </div>
</body>

</html>