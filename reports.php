<?php
// reports.php - Clean Automated Weekly Scrum Report & WhatsApp Message Generator in PHP
require_once __DIR__ . '/includes/header.php';

$selectedWeek = (int)($_GET['week'] ?? 1);

// 1. Fetch Team
$stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
$stmt->execute([$currentTeamId]);
$team = $stmt->fetch() ?: ['name' => 'Kelompok 1', 'project_title' => 'Proyek Scrum'];

// 2. Fetch Members
$stmt = $pdo->prepare("SELECT * FROM members WHERE team_id = ?");
$stmt->execute([$currentTeamId]);
$members = $stmt->fetchAll();

// 3. Fetch Tasks
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE team_id = ?");
$stmt->execute([$currentTeamId]);
$tasks = $stmt->fetchAll();

// 4. Fetch PRD
$stmt = $pdo->prepare("SELECT * FROM prds WHERE team_id = ? ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$currentTeamId]);
$prd = $stmt->fetch();

// 5. Fetch Logbooks
$stmt = $pdo->prepare("SELECT * FROM logbooks WHERE team_id = ? AND week_number = ?");
$stmt->execute([$currentTeamId, $selectedWeek]);
$logbooks = $stmt->fetchAll();

// Calculations
$totalPoints = 0;
$completedPoints = 0;
$inProgressPoints = 0;
$doneCount = 0;
$inProgressCount = 0;
$inReviewCount = 0;
$todoCount = 0;

$feTotalPts = 0; $feDonePts = 0; $feDoneCount = 0; $feTotalCount = 0;
$beTotalPts = 0; $beDonePts = 0; $beDoneCount = 0; $beTotalCount = 0;
$blockers = [];

foreach ($tasks as $t) {
    $pts = (int)($t['story_points'] ?? 1);
    $totalPoints += $pts;

    if ($t['status'] === 'done') {
        $completedPoints += $pts;
        $doneCount++;
    } elseif ($t['status'] === 'in_progress') {
        $inProgressPoints += $pts;
        $inProgressCount++;
    } elseif ($t['status'] === 'in_review') {
        $inProgressPoints += $pts;
        $inReviewCount++;
    } else {
        $todoCount++;
    }

    if ($t['role_category'] === 'frontend') {
        $feTotalPts += $pts;
        $feTotalCount++;
        if ($t['status'] === 'done') {
            $feDonePts += $pts;
            $feDoneCount++;
        }
    } elseif ($t['role_category'] === 'backend') {
        $beTotalPts += $pts;
        $beTotalCount++;
        if ($t['status'] === 'done') {
            $beDonePts += $pts;
            $beDoneCount++;
        }
    }

    if (!empty($t['dependency_task_id']) && ($t['status'] === 'in_progress' || $t['status'] === 'sprint_backlog')) {
        $blockers[] = "Tiket '{$t['title']}' (FE) bergantung pada tiket BE: " . ($t['dependency_task_title'] ?: 'API Backend');
    }
}

foreach ($logbooks as $l) {
    if (!empty($l['blockers'])) {
        $blockers[] = "[Logbook {$l['student_name']}]: " . $l['blockers'];
    }
}

$completionRate = $totalPoints > 0 ? round(($completedPoints / $totalPoints) * 100) : 0;
$beRate = $beTotalPts > 0 ? round(($beDonePts / $beTotalPts) * 100) : 0;
$feRate = $feTotalPts > 0 ? round(($feDonePts / $feTotalPts) * 100) : 0;

// Logbook compliance
$submittedCount = 0;
$complianceList = [];
foreach ($members as $m) {
    $hasSubmitted = false;
    foreach ($logbooks as $l) {
        if ($l['student_id'] === $m['id']) {
            $hasSubmitted = true;
            break;
        }
    }
    if ($hasSubmitted) $submittedCount++;
    $complianceList[] = [
        'name' => $m['name'],
        'role' => $m['role'],
        'hasSubmitted' => $hasSubmitted
    ];
}
$complianceRate = count($members) > 0 ? round(($submittedCount / count($members)) * 100) : 0;

// Generate WhatsApp Formatted Text
$pmStatus = $prd ? "PRD " . strtoupper($prd['status']) . " (" . $prd['title'] . ")" : "Belum ada PRD";
$beStatus = "{$beDoneCount}/{$beTotalCount} Tiket Selesai ({$beDonePts}/{$beTotalPts} pts)";
$feStatus = "{$feDoneCount}/{$feTotalCount} Tiket Selesai ({$feDonePts}/{$feTotalPts} pts)";

$logbookLines = [];
foreach ($complianceList as $c) {
    $logbookLines[] = ($c['hasSubmitted'] ? "[Sudah]" : "[Belum]") . " " . $c['name'] . " (" . $c['role'] . ")";
}
$logbookStatusText = implode("\n", $logbookLines);

$template = getSystemSetting($pdo, 'weeklyReportTemplate', "LAPORAN MINGGUAN SCRUM (WEEK {week})\nKelompok: *{tim}*\nProyek: *{proyek}*\n\nPROGRESS & VELOCITY:\n- Story Points: {completedPoints}/{totalPoints} ({percent}%)\n- Status: {doneCount} Selesai, {inProgressCount} Dikerjakan, {todoCount} Menunggu\n\nKONTRIBUSI PERAN:\n- PM: {pmStatus}\n- Backend: {beStatus}\n- Frontend: {feStatus}\n\nKEPATUHAN LOGBOOK:\n{logbookStatus}\n\nAkses Laporan: {link}");

$linkUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']);

$waReportMessage = str_replace(
    ['{week}', '{tim}', '{proyek}', '{completedPoints}', '{totalPoints}', '{percent}', '{doneCount}', '{inProgressCount}', '{todoCount}', '{pmStatus}', '{beStatus}', '{feStatus}', '{logbookStatus}', '{link}'],
    [$selectedWeek, $team['name'], $team['project_title'], $completedPoints, $totalPoints, $completionRate, $doneCount, ($inProgressCount + $inReviewCount), $todoCount, $pmStatus, $beStatus, $feStatus, $logbookStatusText, $linkUrl],
    $template
);
?>

<div class="space-y-6">
    <!-- Top Header -->
    <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-pink-100 text-pink-700 rounded-xl font-bold">
                </div>
            <div>
                <h1 class="text-2xl font-black text-black">Laporan Otomatis Mingguan (Weekly Scrum Report)</h1>
                <p class="text-xs text-black font-semibold">
                    Kompilasi otomatis velocity sprint, rasio penyelesaian tugas peran FE vs BE, kepatuhan logbook, dan peringatan blocker.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <select onchange="window.location.href='reports.php?week=' + this.value" class="px-3 py-2 border-2 border-slate-300 rounded-xl text-xs font-black text-black bg-white focus:outline-none">
                <option value="1" <?= $selectedWeek === 1 ? 'selected' : '' ?>>Minggu ke-1</option>
                <option value="2" <?= $selectedWeek === 2 ? 'selected' : '' ?>>Minggu ke-2</option>
                <option value="3" <?= $selectedWeek === 3 ? 'selected' : '' ?>>Minggu ke-3</option>
                <option value="4" <?= $selectedWeek === 4 ? 'selected' : '' ?>>Minggu ke-4</option>
            </select>

            <button type="button" onclick="openWhatsAppModal('Grup Kelas / Guru', '081234567890', <?= htmlspecialchars(json_encode($waReportMessage)) ?>)" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-sm transition">
                <span>Kirim via WhatsApp</span>
            </button>

            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-black hover:bg-slate-800 text-white font-bold text-xs">
                <span>Cetak</span>
            </button>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 border-2 border-slate-200 shadow-sm">
            <span class="text-xs font-black text-black uppercase">Tingkat Penyelesaian (Velocity)</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-3xl font-black text-[#043399]"><?= $completionRate ?>%</span>
                <span class="text-xs font-bold text-black">(<?= $completedPoints ?>/<?= $totalPoints ?> pts)</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-2 mt-3 overflow-hidden">
                <div class="bg-[#043399] h-2 rounded-full" style="width: <?= $completionRate ?>%"></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border-2 border-slate-200 shadow-sm">
            <span class="text-xs font-black text-black uppercase">Status Tiket Sprint</span>
            <div class="mt-2 space-y-1.5 text-xs font-bold text-black">
                <div class="flex justify-between">
                    <span class="text-emerald-700">Done:</span>
                    <span><?= $doneCount ?> Tiket</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-amber-700">In Progress & Review:</span>
                    <span><?= $inProgressCount + $inReviewCount ?> Tiket</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-black">To Do:</span>
                    <span><?= $todoCount ?> Tiket</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border-2 border-slate-200 shadow-sm">
            <span class="text-xs font-black text-black uppercase">Kepatuhan Logbook</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-3xl font-black text-emerald-700"><?= $complianceRate ?>%</span>
                <span class="text-xs font-bold text-black">(<?= $submittedCount ?>/<?= count($members) ?> Siswa)</span>
            </div>
            <p class="text-[11px] font-semibold text-black mt-3">
                Siswa mengumpulkan catatan minggu ke-<?= $selectedWeek ?>
            </p>
        </div>

        <div class="bg-white rounded-2xl p-5 border-2 border-slate-200 shadow-sm">
            <span class="text-xs font-black text-black uppercase">Kesehatan Sprint</span>
            <div class="mt-2 flex items-center gap-2">
                <?php if (!empty($blockers)): ?>
                    <div>
                        <div class="text-xs font-black text-amber-900">Perhatian Guru</div>
                        <div class="text-[11px] font-bold text-black"><?= count($blockers) ?> kendala terdeteksi</div>
                    </div>
                <?php else: ?>
                    <div>
                        <div class="text-xs font-black text-emerald-900">Sprint Lancar</div>
                        <div class="text-[11px] font-bold text-black">Tidak ada kartu macet</div>
                    </div>
                <?php endif; ?>
            </div>
            <p class="text-[11px] font-semibold text-black mt-2">Evaluasi risiko & dependency</p>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm space-y-4">
        <div>
            <h3 class="font-black text-sm text-black uppercase tracking-wider flex items-center gap-1.5">
                <span>Evaluasi Progres Per Kategori Pekerjaan</span>
            </h3>
            <p class="text-[11px] text-slate-600 font-semibold mt-0.5">
                Semua siswa memiliki fungsi yang sama dan bebas memilih tugas di kategori mana pun.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- PRD -->
            <div class="p-4 rounded-xl border-2 border-blue-300 bg-blue-50/60 space-y-2">
                <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-blue-100 text-[#043399] border border-blue-300">Dokumen PRD & Fitur</span>
                <div class="pt-1">
                    <div class="text-xs font-black text-black">Status PRD:</div>
                    <div class="text-xs font-bold capitalize text-[#043399]"><?= htmlspecialchars($prd['status'] ?? 'draft') ?></div>
                </div>
                <div class="text-xs text-black font-semibold"><b>Dokumen:</b> <?= htmlspecialchars($prd['title'] ?? '-') ?></div>
            </div>

            <!-- BE -->
            <div class="p-4 rounded-xl border-2 border-emerald-300 bg-emerald-50/60 space-y-2">
                <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-900 border border-emerald-300">Tugas Backend & API</span>
                <div class="pt-1">
                    <div class="text-xs font-black text-black">Penyelesaian Tiket BE:</div>
                    <div class="text-xs font-bold text-emerald-800"><?= $beDoneCount ?>/<?= $beTotalCount ?> Selesai (<?= $beRate ?>%)</div>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-emerald-600 h-1.5 rounded-full" style="width: <?= $beRate ?>%"></div>
                </div>
                <div class="text-xs text-black font-semibold"><b>Story Points:</b> <?= $beDonePts ?> / <?= $beTotalPts ?> pts</div>
            </div>

            <!-- FE -->
            <div class="p-4 rounded-xl border-2 border-amber-300 bg-amber-50/60 space-y-2">
                <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-950 border border-amber-300">Tugas Frontend & UI</span>
                <div class="pt-1">
                    <div class="text-xs font-black text-black">Penyelesaian Tiket FE:</div>
                    <div class="text-xs font-bold text-amber-900"><?= $feDoneCount ?>/<?= $feTotalCount ?> Selesai (<?= $feRate ?>%)</div>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-[#f59e0b] h-1.5 rounded-full" style="width: <?= $feRate ?>%"></div>
                </div>
                <div class="text-xs text-black font-semibold"><b>Story Points:</b> <?= $feDonePts ?> / <?= $feTotalPts ?> pts</div>
            </div>
        </div>
    </div>

    <!-- Compliance & Blockers -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Compliance -->
        <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm space-y-3">
            <h3 class="font-black text-sm text-black uppercase tracking-wider flex items-center gap-1.5">
                <span>Kepatuhan Pengumpulan Logbook</span>
            </h3>

            <div class="divide-y-2 divide-slate-100">
                <?php foreach ($complianceList as $c): ?>
                    <div class="py-2.5 flex items-center justify-between text-xs">
                        <div>
                            <div class="font-black text-black"><?= htmlspecialchars($c['name']) ?></div>
                            <div class="text-[11px] text-[#043399] font-bold">Siswa</div>
                        </div>
                        <?php if ($c['hasSubmitted']): ?>
                            <span class="inline-flex items-center gap-1 text-emerald-800 bg-emerald-100 px-2.5 py-1 rounded-md font-black text-[11px]">
                                <span>Sudah Mengisi</span>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-rose-800 bg-rose-100 px-2.5 py-1 rounded-md font-black text-[11px]">
                                Belum Mengisi
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Blockers -->
        <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm space-y-3">
            <h3 class="font-black text-sm text-black uppercase tracking-wider flex items-center gap-1.5">
                <span>Catatan Hambatan & Blocker</span>
            </h3>

            <?php if (!empty($blockers)): ?>
                <div class="space-y-2">
                    <?php foreach ($blockers as $b): ?>
                        <div class="p-3 bg-amber-50 border border-amber-300 rounded-xl text-xs text-black font-semibold leading-relaxed">
                            • <?= htmlspecialchars($b) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-8 text-center border-2 border-dashed border-slate-300 rounded-xl text-xs text-black font-semibold">
                    Tidak ada kendala / kartu macet pada minggu ini.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- WhatsApp Preview -->
    <div class="bg-[#021f5c] rounded-2xl p-6 text-white shadow-xl space-y-4 border-2 border-[#f59e0b]">
        <div class="flex items-center justify-between border-b border-blue-400/30 pb-3">
            <span class="font-black text-sm text-white flex items-center gap-1.5">
                <span>Pratinjau Format WhatsApp Mingguan</span>
            </span>
            <button onclick="navigator.clipboard.writeText(document.getElementById('waReportCode').innerText); showAppAlert('Teks WhatsApp berhasil disalin!');" class="text-xs text-[#f59e0b] hover:underline font-bold flex items-center gap-1">
                <span>Salin Teks</span>
            </button>
        </div>

        <pre id="waReportCode" class="bg-black/80 p-4 rounded-xl text-xs font-mono text-white whitespace-pre-wrap leading-relaxed overflow-x-auto border border-blue-400/30"><?= htmlspecialchars($waReportMessage) ?></pre>

        <div class="flex justify-end">
            <button type="button" onclick="openWhatsAppModal('Grup Kelas / Guru', '081234567890', <?= htmlspecialchars(json_encode($waReportMessage)) ?>)" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#f59e0b] hover:bg-[#d97706] text-black font-black text-xs rounded-xl shadow-md transition">
                <span>Buka Modal Kirim WhatsApp</span>
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
