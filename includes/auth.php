<?php
// includes/auth.php - Stateless Serverless Auth & Session Persistence using Signed Cookies

function getAuthSecret() {
    return getenv('AUTH_SECRET') ?: hash('sha256', 'scrumvibe-pt-vinix-seven-aurum-2026-secret-key-v1');
}

/**
 * Restore PHP $_SESSION from HMAC-signed cookie if session is empty (e.g. on new serverless container)
 */
function initAuthSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_lifetime', (string)(86400 * 30));
        ini_set('session.gc_maxlifetime', (string)(86400 * 30));
        session_start();
    }

    if (empty($_SESSION['scrumvibe_logged_in']) && !empty($_COOKIE['scrumvibe_auth'])) {
        $cookie = $_COOKIE['scrumvibe_auth'];
        $dotPos = strpos($cookie, '.');
        if ($dotPos !== false) {
            $payloadB64 = substr($cookie, 0, $dotPos);
            $signature = substr($cookie, $dotPos + 1);
            $expected = hash_hmac('sha256', $payloadB64, getAuthSecret());
            if (hash_equals($expected, $signature)) {
                $rawJson = base64_decode($payloadB64, true);
                if ($rawJson) {
                    $data = json_decode($rawJson, true);
                    if (is_array($data) && !empty($data['user_id']) && ($data['exp'] ?? 0) > time()) {
                        $_SESSION['scrumvibe_logged_in']  = true;
                        $_SESSION['scrumvibe_user_id']    = $data['user_id'];
                        $_SESSION['scrumvibe_user_name']  = $data['user_name'];
                        $_SESSION['scrumvibe_role']       = ($data['role'] === 'super_admin') ? 'guru' : $data['role'];
                        $_SESSION['scrumvibe_team_id']    = $data['team_id'] ?? '';
                        if (!empty($data['student_id'])) {
                            $_SESSION['scrumvibe_student_id'] = $data['student_id'];
                        }
                    }
                }
            }
        }
    }
}

/**
 * Issue a signed persistent cookie (lasts 30 days)
 */
function issueAuthCookie($userId, $userName, $role, $teamId = '', $studentId = null) {
    $payload = [
        'user_id'    => $userId,
        'user_name'  => $userName,
        'role'       => ($role === 'super_admin') ? 'guru' : $role,
        'team_id'    => $teamId,
        'student_id' => $studentId,
        'exp'        => time() + (86400 * 30), // 30 days
    ];

    $payloadB64 = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $payloadB64, getAuthSecret());
    $cookieVal = $payloadB64 . '.' . $signature;

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    setcookie('scrumvibe_auth', $cookieVal, [
        'expires'  => time() + (86400 * 30),
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Clear the auth cookie on logout
 */
function clearAuthCookie() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    setcookie('scrumvibe_auth', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
