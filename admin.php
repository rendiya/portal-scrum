<?php
// admin.php - Clean Super Admin Panel (Guru / Instruktur) & WhatsApp Gateway in PHP
require_once __DIR__ . '/includes/header.php';

// Strict Role Guard: Only Guru can access admin panel
if ($currentRole !== 'guru') {
    header('Location: index.php');
    exit;
}

// Fetch all teams
$stmt = $pdo->query("SELECT * FROM teams ORDER BY created_at DESC");
$teams = $stmt->fetchAll();

// Fetch all members
$stmt = $pdo->query("SELECT m.*, t.name as team_name FROM members m LEFT JOIN teams t ON m.team_id = t.id ORDER BY m.created_at ASC");
$members = $stmt->fetchAll();

// Map members to their teams and identify unassigned students
$teamStudentsMap = [];
$unassignedStudents = [];
foreach ($members as $m) {
    $isGuru = ($m['role'] === 'super_admin' || $m['role'] === 'guru');
    if (!empty($m['team_id'])) {
        $teamStudentsMap[$m['team_id']][] = $m;
    } elseif (!$isGuru) {
        $unassignedStudents[] = $m;
    }
}

// Fetch LMS modules
$stmt = $pdo->query("SELECT * FROM lms_modules ORDER BY week_number ASC");
$lmsModules = $stmt->fetchAll();

// Fetch settings
$waGatewayUrl = getSystemSetting($pdo, 'waGatewayUrl', 'https://api.fonnte.com/send');
$waApiToken = getSystemSetting($pdo, 'waApiToken', '');
$waSenderNumber = getSystemSetting($pdo, 'waSenderNumber', '');
$defaultInvitationTemplate = "Halo {nama}!\n\nKamu telah diundang oleh {guru} untuk bergabung ke Tim Proyek Scrum *{tim}* sebagai *{role}*.\n\nSilakan aktivasi akun dan buat kata sandi Anda melalui tautan undangan berikut:\n{invite_link}\n\nSelamat belajar dan berkolaborasi!";
$invitationTemplate = getSystemSetting($pdo, 'invitationTemplate', $defaultInvitationTemplate);
$weeklyReportTemplate = getSystemSetting($pdo, 'weeklyReportTemplate', '');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$scheme = $isHttps ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$appBaseUrl = $scheme . "://" . $host;
?>

