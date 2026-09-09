<?php
// login.php - Halaman Login Tunggal (Guru & Siswa) Terpadu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/whatsapp.php';

// Jika pengguna sudah memiliki sesi login aktif, otomatis redirect ke beranda
if (!empty($_SESSION['scrumvibe_logged_in'])) {
    if (($_SESSION['scrumvibe_role'] ?? '') === 'siswa') {
        header('Location: board.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

// Ambil data tim untuk fallback
$stmt = $pdo->query("SELECT * FROM teams ORDER BY name ASC");
$teams = $stmt->fetchAll();

$errorMsg = '';
$successMsg = '';
$forgotSuccess = null;
$action = $_POST['action'] ?? '';
$isForgot = isset($_GET['forgot']) || ($action === 'forgot_password');

if (isset($_GET['logout'])) {
    $successMsg = 'Anda telah berhasil keluar dari sistem.';
}

// Proses POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($identifier)) {
            $errorMsg = 'Silakan masukkan email, nomor WhatsApp, atau nama akun Anda.';
        } else {
            // Cari akun berdasarkan email, phone, token, atau nama
            $stmt = $pdo->prepare("
                SELECT * FROM members 
                WHERE LOWER(email) = LOWER(?) OR phone = ? OR token = ? OR LOWER(name) = LOWER(?)
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier, $identifier, $identifier]);
            $user = $stmt->fetch();

            if (!$user) {
                // Fallback pencarian fuzzy LIKE nama
                $stmt = $pdo->prepare("SELECT * FROM members WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
                $stmt->execute(["%$identifier%"]);
                $user = $stmt->fetch();
            }

            if ($user) {
                // Pengecekan kata sandi jika user memiliki password_hash
                $hasPassword = !empty($user['password_hash']);
                if ($hasPassword) {
                    if (empty($password)) {
                        $errorMsg = 'Akun ini memiliki kata sandi. Silakan masukkan kata sandi Anda.';
                        $user = null;
                    } elseif (!password_verify($password, $user['password_hash'])) {
                        $errorMsg = 'Kata sandi salah. Silakan coba lagi atau gunakan fitur Lupa Kata Sandi.';
                        $user = null;
                    }
                }
            }

            if ($user) {
                $_SESSION['scrumvibe_logged_in'] = true;
                $_SESSION['scrumvibe_user_id'] = $user['id'];
                $_SESSION['scrumvibe_user_name'] = $user['name'];

                $isGuru = ($user['role'] === 'guru' || $user['role'] === 'super_admin');
                if ($isGuru) {
                    $_SESSION['scrumvibe_role'] = 'guru';
                    $_SESSION['scrumvibe_team_id'] = $teams[0]['id'] ?? 'team-1';
                    unset($_SESSION['scrumvibe_student_id']);

                    header('Location: index.php');
                    exit;
                } else {
                    $_SESSION['scrumvibe_role'] = 'siswa';
                    $_SESSION['scrumvibe_student_id'] = $user['id'];
                    $_SESSION['scrumvibe_team_id'] = $user['team_id'] ?: ($teams[0]['id'] ?? 'team-1');

                    header('Location: board.php');
                    exit;
                }
            } elseif (empty($errorMsg)) {
                $errorMsg = 'Akun tidak ditemukan. Pastikan email, nama, atau no. WhatsApp sudah terdaftar.';
            }
        }
    } elseif ($action === 'forgot_password') {
        $isForgot = true;
        $phoneInput = trim($_POST['phone'] ?? '');
        $rawDigits = preg_replace('/[^0-9]/', '', $phoneInput);

        if (empty($rawDigits)) {
            $errorMsg = 'Silakan masukkan nomor WhatsApp Anda.';
        } else {
            // Build phone variations to match both 08xxx and 628xxx formats
            $phoneVariants = [$rawDigits];
            if (str_starts_with($rawDigits, '08')) {
                $phoneVariants[] = '628' . substr($rawDigits, 2);
                $phoneVariants[] = substr($rawDigits, 1);
            } elseif (str_starts_with($rawDigits, '628')) {
                $phoneVariants[] = '08' . substr($rawDigits, 3);
                $phoneVariants[] = substr($rawDigits, 2);
            } elseif (str_starts_with($rawDigits, '8')) {
                $phoneVariants[] = '08' . $rawDigits;
                $phoneVariants[] = '628' . $rawDigits;
            }

            $placeholders = implode(',', array_fill(0, count($phoneVariants), '?'));
            $stmt = $pdo->prepare("SELECT * FROM members WHERE phone IN ($placeholders) LIMIT 1");
            $stmt->execute($phoneVariants);
            $foundMember = $stmt->fetch();

            if (!$foundMember) {
                $errorMsg = 'Nomor WhatsApp tidak terdaftar di sistem. Pastikan nomor sudah sesuai atau hubungi Admin / Guru.';
            } else {
                // Generate a new secure token
                $newToken = 'tok-rst-' . bin2hex(random_bytes(8));
                $stmtUpd = $pdo->prepare("UPDATE members SET token = ?, invite_used = 0 WHERE id = ?");
                $stmtUpd->execute([$newToken, $foundMember['id']]);

                // Construct HTTPS reset link
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                $scheme = $isHttps ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
                $resetLink = $scheme . "://" . $host . "/invite.php?token=" . urlencode($newToken);

                $roleLabel = ($foundMember['role'] === 'guru' || $foundMember['role'] === 'super_admin') ? 'Guru' : 'Siswa';
                $waMessage = "Halo {$foundMember['name']}!\n\nKami menerima permintaan untuk mereset kata sandi akun {$roleLabel} Anda di Portal Scrum - VINIX7.\n\nSilakan klik tautan berikut untuk membuat kata sandi baru:\n{$resetLink}\n\n*Catatan*: Tautan ini berlaku untuk akun Anda. Jika tidak merasa melakukan permintaan ini, abaikan pesan ini.";

                // Send via WhatsApp Gateway if configured
                $gwRes = sendViaWaGateway($pdo, $foundMember['phone'], $waMessage);
                $waMeLink = generateWaMeLink($foundMember['phone'], $waMessage);

                $forgotSuccess = [
                    'name' => $foundMember['name'],
                    'phone' => $foundMember['phone'],
                    'reset_link' => $resetLink,
                    'wa_me_link' => $waMeLink,
                    'gateway_sent' => !empty($gwRes['success']),
                    'gateway_msg' => $gwRes['message'] ?? ''
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem - Web Development Program Fast Track</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        fb: {
                            blue: '#1877f2',
                            hover: '#166fe5',
                            border: '#dddfe2',
                            bg: '#f0f2f5'
                        },
                        navy: {
                            800: '#043399',
                            900: '#021f5c',
                            950: '#011238'
                        },
                        amber: {
                            500: '#f59e0b',
                            600: '#d97706'
                        }
                    },
                    fontFamily: {
                        sans: [
                            'SF Pro Display',
                            '-apple-system',
                            'BlinkMacSystemFont',
                            'Segoe UI',
                            'Roboto',
                            'Helvetica',
                            'Arial',
                            'sans-serif'
                        ]
                    }
                }
            }
        };
    </script>
    <style>
        body { 
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
</head>
<body class="bg-[#f0f2f5] min-h-screen flex flex-col justify-between text-[#1c1e21] antialiased">

    <!-- Top Corporate Accent Bar -->
    <div class="bg-[#021f5c] text-white text-xs py-2 px-4 text-center tracking-wide uppercase border-b border-blue-900">
        Portal Masuk • Program Fast Track PT VINIX SEVEN AURUM
    </div>

    <!-- Main Content Container -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-8">
        <div class="bg-white max-w-[420px] w-full rounded-lg border border-[#dddfe2] shadow-[0_2px_4px_rgba(0,0,0,0.1),0_8px_16px_rgba(0,0,0,0.1)] overflow-hidden my-auto">
            
            <!-- Brand Header -->
            <div class="pt-8 pb-5 px-6 text-center border-b border-[#dddfe2] bg-gradient-to-b from-[#f8f9fa] to-white">
                <?php if (file_exists(__DIR__ . '/logo/LOGO VINIX.png')): ?>
                    <img src="logo/LOGO VINIX.png" alt="VINIX7" class="h-14 w-auto mx-auto object-contain mb-3">
                <?php else: ?>
                    <div class="h-12 w-28 mx-auto bg-[#043399] text-white text-xl rounded-lg flex items-center justify-center mb-3">
                        VINIX<span class="text-[#f59e0b] ml-0.5">7</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($isForgot): ?>
                    <h1 class="text-2xl text-[#1c1e21] tracking-normal leading-tight">
                        Lupa Kata Sandi
                    </h1>
                    <p class="text-sm text-[#65676b] mt-1">
                        Reset kata sandi akun Anda via WhatsApp
                    </p>
                <?php else: ?>
                    <h1 class="text-2xl text-[#1c1e21] tracking-normal leading-tight">
                        Web Development
                    </h1>
                    <p class="text-sm text-[#65676b] mt-1">
                        Program Fast Track PT VINIX SEVEN AURUM
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($isForgot): ?>
                <!-- VIEW LUPA PASSWORD -->
                <div class="p-6 pb-0 space-y-3">
                    <?php if (!empty($errorMsg)): ?>
                        <div class="p-3.5 rounded-md bg-red-50 border border-red-200 text-red-900 text-sm leading-relaxed">
                            <?= htmlspecialchars($errorMsg) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($forgotSuccess): ?>
                    <div class="p-6 space-y-4">
                        <div class="p-4 rounded-md bg-emerald-50 border border-emerald-300 text-emerald-950 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">✅</span>
                                <h4 class="text-base text-emerald-900">Tautan Reset Berhasil Dibuat!</h4>
                            </div>
                            <p class="text-sm text-emerald-900 leading-relaxed">
                                Tautan reset kata sandi telah disiapkan untuk akun <strong><?= htmlspecialchars($forgotSuccess['name']) ?></strong> (<?= htmlspecialchars($forgotSuccess['phone']) ?>).
                            </p>
                            <?php if ($forgotSuccess['gateway_sent']): ?>
                                <p class="text-xs text-emerald-700 bg-emerald-100 p-2 rounded">
                                    ✓ Pesan otomatis telah dikirimkan ke WhatsApp Anda melalui Gateway!
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="space-y-2.5 pt-1">
                            <a href="<?= htmlspecialchars($forgotSuccess['wa_me_link']) ?>" target="_blank" class="w-full py-3.5 px-4 rounded-md bg-[#25D366] hover:bg-[#1ebd59] text-white text-base shadow-sm transition flex items-center justify-center gap-2">
                                <span>💬 Buka WhatsApp & Kirim Pesan Reset &rarr;</span>
                            </a>

                            <a href="<?= htmlspecialchars($forgotSuccess['reset_link']) ?>" class="w-full py-3 px-4 rounded-md bg-[#043399] hover:bg-[#021f5c] text-white text-sm shadow-sm transition flex items-center justify-center gap-2">
                                <span>🔑 Atur Kata Sandi Sekarang &rarr;</span>
                            </a>

                            <div class="pt-2 text-center">
                                <a href="login.php" class="text-sm text-[#1877f2] hover:underline">
                                    &larr; Kembali ke Halaman Login
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-6 space-y-4">
                        <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-md text-sm text-[#021f5c] leading-relaxed">
                            Masukkan nomor WhatsApp yang terdaftar pada akun Anda (Guru maupun Siswa). Sistem akan mengirimkan tautan pembuatan kata sandi baru langsung ke WhatsApp Anda.
                        </div>

                        <form method="POST" action="login.php?forgot=1" class="space-y-4">
                            <input type="hidden" name="action" value="forgot_password">

                            <div>
                                <label class="block text-sm text-[#1c1e21] mb-1.5">Nomor WhatsApp Terdaftar</label>
                                <input type="text" name="phone" required placeholder="Contoh: 081234567890 atau 6281234567890" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" class="w-full px-4 py-3 border border-[#ccd0d5] rounded-md text-[16px] text-[#1c1e21] placeholder-[#8d949e] focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]">
                                <span class="text-xs text-[#65676b] mt-1 block">Bisa diawali dengan 08 atau 628</span>
                            </div>

                            <button type="submit" class="w-full py-3.5 px-4 rounded-md bg-[#043399] hover:bg-[#021f5c] text-white text-[16px] transition active:scale-[0.99]">
                                Kirim Link Reset ke WhatsApp &rarr;
                            </button>
                        </form>

                        <div class="pt-2 text-center">
                            <a href="login.php" class="text-sm text-[#1877f2] hover:underline">
                                &larr; Kembali ke Halaman Login
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Messages (Error & Success) -->
                <div class="p-6 pb-0 space-y-3">
                    <?php if (!empty($errorMsg)): ?>
                        <div class="p-3.5 rounded-md bg-red-50 border border-red-200 text-red-900 text-sm leading-relaxed">
                            <?= htmlspecialchars($errorMsg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($successMsg)): ?>
                        <div class="p-3.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm leading-relaxed">
                            <?= htmlspecialchars($successMsg) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SINGLE UNIFIED LOGIN FORM ALA FACEBOOK -->
                <div class="p-6 pt-5 space-y-4">
                    <form id="loginForm" method="POST" action="login.php" class="space-y-4">
                        <input type="hidden" name="action" value="login">

                        <div>
                            <label class="block text-sm text-[#1c1e21] mb-1.5">Email, No. WhatsApp, atau Nama Akun</label>
                            <input type="text" id="identifier" name="identifier" required placeholder="Masukkan email, nomor telepon, atau nama" value="<?= !empty($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : '' ?>" class="w-full px-4 py-3.5 border border-[#ccd0d5] rounded-md text-[16px] text-[#1c1e21] placeholder-[#8d949e] focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]">
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-sm text-[#1c1e21]">Kata Sandi</label>
                                <a href="login.php?forgot=1" class="text-sm text-[#1877f2] hover:underline">Lupa kata sandi?</a>
                            </div>
                            <input type="password" id="password" name="password" placeholder="Kata sandi akun Anda" class="w-full px-4 py-3.5 border border-[#ccd0d5] rounded-md text-[16px] text-[#1c1e21] placeholder-[#8d949e] focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]">
                        </div>

                        <div class="pt-1">
                            <button type="submit" class="w-full py-3.5 px-4 rounded-md bg-[#043399] hover:bg-[#021f5c] text-white text-[17px] tracking-wide transition active:scale-[0.99]">
                                Masuk ke Aplikasi &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Footer of Card -->
            <div class="p-4 bg-[#f8f9fa] border-t border-[#dddfe2] text-center text-xs text-[#65676b]">
                Sistem Terpadu Scrum Vibe • Hak Akses Disesuaikan Berdasarkan Peran
            </div>

        </div>
    </main>

    <!-- Page Footer -->
    <footer class="py-4 text-center text-xs text-[#65676b] border-t border-[#dddfe2] bg-white">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-1">
            <span class="text-[#043399]">VINIX7 • PT VINIX SEVEN AURUM</span>
            <span>Program Fast Track &copy; <?= date('Y') ?> • Web Development</span>
        </div>
    </footer>

</body>
</html>
