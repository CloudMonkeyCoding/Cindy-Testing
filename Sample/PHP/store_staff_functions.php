<?php
// 1) Add a store staff member
function addStoreStaff($pdo, $userId, $isSuperAdmin = 0) {
    $stmt = $pdo->prepare("
        INSERT INTO store_staff (User_ID, Is_Super_Admin)
        VALUES (:user_id, :is_super_admin)
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':is_super_admin' => $isSuperAdmin ? 1 : 0,
    ]);
    return $pdo->lastInsertId();
}

// 2) Get all store staff
function getAllStoreStaff($pdo) {
    $stmt = $pdo->query("SELECT * FROM store_staff");
    return $stmt->fetchAll();
}

// 3) Get store staff by ID
function getStoreStaffById($pdo, $staffId) {
    $stmt = $pdo->prepare("
        SELECT * FROM store_staff WHERE Store_Staff_ID = :staff_id
    ");
    $stmt->execute([':staff_id' => $staffId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4) Get store staff by User_ID
function getStoreStaffByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT * FROM store_staff WHERE User_ID = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getSuperAdmin($pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM store_staff WHERE Is_Super_Admin = 1 LIMIT 1
    ");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function setStoreStaffSuperAdmin(PDO $pdo, int $userId, bool $isSuperAdmin): void
{
    $pdo->beginTransaction();
    try {
        $selectStmt = $pdo->prepare('SELECT Store_Staff_ID, Is_Super_Admin FROM store_staff WHERE User_ID = :user_id FOR UPDATE');
        $selectStmt->execute([':user_id' => $userId]);
        $existing = $selectStmt->fetch(PDO::FETCH_ASSOC);

        if ($isSuperAdmin) {
            $pdo->exec('UPDATE store_staff SET Is_Super_Admin = 0 WHERE Is_Super_Admin = 1');

            if ($existing) {
                $updateStmt = $pdo->prepare('UPDATE store_staff SET Is_Super_Admin = 1 WHERE Store_Staff_ID = :id');
                $updateStmt->execute([':id' => $existing['Store_Staff_ID']]);
            } else {
                addStoreStaff($pdo, $userId, 1);
            }
        } elseif ($existing && (int) ($existing['Is_Super_Admin'] ?? 0) === 1) {
            $updateStmt = $pdo->prepare('UPDATE store_staff SET Is_Super_Admin = 0 WHERE Store_Staff_ID = :id');
            $updateStmt->execute([':id' => $existing['Store_Staff_ID']]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

// 5) Delete store staff by Store_Staff_ID
function deleteStoreStaffById($pdo, $staffId) {
    $stmt = $pdo->prepare("
        DELETE FROM store_staff WHERE Store_Staff_ID = :staff_id
    ");
    $stmt->execute([':staff_id' => $staffId]);
    return $stmt->rowCount();
}

// 6) Delete store staff by User_ID
function deleteStoreStaffByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("
        DELETE FROM store_staff WHERE User_ID = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->rowCount();
}
?>
