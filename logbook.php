<?php
// logbook.php - Format Logbook Fast Track PT VINIX SEVEN AURUM (Identik dengan PDF Sample)
require_once __DIR__ . '/includes/header.php';

$selectedWeek = (int)($_GET['week'] ?? 1);

// Selected student to view (Strict isolation: siswa cannot view other students)
if ($currentRole === 'siswa') {
    $viewStudentId = $currentStudent['id'] ?? 'mem-pm-1';
} else {
    $viewStudentId = $_GET['student_id'] ?? ($teamStudents[0]['id'] ?? 'mem-pm-1');
}

// Fetch student info
$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ? LIMIT 1");
$stmt->execute([$viewStudentId]);
$targetStudent = $stmt->fetch() ?: $currentStudent ?: [
    'id' => 'mem-pm-1',
    'name' => 'Ahmad Rizky',
    'university' => 'Universitas Dian Nuswantoro',
    'major' => 'Teknik Informatika',
    'role' => 'siswa'
];

// Fetch entries for this student
$stmt = $pdo->prepare("SELECT * FROM logbooks WHERE student_id = ? ORDER BY created_at ASC, id ASC");
$stmt->execute([$targetStudent['id']]);
$entries = $stmt->fetchAll();

// Fetch all LMS modules for quick import
$stmt = $pdo->query("SELECT week_number, title, summary FROM lms_modules ORDER BY week_number ASC");
$lmsModules = $stmt->fetchAll();

