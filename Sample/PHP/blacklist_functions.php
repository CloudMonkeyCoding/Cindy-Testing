<?php
// 1) Add a user/IP to blacklist
function addBlacklist($pdo, $userId, $reason, $ipAddress) {
    $stmt = $pdo->prepare("
        INSERT INTO blacklist (User_ID, Blacklist_reason, IP_Address)
        VALUES (:user_id, :reason, :ip_address)
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':reason' => $reason,
        ':ip_address' => $ipAddress
    ]);

    return $pdo->lastInsertId();
}

// 2) Get all blacklist entries
function getAllBlacklist($pdo) {
    $stmt = $pdo->query("SELECT * FROM blacklist");
    return $stmt->fetchAll();
}

// 3) Get blacklist entries for a specific User_ID
function getBlacklistByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT * FROM blacklist
        WHERE User_ID = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

// 4) Get blacklist entries for a specific IP address
function getBlacklistByIp($pdo, $ipAddress) {
    $stmt = $pdo->prepare("
        SELECT * FROM blacklist
        WHERE IP_Address = :ip_address
    ");
    $stmt->execute([':ip_address' => $ipAddress]);
    return $stmt->fetchAll();
}

// 5) Remove a blacklist entry by Blacklist_ID
function deleteBlacklistById($pdo, $blacklistId) {
    $stmt = $pdo->prepare("
        DELETE FROM blacklist
        WHERE Blacklist_ID = :blacklist_id
    ");
    $stmt->execute([':blacklist_id' => $blacklistId]);
    return $stmt->rowCount();
}

// 6) Remove all blacklist entries for a specific User_ID
function deleteBlacklistByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("
        DELETE FROM blacklist
        WHERE User_ID = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->rowCount();
}

// 7) Check if a specific User_ID is blacklisted
function isUserBlacklisted($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM blacklist WHERE User_ID = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchColumn() > 0;
}

// 8) Check if an IP address is blacklisted
function isIpBlacklisted($pdo, $ipAddress) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM blacklist WHERE IP_Address = :ip_address
    ");
    $stmt->execute([':ip_address' => $ipAddress]);
    return $stmt->fetchColumn() > 0;
}
?>