<?php
// invite.php - Siswa set password via unique invite token link
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/whatsapp.php";

$token = trim($_GET["token"] ?? "");
$error = "";
$member = null;

if ($token) {
    $stmt = $pdo->prepare("SELECT m.*, t.name as team_name FROM members m LEFT JOIN teams t ON m.team_id = t.id WHERE m.token = ? LIMIT 1");
    $stmt->execute([$token]);
    $member = $stmt->fetch();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $member) {
    $email = trim($_POST["email"] ?? "");
    $pass1 = $_POST["password"] ?? "";
    $pass2 = $_POST["password_confirm"] ?? "";

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format alamat email tidak valid.";
    } elseif (strlen($pass1) < 6) {
        $error = "Kata sandi minimal 6 karakter.";
    } elseif ($pass1 !== $pass2) {
        $error = "Konfirmasi kata sandi tidak cocok.";
    } else {
        // Check if email is already taken by another member
        $chk = $pdo->prepare("SELECT id FROM members WHERE LOWER(email) = LOWER(?) AND id != ? LIMIT 1");
        $chk->execute([$email, $member["id"]]);
        if ($chk->fetch()) {
            $error = "Alamat email tersebut sudah digunakan oleh akun lain.";
        } else {
            $hash = password_hash($pass1, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE members SET email = ?, password_hash = ?, invite_used = 1 WHERE token = ?");
            $upd->execute([$email, $hash, $token]);

            $isGuru = ($member["role"] === 'guru' || $member["role"] === 'super_admin');
            $_SESSION["scrumvibe_logged_in"] = true;
            $_SESSION["scrumvibe_role"] = $isGuru ? 'guru' : 'siswa';
            $_SESSION["scrumvibe_user_id"] = $member["id"];
            $_SESSION["scrumvibe_user_name"] = $member["name"];

            if ($isGuru) {
                $_SESSION["scrumvibe_team_id"] = $member["team_id"] ?: 'team-1';
                unset($_SESSION["scrumvibe_student_id"]);
                header("Location: index.php");
            } else {
                $_SESSION["scrumvibe_student_id"] = $member["id"];
                $_SESSION["scrumvibe_team_id"] = $member["team_id"] ?: "";
                header("Location: board.php");
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Kata Sandi - ScrumVibe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1c1e21;
            background-color: #f0f2f5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .font-black, .font-extrabold { font-weight: 600 !important; }
        .font-bold { font-weight: 500 !important; }
    </style>
</head>
<body class="min-h-screen bg-[#f0f2f5] flex items-center justify-center p-4 text-[#1c1e21]">
<div class="w-full max-w-md">

    <div class="text-center mb-6">
        <img src="logo/LOGO VINIX.png" alt="VINIX7" class="h-12 w-auto mx-auto object-contain mb-2">
        <span class="text-xl text-[#043399] tracking-tight block">Program Fast Track</span>
        <p class="text-xs text-[#65676b] mt-0.5">PT VINIX SEVEN AURUM &bull; Aktivasi Akun Siswa</p>
    </div>

    <?php if (!$token || !$member): ?>
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 text-center">
        <p class="text-3xl font-black text-red-500 mb-3">!</p>
        <h2 class="text-lg font-black text-slate-800 mb-2">Link Tidak Valid</h2>
        <p class="text-sm text-slate-500 mb-6">Link undangan ini tidak ditemukan atau sudah tidak berlaku. Hubungi Guru untuk mendapatkan link baru.</p>
        <a href="login.php" class="block w-full py-3 bg-[#043399] text-white font-bold text-sm rounded-xl hover:bg-[#021f5c] transition text-center">Kembali ke Login</a>
    </div>

    <?php elseif ($member["invite_used"] == 1 && !empty($member["password_hash"])): ?>
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
        <h2 class="text-lg font-black text-slate-800 mb-2">Link Sudah Digunakan</h2>
        <p class="text-sm text-slate-500 mb-6">
            Kata sandi untuk akun <strong><?= htmlspecialchars($member["name"]) ?></strong> sudah pernah diatur.<br>
            Silakan login dengan kata sandi yang sudah dibuat, atau minta Guru / Instruktur untuk mereset link undangan.
        </p>
        <a href="login.php" class="block w-full py-3 bg-[#043399] text-white font-bold text-sm rounded-xl hover:bg-[#021f5c] transition text-center">Masuk ke Aplikasi</a>
    </div>

    <?php else: ?>
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <div class="bg-[#043399] text-white px-6 py-5">
            <h2 class="text-base font-black">Atur Kata Sandi Akun <?= ($member['role'] === 'guru' || $member['role'] === 'super_admin') ? 'Guru / Instruktur' : 'Siswa' ?></h2>
            <p class="text-xs text-blue-200 mt-1">
                Halo, <strong class="text-white"><?= htmlspecialchars($member["name"]) ?></strong>
                <?php if (!empty($member["team_name"])): ?>
                &mdash; <?= htmlspecialchars($member["team_name"]) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="p-6 space-y-4">
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 text-sm font-semibold"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <p class="text-xs text-slate-500">Lengkapi alamat email dan buat kata sandi untuk aktivasi akun Anda.</p>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap</label>
                    <input type="text" value="<?= htmlspecialchars($member["name"]) ?>" disabled class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 text-slate-500 font-semibold cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Email Aktif *</label>
                    <input type="email" name="email" required placeholder="nama@email.com" value="<?= htmlspecialchars($_POST['email'] ?? ($member['email'] ?? '')) ?>" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Digunakan untuk login ke sistem</span>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Buat Kata Sandi *</label>
                    <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Konfirmasi Kata Sandi *</label>
                    <input type="password" name="password_confirm" required minlength="6" placeholder="Ulangi kata sandi" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                </div>
                <button type="submit" class="w-full py-3 bg-[#043399] hover:bg-[#021f5c] text-white font-black text-sm rounded-xl shadow-md transition active:scale-98">Aktivasi Akun & Masuk Sekarang &rarr;</button>
            </form>
            <p class="text-center text-xs text-slate-400">Sudah punya kata sandi? <a href="login.php" class="text-[#043399] font-bold hover:underline">Masuk di sini</a></p>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
