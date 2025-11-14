<?php
function normalizeAddressComponent($value)
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim((string)$value);
    return $trimmed === '' ? null : $trimmed;
}

function parseAddressComponents($address)
{
    $parts = [
        'street' => null,
        'barangay' => null,
        'city' => null,
        'province' => null,
    ];

    if ($address === null) {
        return $parts;
    }

    $normalized = preg_replace('/\r\n|\r/', "\n", (string)$address);
    $normalized = str_replace("\n", ',', $normalized);
    $segments = array_values(array_filter(array_map('trim', explode(',', $normalized)), static function ($segment) {
        return $segment !== '';
    }));

    if (isset($segments[0])) {
        $parts['street'] = $segments[0];
    }
    if (isset($segments[1])) {
        $parts['barangay'] = $segments[1];
    }
    if (isset($segments[2])) {
        $parts['city'] = $segments[2];
    }
    if (isset($segments[3])) {
        $parts['province'] = $segments[3];
    }

    return $parts;
}

function composeAddressFromParts($street, $barangay, $city, $province)
{
    $segments = [];
    foreach ([$street, $barangay, $city, $province] as $value) {
        $normalized = normalizeAddressComponent($value);
        if ($normalized !== null) {
            $segments[] = $normalized;
        }
    }

    return $segments ? implode(', ', $segments) : null;
}

function resolveAddressData($address, $street = null, $barangay = null, $city = null, $province = null)
{
    $parsed = parseAddressComponents($address);
    $street = normalizeAddressComponent($street) ?? $parsed['street'];
    $barangay = normalizeAddressComponent($barangay) ?? $parsed['barangay'];
    $city = normalizeAddressComponent($city) ?? $parsed['city'];
    $province = normalizeAddressComponent($province) ?? $parsed['province'];

    $fullAddress = composeAddressFromParts($street, $barangay, $city, $province);
    if ($fullAddress === null) {
        $fullAddress = normalizeAddressComponent($address);
    }

    return [$fullAddress, $street, $barangay, $city, $province];
}

function getUserAddressParts(array $user)
{
    $street = normalizeAddressComponent($user['Address_Street'] ?? null);
    $barangay = normalizeAddressComponent($user['Address_Barangay'] ?? null);
    $city = normalizeAddressComponent($user['Address_City'] ?? null);
    $province = normalizeAddressComponent($user['Address_Province'] ?? null);

    if ($street === null && $barangay === null && $city === null && $province === null) {
        $parsed = parseAddressComponents($user['Address'] ?? null);
        $street = normalizeAddressComponent($parsed['street']);
        $barangay = normalizeAddressComponent($parsed['barangay']);
        $city = normalizeAddressComponent($parsed['city']);
        $province = normalizeAddressComponent($parsed['province']);
    }

    return [
        'street' => $street,
        'barangay' => $barangay,
        'city' => $city,
        'province' => $province,
    ];
}

function getAllUsers($pdo)
{
    $stmt = $pdo->query("SELECT * FROM user");
    return $stmt->fetchAll();
}

function addUser($pdo, $name, $email, $password, $address, $warning_count = 0, $face_image_path = null, $addressStreet = null, $addressBarangay = null, $addressCity = null, $addressProvince = null)
{
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    [$resolvedAddress, $street, $barangay, $city, $province] = resolveAddressData($address, $addressStreet, $addressBarangay, $addressCity, $addressProvince);

    $stmt = $pdo->prepare("INSERT INTO user (Name, Email, Password, Address, Address_Street, Address_Barangay, Address_City, Address_Province, Warning_Count, Face_Image_Path)
                            VALUES (:name, :email, :password, :address, :address_street, :address_barangay, :address_city, :address_province, :warning_count, :face_image_path)");

    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $hashed_password,
        ':address' => $resolvedAddress,
        ':address_street' => $street,
        ':address_barangay' => $barangay,
        ':address_city' => $city,
        ':address_province' => $province,
        ':warning_count' => $warning_count,
        ':face_image_path' => $face_image_path
    ]);

    return $pdo->lastInsertId();
}

