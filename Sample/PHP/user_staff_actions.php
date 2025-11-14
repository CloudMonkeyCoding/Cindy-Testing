<?php
require_once __DIR__ . '/action_helpers.php';

startJsonResponse();
requirePostRequest();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/store_staff_functions.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/audit_log_functions.php';

requireDatabaseConnection($pdo);

$action = $_POST['action'] ?? '';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);
if (!$isLoggedIn) {
    record_audit_log($pdo, 'staff_action_denied', 'Staff management request denied: admin not authenticated.', [
        'source' => 'user_staff_actions',
        'metadata' => [
            'requested_action' => $action,
            'reason' => 'not_logged_in',
        ],
    ]);
    sendJsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$currentAdminId = isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : 0;
$currentIsSuperAdmin = !empty($_SESSION['admin_is_super_admin']);
$currentAdminEmail = $_SESSION['admin_email'] ?? null;

$logStaffEvent = static function (string $eventType, string $description, array $metadata = []) use ($pdo, $currentAdminId, $currentAdminEmail, $action): void {
    if (!$pdo instanceof PDO) {
        return;
    }

    $meta = $metadata;
    if (!array_key_exists('requested_action', $meta)) {
        $meta['requested_action'] = $action;
    }

    record_audit_log($pdo, $eventType, $description, [
        'actor_id' => $currentAdminId ?: null,
        'actor_email' => $currentAdminEmail,
        'source' => 'user_staff_actions',
        'metadata' => $meta,
    ]);
};

$supportedActions = ['mark_employee', 'remove_employee', 'promote_super_admin', 'demote_super_admin'];

if (!in_array($action, $supportedActions, true)) {
    $logStaffEvent('staff_action_denied', 'Staff management request denied: unsupported action.', [
        'reason' => 'unsupported_action',
    ]);
    sendJsonResponse(['success' => false, 'message' => 'Unsupported action'], 400);
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$userId) {
    $logStaffEvent('staff_action_denied', 'Staff management request denied: invalid user id.', [
        'reason' => 'invalid_user_id',
    ]);
    sendJsonResponse(['success' => false, 'message' => 'Invalid or missing user ID'], 400);
}

$user = getUserById($pdo, $userId);
if (!$user) {
    $logStaffEvent('staff_action_denied', 'Staff management request denied: user not found.', [
        'target_user_id' => $userId,
        'reason' => 'user_not_found',
    ]);
    sendJsonResponse(['success' => false, 'message' => 'User not found'], 404);
}

$existingStaff = getStoreStaffByUserId($pdo, $userId);
$isTargetSuperAdmin = $existingStaff && !empty($existingStaff['Is_Super_Admin']) && (int) $existingStaff['Is_Super_Admin'] === 1;

if (in_array($action, ['promote_super_admin', 'demote_super_admin'], true) && !$currentIsSuperAdmin) {
    $logStaffEvent('staff_action_denied', 'Staff management request denied: missing super admin rights.', [
        'target_user_id' => $userId,
        'reason' => 'requires_super_admin',
    ]);
    sendJsonResponse(['success' => false, 'message' => 'Only a super admin can modify super admin access.'], 403);
}

if ($action === 'demote_super_admin' && $userId === $currentAdminId) {
    $logStaffEvent('staff_action_denied', 'Staff management request denied: attempted self-demotion.', [
        'target_user_id' => $userId,
        'reason' => 'self_demote_blocked',
    ]);
    sendJsonResponse(['success' => false, 'message' => 'You cannot remove your own super admin access.'], 400);
}

if ($action === 'mark_employee' && !$currentIsSuperAdmin) {
    $logStaffEvent('staff_action_denied', 'Staff management request denied: only super admin can mark employees.', [
        'target_user_id' => $userId,
        'reason' => 'requires_super_admin',
    ]);
    sendJsonResponse(['success' => false, 'message' => 'Only the super admin can mark users as employees.'], 403);
}

if ($action === 'remove_employee' && !$currentIsSuperAdmin) {
    $logStaffEvent('staff_action_denied', 'Staff management request denied: only super admin can remove employees.', [
        'target_user_id' => $userId,
        'reason' => 'requires_super_admin',
    ]);
    sendJsonResponse(['success' => false, 'message' => 'Only the super admin can remove employee status.'], 403);
}

try {
    if ($action === 'mark_employee') {
        if ($existingStaff) {
            $logStaffEvent('staff_action_noop', 'Staff management request acknowledged: user already an employee.', [
                'target_user_id' => $userId,
            ]);
            sendJsonResponse(['success' => true, 'message' => 'User is already marked as an employee.']);
        }

        addStoreStaff($pdo, $userId);
        $logStaffEvent('staff_marked_employee', 'User marked as employee.', [
            'target_user_id' => $userId,
        ]);
        sendJsonResponse(['success' => true, 'message' => 'User marked as employee successfully.']);
    }

    if ($action === 'remove_employee') {
        if ($isTargetSuperAdmin) {
            $logStaffEvent('staff_action_denied', 'Staff management request denied: target is super admin.', [
                'target_user_id' => $userId,
                'reason' => 'target_super_admin',
            ]);
            sendJsonResponse(['success' => false, 'message' => 'Remove super admin access before removing this employee.'], 400);
        }
        if (!$existingStaff) {
            $logStaffEvent('staff_action_noop', 'Staff management request acknowledged: user already not an employee.', [
                'target_user_id' => $userId,
            ]);
            sendJsonResponse(['success' => true, 'message' => 'User is not currently marked as an employee.']);
        }

        deleteStoreStaffByUserId($pdo, $userId);
        $logStaffEvent('staff_removed_employee', 'Employee status removed.', [
            'target_user_id' => $userId,
        ]);
        sendJsonResponse(['success' => true, 'message' => 'Employee status removed successfully.']);
    }

    if ($action === 'promote_super_admin') {
        setStoreStaffSuperAdmin($pdo, $userId, true);
        $logStaffEvent('staff_promoted_super_admin', 'Super admin access granted.', [
            'target_user_id' => $userId,
        ]);
        sendJsonResponse(['success' => true, 'message' => 'Super admin updated successfully.', 'super_admin_user_id' => $userId]);
    }

    if ($action === 'demote_super_admin') {
        if (!$isTargetSuperAdmin) {
            $logStaffEvent('staff_action_noop', 'Staff management request acknowledged: user already not super admin.', [
                'target_user_id' => $userId,
            ]);
            sendJsonResponse(['success' => true, 'message' => 'User is not currently a super admin.']);
        }

        setStoreStaffSuperAdmin($pdo, $userId, false);
        $logStaffEvent('staff_demoted_super_admin', 'Super admin access removed.', [
            'target_user_id' => $userId,
        ]);
        sendJsonResponse(['success' => true, 'message' => 'Super admin access removed.', 'super_admin_user_id' => null]);
    }
} catch (PDOException $exception) {
    $logStaffEvent('staff_action_error', 'Staff management request failed due to database error.', [
        'target_user_id' => $userId,
        'reason' => 'database_error',
    ]);
    sendJsonResponse([
        'success' => false,
        'message' => 'Failed to update employee status.',
    ], 500);
}
