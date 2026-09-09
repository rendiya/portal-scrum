<?php
// invite.php - Siswa set password via unique invite token link
session_name("SCRUMVIBE_SESS");
session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/whatsapp.php";

$token = trim($_GET["token"] ?? "");
$error = "";
$member = null;

if ($token) {
    $stmt = $pdo->prepare("SELECT m.*, t.name as team_name FROM members m LEFT JOIN teams t ON m.team_id = t.id WHERE m.token = ? AND (m.role = \"siswa\" OR (m.role != \"guru\" AND m.role != \"super_admin\")) LIMIT 1");
    $stmt->execute([$token]);
    $member = $stmt->fetch();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $member) {
    $pass1 = $_POST["password"] ?? "";
    $pass2 = $_POST["password_confirm"] ?? "";

    if (strlen($pass1) < 6) {
        $error = "Kata sandi minimal 6 karakter.";
    } elseif ($pass1 !== $pass2) {
        $error = "Konfirmasi kata sandi tidak cocok.";
    } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE members SET password_hash = ?, invite_used = 1 WHERE token = ?");
        $upd->execute([$hash, $token]);

        $_SESSION["scrumvibe_logged_in"] = true;
        $_SESSION["scrumvibe_role"] = "siswa";
        $_SESSION["scrumvibe_user_id"] = $member["id"];
        $_SESSION["scrumvibe_user_name"] = $member["name"];
        $_SESSION["scrumvibe_student_id"] = $member["id"];
        $_SESSION["scrumvibe_team_id"] = $member["team_id"] ?: "";

        header("Location: board.php");
        exit;
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
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 to-blue-50 flex items-center justify-center p-4">
<div class="w-full max-w-md">

    <div class="text-center mb-6">
        <span class="text-2xl font-black text-[#043399] tracking-tight">ScrumVibe</span>
        <p class="text-xs text-slate-500 mt-1 font-medium">Manajemen Tim Scrum</p>
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
            <h2 class="text-base font-black">Atur Kata Sandi Akun Siswa</h2>
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

            <p class="text-xs text-slate-500">Buat kata sandi untuk masuk ke aplikasi. Minimal 6 karakter.</p>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap</label>
                    <input type="text" value="<?= htmlspecialchars($member["name"]) ?>" disabled class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 text-slate-500 font-semibold cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Buat Kata Sandi *</label>
                    <input type="password" name="password" required minlength="6" autofocus placeholder="Minimal 6 karakter" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Konfirmasi Kata Sandi *</label>
                    <input type="password" name="password_confirm" required minlength="6" placeholder="Ulangi kata sandi" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                </div>
                <button type="submit" class="w-full py-3 bg-[#043399] hover:bg-[#021f5c] text-white font-bold text-sm rounded-xl transition">Simpan Kata Sandi dan Masuk</button>
            </form>
            <p class="text-center text-xs text-slate-400">Sudah punya kata sandi? <a href="login.php" class="text-[#043399] font-bold hover:underline">Masuk di sini</a></p>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
