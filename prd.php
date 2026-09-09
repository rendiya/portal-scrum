<?php
// prd.php - Clean Product Requirement Document Editor & One-Click Breakdown in PHP
require_once __DIR__ . '/includes/header.php';

// Fetch PRD for active team
$stmt = $pdo->prepare("SELECT * FROM prds WHERE team_id = ? ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$currentTeamId]);
$prd = $stmt->fetch();

if (!$prd) {
    $prd = [
        'id' => 'prd-' . $currentTeamId,
        'team_id' => $currentTeamId,
        'title' => (!empty($currentTeam['project_title']) && $currentTeam['project_title'] !== 'Belum Ditentukan (Diisi di PRD)' && $currentTeam['project_title'] !== 'Belum Ditentukan (Diisi Siswa di PRD)') ? $currentTeam['project_title'] : '',
        'version' => '1.0',
        'status' => 'draft',
        'problem_statement' => '',
        'user_personas' => '',
        'user_stories' => '[]',
        'technical_notes' => '',
        'feedback_teacher' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

$userStories = [];
if (!empty($prd['user_stories'])) {
    $userStories = json_decode($prd['user_stories'], true) ?? [];
}

// Judul proyek yang ditentukan mahasiswa
$projectDisplayTitle = !empty($prd['title']) 
    ? $prd['title'] 
    : ((!empty($currentTeam['project_title']) && $currentTeam['project_title'] !== 'Belum Ditentukan (Diisi di PRD)' && $currentTeam['project_title'] !== 'Belum Ditentukan (Diisi Siswa di PRD)') ? $currentTeam['project_title'] : '');

$viewMode = $_GET['mode'] ?? 'doc'; // 'doc' (Tampilan Dokumen Rapi) atau 'edit' (Form Editor)
?>

<div class="space-y-6">
    <!-- PRD Top Toolbar (Hidden on Print) -->
    <div class="no-print bg-white rounded-2xl p-5 border-2 border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-black">Product Requirement Document (PRD)</h1>
                </div>
                <p class="text-xs text-black font-semibold mt-1">
                    Dokumen spesifikasi proyek <b><?= htmlspecialchars($projectDisplayTitle ?: 'Belum Ditentukan') ?></b> yang disusun siswa sebagai acuan kebutuhan fitur sebelum dipecah menjadi tiket Scrum.
                </p>
            </div>
        </div>

        <!-- Mode Switcher Tabs -->
        <div class="pt-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2">
                <span class="font-black text-black">Mode PRD:</span>
                <a href="prd.php?mode=doc" class="px-3.5 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 <?= $viewMode === 'doc' ? 'bg-[#043399] text-white' : 'bg-slate-100 text-black hover:bg-slate-200' ?>">
                    <span>Tampilan Dokumen (Siap Baca & Cetak)</span>
                </a>
                <a href="prd.php?mode=edit" class="px-3.5 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 <?= $viewMode === 'edit' ? 'bg-[#f59e0b] text-black font-extrabold shadow-xs' : 'bg-slate-100 text-black hover:bg-slate-200' ?>">
                    <span>Form Editor PRD (Tambah & Edit Fitur)</span>
                </a>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-black">Total Story Points:</span>
                    <span class="font-black px-2 py-0.5 rounded bg-blue-100 text-[#043399]">
                        <?php
                        $totalPts = 0;
                        foreach ($userStories as $s) { $totalPts += (int)($s['points'] ?? 3); }
                        echo $totalPts . ' Pts';
                        ?>
                    </span>
                </div>
                <button type="button" onclick="window.print()" class="px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-black text-white font-bold text-xs transition active:scale-95 shadow-sm">
                    Cetak PRD
                </button>
            </div>
        </div>
    </div>

    <?php if ($viewMode === 'doc'): ?>
    <!-- ============================================================= -->
    <!-- VIEW MODE: DOCUMENT PRESENTATION (CLEAN, OFFICIAL, PRINTABLE) -->
    <!-- ============================================================= -->
    <div class="bg-white p-8 sm:p-12 max-w-4xl mx-auto rounded-2xl border-2 border-slate-200 shadow-xl print:shadow-none print:border-none print:p-0 space-y-8 text-black">
        <?php if (empty($projectDisplayTitle)): ?>
            <!-- Alert Banner jika judul proyek belum diisi mahasiswa -->
            <div class="no-print bg-gradient-to-r from-amber-50 to-blue-50 border-2 border-amber-300 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                <div>
                    <div class="text-xs font-black text-amber-900 uppercase tracking-wide">
                        Judul Proyek Belum Ditentukan
                    </div>
                    <p class="text-xs text-black font-semibold mt-0.5">
                        Kelompok <b><?= htmlspecialchars($currentTeam['name']) ?></b> belum memiliki judul proyek. Mahasiswa diharapkan mengisikan judul proyek / aplikasi di sini.
                    </p>
                </div>
                <button type="button" onclick="openEditProjectTitleModal()" class="px-4 py-2 bg-[#043399] hover:bg-[#021f5c] text-white text-xs font-black rounded-lg transition shrink-0 shadow-xs">
                    + Tentukan Judul Proyek Sekarang
                </button>
            </div>
        <?php endif; ?>

        <!-- Document Title & Header -->
        <div class="border-b-2 border-slate-300 pb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <img src="logo/LOGO VINIX.png" alt="VINIX7" style="height: 36px; width: auto;" class="object-contain">
                    <span class="text-xs font-black uppercase tracking-widest text-[#043399] border-l-2 border-slate-300 pl-3">Fast Track Program</span>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-black text-black uppercase tracking-tight">
                        <?= !empty($projectDisplayTitle) ? htmlspecialchars($projectDisplayTitle) : '<span class="text-slate-400 font-bold normal-case italic">Judul Proyek Belum Diisi</span>' ?>
                    </h1>
                    <button type="button" onclick="openEditProjectTitleModal()" class="no-print px-3 py-1 bg-slate-100 hover:bg-slate-200 text-black border border-slate-300 rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-2xs" title="Ubah Judul Proyek Mahasiswa">
                        <span>Edit Judul Proyek</span>
                    </button>
                </div>
                <div class="text-xs text-black font-bold mt-1">
                    Kelompok: <b><?= htmlspecialchars($currentTeam['name'] ?? 'Tim Fast Track') ?></b> &bull; Dokumen ID: <span class="font-mono"><?= htmlspecialchars($prd['id'] ?? 'prd-1') ?></span>
                </div>
            </div>

            <div class="text-left sm:text-right space-y-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-black bg-[#f59e0b] text-black">
                    <span>STATUS: <?= strtoupper(htmlspecialchars($prd['status'] ?? 'Draft')) ?></span>
                </div>
                <div class="text-xs text-black font-semibold">
                    Versi: <span class="font-mono font-bold"><?= htmlspecialchars($prd['version'] ?? '1.0') ?></span>
                </div>
                <div class="text-[11px] text-black/70">
                    Pembaruan: <?= htmlspecialchars($prd['updated_at'] ?? date('Y-m-d')) ?>
                </div>
            </div>
        </div>

        <!-- 1. Problem Statement -->
        <div class="space-y-2">
            <h2 class="text-sm font-black text-[#043399] uppercase tracking-wider flex items-center gap-2 border-b border-blue-100 pb-1.5">
                <span class="w-6 h-6 rounded-lg bg-[#043399] text-white inline-flex items-center justify-center text-xs font-black">1</span>
                Latar Belakang & Problem Statement (The Why)
            </h2>
            <div class="text-xs font-medium leading-relaxed text-black bg-slate-50 p-4 rounded-xl border border-slate-200">
                <?= nl2br(htmlspecialchars($prd['problem_statement'] ?? 'Belum ada penjelasan problem statement.')) ?>
            </div>
        </div>

        <!-- 2. Target Persona -->
        <div class="space-y-2">
            <h2 class="text-sm font-black text-[#043399] uppercase tracking-wider flex items-center gap-2 border-b border-blue-100 pb-1.5">
                <span class="w-6 h-6 rounded-lg bg-[#043399] text-white inline-flex items-center justify-center text-xs font-black">2</span>
                Target Pengguna (User Persona)
            </h2>
            <div class="text-xs font-medium leading-relaxed text-black bg-slate-50 p-4 rounded-xl border border-slate-200">
                <?= nl2br(htmlspecialchars($prd['user_personas'] ?? 'Belum ada target persona.')) ?>
            </div>
        </div>

        <!-- 3. Functional Specifications (User Stories Table) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-black text-[#043399] uppercase tracking-wider flex items-center gap-2 border-b border-blue-100 pb-1.5 flex-1">
                    <span class="w-6 h-6 rounded-lg bg-[#043399] text-white inline-flex items-center justify-center text-xs font-black">3</span>
                    Spesifikasi Fungsional: User Stories & Acceptance Criteria
                </h2>
            </div>
            
            <div class="overflow-x-auto border-2 border-black rounded-xl">
                <table class="w-full text-left border-collapse text-xs text-black">
                    <thead>
                        <tr class="bg-slate-100 border-b-2 border-black">
                            <th class="p-3 font-black border-r border-black w-10 text-center">No</th>
                            <th class="p-3 font-black border-r border-black w-24 text-center">Kategori</th>
                            <th class="p-3 font-black border-r border-black">User Story (Kebutuhan Pengguna)</th>
                            <th class="p-3 font-black border-r border-black">Acceptance Criteria (DoD)</th>
                            <th class="p-3 font-black w-14 text-center">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($userStories)): ?>
                            <tr>
                                <td colspan="5" class="p-4 text-center text-slate-500 italic">
                                    Belum ada User Story yang dimasukkan. Klik tab "Form Editor PRD" untuk menambahkan kebutuhan fitur.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($userStories as $idx => $st): ?>
                                <tr class="border-b border-slate-300 align-top hover:bg-amber-50/50">
                                    <td class="p-3 font-black text-center border-r border-slate-300"><?= $idx + 1 ?>.</td>
                                    <td class="p-3 text-center border-r border-slate-300">
                                        <span class="px-2 py-0.5 rounded font-black text-[10px] <?= ($st['role'] ?? '') === 'FE' ? 'bg-blue-100 text-blue-950' : (($st['role'] ?? '') === 'BE' ? 'bg-emerald-100 text-emerald-950' : 'bg-purple-100 text-purple-950') ?>">
                                            <?= htmlspecialchars($st['role'] ?? 'BOTH') ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-semibold border-r border-slate-300 leading-relaxed">
                                        <?= htmlspecialchars($st['story'] ?? '') ?>
                                    </td>
                                    <td class="p-3 border-r border-slate-300 leading-relaxed font-medium">
                                        <?= nl2br(htmlspecialchars($st['acceptanceCriteria'] ?? 'Standar DoD')) ?>
                                    </td>
                                    <td class="p-3 font-mono font-black text-center">
                                        <?= (int)($st['points'] ?? 3) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. Technical Notes -->
        <div class="space-y-2">
            <h2 class="text-sm font-black text-[#043399] uppercase tracking-wider flex items-center gap-2 border-b border-blue-100 pb-1.5">
                <span class="w-6 h-6 rounded-lg bg-[#043399] text-white inline-flex items-center justify-center text-xs font-black">4</span>
                Catatan Arsitektur & Kontrak API (FE-BE Sync)
            </h2>
            <pre class="text-xs font-mono font-semibold bg-slate-900 text-amber-300 p-4 rounded-xl overflow-x-auto leading-relaxed"><?= htmlspecialchars($prd['technical_notes'] ?? '// Belum ada catatan teknis') ?></pre>
        </div>

        <!-- 5. Teacher Feedback -->
        <div class="space-y-2 bg-blue-50 p-5 rounded-2xl border-2 border-blue-200">
            <h2 class="text-xs font-black text-[#043399] uppercase tracking-wider flex items-center gap-2">
                <span>Catatan & Masukan dari Guru / Instruktur</span>
            </h2>
            <p class="text-xs text-black font-semibold leading-relaxed">
                <?= !empty($prd['feedback_teacher']) ? nl2br(htmlspecialchars($prd['feedback_teacher'])) : 'Belum ada catatan masukan dari instruktur.' ?>
            </p>
        </div>

        <!-- Document Signoff Placeholder for Print -->
        <div class="print:block hidden pt-8 border-t-2 border-slate-300">
            <div class="grid grid-cols-2 gap-8 text-center text-xs font-bold text-black">
                <div>
                    <div>Disusun Oleh Perwakilan Siswa:</div>
                    <div class="h-16"></div>
                    <div class="border-t border-black inline-block px-8">( Ketua Kelompok )</div>
                </div>
                <div>
                    <div>Disetujui Oleh Guru / Instruktur:</div>
                    <div class="h-16"></div>
                    <div class="border-t border-black inline-block px-8">( Guru Pendamping )</div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Main PRD Form -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 border-2 border-slate-200 shadow-sm space-y-8">
        <form id="prdForm" onsubmit="event.preventDefault(); savePRDForm();">
            <input type="hidden" name="id" id="prdId" value="<?= htmlspecialchars($prd['id'] ?? 'prd-1') ?>">
            <input type="hidden" name="team_id" value="<?= htmlspecialchars($currentTeamId) ?>">

            <!-- Document Meta -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pb-6 border-b-2 border-slate-200">
                <div class="md:col-span-2">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-black text-black uppercase tracking-wider">Judul Proyek / Aplikasi Mahasiswa *</label>
                        <span class="text-[10px] text-[#043399] font-bold">Diisi oleh Mahasiswa</span>
                    </div>
                    <input type="text" name="title" id="prdTitle" value="<?= htmlspecialchars($projectDisplayTitle) ?>" placeholder="Contoh: FinTrack - Sistem Manajemen Keuangan UMKM" required class="w-full text-lg font-black text-black px-3 py-2 border-2 border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    <p class="text-[11px] text-slate-600 font-semibold mt-1">Diisi oleh mahasiswa. Judul ini otomatis menjadi nama proyek tim di beranda, papan Scrum, dan laporan.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black text-black uppercase tracking-wider mb-1">Versi</label>
                        <input type="text" name="version" id="prdVersion" value="<?= htmlspecialchars($prd['version'] ?? '1.0') ?>" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-xs font-mono font-bold text-black focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-black uppercase tracking-wider mb-1">Status Dokumen</label>
                        <select name="status" id="prdStatus" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-xs font-black text-black focus:outline-none bg-slate-50">
                            <option value="draft" <?= ($prd['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="in_review" <?= ($prd['status'] ?? '') === 'in_review' ? 'selected' : '' ?>>In Review</option>
                            <option value="approved" <?= ($prd['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="in_development" <?= ($prd['status'] ?? '') === 'in_development' ? 'selected' : '' ?>>In Development</option>
                            <option value="completed" <?= ($prd['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 1. Problem Statement -->
            <div class="space-y-2 mt-6">
                <h3 class="text-sm font-black text-black uppercase tracking-wide flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#043399] text-white inline-flex items-center justify-center text-xs font-bold">1</span>
                    Latar Belakang & Problem Statement (The Why)
                </h3>
                <textarea name="problem_statement" id="prdProblem" rows="4" class="w-full p-3.5 border-2 border-slate-300 rounded-xl text-xs font-medium text-black leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#043399]"><?= htmlspecialchars($prd['problem_statement'] ?? '') ?></textarea>
            </div>

            <!-- 2. Target Persona -->
            <div class="space-y-2 mt-6">
                <h3 class="text-sm font-black text-black uppercase tracking-wide flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#043399] text-white inline-flex items-center justify-center text-xs font-bold">2</span>
                    Target Pengguna (User Persona)
                </h3>
                <textarea name="user_personas" id="prdPersona" rows="3" class="w-full p-3.5 border-2 border-slate-300 rounded-xl text-xs font-medium text-black leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#043399]"><?= htmlspecialchars($prd['user_personas'] ?? '') ?></textarea>
            </div>

            <!-- 3. User Stories -->
            <div class="space-y-4 mt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-black uppercase tracking-wide flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-[#043399] text-white inline-flex items-center justify-center text-xs font-bold">3</span>
                            Spesifikasi Fungsional: User Stories & Acceptance Criteria
                        </h3>
                        <p class="text-xs text-black font-semibold mt-0.5">
                            Format: <i>"Sebagai [user], saya ingin [tindakan], sehingga [manfaat]"</i>
                        </p>
                    </div>

                    <button type="button" onclick="addUserStoryItem()" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-[#043399] border border-blue-300 rounded-lg text-xs font-bold">
                        <span>Tambah Story</span>
                    </button>
                </div>

                <div id="userStoriesContainer" class="space-y-4">
                    <?php foreach ($userStories as $idx => $st): ?>
                        <div class="user-story-card p-4 rounded-xl border-2 border-slate-200 bg-slate-50 space-y-3" data-id="<?= htmlspecialchars($st['id'] ?? 'us-' . $idx) ?>">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-2">
                                <span class="font-bold text-xs text-black">User Story #<span class="story-num"><?= $idx + 1 ?></span></span>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-black font-semibold">Kategori:</span>
                                    <select class="story-role px-2 py-1 bg-white border border-slate-300 rounded text-xs font-bold text-black">
                                        <option value="FE" <?= ($st['role'] ?? '') === 'FE' ? 'selected' : '' ?>>Frontend (FE)</option>
                                        <option value="BE" <?= ($st['role'] ?? '') === 'BE' ? 'selected' : '' ?>>Backend (BE)</option>
                                        <option value="BOTH" <?= ($st['role'] ?? '') === 'BOTH' ? 'selected' : '' ?>>Fullstack / Both</option>
                                    </select>

                                    <span class="text-xs text-black font-semibold">Points:</span>
                                    <select class="story-points px-2 py-1 bg-white border border-slate-300 rounded text-xs font-mono font-black text-black">
                                        <option value="1" <?= ((int)($st['points'] ?? 3)) === 1 ? 'selected' : '' ?>>1 pt</option>
                                        <option value="2" <?= ((int)($st['points'] ?? 3)) === 2 ? 'selected' : '' ?>>2 pts</option>
                                        <option value="3" <?= ((int)($st['points'] ?? 3)) === 3 ? 'selected' : '' ?>>3 pts</option>
                                        <option value="5" <?= ((int)($st['points'] ?? 3)) === 5 ? 'selected' : '' ?>>5 pts</option>
                                        <option value="8" <?= ((int)($st['points'] ?? 3)) === 8 ? 'selected' : '' ?>>8 pts</option>
                                    </select>

                                    <button type="button" onclick="this.closest('.user-story-card').remove(); renumberStories();" class="text-rose-600 hover:text-rose-800 font-bold p-1 text-sm leading-none">&times;</button>
                                </div>
                            </div>

                            <div>
                                <input type="text" class="story-text w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-black" value="<?= htmlspecialchars($st['story'] ?? '') ?>" placeholder="Sebagai pengguna, saya ingin...">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-black mb-1">Acceptance Criteria (Checklist Lolos Uji / DoD):</label>
                                <textarea rows="2" class="story-criteria w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium text-black"><?= htmlspecialchars($st['acceptanceCriteria'] ?? '') ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 4. Technical Notes (Contract) -->
            <div class="space-y-2 mt-6">
                <h3 class="text-sm font-black text-black uppercase tracking-wide flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#043399] text-white inline-flex items-center justify-center text-xs font-bold">4</span>
                    Catatan Arsitektur & Kontrak API (FE-BE Sync)
                </h3>
                <textarea name="technical_notes" id="prdTechnical" rows="3" class="w-full p-3.5 border-2 border-slate-300 rounded-xl text-xs font-mono font-medium text-black bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#043399]"><?= htmlspecialchars($prd['technical_notes'] ?? '') ?></textarea>
            </div>

            <!-- 5. Teacher Feedback -->
            <div class="bg-blue-50/70 rounded-2xl p-5 border-2 border-blue-200 space-y-2 mt-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black text-black uppercase tracking-wider flex items-center gap-2">
                        <span>Catatan & Masukan dari Guru / Instruktur</span>
                    </h3>
                    <?php if ($currentRole === 'guru' || $currentRole === 'super_admin'): ?>
                        <span class="text-[11px] font-black text-[#043399] bg-blue-100 px-2 py-0.5 rounded">Mode Guru Aktif</span>
                    <?php endif; ?>
                </div>
                <textarea name="feedback_teacher" id="prdFeedback" rows="3" <?= ($currentRole !== 'guru' && $currentRole !== 'super_admin') ? 'readonly' : '' ?> class="w-full p-3 bg-white border-2 border-blue-300 rounded-xl text-xs text-black font-semibold focus:outline-none"><?= htmlspecialchars($prd['feedback_teacher'] ?? '') ?></textarea>
            </div>

            <div class="pt-4 flex justify-end gap-3">
                <a href="prd.php?mode=doc" class="px-5 py-2.5 rounded-xl border border-slate-300 font-bold text-xs text-black hover:bg-slate-100">Batal / Kembali ke Dokumen</a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-sm">Simpan PRD</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function renumberStories() {
    document.querySelectorAll('.story-num').forEach((el, i) => {
        el.innerText = i + 1;
    });
}

function addUserStoryItem() {
    const container = document.getElementById('userStoriesContainer');
    if (!container) return;
    const count = container.querySelectorAll('.user-story-card').length + 1;
    const card = document.createElement('div');
    card.className = 'user-story-card p-4 rounded-xl border-2 border-slate-200 bg-slate-50 space-y-3';
    card.dataset.id = 'us-' + Date.now();
    card.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-2">
            <span class="font-bold text-xs text-black">User Story #<span class="story-num">${count}</span></span>
            <div class="flex items-center gap-2">
                <span class="text-xs text-black font-semibold">Kategori:</span>
                <select class="story-role px-2 py-1 bg-white border border-slate-300 rounded text-xs font-bold text-black">
                    <option value="FE">Frontend (FE)</option>
                    <option value="BE">Backend (BE)</option>
                    <option value="BOTH">Fullstack / Both</option>
                </select>
                <span class="text-xs text-black font-semibold">Points:</span>
                <select class="story-points px-2 py-1 bg-white border border-slate-300 rounded text-xs font-mono font-black text-black">
                    <option value="1">1 pt</option>
                    <option value="2">2 pts</option>
                    <option value="3" selected>3 pts</option>
                    <option value="5">5 pts</option>
                    <option value="8">8 pts</option>
                </select>
                <button type="button" onclick="this.closest('.user-story-card').remove(); renumberStories();" class="text-rose-600 hover:text-rose-800 font-bold p-1 text-sm leading-none">&times;</button>
            </div>
        </div>
        <div>
            <input type="text" class="story-text w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-black" placeholder="Sebagai pengguna, saya ingin...">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-black mb-1">Acceptance Criteria (Checklist Lolos Uji / DoD):</label>
            <textarea rows="2" class="story-criteria w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium text-black" placeholder="1. Kriteria lolos uji..."></textarea>
        </div>
    `;
    container.appendChild(card);
}

function collectStories() {
    const stories = [];
    document.querySelectorAll('.user-story-card').forEach((card, i) => {
        stories.push({
            id: card.dataset.id || ('us-' + i),
            story: card.querySelector('.story-text').value,
            acceptanceCriteria: card.querySelector('.story-criteria').value,
            role: card.querySelector('.story-role').value,
            points: parseInt(card.querySelector('.story-points').value) || 3
        });
    });
    return stories;
}

async function savePRDForm() {
    const titleEl = document.getElementById('prdTitle');
    if (!titleEl) return;

    const payload = {
        id: document.getElementById('prdId').value,
        team_id: '<?= $currentTeamId ?>',
        title: titleEl.value,
        version: document.getElementById('prdVersion').value,
        status: document.getElementById('prdStatus').value,
        problem_statement: document.getElementById('prdProblem').value,
        user_personas: document.getElementById('prdPersona').value,
        technical_notes: document.getElementById('prdTechnical').value,
        feedback_teacher: document.getElementById('prdFeedback').value,
        user_stories: collectStories()
    };

    try {
        const res = await fetch('api.php?action=save_prd', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            await showAppAlert('PRD dan Judul Proyek berhasil disimpan!');
            window.location.href = 'prd.php?mode=doc';
        } else {
            await showAppAlert(data.error || 'Gagal menyimpan PRD');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

// 1-Click Breakdown directly from Document View or Form
async function triggerBreakdownFromDoc() {
    <?php if ($viewMode === 'edit'): ?>
        const stories = collectStories();
    <?php else: ?>
        const stories = <?= json_encode($userStories) ?>;
    <?php endif; ?>

    if (!stories || stories.length === 0) {
        await showAppAlert('Tidak ada User Story di PRD ini. Silakan tambahkan User Story terlebih dahulu di Form Editor!');
        window.location.href = 'prd.php?mode=edit';
        return;
    }

    const ok = await showAppConfirm('Apakah kamu ingin memecah ' + stories.length + ' User Story ini langsung menjadi tiket di Scrum Kanban Board?', {
        title: 'Konfirmasi Breakdown PRD',
        confirmText: 'Ya, Breakdown ke Board',
        confirmColor: 'navy'
    });
    if (!ok) return;

    try {
        const res = await fetch('api.php?action=breakdown_prd', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                prd_id: '<?= htmlspecialchars($prd['id'] ?? 'prd-1') ?>',
                team_id: '<?= $currentTeamId ?>',
                user_stories: stories
            })
        });
        const data = await res.json();
        if (data.success) {
            await showAppAlert(data.message);
            window.location.href = 'board.php';
        } else {
            await showAppAlert(data.error || 'Gagal breakdown PRD');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

// Modal Cepat Ubah Judul Proyek Mahasiswa
function openEditProjectTitleModal() {
    const m = document.getElementById('editProjectTitleModal');
    if (m) {
        m.style.display = 'flex';
        m.classList.remove('hidden');
        setTimeout(() => {
            const inp = document.getElementById('quickProjectTitleInput');
            if (inp) {
                inp.focus();
                inp.select();
            }
        }, 50);
    }
}

function closeEditProjectTitleModal() {
    const m = document.getElementById('editProjectTitleModal');
    if (m) {
        m.style.display = 'none';
        m.classList.add('hidden');
    }
}

async function handleSaveProjectTitle(e) {
    e.preventDefault();
    const title = document.getElementById('quickProjectTitleInput').value.trim();
    if (!title) {
        await showAppAlert('Silakan masukkan judul proyek / aplikasi.');
        return;
    }

    try {
        const res = await fetch('api.php?action=update_project_title', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: '<?= $currentTeamId ?>',
                title: title
            })
        });
        const result = await res.json();
        if (result.success) {
            closeEditProjectTitleModal();
            await showAppAlert('Judul proyek berhasil disimpan oleh mahasiswa!');
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal menyimpan judul proyek');
        }
    } catch (err) {
        await showAppAlert('Terjadi kesalahan: ' + err.message);
    }
}
</script>

<!-- Modal Cepat Ubah Judul Proyek (Diisi oleh Mahasiswa) -->
<div id="editProjectTitleModal" class="fixed inset-0 z-50 bg-black/60 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white w-full max-w-md rounded-2xl border-2 border-slate-300 shadow-2xl overflow-hidden p-6 space-y-4" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <span class="text-[10px] font-black text-[#043399] uppercase tracking-wider block">Kelompok: <?= htmlspecialchars($currentTeam['name']) ?></span>
                <h3 class="text-sm font-black text-black">Tentukan Judul Proyek / Aplikasi</h3>
            </div>
            <button type="button" onclick="closeEditProjectTitleModal()" class="text-slate-500 hover:text-black text-xl font-black px-2 leading-none" title="Tutup Modal">&times;</button>
        </div>

        <p class="text-xs text-black font-semibold">
            Mahasiswa bebas menentukan nama produk atau aplikasi yang akan dikerjakan bersama tim:
        </p>

        <form onsubmit="handleSaveProjectTitle(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-black mb-1">Judul Proyek / Aplikasi *</label>
                <input type="text" id="quickProjectTitleInput" required value="<?= htmlspecialchars($projectDisplayTitle) ?>" placeholder="Contoh: EduLearn - Platform Kursus Online" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                <p class="text-[11px] text-slate-500 font-semibold mt-1">Judul ini akan otomatis muncul di seluruh modul Scrum tim.</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeEditProjectTitleModal()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-black text-xs font-bold rounded-lg transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-[#043399] hover:bg-[#021f5c] text-white text-xs font-black rounded-lg transition shadow-xs">
                    Simpan Judul Proyek
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
