<?php
// includes/whatsapp.php - Helper functions for WhatsApp notifications and wa.me links

function formatPhoneNumber($phone) {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($cleaned, '08')) {
        $cleaned = '628' . substr($cleaned, 2);
    } elseif (str_starts_with($cleaned, '8')) {
        $cleaned = '628' . substr($cleaned, 1);
    }
    return $cleaned;
}

function generateWaMeLink($phone, $message) {
    $formattedPhone = formatPhoneNumber($phone);
    $encodedText = rawurlencode($message);
    return "https://wa.me/{$formattedPhone}?text={$encodedText}";
}

function getSystemSetting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function sendViaWaGateway($pdo, $phone, $message) {
    $formattedPhone = formatPhoneNumber($phone);
    $token = trim(getSystemSetting($pdo, 'waApiToken', ''));
    $gatewayUrl = trim(getSystemSetting($pdo, 'waGatewayUrl', 'https://api.fonnte.com/send'));

    if (empty($token)) {
        return [
            'success' => false,
            'message' => 'Token WhatsApp Gateway belum dikonfigurasi di Pengaturan Super Admin. Silakan gunakan tombol link WhatsApp langsung (wa.me).'
        ];
    }

    // Fonnte API uses multipart/form-data (array), NOT JSON
    $postFields = [
        'target'  => $formattedPhone,
        'message' => $message,
        'countryCode' => '62',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $gatewayUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields); // array = multipart/form-data
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $token,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'message' => 'Error koneksi cURL: ' . $curlError];
    }

    $resJson = json_decode($response, true);

    // Fonnte returns {"status": true/false, "detail": "...", "reason": "..."}
    if (is_array($resJson)) {
        $status = $resJson['status'] ?? null;
        if ($status === true || $status === 'true' || $status === 1) {
            return ['success' => true, 'message' => 'Pesan berhasil dikirim via WhatsApp Gateway (Fonnte)!'];
        } else {
            $reason = $resJson['reason'] ?? $resJson['detail'] ?? $resJson['message'] ?? $response;
            return ['success' => false, 'message' => 'Gateway menolak: ' . $reason];
        }
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'Pesan berhasil dikirim via WhatsApp Gateway!'];
    } else {
        return ['success' => false, 'message' => 'Gagal mengirim (HTTP ' . $httpCode . '): ' . $response];
    }
}
?>
