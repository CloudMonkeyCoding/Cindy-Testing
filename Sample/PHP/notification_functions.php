<?php
function addNotification($pdo, $type, $referenceId, $message) {
    $stmt = $pdo->prepare('INSERT INTO notification (Type, Reference_ID, Message) VALUES (:type, :ref, :message)');
    $stmt->execute([
        ':type'    => $type,
        ':ref'     => $referenceId,
        ':message' => $message
    ]);
    return $pdo->lastInsertId();
}

function getUnreadNotifications($pdo) {
    $stmt = $pdo->query(
        "SELECT Notification_ID, Type, Reference_ID, Message, Created_At " .
        "FROM notification WHERE Is_Read = 0 ORDER BY Created_At DESC"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllNotifications($pdo) {
    $stmt = $pdo->query(
        "SELECT Notification_ID, Type, Reference_ID, Message, Created_At, Is_Read " .
        "FROM notification ORDER BY Created_At DESC"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markNotificationsAsRead($pdo, $ids) {
    if (empty($ids)) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE notification SET Is_Read = 1 WHERE Notification_ID IN ($placeholders)");
    $stmt->execute($ids);
    return $stmt->rowCount();
}
?>
