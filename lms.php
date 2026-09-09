<?php
// lms.php - Clean Weekly Learning Modules for VINIX7 ScrumVibe
require_once __DIR__ . '/includes/header.php';

$isGuru = ($currentRole === 'guru' || $currentRole === 'super_admin');
$selectedWeek = (int)($_GET['week'] ?? 1);

// Fetch all LMS modules
$stmt = $pdo->query("SELECT * FROM lms_modules ORDER BY week_number ASC");
$modules = $stmt->fetchAll();

$currentModule = null;
foreach ($modules as $m) {
    if ((int)$m['week_number'] === $selectedWeek) {
        $currentModule = $m;
        break;
    }
}
if (!$currentModule && count($modules) > 0) {
    $currentModule = $modules[0];
    $selectedWeek = (int)$currentModule['week_number'];
}

$objectives = $currentModule ? (json_decode($currentModule['objectives'] ?? '[]', true) ?? []) : [];
$deliverables = $currentModule ? (json_decode($currentModule['deliverables'] ?? '[]', true) ?? []) : [];
$externalLinks = $currentModule ? (json_decode($currentModule['external_links'] ?? '[]', true) ?? []) : [];
?>

<div class="space-y-6">
    <!-- Header Banner -->
    <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 bg-[#043399] text-white text-[11px] font-black rounded-full uppercase tracking-wider">Kurikulum Fast Track</span>
                <?php if ($isGuru): ?>
                    <span class="px-2.5 py-0.5 bg-purple-100 text-purple-900 border border-purple-300 text-[11px] font-black rounded-full">Mode Guru: Akses Edit Aktif</span>
                <?php else: ?>
                    <span class="px-2.5 py-0.5 bg-slate-100 text-slate-800 border border-slate-300 text-[11px] font-bold rounded-full">Mode Siswa: Hanya Baca</span>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl font-black text-black">Materi Pembelajaran</h1>
            <p class="text-xs text-black font-semibold mt-1">
                Panduan kurikulum bertahap per pertemuan untuk membimbing tim siswa dari konsep awal hingga rilis produk jadi.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <?php if ($isGuru): ?>
                <button type="button" onclick="openEditModuleModal(null)" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-black border border-slate-300 rounded-xl text-xs font-black transition">
                    + Tambah Pertemuan Baru
                </button>
                <?php if ($currentModule): ?>
                    <button type="button" onclick="openEditModuleModal('<?= htmlspecialchars($currentModule['id']) ?>')" class="px-4 py-2 bg-[#043399] hover:bg-[#021f5c] text-white rounded-xl text-xs font-black shadow-sm transition">
                        Edit Materi Pertemuan <?= $currentModule['week_number'] ?>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <span class="px-3 py-1.5 bg-blue-50 text-[#043399] border border-blue-200 rounded-xl text-xs font-black">
                    Total <?= count($modules) ?> Modul Pertemuan
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grid: Left Navigation & Right Lesson Content -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        <!-- Left: Week selector -->
        <div class="lg:col-span-1 space-y-2">
            <div class="flex items-center justify-between px-2 mb-2">
                <span class="text-xs font-black text-black uppercase tracking-wider">
                    Daftar Modul Pertemuan
                </span>
                <?php if ($isGuru): ?>
                    <button type="button" onclick="openEditModuleModal(null)" class="text-[11px] text-[#043399] hover:underline font-black">
                        + Tambah
                    </button>
                <?php endif; ?>
            </div>

            <?php foreach ($modules as $m): ?>
                <a href="lms.php?week=<?= $m['week_number'] ?>" class="block p-4 rounded-xl border-2 transition <?= (int)$m['week_number'] === $selectedWeek ? 'bg-blue-50 border-[#043399] shadow-sm' : 'bg-white border-slate-200 hover:bg-slate-50' ?>">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full <?= (int)$m['week_number'] === $selectedWeek ? 'bg-[#043399] text-white' : 'bg-slate-200 text-black' ?>">
                            Pertemuan <?= $m['week_number'] ?>
                        </span>
                        <?php if ($isGuru): ?>
                            <button type="button" onclick="event.preventDefault(); event.stopPropagation(); openEditModuleModal('<?= htmlspecialchars($m['id']) ?>')" class="text-[10px] text-[#043399] hover:underline font-black">
                                Edit
                            </button>
                        <?php endif; ?>
                    </div>
                    <h4 class="text-xs font-black mt-2 text-black line-clamp-2">
                        <?= htmlspecialchars(preg_replace('/^(Minggu|Pekan|Pertemuan) \d+: /', '', $m['title'])) ?>
                    </h4>
                </a>
            <?php endforeach; ?>

            <?php if ($isGuru): ?>
                <button type="button" onclick="openEditModuleModal(null)" class="w-full py-2.5 px-3 border-2 border-dashed border-slate-300 hover:border-[#043399] hover:bg-blue-50 text-slate-700 hover:text-[#043399] rounded-xl text-xs font-black transition text-center mt-2">
                    + Tambah Modul Pertemuan Baru
                </button>
            <?php endif; ?>
        </div>

        <!-- Right: Lesson Details -->
        <div class="lg:col-span-3 space-y-6">
            <?php if ($currentModule): ?>
                <div class="bg-white rounded-2xl p-6 sm:p-8 border-2 border-slate-200 shadow-sm space-y-6">
                    <!-- Title & Header Bar -->
                    <div class="border-b-2 border-slate-100 pb-5">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full bg-[#043399] text-white text-xs font-bold">
                                    Pertemuan ke-<?= $currentModule['week_number'] ?>
                                </span>
                                <span class="text-xs text-black font-semibold">• Estimasi 1 Sesi Pertemuan</span>
                            </div>
                            <?php if ($isGuru): ?>
                                <button type="button" onclick="openEditModuleModal('<?= htmlspecialchars($currentModule['id']) ?>')" class="px-3 py-1 bg-[#043399] hover:bg-[#021f5c] text-white text-xs font-bold rounded-lg shadow-xs transition">
                                    Edit Materi Ini
                                </button>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-black text-black leading-tight">
                            <?= htmlspecialchars($currentModule['title']) ?>
                        </h2>
                        <p class="text-xs sm:text-sm text-black font-medium mt-2 leading-relaxed">
                            <?= htmlspecialchars($currentModule['summary']) ?>
                        </p>
                    </div>

                    <!-- Objectives & Deliverables -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-blue-50 p-4 rounded-xl border-2 border-blue-200 space-y-2">
                            <h4 class="text-xs font-black text-black uppercase tracking-wider flex items-center gap-1.5">
                                <span>Tujuan Pembelajaran:</span>
                            </h4>
                            <ul class="space-y-1.5 text-xs text-black font-medium">
                                <?php if (!empty($objectives)): ?>
                                    <?php foreach ($objectives as $obj): ?>
                                        <li class="flex items-start gap-1.5">
                                            <span class="text-[#043399] font-black">•</span>
                                            <span><?= htmlspecialchars($obj) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="text-slate-500 italic text-xs">Belum ada tujuan pembelajaran dicatat.</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="bg-emerald-50 p-4 rounded-xl border-2 border-emerald-200 space-y-2">
                            <h4 class="text-xs font-black text-black uppercase tracking-wider flex items-center gap-1.5">
                                <span>Output / Tugas Wajib Dikumpulkan:</span>
                            </h4>
                            <ul class="space-y-1.5 text-xs text-black font-medium">
                                <?php if (!empty($deliverables)): ?>
                                    <?php foreach ($deliverables as $del): ?>
                                        <li class="flex items-start gap-1.5">
                                            <span class="text-emerald-700 font-bold">•</span>
                                            <span class="font-bold"><?= htmlspecialchars($del) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="text-slate-500 italic text-xs">Belum ada output wajib dicatat.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Lesson Body Content -->
                    <div class="pt-2 border-t border-slate-100 text-xs sm:text-sm leading-relaxed space-y-4">
                        <div class="whitespace-pre-line text-black font-medium">
                            <?= htmlspecialchars($currentModule['content']) ?>
                        </div>
                    </div>

                    <!-- External References -->
                    <?php if (!empty($externalLinks)): ?>
                        <div class="pt-4 border-t border-slate-100">
                            <h4 class="text-xs font-black text-black mb-2">Bahan Bacaan & Referensi Industri:</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($externalLinks as $link): ?>
                                    <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-black rounded-lg text-xs font-bold transition flex items-center gap-1">
                                        <span><?= htmlspecialchars($link['title']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action shortcuts -->
                    <div class="bg-gradient-to-r from-blue-50 via-amber-50 to-blue-50 p-4 rounded-xl border-2 border-blue-200 flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs font-black text-black">
                            Siap mempraktikkan materi pertemuan ini? Buka modul kerja tim:
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="prd.php" class="px-3 py-1.5 rounded-lg bg-purple-700 text-white text-xs font-bold shadow-xs">Modul PRD</a>
                            <a href="board.php" class="px-3 py-1.5 rounded-lg bg-[#043399] text-white text-xs font-bold shadow-xs">Scrum Board</a>
                            <a href="logbook.php" class="px-3 py-1.5 rounded-lg bg-emerald-700 text-white text-xs font-bold shadow-xs">Isi Log Book</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-2xl p-12 border-2 border-slate-200 text-center space-y-4">
                    <h3 class="text-base font-black text-black">Belum Ada Materi Pembelajaran</h3>
                    <p class="text-xs text-slate-600">Silakan tambahkan modul pertemuan pertama untuk memulai pembelajaran tim.</p>
                    <?php if ($isGuru): ?>
                        <button type="button" onclick="openEditModuleModal(null)" class="px-4 py-2 bg-[#043399] text-white text-xs font-bold rounded-xl">
                            + Tambah Modul Pertama
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Guru Edit / Create LMS Module -->
<div id="editLmsModal" class="fixed inset-0 z-50 bg-black/60 hidden overflow-y-auto" style="display: none;">
    <div class="min-h-full flex items-center justify-center p-3 sm:p-6">
        <div class="bg-white w-full max-w-3xl rounded-2xl border-2 border-slate-300 shadow-2xl overflow-hidden my-8" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-[#043399] px-6 py-4 flex items-center justify-between text-white border-b-2 border-blue-900">
                <div>
                    <span class="text-[10px] uppercase font-black tracking-wider text-amber-300 block">Panel Guru & Instruktur</span>
                    <h3 id="lmsModalTitle" class="text-base font-black text-white">Edit Materi Pembelajaran</h3>
                </div>
                <button type="button" onclick="closeEditModuleModal()" class="text-white hover:text-amber-300 text-xl font-black px-2 py-1 leading-none" title="Tutup Modal">
                    &times;
                </button>
            </div>

            <!-- Modal Form -->
            <form id="lmsForm" onsubmit="handleSaveLms(event)" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto text-black">
                <input type="hidden" id="lmsId" name="id" value="">

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-black text-black mb-1">Pertemuan Ke- *</label>
                        <input type="number" id="lmsWeek" name="week_number" min="1" max="52" required class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-black text-black mb-1">Judul Materi Pembelajaran *</label>
                        <input type="text" id="lmsTitle" name="title" required placeholder="Contoh: Pertemuan 1: Fondasi Agile, Scrum, & Menyusun PRD Berkualitas" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black text-black mb-1">Ringkasan / Summary Singkat *</label>
                    <textarea id="lmsSummary" name="summary" rows="2" required placeholder="Jelaskan ringkasan materi dalam 1-2 kalimat padat..." class="w-full p-2.5 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399]"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-black text-black">Tujuan Pembelajaran</label>
                            <span class="text-[10px] text-slate-500 font-semibold">1 poin per baris</span>
                        </div>
                        <textarea id="lmsObjectives" name="objectives" rows="4" placeholder="Memahami konsep Agile vs Waterfall&#10;Mampu membagi peran tim siswa&#10;Menyusun Acceptance Criteria" class="w-full p-2.5 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399]"></textarea>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-black text-black">Output / Tugas Wajib Dikumpulkan</label>
                            <span class="text-[10px] text-slate-500 font-semibold">1 poin per baris</span>
                        </div>
                        <textarea id="lmsDeliverables" name="deliverables" rows="4" placeholder="Dokumen PRD versi 1.0 yang disetujui&#10;Daftar User Persona awal&#10;Logbook Harian Pertemuan 1" class="w-full p-2.5 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399]"></textarea>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-black text-black">Bahan Bacaan & Referensi Industri</label>
                        <span class="text-[10px] text-slate-500 font-semibold">Format: Judul Link | URL (1 per baris)</span>
                    </div>
                    <textarea id="lmsExternalLinks" name="external_links" rows="3" placeholder="Panduan Agile Manifesto | https://agilemanifesto.org/&#10;Contoh Template PRD Industri | https://www.atlassian.com/agile/product-management/requirements" class="w-full p-2.5 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399]"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-black text-black mb-1">Isi Lengkap Modul Pembelajaran *</label>
                    <textarea id="lmsContent" name="content" rows="9" required placeholder="Tuliskan materi pembelajaran terperinci, panduan langkah demi langkah, dan penjelasan kurikulum..." class="w-full p-3 border-2 border-slate-300 rounded-lg text-xs font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399] font-sans leading-relaxed"></textarea>
                </div>

                <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                    <label class="flex items-center gap-2 text-xs font-black text-black cursor-pointer select-none">
                        <input type="checkbox" id="lmsPublished" name="is_published" value="1" checked class="w-4 h-4 text-[#043399] rounded border-slate-300">
                        <span>Publikasikan modul ini ke siswa</span>
                    </label>

                    <button type="button" id="btnDeleteLms" onclick="handleDeleteLms()" class="text-rose-600 hover:text-rose-800 text-xs font-black transition underline hidden">
                        Hapus Modul Ini
                    </button>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 border-t-2 border-slate-200 flex items-center justify-end gap-3">
                    <button type="button" onclick="closeEditModuleModal()" class="px-4 py-2 border-2 border-slate-300 hover:bg-slate-100 text-black font-black text-xs rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs rounded-xl shadow-sm transition">
                        Simpan Materi Pembelajaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.lmsModules = <?= json_encode($modules) ?>;
window.currentSelectedWeek = <?= (int)$selectedWeek ?>;

function openEditModuleModal(moduleId) {
    const modal = document.getElementById('editLmsModal');
    const titleEl = document.getElementById('lmsModalTitle');
    const deleteBtn = document.getElementById('btnDeleteLms');

    let mod = null;
    if (moduleId) {
        mod = window.lmsModules.find(m => String(m.id) === String(moduleId));
    }

    if (mod) {
        titleEl.textContent = 'Edit Materi Pembelajaran - Pertemuan ' + mod.week_number;
        document.getElementById('lmsId').value = mod.id || '';
        document.getElementById('lmsWeek').value = mod.week_number || '1';
        document.getElementById('lmsTitle').value = mod.title || '';
        document.getElementById('lmsSummary').value = mod.summary || '';
        document.getElementById('lmsContent').value = mod.content || '';
        document.getElementById('lmsPublished').checked = (mod.is_published != 0);

        // Objectives
        let objs = [];
        try { objs = JSON.parse(mod.objectives || '[]'); } catch(e) { objs = []; }
        document.getElementById('lmsObjectives').value = Array.isArray(objs) ? objs.join("\n") : '';

        // Deliverables
        let dels = [];
        try { dels = JSON.parse(mod.deliverables || '[]'); } catch(e) { dels = []; }
        document.getElementById('lmsDeliverables').value = Array.isArray(dels) ? dels.join("\n") : '';

        // External links
        let links = [];
        try { links = JSON.parse(mod.external_links || '[]'); } catch(e) { links = []; }
        let linksText = '';
        if (Array.isArray(links)) {
            linksText = links.map(l => (l.title && l.url) ? `${l.title} | ${l.url}` : (l.url || l.title || '')).join("\n");
        }
        document.getElementById('lmsExternalLinks').value = linksText;

        deleteBtn.classList.remove('hidden');
    } else {
        // New module
        const nextWeek = window.lmsModules.length > 0 ? (Math.max(...window.lmsModules.map(m => parseInt(m.week_number) || 0)) + 1) : 1;
        titleEl.textContent = 'Tambah Materi Pembelajaran Baru (Pertemuan ' + nextWeek + ')';
        document.getElementById('lmsId').value = '';
        document.getElementById('lmsWeek').value = nextWeek;
        document.getElementById('lmsTitle').value = 'Pertemuan ' + nextWeek + ': ';
        document.getElementById('lmsSummary').value = '';
        document.getElementById('lmsObjectives').value = '';
        document.getElementById('lmsDeliverables').value = '';
        document.getElementById('lmsExternalLinks').value = '';
        document.getElementById('lmsContent').value = '';
        document.getElementById('lmsPublished').checked = true;

        deleteBtn.classList.add('hidden');
    }

    modal.style.display = 'block';
    modal.classList.remove('hidden');
}

function closeEditModuleModal() {
    const modal = document.getElementById('editLmsModal');
    modal.style.display = 'none';
    modal.classList.add('hidden');
}

async function handleSaveLms(e) {
    e.preventDefault();
    const form = e.target;
    const data = {
        id: form.id.value,
        week_number: form.week_number.value,
        title: form.title.value,
        summary: form.summary.value,
        objectives: form.objectives.value,
        deliverables: form.deliverables.value,
        external_links: form.external_links.value,
        content: form.content.value,
        is_published: form.is_published.checked ? 1 : 0
    };

    try {
        const res = await fetch('api.php?action=save_lms_module', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            closeEditModuleModal();
            await showAppAlert(result.message || 'Materi pembelajaran berhasil disimpan!');
            window.location.href = 'lms.php?week=' + (result.week_number || data.week_number);
        } else {
            await showAppAlert(result.error || 'Gagal menyimpan materi');
        }
    } catch (err) {
        await showAppAlert('Terjadi kesalahan: ' + err.message);
    }
}

async function handleDeleteLms() {
    const id = document.getElementById('lmsId').value;
    if (!id) return;

    const confirmed = await showAppConfirm(
        'Apakah Anda yakin ingin menghapus modul materi ini? Seluruh data materi pertemuan ini akan dihapus secara permanen.',
        'Ya, Hapus Modul'
    );

    if (!confirmed) return;

    try {
        const res = await fetch('api.php?action=delete_lms_module&id=' + encodeURIComponent(id), {
            method: 'POST'
        });
        const result = await res.json();
        if (result.success) {
            closeEditModuleModal();
            await showAppAlert('Modul materi berhasil dihapus.');
            window.location.href = 'lms.php';
        } else {
            await showAppAlert(result.error || 'Gagal menghapus modul');
        }
    } catch (err) {
        await showAppAlert('Terjadi kesalahan: ' + err.message);
    }
}

// Auto open modal if URL has ?edit=1 or ?add=1
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('add') === '1') {
        openEditModuleModal(null);
    } else if (urlParams.get('edit') === '1') {
        const currentModId = '<?= $currentModule ? htmlspecialchars($currentModule['id']) : '' ?>';
        if (currentModId) {
            openEditModuleModal(currentModId);
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
