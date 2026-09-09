<?php
// index.php - Clean Home & Overview Dashboard for VINIX7 ScrumVibe
require_once __DIR__ . '/includes/header.php';

// Calculate active team metrics
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE team_id = ?");
$stmt->execute([$currentTeamId]);
$tasks = $stmt->fetchAll();

$totalTasks = count($tasks);
$doneTasks = 0;
$inProgressTasks = 0;
$totalPoints = 0;
$donePoints = 0;

foreach ($tasks as $t) {
    $pts = (int)($t['story_points'] ?? 1);
    $totalPoints += $pts;
    if ($t['status'] === 'done') {
        $doneTasks++;
        $donePoints += $pts;
    } elseif ($t['status'] === 'in_progress' || $t['status'] === 'in_review') {
        $inProgressTasks++;
    }
}

$completionPercent = $totalPoints > 0 ? round(($donePoints / $totalPoints) * 100) : 0;
?>

<div class="space-y-8">
    <!-- Hero Banner with VINIX7 Navy Blue & Accent -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#043399] via-[#03297a] to-[#021f5c] text-white p-6 sm:p-10 shadow-lg border-b-4 border-[#f59e0b]">
        <div class="relative z-10 max-w-3xl space-y-4">
            <div class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 border border-white/20 text-white text-xs font-semibold">
                <span>Program Fast Track PT VINIX SEVEN AURUM</span>
            </div>

            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
                Selamat Datang di <span class="text-[#f59e0b]">Web Development</span>
            </h1>

            <p class="text-slate-100 text-sm sm:text-base leading-relaxed font-normal">
                Platform pembelajaran terstruktur bagi siswa untuk mempraktikkan alur kerja standar industri Scrum secara kolaboratif. 
                Sistem dirancang dengan 2 peran: <b class="text-[#f59e0b]">Guru</b> dan <b class="text-emerald-300">Siswa</b>. 
                Semua siswa dalam kelompok memiliki fungsi yang sama dan bebas memilih tugas (Frontend, Backend, PRD, dan Desain) di Scrum Board.
            </p>

            <!-- Baris Informasi Peran, Kelompok & Tombol Aksi (Menyatu dengan Teks & Latar Banner) -->
            <div class="pt-4 mt-2 border-t border-white/15 flex flex-wrap items-center justify-between gap-4 text-xs">
                <div class="flex flex-wrap items-center gap-y-2 gap-x-4">
                    <div class="inline-flex items-center gap-1.5 text-white/90">
                        <span class="text-white/70 font-medium">Peran Aktif:</span>
                        <strong class="font-bold text-[#f59e0b]"><?= htmlspecialchars($roleInfo['label']) ?></strong>
                    </div>

                    <span class="text-white/30 hidden sm:inline">•</span>

                    <div class="inline-flex items-center gap-1.5 text-white/90">
                        <span class="text-white/70 font-medium">Kelompok:</span>
                        <strong class="font-bold text-white"><?= htmlspecialchars($currentTeam ? $currentTeam['name'] : 'Tim Scrum') ?></strong>
                    </div>
                </div>

                <a href="board.php" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-[#f59e0b] hover:bg-[#d97706] text-slate-950 font-bold text-xs shadow-md transition active:scale-95">
                    <span>Buka Scrum Board &rarr;</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Metrics Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs hover:border-[#043399] transition">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Progres Sprint 1</p>
            <h3 class="text-3xl font-extrabold text-[#043399] mt-2"><?= $completionPercent ?>%</h3>
            <p class="text-xs text-slate-600 font-medium mt-1">
                <span class="text-emerald-700 font-bold"><?= $donePoints ?></span> dari <?= $totalPoints ?> Story Points
            </p>
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-3 overflow-hidden">
                <div class="bg-[#043399] h-1.5 rounded-full" style="width: <?= $completionPercent ?>%"></div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs hover:border-emerald-500 transition">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tiket Selesai (Done)</p>
            <h3 class="text-3xl font-extrabold text-emerald-700 mt-2"><?= $doneTasks ?></h3>
            <p class="text-xs text-slate-600 font-medium mt-1">Dari total <?= $totalTasks ?> tiket tugas</p>
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-3 overflow-hidden">
                <div class="bg-emerald-600 h-1.5 rounded-full" style="width: <?= $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100) : 0 ?>%"></div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs hover:border-amber-500 transition">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Sedang Dikerjakan</p>
            <h3 class="text-3xl font-extrabold text-[#d97706] mt-2"><?= $inProgressTasks ?></h3>
            <p class="text-xs text-slate-600 font-medium mt-1">Status In Progress & Review</p>
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-3 overflow-hidden">
                <div class="bg-[#f59e0b] h-1.5 rounded-full" style="width: <?= $totalTasks > 0 ? round(($inProgressTasks / $totalTasks) * 100) : 0 ?>%"></div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs hover:border-[#043399] transition">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Proyek Kelompok</p>
            <h3 class="text-base font-extrabold text-slate-900 mt-2 line-clamp-1" title="<?= htmlspecialchars($currentTeam['project_title'] ?? '') ?>">
                <?php if (!empty($currentTeam['project_title'])): ?>
                    <?= htmlspecialchars($currentTeam['project_title']) ?>
                <?php else: ?>
                    <a href="prd.php" class="text-amber-800 hover:text-[#043399] underline decoration-dotted text-sm font-bold">
                        Belum Diisi (Isi di PRD &rarr;)
                    </a>
                <?php endif; ?>
            </h3>
            <p class="text-xs text-[#043399] font-semibold mt-1">Sprint 1 (Aktif)</p>
            <div class="mt-3 text-[11px] font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200 inline-block">
                <?= htmlspecialchars($currentTeam['name'] ?? 'Kelompok 1') ?>
            </div>
        </div>
    </div>

    <!-- Modules Navigation Cards (Clean Corporate Cards without Colorful Icon Squares) -->
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-[#f59e0b] rounded-full"></span>
                <span>Modul Pembelajaran & Ruang Kerja Tim</span>
            </h2>
            <span class="text-xs text-slate-500 font-medium">Pilih modul untuk mulai</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- 1. Board -->
            <a href="board.php" class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs hover:border-[#043399] hover:shadow-md transition flex flex-col justify-between space-y-4 group">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Modul 1</span>
                        <span class="bg-blue-50 text-[#043399] px-2.5 py-0.5 rounded-full text-xs font-semibold"><?= $totalTasks ?> Tiket</span>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 group-hover:text-[#043399] transition">
                        Scrum Kanban Board
                    </h3>
                    <p class="text-xs text-slate-600 font-normal leading-relaxed">
                        Papan tugas interaktif 5 status (Backlog, Sprint, In Progress, Review, Done). Filter kategori tugas, Story Points Fibonacci, dan fitur ambil tugas langsung.
                    </p>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-[#043399]">
                    <span>Buka Papan Tugas &rarr;</span>
                </div>
            </a>

            <!-- 2. PRD -->
            <a href="prd.php" class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs hover:border-[#043399] hover:shadow-md transition flex flex-col justify-between space-y-4 group">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Modul 2</span>
                        <span class="bg-slate-100 text-slate-700 px-2.5 py-0.5 rounded-full text-xs font-semibold">Versi 1.0</span>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 group-hover:text-[#043399] transition">
                        Modul Dokumen PRD
                    </h3>
                    <p class="text-xs text-slate-600 font-normal leading-relaxed">
                        Template terpandu untuk siswa menyusun spesifikasi produk, user stories, dan acceptance criteria. Dilengkapi tombol <b>One-Click Breakdown ke Board</b>.
                    </p>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-[#043399]">
                    <span>Kelola Dokumen PRD &rarr;</span>
                </div>
            </a>

            <!-- 3. Log Book -->
            <a href="logbook.php" class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs hover:border-[#043399] hover:shadow-md transition flex flex-col justify-between space-y-4 group">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Modul 3</span>
                        <span class="bg-emerald-50 text-emerald-800 px-2.5 py-0.5 rounded-full text-xs font-semibold">Format Fast Track</span>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 group-hover:text-[#043399] transition">
                        Log Book Kegiatan
                    </h3>
                    <p class="text-xs text-slate-600 font-normal leading-relaxed">
                        Format standar <b>Program Fast Track PT VINIX SEVEN AURUM</b>. Input harian siswa, paraf guru, dan mode cetak PDF.
                    </p>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-[#043399]">
                    <span>Isi / Tinjau Log Book &rarr;</span>
                </div>
            </a>

            <!-- 4. LMS -->
            <a href="lms.php" class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs hover:border-[#043399] hover:shadow-md transition flex flex-col justify-between space-y-4 group">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Modul 4</span>
                        <span class="bg-amber-50 text-amber-800 px-2.5 py-0.5 rounded-full text-xs font-semibold">4 Pertemuan Lengkap</span>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 group-hover:text-[#043399] transition">
                        Materi Pembelajaran
                    </h3>
                    <p class="text-xs text-slate-600 font-normal leading-relaxed">
                        Kurikulum Scrum bertahap dari Pertemuan 1 hingga Pertemuan 4. Menjelaskan konsep Sprint, Planning Poker, Daily Standup, sinkronisasi API, dan Retrospective.
                    </p>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-[#043399]">
                    <span>Buka Materi Pembelajaran &rarr;</span>
                </div>
            </a>

            <!-- 5. Weekly Report -->
            <a href="reports.php" class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs hover:border-[#043399] hover:shadow-md transition flex flex-col justify-between space-y-4 group">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Modul 5</span>
                        <span class="bg-slate-100 text-slate-700 px-2.5 py-0.5 rounded-full text-xs font-semibold">Auto-Generated</span>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 group-hover:text-[#043399] transition">
                        Laporan Otomatis Mingguan
                    </h3>
                    <p class="text-xs text-slate-600 font-normal leading-relaxed">
                        Kompilasi otomatis velocity sprint, rasio penyelesaian tugas FE vs BE, tingkat kepatuhan logbook, deteksi kartu macet, dan generator teks WhatsApp.
                    </p>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-[#043399]">
                    <span>Lihat Laporan Mingguan &rarr;</span>
                </div>
            </a>

            <!-- 6. Admin Panel -->
            <a href="admin.php" class="bg-white rounded-xl p-6 border border-slate-200 shadow-xs hover:border-[#043399] hover:shadow-md transition flex flex-col justify-between space-y-4 group">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Panel Guru</span>
                        <span class="bg-emerald-50 text-emerald-800 px-2.5 py-0.5 rounded-full text-xs font-semibold">WA Ready</span>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 group-hover:text-[#043399] transition">
                        Panel Guru & Undangan WA
                    </h3>
                    <p class="text-xs text-slate-600 font-normal leading-relaxed">
                        Guru membuatkan kelompok siswa, menetapkan peran (PM, BE, FE), konfigurasi token <b>WhatsApp Gateway</b>, dan mengirim undangan instan ke nomor siswa.
                    </p>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-[#043399]">
                    <span>Buka Panel Guru &rarr;</span>
                </div>
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