<div class="space-y-8">
    <!-- Top Header -->
    <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-purple-100 text-purple-900 rounded-xl font-bold">
                </div>
            <div>
                <h1 class="text-2xl font-black text-black">Panel Super Admin (Guru / Instruktur)</h1>
                <p class="text-xs text-black font-semibold">
                    Buat kelompok siswa, daftarkan siswa (fungsi setara & bebas memilih tugas), kirim undangan WhatsApp, dan konfigurasi gateway.
                </p>
            </div>
        </div>

        <span class="px-3 py-1 bg-purple-100 text-purple-950 border border-purple-300 rounded-lg text-xs font-black">
            Hak Akses Super Admin
        </span>
    </div>

    <!-- Forms: 1. Create Team, 2. Add Student -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 1. Form Create Team -->
        <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm space-y-4">
            <div class="border-b-2 border-slate-100 pb-3">
                <h2 class="text-sm font-black text-black flex items-center gap-1.5">
                    <span>Buat Kelompok Siswa Baru</span>
                </h2>
                <p class="text-[11px] text-black font-medium mt-0.5">
                    Guru membuatkan kelompok kerja untuk satu proyek Scrum.
                </p>
            </div>

            <form onsubmit="handleCreateTeam(event)" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-black mb-1">Nama Kelompok *</label>
                    <input type="text" name="name" required placeholder="Contoh: Kelompok 2 (Beta)" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    <p class="text-[11px] text-slate-500 font-semibold mt-1">Catatan: Judul proyek akan ditentukan dan diisi langsung oleh mahasiswa di modul PRD.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-black mb-1">Deskripsi / Catatan Kelompok (Opsional)</label>
                    <textarea name="description" rows="3" placeholder="Catatan kelompok, pembagian tugas, atau fokus tim..." class="w-full p-2.5 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399]"></textarea>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-sm transition active:scale-98">
                    + Simpan Kelompok Baru
                </button>
            </form>
        </div>

        <!-- 2. Form Register Student -->
        <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm space-y-4">
            <div class="border-b-2 border-slate-100 pb-3">
                <h2 class="text-sm font-black text-black flex items-center gap-1.5">
                    <span>Daftarkan Siswa & Tetapkan Peran</span>
                </h2>
                <p class="text-[11px] text-black font-medium mt-0.5">
                    Masukkan nomor WhatsApp untuk mengirimkan tautan akses ke siswa.
                </p>
            </div>

            <form onsubmit="handleAddMember(event)" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-black mb-1">Nama Siswa *</label>
                        <input type="text" name="name" required placeholder="Contoh: Muhammad Farhan" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-black mb-1">No. WhatsApp *</label>
                        <input type="text" name="phone" required placeholder="081234567890" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-black mb-1">Kelompok Tim *</label>
                        <select name="team_id" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-black text-black focus:outline-none">
                            <?php foreach ($teams as $t): ?>
                                <option value="<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-black mb-1">Peran dalam Tim</label>
                        <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 border-2 border-blue-200 rounded-lg text-xs font-bold text-[#043399]">
                            <span>Siswa (Fungsi Setara • Bebas Memilih Tugas)</span>
                            <input type="hidden" name="role" value="siswa">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-black mb-1">Asal Kampus / Sekolah</label>
                        <input type="text" name="university" value="Universitas Dian Nuswantoro" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-black mb-1">Jurusan / Prodi</label>
                        <input type="text" name="major" value="Teknik Informatika" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none">
                    </div>
                </div>

                <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-[11px] text-black font-medium leading-relaxed">
                    Semua siswa memiliki fungsi dan hak akses yang sama di dalam tim. Siswa bebas berkolaborasi dan memilih tugas (Frontend, Backend, PRD) di Scrum Board.
                </div>

                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-black text-xs shadow-sm transition active:scale-98">
                    + Daftarkan Siswa ke Tim
                </button>
            </form>
        </div>
    </div>

    <!-- 3. Daftar Kelompok & Anggota Tim (Hapus Kelompok & Manajemen Siswa) -->
    <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b-2 border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black text-black">
                    Daftar Kelompok & Anggota Tim
                </h2>
                <p class="text-xs text-black font-semibold mt-0.5">
                    Guru dapat memantau anggota tim, memindahkan/mengeluarkan siswa dari tim, atau menghapus kelompok.
                </p>
            </div>
            <span class="text-xs font-bold text-black bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-200 self-start sm:self-auto">
                Total <?= count($teams) ?> Kelompok
            </span>
        </div>

        <!-- Cards per Team -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($teams as $t): 
                $teamMems = $teamStudentsMap[$t['id']] ?? [];
            ?>
                <div class="border-2 border-slate-200 rounded-xl p-4 bg-slate-50 flex flex-col justify-between hover:border-slate-300 transition shadow-xs">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="font-black text-sm text-black">
                                    <?= htmlspecialchars($t['name']) ?>
                                </h3>
                                <p class="text-[11px] font-bold text-slate-600 mt-0.5">
                                    Proyek: <span class="text-black font-extrabold"><?= htmlspecialchars($t['project_title'] ?: 'Belum diisi (diisi oleh siswa di PRD)') ?></span>
                                </p>
                            </div>
                            <span class="px-2 py-0.5 bg-blue-100 text-[#043399] font-black text-[10px] rounded-md border border-blue-200 shrink-0">
                                <?= count($teamMems) ?> Siswa
                            </span>
                        </div>

                        <!-- Members list in this team -->
                        <div class="space-y-1.5 pt-2 border-t border-slate-200">
                            <span class="text-[10px] uppercase font-black tracking-wider text-slate-600">Anggota Siswa:</span>
                            <?php if (empty($teamMems)): ?>
                                <p class="text-xs text-slate-500 italic py-1">Belum ada siswa di kelompok ini.</p>
                            <?php else: ?>
                                <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                    <?php foreach ($teamMems as $tm): ?>
                                        <div class="flex items-center justify-between p-2 bg-white rounded-lg border border-slate-200 text-xs">
                                            <div class="min-w-0 pr-2">
                                                <div class="font-black text-black truncate"><?= htmlspecialchars($tm['name']) ?></div>
                                                <div class="text-[10px] text-slate-600 truncate"><?= htmlspecialchars($tm['university'] ?: '-') ?></div>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <button type="button" onclick='openEditStudentModal(<?= json_encode($tm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="px-2 py-1 text-[11px] bg-slate-100 hover:bg-slate-200 text-black font-bold rounded border border-slate-300 transition" title="Edit / Pindah Kelompok">
                                                    Pindah
                                                </button>
                                                <button type="button" onclick="quickRemoveFromTeam('<?= $tm['id'] ?>', '<?= htmlspecialchars(addslashes($tm['name'])) ?>')" class="px-1.5 py-1 text-[11px] bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded border border-rose-200 transition" title="Keluarkan dari Kelompok">
                                                    Lepas
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-200 flex items-center justify-between">
                        <span class="text-[10px] text-slate-500 font-medium">ID: <?= htmlspecialchars($t['id']) ?></span>
                        <button type="button" onclick="handleDeleteTeam('<?= $t['id'] ?>', '<?= htmlspecialchars(addslashes($t['name'])) ?>')" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 rounded-lg text-xs font-black transition">
                            Hapus Kelompok
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Unassigned Students Warning / Box if any -->
        <?php if (!empty($unassignedStudents)): ?>
            <div class="p-4 bg-amber-50 border-2 border-amber-200 rounded-xl space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black text-amber-900">
                        Siswa Belum Memiliki Kelompok (<?= count($unassignedStudents) ?> Siswa)
                    </span>
                    <span class="text-[11px] text-amber-800 font-semibold">
                        Siswa ini dapat dimasukkan ke dalam kelompok yang aktif
                    </span>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <?php foreach ($unassignedStudents as $u): ?>
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-amber-300 rounded-lg text-xs">
                            <span class="font-black text-black"><?= htmlspecialchars($u['name']) ?></span>
                            <span class="text-[10px] text-slate-500">(<?= htmlspecialchars($u['university'] ?: 'Siswa') ?>)</span>
                            <button type="button" onclick='openEditStudentModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="ml-1 text-[11px] font-black text-[#043399] hover:underline">
                                + Masukkan Tim
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Member Table with WhatsApp Button -->
    <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b-2 border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-black text-black flex items-center gap-1.5">
                    <span>Daftar Guru & Siswa Terdaftar (Hanya 2 Peran)</span>
                </h2>
                <p class="text-[11px] text-black font-medium mt-0.5">
                    Kirim undangan WhatsApp secara langsung atau melalui Gateway API.
                </p>
            </div>
            <span class="text-xs font-bold text-black bg-purple-50 px-2.5 py-1 rounded-lg border border-purple-200">
                Total <?= count($members) ?> Akun
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-black border-b-2 border-slate-300">
                        <th class="px-4 py-3 font-black">Nama Pengguna</th>
                        <th class="px-4 py-3 font-black">Peran (Role)</th>
                        <th class="px-4 py-3 font-black">Kelompok</th>
                        <th class="px-4 py-3 font-black">No. WhatsApp</th>
                        <th class="px-4 py-3 font-black">Kampus / Sekolah</th>
                        <th class="px-4 py-3 font-black text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php foreach ($members as $m):
                        $isGuru = ($m['role'] === 'super_admin' || $m['role'] === 'guru');
                        $roleName = $isGuru ? 'Guru / Instruktur' : 'Siswa';
                        $guruName = $currentUserName ?? 'Guru/Instruktur';
                        $inviteLink = $appBaseUrl . '/invite.php?token=' . urlencode($m['token']);
                        $inviteMsg = str_replace(
                            ['{nama}', '{guru}', '{tim}', '{role}', '{invite_link}', '{link}'],
                            [$m['name'], $guruName, ($m['team_name'] ?: 'Tim Scrum'), $roleName, $inviteLink, $inviteLink],
                            $invitationTemplate
                        );
                    ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-black text-black">
                                <?= htmlspecialchars($m['name']) ?>
                                <?php if ($isGuru): ?>
                                    <span class="ml-1.5 px-1.5 py-0.5 bg-purple-100 text-purple-900 text-[10px] rounded font-black">Guru</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 font-bold">
                                <?php if ($isGuru): ?>
                                    <span class="px-2.5 py-0.5 rounded-full border text-[10px] uppercase bg-purple-100 text-purple-900 border-purple-300 font-black">
                                        Guru
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded-full border text-[10px] uppercase bg-blue-100 text-[#043399] border-blue-300 font-black">
                                        Siswa
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 font-black text-black">
                                <?= htmlspecialchars($m['team_name'] ?: ($isGuru ? '-' : 'Belum ada')) ?>
                            </td>
                            <td class="px-4 py-3 font-mono font-bold text-black">
                                <?= htmlspecialchars($m['phone'] ?: '-') ?>
                            </td>
                            <td class="px-4 py-3 text-black font-semibold">
                                <?= htmlspecialchars($m['university']) ?> • <?= htmlspecialchars($m['major']) ?>
                            </td>
                            <td class="px-4 py-3 text-right space-x-1">
                                <?php if (!$isGuru): ?>
                                    <button type="button" onclick='openEditStudentModal(<?= json_encode($m, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-[#043399] border border-blue-300 rounded-lg text-xs font-black transition">
                                        <span>Edit / Pindah</span>
                                    </button>

                                    <button type="button" onclick="openWhatsAppModal('<?= htmlspecialchars($m['name']) ?>', '<?= htmlspecialchars($m['phone']) ?>', <?= htmlspecialchars(json_encode($inviteMsg)) ?>)" class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-100 hover:bg-emerald-200 text-emerald-900 border border-emerald-400 rounded-lg text-xs font-black transition">
                                        <span>Undang WA</span>
                                    </button>

                                    <button type="button" onclick="resetInviteLink('<?= $m['id'] ?>', '<?= htmlspecialchars($m['name']) ?>')" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-400 rounded-lg text-xs font-black transition" title="Reset link undangan dan password siswa">
                                        <span>Reset Link</span>
                                    </button>

                                    <button type="button" onclick="deleteMember('<?= $m['id'] ?>')" class="px-2 py-1 text-rose-600 hover:text-rose-800 font-bold text-xs" title="Hapus">
                                        Hapus
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. Kurikulum & Materi Pembelajaran (LMS) Management -->
    <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b-2 border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-black text-black flex items-center gap-1.5">
                    <span>Kurikulum & Materi Pembelajaran</span>
                </h2>
                <p class="text-[11px] text-black font-medium mt-0.5">
                    Guru dapat menyusun dan mengedit silabus, tujuan pembelajaran, tugas wajib, dan referensi per pertemuan.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="lms.php" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-black border border-slate-300 rounded-lg text-xs font-black transition">
                    Buka Halaman Materi
                </a>
                <a href="lms.php?add=1" class="px-3 py-1.5 bg-[#043399] hover:bg-[#021f5c] text-white rounded-lg text-xs font-black shadow-xs transition">
                    + Tambah Pertemuan Baru
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-black border-b-2 border-slate-300">
                        <th class="px-4 py-3 font-black">Pertemuan</th>
                        <th class="px-4 py-3 font-black">Judul Modul Pembelajaran</th>
                        <th class="px-4 py-3 font-black">Tujuan & Output</th>
                        <th class="px-4 py-3 font-black">Status</th>
                        <th class="px-4 py-3 font-black text-right">Aksi Guru</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php foreach ($lmsModules as $lm): 
                        $lObj = json_decode($lm['objectives'] ?? '[]', true) ?? [];
                        $lDel = json_decode($lm['deliverables'] ?? '[]', true) ?? [];
                    ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-black text-black">
                                <span class="px-2 py-0.5 rounded-full bg-[#043399] text-white text-[10px] font-black">
                                    Pertemuan <?= $lm['week_number'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 font-black text-black">
                                <div><?= htmlspecialchars($lm['title']) ?></div>
                                <div class="text-[11px] text-slate-600 font-normal line-clamp-1 mt-0.5"><?= htmlspecialchars($lm['summary']) ?></div>
                            </td>
                            <td class="px-4 py-3 font-medium text-black">
                                <span class="text-blue-900 font-bold"><?= count($lObj) ?> Tujuan</span> • <span class="text-emerald-800 font-bold"><?= count($lDel) ?> Tugas Output</span>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($lm['is_published'] != 0): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-900 border border-emerald-300 text-[10px] font-black">
                                        Aktif / Tayang
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 text-[10px] font-black">
                                        Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <a href="lms.php?week=<?= $lm['week_number'] ?>" class="inline-flex items-center px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-black border border-slate-300 rounded-md text-xs font-bold transition">
                                    Lihat
                                </a>
                                <a href="lms.php?week=<?= $lm['week_number'] ?>&edit=1" class="inline-flex items-center px-2.5 py-1 bg-[#043399] hover:bg-[#021f5c] text-white rounded-md text-xs font-black shadow-xs transition">
                                    Edit Materi
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- WhatsApp Gateway Settings -->
    <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm space-y-4">
        <div class="border-b-2 border-slate-100 pb-3">
            <h2 class="text-sm font-black text-black flex items-center gap-2">
                <span>Konfigurasi WhatsApp Gateway (Fonnte / Wablas / API)</span>
            </h2>
            <p class="text-xs text-black font-semibold mt-0.5">
                Opsional: Masukkan token gateway agar pesan dapat terkirim secara otomatis.
            </p>
        </div>

        <form onsubmit="handleSaveSettings(event)" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-black mb-1">URL Endpoint Gateway</label>
                    <input type="url" name="waGatewayUrl" value="<?= htmlspecialchars($waGatewayUrl) ?>" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-mono font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    <span class="text-[11px] text-black font-medium mt-1 block">Default: https://api.fonnte.com/send</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-black mb-1">API Token / Authorization Key</label>
                    <input type="password" name="waApiToken" value="<?= htmlspecialchars($waApiToken) ?>" placeholder="Masukkan token jika ada" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-mono font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    <span class="text-[11px] text-black font-medium mt-1 block">Jika kosong, sistem menggunakan 1-Click WhatsApp (wa.me) tanpa biaya.</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-black mb-1">Template Pesan Undangan Siswa</label>
                <textarea name="invitationTemplate" rows="4" class="w-full p-3 border-2 border-slate-300 rounded-lg text-xs font-mono font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399]"><?= htmlspecialchars($invitationTemplate) ?></textarea>
                <p class="text-[11px] text-black font-semibold mt-1">
                    Tag yang tersedia: <code>{nama}</code>, <code>{guru}</code>, <code>{tim}</code>, <code>{role}</code>, <code>{link}</code>, <code class="bg-amber-100 px-0.5">{invite_link}</code> (link set password)
                </p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-black hover:bg-slate-800 text-white rounded-xl text-xs font-black shadow-sm transition">
                    <span>Simpan Pengaturan Gateway</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Student & Move Team Modal (Strict Manual Close Only) -->
<div id="editStudentModal" class="hidden fixed inset-0 bg-black/60 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border-2 border-slate-300 relative space-y-4">
        <div class="flex items-center justify-between border-b-2 border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-black text-black">Edit Siswa & Pindah Kelompok</h3>
                <p class="text-[11px] text-slate-600 font-medium mt-0.5">Ubah data siswa atau pindahkan siswa ke kelompok lain secara instan.</p>
            </div>
            <button type="button" onclick="closeEditStudentModal()" class="text-slate-400 hover:text-slate-700 font-bold text-lg p-1">
                &times;
            </button>
        </div>

        <form id="editStudentForm" onsubmit="handleUpdateStudent(event)" class="space-y-4">
            <input type="hidden" name="id" id="edit_student_id">

            <div>
                <label class="block text-xs font-bold text-black mb-1">Nama Lengkap Siswa</label>
                <input type="text" name="name" id="edit_student_name" required class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-black focus:outline-none focus:border-[#043399]">
            </div>

            <div>
                <label class="block text-xs font-bold text-black mb-1">Pilih Kelompok (Pindah Tim)</label>
                <select name="team_id" id="edit_student_team_id" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-black focus:outline-none focus:border-[#043399]">
                    <option value="">-- Belum Ada Kelompok --</option>
                    <?php foreach ($teams as $t): ?>
                        <option value="<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-500 mt-1">Pilih kelompok tujuan untuk memindahkan siswa ini.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-black mb-1">No. WhatsApp</label>
                    <input type="text" name="phone" id="edit_student_phone" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-black focus:outline-none focus:border-[#043399]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-black mb-1">Kampus / Sekolah</label>
                    <input type="text" name="university" id="edit_student_university" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none focus:border-[#043399]">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-black mb-1">Jurusan / Program Studi</label>
                <input type="text" name="major" id="edit_student_major" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none focus:border-[#043399]">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                <button type="button" onclick="closeEditStudentModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-black rounded-xl text-xs font-bold transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 bg-[#043399] hover:bg-[#021f5c] text-white rounded-xl text-xs font-black shadow-sm transition">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
async function handleCreateTeam(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
        const res = await fetch('api.php?action=create_team', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            await showAppAlert('Kelompok berhasil dibuat! Mahasiswa dapat mengisi judul proyek di modul PRD.');
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal membuat kelompok');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

async function handleAddMember(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
        const res = await fetch('api.php?action=add_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            await showAppAlert('Siswa berhasil didaftarkan!');
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal mendaftarkan siswa');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

async function deleteMember(id) {
    const ok = await showAppConfirm('Hapus siswa ini dari sistem?', {
        title: 'Konfirmasi Hapus Siswa',
        confirmText: 'Ya, Hapus',
        confirmColor: 'red'
    });
    if (!ok) return;
    try {
        const res = await fetch('api.php?action=delete_member&id=' + id);
        const result = await res.json();
        if (result.success) {
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal menghapus siswa');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

function openEditStudentModal(student) {
    document.getElementById('edit_student_id').value = student.id || '';
    document.getElementById('edit_student_name').value = student.name || '';
    document.getElementById('edit_student_phone').value = student.phone || '';
    document.getElementById('edit_student_team_id').value = student.team_id || '';
    document.getElementById('edit_student_university').value = student.university || '';
    document.getElementById('edit_student_major').value = student.major || '';

    const modal = document.getElementById('editStudentModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeEditStudentModal() {
    const modal = document.getElementById('editStudentModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function handleUpdateStudent(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
        const res = await fetch('api.php?action=update_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            closeEditStudentModal();
            await showAppAlert(result.message || 'Data siswa berhasil diperbarui!');
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal memperbarui data siswa');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

async function quickRemoveFromTeam(studentId, studentName) {
    const ok = await showAppConfirm('Keluarkan "' + studentName + '" dari kelompok ini?', {
        title: 'Konfirmasi Keluar Kelompok',
        confirmText: 'Ya, Keluarkan',
        confirmColor: 'amber'
    });
    if (!ok) return;

    try {
        const res = await fetch('api.php?action=update_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: studentId,
                name: studentName,
                team_id: ''
            })
        });
        const result = await res.json();
        if (result.success) {
            await showAppAlert('Siswa berhasil dikeluarkan dari kelompok.');
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal mengeluarkan siswa dari kelompok');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

async function handleDeleteTeam(teamId, teamName) {
    const ok = await showAppConfirm('Hapus kelompok "' + teamName + '"?\n\nCatatan: Akun siswa di dalam kelompok ini tidak akan dihapus, melainkan statusnya diubah menjadi belum ada kelompok.', {
        title: 'Konfirmasi Hapus Kelompok',
        confirmText: 'Ya, Hapus Kelompok',
        confirmColor: 'red'
    });
    if (!ok) return;

    try {
        const res = await fetch('api.php?action=delete_team', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: teamId })
        });
        const result = await res.json();
        if (result.success) {
            await showAppAlert(result.message || 'Kelompok berhasil dihapus!');
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal menghapus kelompok');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

async function handleSaveSettings(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
        const res = await fetch('api.php?action=save_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            await showAppAlert('Pengaturan berhasil disimpan!');
        } else {
            await showAppAlert(result.error || 'Gagal menyimpan pengaturan');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

async function resetInviteLink(memberId, memberName) {
    const ok = await showAppConfirm(
        'Reset link undangan untuk "' + memberName + '"?\n\nKata sandi lama akan dihapus dan siswa perlu mengatur kata sandi baru melalui link undangan yang baru.',
        { title: 'Reset Link Undangan', confirmText: 'Ya, Reset Link', confirmColor: 'amber' }
    );
    if (!ok) return;

    try {
        const res = await fetch('api.php?action=reset_invite', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ member_id: memberId })
        });
        const result = await res.json();
        if (result.success) {
            // Show the invite link in a modal so admin can copy & share
            document.getElementById('inviteLinkUrl').value = result.invite_link;
            document.getElementById('inviteLinkName').textContent = result.member_name;
            document.getElementById('inviteLinkModal').style.display = 'flex';
        } else {
            await showAppAlert(result.error || 'Gagal mereset link undangan');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

function closeInviteLinkModal() {
    document.getElementById('inviteLinkModal').style.display = 'none';
}

function copyInviteLink() {
    const input = document.getElementById('inviteLinkUrl');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        showAppAlert('Link berhasil disalin ke clipboard!');
    }).catch(() => {
        document.execCommand('copy');
        showAppAlert('Link berhasil disalin!');
    });
}
</script>

<!-- Invite Link Result Modal -->
<div id="inviteLinkModal" class="no-print fixed inset-0 z-[9999] bg-black/60 backdrop-blur-xs p-4" style="display:none; align-items:center; justify-content:center;">
    <div class="bg-white rounded-none border border-slate-300 shadow-xl max-w-md w-full overflow-hidden">
        <div class="bg-[#043399] text-white px-5 py-3.5 flex items-center justify-between">
            <h3 class="font-black text-sm text-white">Link Undangan Baru</h3>
            <button type="button" onclick="closeInviteLinkModal()" class="text-white hover:text-amber-300 font-bold text-2xl p-1 leading-none">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-xs text-slate-600 font-medium">
                Link undangan untuk <strong id="inviteLinkName" class="text-slate-900"></strong> berhasil direset. Salin link di bawah dan kirimkan ke siswa melalui WhatsApp atau media lainnya.
            </p>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Link Undangan (Set Password)</label>
                <div class="flex gap-2">
                    <input type="text" id="inviteLinkUrl" readonly class="flex-1 px-3 py-2 border border-slate-300 text-xs font-mono text-slate-800 bg-slate-50 focus:outline-none">
                    <button type="button" onclick="copyInviteLink()" class="px-3 py-2 bg-[#043399] hover:bg-[#021f5c] text-white text-xs font-black transition">Salin</button>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Siswa klik link ini untuk mengatur kata sandi dan langsung masuk ke aplikasi.</p>
            </div>
            <button type="button" onclick="closeInviteLinkModal()" class="w-full py-2.5 border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition">Tutup</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
