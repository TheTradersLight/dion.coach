<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== SESSION DEBUG ===\n\n";

echo "1. Session status: " . session_status() . " (1=disabled, 2=active)\n";
echo "2. Session ID: " . session_id() . "\n";
echo "3. Session name: " . session_name() . "\n\n";

echo "4. Session data:\n";
print_r($_SESSION);
echo "\n";

// Set a test value to verify write works
if (!isset($_SESSION['debug_counter'])) {
    $_SESSION['debug_counter'] = 0;
}
$_SESSION['debug_counter']++;
$_SESSION['debug_time'] = date('Y-m-d H:i:s');
echo "5. Set debug_counter to: " . $_SESSION['debug_counter'] . "\n\n";

echo "6. Cookie header from browser:\n";
echo ($_SERVER['HTTP_COOKIE'] ?? '(no cookies sent)') . "\n\n";

echo "7. DB check - all sessions:\n";
try {
    $pdo = \App\Database\Database::pdo();
    $stmt = $pdo->query("SELECT id, LENGTH(payload) as payload_len, last_activity, FROM_UNIXTIME(last_activity) as last_activity_human FROM sessions ORDER BY last_activity DESC LIMIT 10");
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        echo "  (empty - no sessions in DB)\n";
    } else {
        foreach ($rows as $row) {
            $match = ($row['id'] === session_id()) ? ' <<<< CURRENT' : '';
            echo "  id={$row['id']} payload_len={$row['payload_len']} last={$row['last_activity_human']}{$match}\n";
        }
    }
} catch (\Throwable $e) {
    echo "  DB ERROR: " . $e->getMessage() . "\n";
}

echo "\n8. Force write test:\n";
try {
    session_write_close();
    echo "  session_write_close() called OK\n";

    // Now read back from DB
    $sid = session_id();
    $stmt = $pdo->prepare("SELECT id, payload, LENGTH(payload) as payload_len FROM sessions WHERE id = :id");
    $stmt->execute(['id' => $sid]);
    $row = $stmt->fetch();
    if ($row) {
        echo "  FOUND in DB after write_close - payload_len={$row['payload_len']}\n";
        echo "  payload: {$row['payload']}\n";
    } else {
        echo "  NOT FOUND in DB after write_close for id: $sid\n";
    }
} catch (\Throwable $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}
