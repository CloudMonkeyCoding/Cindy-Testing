<?php
require_once __DIR__ . '/action_helpers.php';
require_once __DIR__ . '/user_functions.php';

/**
 * Resolve a user identifier (and optionally the corresponding user record) from a request payload.
 *
 * @param array<string,mixed> $request
 * @param array{
 *     allowMissing?: bool,
 *     includeUser?: bool,
 *     emailKey?: string,
 *     userIdKey?: string,
 *     notFoundMessage?: string,
 *     emailOptional?: bool
 * } $options
 *
 * @return array{0:int,1:array|null}
 */
function resolveUserContext(PDO $pdo, array $request, array $options = []): array
{
    $allowMissing = $options['allowMissing'] ?? false;
    $includeUser = $options['includeUser'] ?? false;
    $emailKey = $options['emailKey'] ?? 'email';
    $userIdKey = $options['userIdKey'] ?? 'user_id';
    $notFoundMessage = $options['notFoundMessage'] ?? 'User not found';
    $emailOptional = $options['emailOptional'] ?? false;

    $emailValue = $request[$emailKey] ?? '';
    $email = is_string($emailValue) ? trim($emailValue) : '';
    if ($email !== '') {
        $user = getUserByEmail($pdo, $email);
        if ($user) {
            return [(int)$user['User_ID'], $includeUser ? $user : null];
        }

        if (!$emailOptional && !$allowMissing) {
            sendJsonResponse(['error' => $notFoundMessage], 404);
        }
    }

    $userId = isset($request[$userIdKey]) ? (int)$request[$userIdKey] : 0;
    if ($userId <= 0) {
        if ($allowMissing) {
            return [0, null];
        }

        sendJsonResponse(['error' => $notFoundMessage], 404);
    }

    $user = null;
    if ($includeUser) {
        $user = getUserById($pdo, $userId);
        if (!$user) {
            if ($allowMissing) {
                return [0, null];
            }

            sendJsonResponse(['error' => $notFoundMessage], 404);
        }
    }

    return [$userId, $user];
}