function deleteUserById($pdo, $userId)
{
    $stmt = $pdo->prepare("DELETE FROM user WHERE User_ID = :user_id");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->rowCount();
}

function deleteAllUsers($pdo)
{
    $stmt = $pdo->prepare("DELETE FROM user");
    $stmt->execute();
    return $stmt->rowCount();
}

function updateUserById($pdo, $userId, $name, $email, $address, $warning_count, $addressStreet = null, $addressBarangay = null, $addressCity = null, $addressProvince = null)
{
    [$resolvedAddress, $street, $barangay, $city, $province] = resolveAddressData($address, $addressStreet, $addressBarangay, $addressCity, $addressProvince);

    $stmt = $pdo->prepare("
        UPDATE user
        SET Name = :name,
            Email = :email,
            Address = :address,
            Address_Street = :address_street,
            Address_Barangay = :address_barangay,
            Address_City = :address_city,
            Address_Province = :address_province,
            Warning_Count = :warning_count
        WHERE User_ID = :user_id
    ");

    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':address' => $resolvedAddress,
        ':address_street' => $street,
        ':address_barangay' => $barangay,
        ':address_city' => $city,
        ':address_province' => $province,
        ':warning_count' => $warning_count,
        ':user_id' => $userId
    ]);

    return $stmt->rowCount();
}

function updateUserPasswordById($pdo, $userId, $newPassword)
{
    $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE user
        SET Password = :password
        WHERE User_ID = :user_id
    ");

    $stmt->execute([
        ':password' => $hashed_password,
        ':user_id' => $userId
    ]);

    return $stmt->rowCount();
}

function updateUserNameAddress($pdo, $userId, $name, $address, $addressStreet = null, $addressBarangay = null, $addressCity = null, $addressProvince = null)
{
    [$resolvedAddress, $street, $barangay, $city, $province] = resolveAddressData($address, $addressStreet, $addressBarangay, $addressCity, $addressProvince);

    $stmt = $pdo->prepare("
        UPDATE user
        SET Name = :name,
            Address = :address,
            Address_Street = :address_street,
            Address_Barangay = :address_barangay,
            Address_City = :address_city,
            Address_Province = :address_province
        WHERE User_ID = :user_id
    ");

    $stmt->execute([
        ':name' => $name,
        ':address' => $resolvedAddress,
        ':address_street' => $street,
        ':address_barangay' => $barangay,
        ':address_city' => $city,
        ':address_province' => $province,
        ':user_id' => $userId
    ]);

    return $stmt->rowCount();
}

function getUserById($pdo, $userId)
{
    $stmt = $pdo->prepare("SELECT * FROM user WHERE User_ID = :user_id");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByEmail($pdo, $email)
{
    $stmt = $pdo->prepare("SELECT * FROM user WHERE Email = :email");
    $stmt->execute([':email' => $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function checkEmailExists($pdo, $email)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE Email = :email");
    $stmt->execute([':email' => $email]);
    return $stmt->fetchColumn() > 0;
}

function authenticateUser($pdo, $email, $password)
{
    $user = getUserByEmail($pdo, $email);
    if ($user && password_verify($password, $user['Password'])) {
        return $user;
    }

    return false;
}

function countUsers($pdo, $startDate = null, $endDate = null, $dateColumn = null)
{
    $column = $dateColumn ? preg_replace('/[^A-Za-z0-9_]/', '', (string)$dateColumn) : '';

    if ($startDate && $endDate && $column !== '') {
        $query = sprintf(
            "SELECT COUNT(*) FROM user WHERE `%s` BETWEEN :start_date AND :end_date",
            $column
        );
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM user");
    }

    return $stmt->fetchColumn();
}

function searchUsers($pdo, $keyword)
{
    $stmt = $pdo->prepare("
        SELECT * FROM user
        WHERE Name LIKE :kw OR Email LIKE :kw
    ");
    $stmt->execute([':kw' => "%$keyword%"]);
    return $stmt->fetchAll();
}

function incrementWarningCount($pdo, $userId)
{
    $stmt = $pdo->prepare("
        UPDATE user
        SET Warning_Count = Warning_Count + 1
        WHERE User_ID = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->rowCount();
}
?>