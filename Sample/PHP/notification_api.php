<?php
require_once 'db_connect.php';
require_once 'notification_functions.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'unread':
        $notifications = getUnreadNotifications($pdo);
        $ids = array_column($notifications, 'Notification_ID');
        if (!empty($ids)) {
            markNotificationsAsRead($pdo, $ids);
        }
        echo json_encode($notifications);
        break;
    case 'all':
    case '':
        $notifications = getAllNotifications($pdo);
        $unreadIds = array_column(
            array_filter($notifications, function ($n) {
                return $n['Is_Read'] == 0;
            }),
            'Notification_ID'
        );
        if (!empty($unreadIds)) {
            markNotificationsAsRead($pdo, $unreadIds);
        }
        echo json_encode($notifications);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
?>
