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
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
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
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col justify-between text-black antialiased">

    <!-- Top Corporate Accent Bar -->
    <div class="bg-[#021f5c] text-white text-[11px] font-bold py-2 px-4 text-center tracking-wider uppercase border-b border-blue-900">
        Portal Masuk • Program Fast Track PT VINIX SEVEN AURUM
    </div>

    <!-- Main Content Container -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-8">
        <div class="bg-white max-w-md w-full rounded-2xl border-2 border-slate-300 shadow-xl overflow-hidden my-auto">
            
            <!-- Brand Header -->
            <div class="pt-8 pb-6 px-6 text-center border-b-2 border-slate-100 bg-gradient-to-b from-blue-50/40 to-white">
                <?php if (file_exists(__DIR__ . '/logo/LOGO VINIX.png')): ?>
                    <img src="logo/LOGO VINIX.png" alt="VINIX7" class="h-14 sm:h-16 w-auto mx-auto object-contain mb-3.5">
                <?php else: ?>
                    <div class="h-12 w-28 mx-auto bg-[#043399] text-white font-black text-xl rounded-xl flex items-center justify-center mb-3">
                        VINIX<span class="text-[#f59e0b] ml-0.5">7</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($isForgot): ?>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight leading-none">
                        Lupa Kata Sandi
                    </h1>
                    <p class="text-xs text-slate-600 font-semibold mt-1.5 tracking-tight">
                        Reset kata sandi akun Anda via WhatsApp
                    </p>
                <?php else: ?>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight leading-none">
                        Web Development
                    </h1>
                    <p class="text-xs text-slate-600 font-semibold mt-1.5 tracking-tight">
                        Program Fast Track PT VINIX SEVEN AURUM
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($isForgot): ?>
                <!-- VIEW LUPA PASSWORD -->
                <div class="p-6 pb-0 space-y-3">
                    <?php if (!empty($errorMsg)): ?>
                        <div class="p-3.5 rounded-xl bg-red-50 border-2 border-red-200 text-red-900 text-xs font-bold leading-relaxed">
                            <?= htmlspecialchars($errorMsg) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($forgotSuccess): ?>
                    <div class="p-6 space-y-4">
                        <div class="p-4 rounded-xl bg-emerald-50 border-2 border-emerald-300 text-emerald-950 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">✅</span>
                                <h4 class="font-black text-xs sm:text-sm text-emerald-900">Tautan Reset Berhasil Dibuat!</h4>
                            </div>
                            <p class="text-xs font-medium text-emerald-900 leading-relaxed">
                                Tautan reset kata sandi telah disiapkan untuk akun <strong><?= htmlspecialchars($forgotSuccess['name']) ?></strong> (<?= htmlspecialchars($forgotSuccess['phone']) ?>).
                            </p>
                            <?php if ($forgotSuccess['gateway_sent']): ?>
                                <p class="text-[11px] font-bold text-emerald-700 bg-emerald-100 p-2 rounded-lg">
                                    ✓ Pesan otomatis telah dikirimkan ke WhatsApp Anda melalui Gateway!
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="space-y-2.5 pt-1">
                            <a href="<?= htmlspecialchars($forgotSuccess['wa_me_link']) ?>" target="_blank" class="w-full py-3 px-4 rounded-xl bg-[#25D366] hover:bg-[#1ebd59] text-white font-black text-xs sm:text-sm shadow-md transition flex items-center justify-center gap-2">
                                <span>💬 Buka WhatsApp & Kirim Pesan Reset &rarr;</span>
                            </a>

                            <a href="<?= htmlspecialchars($forgotSuccess['reset_link']) ?>" class="w-full py-2.5 px-4 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2">
                                <span>🔑 Atur Kata Sandi Sekarang (Di Browser Ini) &rarr;</span>
                            </a>

                            <div class="pt-2 text-center">
                                <a href="login.php" class="text-xs text-slate-600 font-bold hover:text-black hover:underline">
                                    &larr; Kembali ke Halaman Login
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-6 space-y-4">
                        <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-950 font-medium leading-relaxed">
                            Masukkan nomor WhatsApp yang terdaftar pada akun Anda (Guru maupun Siswa). Sistem akan mengirimkan tautan pembuatan kata sandi baru langsung ke WhatsApp Anda.
                        </div>

                        <form method="POST" action="login.php?forgot=1" class="space-y-4">
                            <input type="hidden" name="action" value="forgot_password">

                            <div>
                                <label class="block text-xs font-black text-black mb-1">Nomor WhatsApp Terdaftar *</label>
                                <input type="text" name="phone" required placeholder="Contoh: 081234567890 atau 6281234567890" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                                <span class="text-[10px] text-slate-500 mt-1 block">Bisa diawali dengan 08 atau 628</span>
                            </div>

                            <button type="submit" class="w-full py-3 px-4 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs sm:text-sm shadow-md transition active:scale-98">
                                Kirim Link Reset ke WhatsApp &rarr;
                            </button>
                        </form>

                        <div class="pt-2 text-center">
                            <a href="login.php" class="text-xs text-slate-600 font-bold hover:text-black hover:underline">
                                &larr; Kembali ke Halaman Login
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Messages (Error & Success) -->
                <div class="p-6 pb-0 space-y-3">
                    <?php if (!empty($errorMsg)): ?>
                        <div class="p-3.5 rounded-xl bg-red-50 border-2 border-red-200 text-red-900 text-xs font-bold leading-relaxed">
                            <?= htmlspecialchars($errorMsg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($successMsg)): ?>
                        <div class="p-3.5 rounded-xl bg-emerald-50 border-2 border-emerald-200 text-emerald-900 text-xs font-bold leading-relaxed">
                            <?= htmlspecialchars($successMsg) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SINGLE UNIFIED LOGIN FORM -->
                <div class="p-6 pt-4 space-y-5">
                    <form id="loginForm" method="POST" action="login.php" class="space-y-4">
                        <input type="hidden" name="action" value="login">

                        <div>
                            <label class="block text-xs font-black text-black mb-1">Email, No. WhatsApp, atau Nama Akun *</label>
                            <input type="text" id="identifier" name="identifier" required placeholder="Masukkan email, WhatsApp, atau nama" value="<?= !empty($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : '' ?>" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-black text-black">Kata Sandi / Password</label>
                                <a href="login.php?forgot=1" class="text-[11px] text-[#043399] font-bold hover:underline">Lupa kata sandi?</a>
                            </div>
                            <input type="password" id="password" name="password" placeholder="Masukkan kata sandi akun Anda" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-mono font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                        </div>

                        <button type="submit" class="w-full py-3 px-4 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs sm:text-sm shadow-md transition active:scale-98">
                            Masuk ke Aplikasi &rarr;
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Footer of Card -->
            <div class="p-4 bg-slate-50 border-t-2 border-slate-200 text-center text-[11px] text-slate-500 font-medium">
                Sistem Terpadu Scrum Vibe &bull; Hak Akses Disesuaikan Berdasarkan Peran
            </div>

        </div>
    </main>

    <!-- Page Footer -->
    <footer class="py-4 text-center text-xs text-slate-600 font-medium border-t border-slate-200 bg-white">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-1">
            <span class="font-black text-[#043399]">VINIX7 &bull; PT VINIX SEVEN AURUM</span>
            <span>Program Fast Track &copy; <?= date('Y') ?> &bull; Web Development</span>
        </div>
    </footer>

</body>
</html>