// Fetch Kanban tasks assigned to this student (or team tasks fallback)
$stmt = $pdo->prepare("
    SELECT id, title, status, story_points, role_category, assignee_name 
    FROM tasks 
    WHERE (assignee_name = ? OR assignee_id = ?)
    ORDER BY CASE status 
        WHEN 'in_progress' THEN 1 
        WHEN 'in_review' THEN 2 
        WHEN 'done' THEN 3 
        WHEN 'sprint_backlog' THEN 4 
        ELSE 5 END, updated_at DESC
");
$stmt->execute([$targetStudent['name'], $targetStudent['id']]);
$studentTasks = $stmt->fetchAll();

if (empty($studentTasks)) {
    $stmt = $pdo->prepare("SELECT id, title, status, story_points, role_category, assignee_name FROM tasks WHERE team_id = ? ORDER BY status ASC LIMIT 10");
    $stmt->execute([$currentTeamId]);
    $studentTasks = $stmt->fetchAll();
}
?>

<div class="space-y-6">
    <!-- TOOLBAR & FORM INPUT (Hidden when printing via .no-print) -->
    <div class="no-print bg-white rounded-2xl p-5 border-2 border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-black">Log Book Kegiatan Fast Track</h1>
                    <span class="px-2.5 py-0.5 rounded-full bg-[#043399] text-white text-xs font-bold">
                        Format PDF
                    </span>
                </div>
                <p class="text-xs text-black font-semibold mt-1">
                    Format cetak PT VINIX SEVEN AURUM. Catatan kegiatan harian otomatis terisi ke tabel di bawah.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <?php if ($currentRole === 'guru' && count($teamStudents) > 0): ?>
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="font-bold text-black">Pilih Siswa:</span>
                        <select onchange="window.location.href='logbook.php?student_id=' + this.value" class="bg-slate-100 border border-slate-300 font-bold text-black rounded-lg px-2.5 py-1.5 text-xs focus:outline-none cursor-pointer">
                            <?php foreach ($teamStudents as $st): ?>
                                <option value="<?= htmlspecialchars($st['id']) ?>" <?= $st['id'] === $targetStudent['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($st['name']) ?> (Siswa)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <button type="button" onclick="openLogbookModal()" class="px-4 py-2 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-sm transition active:scale-95 flex items-center gap-1.5">
                    + Isi Catatan Hari Ini
                </button>

                <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-black hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition active:scale-95">
                    Cetak ke PDF (Print)
                </button>
            </div>
        </div>

        <!-- Formulir Input Langsung (Kedua Field: Hari/Tanggal & Deskripsi Kegiatan) -->
        <div class="pt-4 border-t border-slate-200">
            <form id="quickLogbookForm" onsubmit="handleQuickLogbookSubmit(event)" class="space-y-3">
                <input type="hidden" name="team_id" value="<?= htmlspecialchars($currentTeamId) ?>">
                <input type="hidden" name="student_id" value="<?= htmlspecialchars($targetStudent['id']) ?>">
                <input type="hidden" name="student_name" value="<?= htmlspecialchars($targetStudent['name']) ?>">
                <input type="hidden" name="week_number" value="<?= $selectedWeek ?>">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs">
                    <span class="font-black text-black uppercase tracking-wide">
                        Tambah Catatan Harian (Otomatis Masuk ke Tabel di Bawah)
                    </span>
                    <span class="text-[11px] text-slate-500 font-semibold">
                        Gunakan pintasan import atau ketik manual. Urutan baris otomatis 1, 2, 3 dst.
                    </span>
                </div>

                <!-- Pintasan Import Cepat di Atas Form -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-[11px] text-black shrink-0">Import Materi LMS:</span>
                        <select onchange="importToTextarea(this.value, 'entryDescInput', 'lms')" class="w-full bg-white border border-slate-300 font-semibold text-black rounded-lg px-2 py-1 text-xs focus:outline-none focus:border-[#043399] cursor-pointer">
                            <option value="">-- Pilih Materi LMS --</option>
                            <?php foreach ($lmsModules as $mod): ?>
                                <option value="<?= htmlspecialchars($mod['title']) ?>">
                                    Pertemuan <?= $mod['week_number'] ?>: <?= htmlspecialchars(preg_replace('/^(Minggu|Pekan|Pertemuan) \d+: /', '', $mod['title'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="font-bold text-[11px] text-black shrink-0">Import dari Kanban (<?= htmlspecialchars($targetStudent['name']) ?>):</span>
                        <select onchange="importKanbanToTextarea(this, 'entryDescInput')" class="w-full bg-white border border-slate-300 font-semibold text-black rounded-lg px-2 py-1 text-xs focus:outline-none focus:border-[#043399] cursor-pointer">
                            <option value="">-- Pilih Tugas Kanban --</option>
                            <?php if (empty($studentTasks)): ?>
                                <option value="" disabled>(Belum ada tiket kanban)</option>
                            <?php else: ?>
                                <?php foreach ($studentTasks as $t): ?>
                                    <option value="<?= htmlspecialchars($t['title']) ?>" data-status="<?= htmlspecialchars($t['status']) ?>" data-pts="<?= (int)($t['story_points'] ?? 1) ?>">
                                        [<?= strtoupper(str_replace('_', ' ', $t['status'])) ?>] <?= htmlspecialchars($t['title']) ?> (<?= (int)$t['story_points'] ?> Pts)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                    <div class="md:col-span-4">
                        <label class="block text-[11px] font-bold text-black mb-1">Hari / Tanggal *</label>
                        <input type="text" name="entry_date" id="entryDateInput" required value="<?= date('j F Y') ?>" placeholder="Contoh: 10 Maret 2026" class="w-full px-3 py-2 bg-slate-50 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:bg-white focus:outline-none focus:border-[#043399]">
                    </div>

                    <div class="md:col-span-8">
                        <label class="block text-[11px] font-bold text-black mb-1">Deskripsi Kegiatan *</label>
                        <div class="flex gap-2">
                            <textarea name="description" id="entryDescInput" rows="2" required placeholder="Tuliskan kegiatan harian, atau klik dropdown import materi LMS & Kanban di atas..." class="w-full px-3 py-2 bg-slate-50 border-2 border-slate-300 rounded-xl text-xs font-medium text-black focus:bg-white focus:outline-none focus:border-[#043399] resize-none"></textarea>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shrink-0 shadow-sm transition active:scale-95 flex items-center justify-center">
                                + Simpan ke Tabel
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- PRINTABLE SHEET (100% Matching Template LOGBOOK Kegiatan Fast Track.pdf) -->
    <div class="logbook-official-sheet bg-white p-8 sm:p-14 max-w-4xl mx-auto text-black border border-slate-300 shadow-xl print:shadow-none print:border-none print:p-0">
        <!-- Logo: VINIX7 on TOP-LEFT -->
        <div class="mb-5">
            <?php if (file_exists(__DIR__ . '/logo/LOGO VINIX.png')): ?>
                <img src="logo/LOGO VINIX.png" alt="VINIX7" style="height: 38px; width: auto;" class="object-contain">
            <?php else: ?>
                <div style="font-size: 24px; font-weight: 900; color: #043399;">
                    VINIX<span style="color: #f59e0b;">7</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Document Title -->
        <div style="margin-bottom: 16px;">
            <div style="font-weight: 800; font-size: 13pt; text-transform: uppercase; line-height: 1.35; color: #000; letter-spacing: 0.2px;">
                LOGBOOK MINGGUAN KEGIATAN PROGRAM FAST TRACK JUNI 2026
            </div>
            <div style="font-weight: 800; font-size: 11pt; text-transform: uppercase; line-height: 1.35; color: #000; margin-top: 1px;">
                PT VINIX SEVEN AURUM
            </div>
        </div>

        <!-- Guidelines Points 1 & 2 -->
        <div style="font-size: 10.5pt; line-height: 1.45; color: #000; margin-bottom: 20px;">
            <div>1. Wajib diisi dengan kegiatan harian, contoh: briefing, apa yang dipelajari, mengerjakan tugas apa saja, diskusi kelompok, dll.</div>
            <div>2. Wajib dikumpulkan setiap minggu bersama dengan pengumpulan tugas.</div>
        </div>

        <!-- Student Information Key-Value List (Left Aligned, Single Column) -->
        <div style="font-size: 10.5pt; line-height: 1.55; color: #000; margin-bottom: 20px;">
            <table style="border: none; border-collapse: collapse; font-size: 10.5pt; color: #000;">
                <tr>
                    <td style="width: 120px; padding: 1.5px 0;">Nama</td>
                    <td style="width: 15px; padding: 1.5px 0; text-align: center;">:</td>
                    <td style="padding: 1.5px 0; font-weight: 600;"><?= htmlspecialchars($targetStudent['name'] ?? 'Ahmad Rizky') ?></td>
                </tr>
                <tr>
                    <td style="padding: 1.5px 0;">Universitas</td>
                    <td style="padding: 1.5px 0; text-align: center;">:</td>
                    <td style="padding: 1.5px 0;"><?= htmlspecialchars($targetStudent['university'] ?? 'Universitas Dian Nuswantoro') ?></td>
                </tr>
                <tr>
                    <td style="padding: 1.5px 0;">Prodi</td>
                    <td style="padding: 1.5px 0; text-align: center;">:</td>
                    <td style="padding: 1.5px 0;"><?= htmlspecialchars($targetStudent['major'] ?? 'Teknik Informatika') ?></td>
                </tr>
                <tr>
                    <td style="padding: 1.5px 0;">Divisi</td>
                    <td style="padding: 1.5px 0; text-align: center;">:</td>
                    <td style="padding: 1.5px 0;">Siswa</td>
                </tr>
                <tr>
                    <td style="padding: 1.5px 0;">Minggu ke</td>
                    <td style="padding: 1.5px 0; text-align: center;">:</td>
                    <td style="padding: 1.5px 0; font-weight: 600;"><?= $selectedWeek ?></td>
                </tr>
            </table>
        </div>

        <!-- Official Table (Black Borders, Matching Sample PDF) -->
        <table style="width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 10.5pt; color: #000;">
            <thead>
                <tr>
                    <th style="border: 1.5px solid #000; padding: 8px 6px; width: 42px; text-align: center; font-weight: 700; color: #000;">No</th>
                    <th style="border: 1.5px solid #000; padding: 8px 10px; width: 140px; text-align: left; font-weight: 700; color: #000;">Hari/Tanggal</th>
                    <th style="border: 1.5px solid #000; padding: 8px 12px; text-align: left; font-weight: 700; color: #000;">Deskripsi Kegiatan</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rowCount = 0;
                if (!empty($entries)):
                    foreach ($entries as $idx => $e): 
                        $rowCount++;
                ?>
                    <tr style="vertical-align: top;">
                        <td style="border: 1.5px solid #000; padding: 10px 6px; text-align: center; font-weight: 600;">
                            <?= $idx + 1 ?>.
                        </td>
                        <td style="border: 1.5px solid #000; padding: 10px 10px; font-weight: 600;">
                            <?= htmlspecialchars($e['entry_date']) ?>
                        </td>
                        <td style="border: 1.5px solid #000; padding: 10px 12px; line-height: 1.5; white-space: pre-line;">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <?= htmlspecialchars($e['description']) ?>
                                    <?php if (!empty($e['teacher_notes'])): ?>
                                        <div class="no-print mt-2 pt-2 border-t border-blue-200 text-xs text-[#043399] font-bold">
                                            Catatan Guru: <?= htmlspecialchars($e['teacher_notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="no-print shrink-0">
                                    <button type="button" onclick="deleteLogbookEntry('<?= htmlspecialchars($e['id']) ?>')" title="Hapus baris ini" class="text-[11px] px-2 py-0.5 rounded bg-red-50 text-red-700 hover:bg-red-100 font-bold border border-red-200 transition">
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php 
                    endforeach;
                endif;
                ?>

                <!-- Sisa baris kosong hingga minimal 6 baris agar proporsi tabel sama persis dengan template PDF -->
                <?php for ($i = $rowCount + 1; $i <= max(6, $rowCount); $i++): ?>
                    <tr style="height: 65px; vertical-align: top;">
                        <td style="border: 1.5px solid #000; padding: 8px 6px; text-align: center;"></td>
                        <td style="border: 1.5px solid #000; padding: 8px 10px;"></td>
                        <td style="border: 1.5px solid #000; padding: 8px 12px;"></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <!-- Teacher Review Section for Screen (Hidden on Print) -->
        <?php if (!empty($entries) && ($currentRole === 'guru' || $currentRole === 'super_admin')): ?>
            <div class="no-print mt-8 p-4 bg-blue-50 border-2 border-blue-200 rounded-2xl space-y-3">
                <div class="flex items-center gap-2 text-xs font-black text-[#043399]">
                    <span>Tinjauan & Paraf Guru (Instruktur)</span>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-2">
                    <input type="text" id="guruNoteInput" placeholder="Tuliskan catatan apresiasi / paraf review untuk logbook pertemuan ini..." class="w-full sm:flex-1 px-3 py-2 bg-white border border-blue-300 rounded-lg text-xs font-semibold text-black">
                    <button onclick="saveAllTeacherReview('<?= $targetStudent['id'] ?>', <?= $selectedWeek ?>)" class="w-full sm:w-auto px-4 py-2 bg-[#043399] hover:bg-[#021f5c] text-white font-bold text-xs rounded-lg transition">
                        Beri Paraf Pertemuan Ini
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: Tambah Catatan Log Book Harian (Centered & Spacious) -->
<div id="logbookModal" class="no-print fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs p-4 sm:p-6" style="display: none;">
    <div class="min-h-full flex items-center justify-center p-2">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden border-2 border-[#043399] transition-all">
            <!-- Modal Header -->
            <div class="bg-[#043399] text-white px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-black text-base text-white">Tambah Catatan Log Book Harian</h3>
                    <p class="text-xs text-amber-300 font-semibold mt-0.5">
                        Siswa: <?= htmlspecialchars($targetStudent['name']) ?> • Universitas: <?= htmlspecialchars($targetStudent['university']) ?>
                    </p>
                </div>
                <button type="button" onclick="closeLogbookModal()" class="text-white hover:text-amber-300 font-bold text-2xl p-1 leading-none transition">&times;</button>
            </div>

            <form onsubmit="handleModalLogbookSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" name="team_id" value="<?= htmlspecialchars($currentTeamId) ?>">
                <input type="hidden" name="student_id" value="<?= htmlspecialchars($targetStudent['id']) ?>">
                <input type="hidden" name="student_name" value="<?= htmlspecialchars($targetStudent['name']) ?>">
                <input type="hidden" name="week_number" value="<?= $selectedWeek ?>">

                <!-- Pintasan Import Cepat -->
                <div class="bg-blue-50/80 border-2 border-blue-200 rounded-xl p-3.5 space-y-2.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-black text-[#043399] uppercase tracking-wide">
                            Pintasan Import Cepat ke Deskripsi:
                        </span>
                        <span class="text-[11px] text-slate-500 font-semibold">
                            Klik materi atau tiket untuk menyisipkan
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Import Materi LMS -->
                        <div>
                            <label class="block text-[11px] font-bold text-black mb-1">
                                1. Import Materi LMS:
                            </label>
                            <select id="modalImportLms" onchange="importToTextarea(this.value, 'modalDescInput', 'lms')" class="w-full bg-white border-2 border-blue-300 font-semibold text-black rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:border-[#043399] cursor-pointer shadow-xs">
                                <option value="">-- Pilih Materi LMS --</option>
                                <?php foreach ($lmsModules as $mod): ?>
                                    <option value="<?= htmlspecialchars($mod['title']) ?>">
                                        Pertemuan <?= $mod['week_number'] ?>: <?= htmlspecialchars(preg_replace('/^(Minggu|Pekan|Pertemuan) \d+: /', '', $mod['title'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Import Tugas Kanban -->
                        <div>
                            <label class="block text-[11px] font-bold text-black mb-1">
                                2. Import dari Kanban (<?= htmlspecialchars($targetStudent['name']) ?>):
                            </label>
                            <select id="modalImportKanban" onchange="importKanbanToTextarea(this, 'modalDescInput')" class="w-full bg-white border-2 border-blue-300 font-semibold text-black rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:border-[#043399] cursor-pointer shadow-xs">
                                <option value="">-- Pilih Tugas Kanban Saya --</option>
                                <?php if (empty($studentTasks)): ?>
                                    <option value="" disabled>(Belum ada tiket kanban untuk <?= htmlspecialchars($targetStudent['name']) ?>)</option>
                                <?php else: ?>
                                    <?php foreach ($studentTasks as $t): ?>
                                        <option value="<?= htmlspecialchars($t['title']) ?>" data-status="<?= htmlspecialchars($t['status']) ?>" data-pts="<?= (int)($t['story_points'] ?? 1) ?>">
                                            [<?= strtoupper(str_replace('_', ' ', $t['status'])) ?>] <?= htmlspecialchars($t['title']) ?> (<?= (int)$t['story_points'] ?> Pts)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Input Hari/Tanggal -->
                <div>
                    <label class="block text-xs font-black text-black uppercase tracking-wider mb-1">
                        Hari / Tanggal *
                    </label>
                    <input type="text" name="entry_date" id="modalEntryDate" required value="<?= date('j F Y') ?>" placeholder="Contoh: 10 Maret 2026" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none focus:border-[#043399] bg-slate-50 focus:bg-white">
                </div>

                <!-- Input Deskripsi Kegiatan -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-black text-black uppercase tracking-wider">
                            Deskripsi Kegiatan *
                        </label>
                        <span class="text-[11px] text-slate-500 font-medium">Bisa diedit atau ditambah manual</span>
                    </div>
                    <textarea name="description" id="modalDescInput" rows="5" required placeholder="Tuliskan aktivitas harian Anda, atau gunakan dropdown import materi LMS dan Kanban di atas..." class="w-full p-3.5 border-2 border-slate-300 rounded-xl text-xs font-medium text-black leading-relaxed focus:outline-none focus:border-[#043399] bg-slate-50 focus:bg-white"></textarea>
                </div>

                <!-- Actions -->
                <div class="pt-3 border-t border-slate-200 flex items-center justify-between">
                    <button type="button" onclick="closeLogbookModal()" class="px-4 py-2 rounded-xl text-black font-bold text-xs hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-sm transition active:scale-95">
                        + Simpan ke Logbook
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function importToTextarea(val, textareaId, type) {
    if (!val) return;
    const descArea = document.getElementById(textareaId);
    if (!descArea) return;
    
    let template = '';
    if (type === 'lms') {
        template = 'Mempelajari materi LMS: ' + val + '. Memahami konsep dan implementasinya pada proyek.';
    }
    
    if (!descArea.value || descArea.value.trim() === '') {
        descArea.value = template;
    } else {
        descArea.value = descArea.value.trim() + '\n' + template;
    }
    descArea.focus();
}

function importKanbanToTextarea(selectEl, textareaId) {
    if (!selectEl || !selectEl.value) return;
    const opt = selectEl.options[selectEl.selectedIndex];
    const title = opt.value;
    const status = (opt.getAttribute('data-status') || '').toLowerCase();
    const pts = opt.getAttribute('data-pts') || '1';
    
    let action = 'Mengerjakan';
    if (status === 'done') action = 'Menyelesaikan';
    else if (status === 'in_review') action = 'Melakukan review dan pengujian untuk';
    else if (status === 'sprint_backlog') action = 'Mempersiapkan pengerjaan';
    
    const formattedStatus = status.replace('_', ' ').toUpperCase();
    const template = action + ' tiket Kanban: "' + title + '" (Status: ' + formattedStatus + ', ' + pts + ' Story Points).';
    
    const descArea = document.getElementById(textareaId);
    if (!descArea) return;
    
    if (!descArea.value || descArea.value.trim() === '') {
        descArea.value = template;
    } else {
        descArea.value = descArea.value.trim() + '\n' + template;
    }
    descArea.focus();
}

function openLogbookModal() {
    const modal = document.getElementById('logbookModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeLogbookModal() {
    const modal = document.getElementById('logbookModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

async function handleModalLogbookSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const entryDate = form.entry_date.value.trim();
    const description = form.description.value.trim();

    if (!entryDate || !description) {
        await showAppAlert('Harap isi kedua kolom: Hari/Tanggal dan Deskripsi Kegiatan!');
        return;
    }

    const payload = {
        team_id: form.team_id.value,
        student_id: form.student_id.value,
        student_name: form.student_name.value,
        week_number: form.week_number.value,
        entry_date: entryDate,
        description: description
    };

    try {
        const res = await fetch('api.php?action=add_logbook', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
            closeLogbookModal();
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal menyimpan catatan logbook');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

async function handleQuickLogbookSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const entryDate = form.entry_date.value.trim();
    const description = form.description.value.trim();

    if (!entryDate || !description) {
        await showAppAlert('Harap isi kedua kolom: Hari/Tanggal dan Deskripsi Kegiatan!');
        return;
    }

    const payload = {
        team_id: form.team_id.value,
        student_id: form.student_id.value,
        student_name: form.student_name.value,
        week_number: form.week_number.value,
        entry_date: entryDate,
        description: description
    };

    try {
        const res = await fetch('api.php?action=add_logbook', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal menyimpan catatan logbook');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

async function deleteLogbookEntry(id) {
    const ok = await showAppConfirm('Apakah Anda yakin ingin menghapus catatan baris ini?', {
        title: 'Konfirmasi Hapus Catatan',
        confirmText: 'Ya, Hapus',
        confirmColor: 'red'
    });
    if (!ok) return;
    try {
        const res = await fetch('api.php?action=delete_logbook', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const result = await res.json();
        if (result.success) {
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal menghapus catatan');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

async function saveAllTeacherReview(studentId, week) {
    const input = document.getElementById('guruNoteInput');
    const notes = input ? input.value.trim() : '';
    if (!notes) {
        await showAppAlert('Harap masukkan catatan review / paraf');
        return;
    }

    try {
        const res = await fetch('api.php?action=review_logbook_week', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ student_id: studentId, week_number: week, teacher_notes: notes })
        });
        const result = await res.json();
        if (result.success) {
            await showAppAlert('Paraf guru berhasil disimpan');
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal menyimpan review');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
