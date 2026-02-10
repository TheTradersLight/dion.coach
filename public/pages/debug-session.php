<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== SESSION DEBUG ===\n\n";

echo "1. Session status: " . session_status() . " (1=disabled, 2=active)\n";
echo "2. Session ID: " . session_id() . "\n";
echo "3. Session name: " . session_name() . "\n\n";

echo "4. Session data:\n";
print_r($_SESSION);
echo "\n";

echo "5. Cookie header:\n";
echo ($_SERVER['HTTP_COOKIE'] ?? '(none)') . "\n\n";

echo "6. DB check - sessions table:\n";
try {
    $pdo = \App\Database\Database::pdo();
    $stmt = $pdo->query("SELECT id, LENGTH(payload) as payload_len, last_activity, FROM_UNIXTIME(last_activity) as last_activity_human FROM sessions ORDER BY last_activity DESC LIMIT 10");
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        echo "(empty - no sessions in DB)\n";
    } else {
        foreach ($rows as $row) {
            echo "  id=" . substr($row['id'], 0, 16) . "... payload_len={$row['payload_len']} last={$row['last_activity_human']}\n";
        }
    }
} catch (\Throwable $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
}

echo "\n7. Current session in DB:\n";
try {
    $sid = session_id();
    if ($sid) {
        $stmt = $pdo->prepare("SELECT id, payload, last_activity FROM sessions WHERE id = :id");
        $stmt->execute(['id' => $sid]);
        $row = $stmt->fetch();
        if ($row) {
            echo "  FOUND - payload: " . $row['payload'] . "\n";
        } else {
            echo "  NOT FOUND in DB for session id: $sid\n";
        }
    } else {
        echo "  No session ID\n";
    }
} catch (\Throwable $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
}
