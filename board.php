<?php
// board.php - Clean Scrum Kanban Board for VINIX7 ScrumVibe
require_once __DIR__ . '/includes/header.php';

// Filter role
$filterRole = $_GET['filter_role'] ?? 'all';
$search = trim($_GET['q'] ?? '');

// Fetch all tasks for active team
$sql = "SELECT * FROM tasks WHERE team_id = ?";
$params = [$currentTeamId];

if ($filterRole !== 'all') {
    if ($filterRole === 'my_tasks' && $currentStudent) {
        $sql .= " AND assignee_id = ?";
        $params[] = $currentStudent['id'];
    } elseif ($filterRole === 'frontend') {
        $sql .= " AND (role_category = 'frontend' OR role_category = 'fullstack')";
    } elseif ($filterRole === 'backend') {
        $sql .= " AND (role_category = 'backend' OR role_category = 'fullstack')";
    } elseif ($filterRole === 'pm') {
        $sql .= " AND (role_category = 'pm' OR role_category = 'fullstack')";
    }
}

if ($search !== '') {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY priority DESC, created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Fetch members for assignee dropdown
$stmt = $pdo->prepare("SELECT * FROM members WHERE team_id = ? OR role = 'super_admin' ORDER BY name ASC");
$stmt->execute([$currentTeamId]);
$teamMembers = $stmt->fetchAll();

// Column definitions
$columns = [
    'backlog' => ['title' => 'Product Backlog', 'desc' => 'Ide & kebutuhan belum terjadwal', 'badge' => 'bg-slate-200 text-black'],
    'sprint_backlog' => ['title' => 'Sprint Backlog', 'desc' => 'Komitmen Sprint 1', 'badge' => 'bg-blue-100 text-blue-900'],
    'in_progress' => ['title' => 'In Progress', 'desc' => 'Aktif dikerjakan engineer', 'badge' => 'bg-amber-100 text-amber-950'],
    'in_review' => ['title' => 'In Review / QA', 'desc' => 'Verifikasi & Code Review', 'badge' => 'bg-purple-100 text-purple-950'],
    'done' => ['title' => 'Done', 'desc' => 'Selesai lolos DoD', 'badge' => 'bg-emerald-100 text-emerald-950']
];

// Velocity calculations
$totalPoints = 0;
$donePoints = 0;
foreach ($tasks as $t) {
    $pts = (int)($t['story_points'] ?? 1);
    $totalPoints += $pts;
    if ($t['status'] === 'done') $donePoints += $pts;
}

// Fetch active PRD for this team
$stmtPrd = $pdo->prepare("SELECT * FROM prds WHERE team_id = ? ORDER BY updated_at DESC LIMIT 1");
$stmtPrd->execute([$currentTeamId]);
$activePrd = $stmtPrd->fetch();
?>

<div class="space-y-6">
    <?php if ($activePrd): ?>
    <!-- PRD Integration Banner -->
    <div class="bg-amber-50 border-2 border-[#f59e0b] rounded-2xl p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xs">
        <div class="flex items-start sm:items-center gap-3">
            
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[11px] font-black uppercase tracking-wider text-[#043399]">Dokumen PRD Terkait</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#f59e0b] text-black">Versi <?= htmlspecialchars($activePrd['version']) ?></span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-black border border-slate-300 uppercase"><?= htmlspecialchars($activePrd['status']) ?></span>
                </div>
                <div class="text-sm font-black text-black mt-0.5">
                    <?= htmlspecialchars($activePrd['title']) ?>
                </div>
                <p class="text-xs text-black/80 font-medium line-clamp-1 mt-0.5">
                    <?= htmlspecialchars(substr($activePrd['problem_statement'], 0, 140)) ?>...
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="prd.php" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-xs transition active:scale-98">
                <span>Buka Dokumen PRD Lengkap &rarr;</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Board Header & Metrics -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border-2 border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-black text-black">Scrum Kanban Board</h1>
                <span class="px-2.5 py-0.5 rounded-full bg-[#043399] text-white text-xs font-bold">
                    Sprint 1
                </span>
            </div>
            <p class="text-xs text-black font-medium mt-1">
                Tarik dan lepas kartu antar kolom atau gunakan tombol geser untuk memindahkan status tugas.
            </p>
        </div>

        <!-- Sprint Velocity & Add Task -->
        <div class="flex items-center gap-3">
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl px-4 py-2 text-right">
                <div class="text-[11px] text-black font-semibold">Sprint Velocity</div>
                <div class="text-sm font-black text-black">
                    <span class="text-emerald-700 font-extrabold"><?= $donePoints ?></span> / <?= $totalPoints ?> Story Points
                </div>
            </div>

            <button type="button" onclick="openBackupModal()" class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-black border border-slate-300 font-bold text-xs shadow-2xs transition" title="Cadangkan atau Pulihkan Tiket Papan Kanban">
                <span>Backup Data</span>
            </button>

            <button type="button" onclick="openCreateTaskModal()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-bold text-xs shadow-sm transition active:scale-98">
                <span>+ Tambah Tiket</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-xl border-2 border-slate-200 text-xs">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-bold text-black mr-1 flex items-center gap-1">
                Filter Tugas:
            </span>
            <a href="board.php?filter_role=all" class="px-3 py-1.5 rounded-lg font-bold transition <?= $filterRole === 'all' ? 'bg-[#043399] text-white' : 'bg-slate-100 text-black hover:bg-slate-200' ?>">
                Semua (<?= count($tasks) ?>)
            </a>
            <?php if ($currentRole === 'siswa' && $currentStudent): ?>
                <a href="board.php?filter_role=my_tasks" class="px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1 <?= $filterRole === 'my_tasks' ? 'bg-[#043399] text-white shadow-xs' : 'bg-blue-50 text-[#043399] border border-blue-300 hover:bg-blue-100' ?>">
                    <span>Tugas Saya (<?= htmlspecialchars(explode(' ', $currentStudent['name'])[0]) ?>)</span>
                </a>
            <?php endif; ?>
            <a href="board.php?filter_role=frontend" class="px-3 py-1.5 rounded-lg font-bold transition <?= $filterRole === 'frontend' ? 'bg-[#f59e0b] text-black shadow-sm' : 'bg-amber-100 text-black hover:bg-amber-200' ?>">
                Frontend
            </a>
            <a href="board.php?filter_role=backend" class="px-3 py-1.5 rounded-lg font-bold transition <?= $filterRole === 'backend' ? 'bg-emerald-700 text-white' : 'bg-emerald-100 text-black hover:bg-emerald-200' ?>">
                Backend
            </a>
            <a href="board.php?filter_role=pm" class="px-3 py-1.5 rounded-lg font-bold transition <?= $filterRole === 'pm' ? 'bg-blue-700 text-white' : 'bg-blue-100 text-black hover:bg-blue-200' ?>">
                Analisis & PRD
            </a>
        </div>

        <!-- Search input -->
        <form method="GET" action="board.php" class="flex items-center gap-1.5">
            <input type="hidden" name="filter_role" value="<?= htmlspecialchars($filterRole) ?>">
            <div class="relative">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari tugas..." class="px-3 py-1.5 bg-slate-100 border border-slate-300 rounded-lg text-xs font-semibold text-black focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#043399]">
            </div>
            <button type="submit" class="px-3 py-1.5 bg-[#043399] text-white rounded-lg text-xs font-bold">Cari</button>
            <?php if ($search): ?>
                <a href="board.php?filter_role=<?= htmlspecialchars($filterRole) ?>" class="px-2 py-1.5 text-xs text-rose-600 font-bold">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 5 Columns Kanban Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 items-start">
        <?php 
        $colKeys = array_keys($columns);
        foreach ($columns as $statusKey => $col): 
            $colTasks = array_filter($tasks, fn($t) => $t['status'] === $statusKey);
            $colPoints = array_sum(array_map(fn($t) => (int)($t['story_points'] ?? 1), $colTasks));
            $colIndex = array_search($statusKey, $colKeys);
        ?>
            <div class="kanban-col bg-slate-100/90 rounded-2xl p-3 border-2 border-slate-300 flex flex-col" data-status="<?= $statusKey ?>">
                <!-- Column Header -->
                <div class="flex items-center justify-between pb-3 mb-2 border-b-2 border-slate-200">
                    <div class="flex items-center gap-1.5">
                        <h3 class="font-extrabold text-xs text-black tracking-tight"><?= $col['title'] ?></h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black <?= $col['badge'] ?>">
                            <?= count($colTasks) ?>
                        </span>
                    </div>
                    <span class="text-[11px] font-mono font-bold text-black bg-white px-1.5 py-0.5 rounded border border-slate-300">
                        <?= $colPoints ?> pts
                    </span>
                </div>

                <!-- Cards List -->
                <div class="space-y-3 flex-1">
                    <?php if (empty($colTasks)): ?>
                        <div class="h-28 border-2 border-dashed border-slate-300 rounded-xl flex items-center justify-center text-black font-semibold text-[11px] text-center p-2">
                            Tarik kartu ke kolom ini
                        </div>
                    <?php else: ?>
                        <?php foreach ($colTasks as $task): ?>
                            <div class="kanban-card bg-white rounded-xl p-3.5 border-2 border-slate-200 shadow-xs hover:border-[#043399] hover:shadow-md transition space-y-2.5 cursor-pointer" draggable="true" data-id="<?= htmlspecialchars($task['id']) ?>" onclick="handleCardClick('<?= $task['id'] ?>')">
                                <!-- Role & Priority Badge -->
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full border <?= $task['role_category'] === 'frontend' ? 'bg-amber-100 text-amber-900 border-amber-300' : ($task['role_category'] === 'backend' ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : ($task['role_category'] === 'pm' ? 'bg-blue-100 text-blue-900 border-blue-300' : 'bg-slate-100 text-black border-slate-300')) ?>">
                                        <?= strtoupper($task['role_category']) ?>
                                    </span>

                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] uppercase font-black px-1.5 py-0.5 rounded <?= $task['priority'] === 'urgent' ? 'bg-rose-100 text-rose-900 font-extrabold' : ($task['priority'] === 'high' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-black') ?>">
                                            <?= $task['priority'] ?>
                                        </span>
                                        <button type="button" onclick="event.stopPropagation(); openEditTaskModal('<?= $task['id'] ?>')" title="Edit Tiket" class="p-0.5 text-slate-400 hover:text-[#043399] rounded transition">
                                            </button>
                                    </div>
                                </div>

                                <!-- Card Title -->
                                <h4 class="text-xs font-black text-black leading-snug">
                                    <?= htmlspecialchars($task['title']) ?>
                                </h4>

                                <!-- Dependency Alert -->
                                <?php if (!empty($task['dependency_task_id'])): ?>
                                    <div class="bg-amber-50 border border-amber-300 rounded-lg p-1.5 text-[10px] text-black font-bold flex items-center gap-1.5">
                                        <span class="truncate">Tergantung BE: <?= htmlspecialchars($task['dependency_task_title'] ?: 'API BE') ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Card Footer: Assignee, Points & Move Controls -->
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <div class="flex items-center gap-1.5 text-black font-semibold">
                                        <?php if (!empty($task['assignee_name'])): ?>
                                            <span class="truncate max-w-[75px] <?= ($currentRole === 'siswa' && $currentStudent && $task['assignee_id'] === $currentStudent['id']) ? 'text-[#043399] font-black' : '' ?>">
                                                <?= htmlspecialchars(explode(' ', $task['assignee_name'])[0]) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-[10px]">Bebas</span>
                                            <?php if ($currentRole === 'siswa' && $currentStudent): ?>
                                                <button type="button" onclick="event.stopPropagation(); claimTask('<?= $task['id'] ?>', '<?= $currentStudent['id'] ?>', '<?= htmlspecialchars(addslashes($currentStudent['name'])) ?>')" title="Ambil tugas ini untuk Anda" class="px-1.5 py-0.5 bg-amber-100 hover:bg-amber-200 text-black border border-amber-300 rounded text-[10px] font-black transition">
                                                    Ambil
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <span class="font-mono font-black bg-blue-50 text-[#043399] border border-blue-200 px-1.5 py-0.5 rounded text-[10px]">
                                            <?= (int)$task['story_points'] ?> pts
                                        </span>

                                        <!-- Quick shift buttons -->
                                        <div class="flex items-center gap-0.5">
                                            <?php if ($colIndex > 0): ?>
                                                <button type="button" onclick="event.stopPropagation(); moveTaskQuick('<?= $task['id'] ?>', '<?= $colKeys[$colIndex - 1] ?>')" title="Pindah ke kiri (<?= $columns[$colKeys[$colIndex - 1]]['title'] ?>)" class="p-0.5 hover:bg-slate-200 rounded text-black font-bold">
                                                    &larr;
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($colIndex < count($colKeys) - 1): ?>
                                                <button type="button" onclick="event.stopPropagation(); moveTaskQuick('<?= $task['id'] ?>', '<?= $colKeys[$colIndex + 1] ?>')" title="Pindah ke kanan (<?= $columns[$colKeys[$colIndex + 1]]['title'] ?>)" class="p-0.5 hover:bg-slate-200 rounded text-black font-bold">
                                                    &rarr;
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Add Card Button for this Column -->
                <button type="button" onclick="openCreateTaskModal('<?= $statusKey ?>')" class="mt-2.5 w-full py-2 px-3 border-2 border-dashed border-slate-300 hover:border-[#043399] hover:bg-white text-black hover:text-[#043399] rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 active:scale-98">
                    <span>+ Tambah Kartu</span>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 1. MODAL TAMBAH TIKET (DISEMPURNAKAN: LEBIH BESAR & DITENGAH) -->
<div id="addTaskModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl lg:max-w-3xl w-full mx-auto overflow-hidden border-2 border-[#043399] my-auto">
        <div class="bg-[#043399] text-white px-6 sm:px-8 py-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h3 class="font-black text-lg text-white">Buat Tiket Tugas Baru</h3>
                    <p class="text-xs text-amber-300 font-semibold">Tambahkan tiket tugas ke Scrum Kanban Board kelompok</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateTaskModal()" class="text-white hover:text-amber-300 font-bold text-3xl leading-none p-1">&times;</button>
        </div>

        <form id="createTaskForm" onsubmit="handleCreateTask(event)" class="p-6 sm:p-8 space-y-5">
            <input type="hidden" name="team_id" value="<?= htmlspecialchars($currentTeamId) ?>">

            <div>
                <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Judul Tugas / User Story *</label>
                <input type="text" name="title" id="createTaskTitle" required placeholder="Contoh: Slicing UI Form Login atau Endpoint POST /auth/login" class="w-full px-4 py-2.5 border-2 border-slate-300 rounded-xl text-sm font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Peran Penanggung Jawab</label>
                    <select name="role_category" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none bg-slate-50">
                        <option value="frontend">Frontend (FE)</option>
                        <option value="backend">Backend (BE)</option>
                        <option value="pm">Product Manager (PM)</option>
                        <option value="fullstack">Fullstack / Shared</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Story Points (Fibonacci)</label>
                    <select name="story_points" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold font-mono text-black focus:outline-none bg-slate-50">
                        <option value="1">1 Poin (Sangat Mudah)</option>
                        <option value="2">2 Poin (Mudah)</option>
                        <option value="3" selected>3 Poin (Standar)</option>
                        <option value="5">5 Poin (Kompleks)</option>
                        <option value="8">8 Poin (Sangat Kompleks)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Prioritas</label>
                    <select name="priority" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none bg-slate-50">
                        <option value="low">Rendah (Low)</option>
                        <option value="medium" selected>Sedang (Medium)</option>
                        <option value="high">Tinggi (High)</option>
                        <option value="urgent">Mendesak (Urgent)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Kolom Status Awal</label>
                    <select name="status" id="createTaskStatus" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-black text-black focus:outline-none bg-slate-50">
                        <option value="backlog">Product Backlog</option>
                        <option value="sprint_backlog" selected>Sprint Backlog</option>
                        <option value="in_progress">In Progress</option>
                        <option value="in_review">In Review / QA</option>
                        <option value="done">Done</option>
                    </select>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-black text-black uppercase tracking-wider">Penerima Tugas (Assignee)</label>
                        <?php if ($currentRole === 'siswa' && $currentStudent): ?>
                            <button type="button" onclick="setCreateAssignee('<?= $currentStudent['id'] ?>')" class="text-[11px] font-black text-[#043399] hover:underline">
                                Tugaskan ke Saya
                            </button>
                        <?php endif; ?>
                    </div>
                    <select name="assignee_id" id="assigneeSelect" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none bg-slate-50">
                        <option value="">-- Bebas Dipilih Siswa --</option>
                        <?php foreach ($teamMembers as $m): 
                            $roleLabel = ($m['role'] === 'guru' || $m['role'] === 'super_admin') ? 'Guru' : 'Siswa';
                        ?>
                            <option value="<?= htmlspecialchars($m['id']) ?>" data-name="<?= htmlspecialchars($m['name']) ?>" <?= ($currentRole === 'siswa' && $currentStudent && $m['id'] === $currentStudent['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['name']) ?> (<?= $roleLabel ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Ketergantungan Tugas Lain (Opsional)</label>
                <select name="dependency_task_id" id="dependencySelect" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none truncate bg-slate-50">
                    <option value="">-- Tidak ada (Dapat langsung dikerjakan) --</option>
                    <?php foreach ($tasks as $t): ?>
                        <option value="<?= htmlspecialchars($t['id']) ?>" data-title="<?= htmlspecialchars($t['title']) ?>">
                            [<?= strtoupper($t['role_category']) ?>] <?= htmlspecialchars($t['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Deskripsi & Kriteria Penerimaan (Acceptance Criteria / DoD)</label>
                <textarea name="description" rows="4" placeholder="Jelaskan spesifikasi kebutuhan dan checklist definisi selesai (DoD) agar tiket ini dianggap tuntas..." class="w-full p-3 border-2 border-slate-300 rounded-xl text-xs font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399] leading-relaxed"></textarea>
            </div>

            <div class="pt-4 border-t-2 border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="closeCreateTaskModal()" class="px-5 py-2.5 rounded-xl text-black font-bold text-xs hover:bg-slate-100 border border-slate-300 transition">Batal</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-sm transition active:scale-98">Simpan Tiket</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL DETAIL TIKET (DISEMPURNAKAN: LEBIH BESAR & DITENGAH) -->
<div id="taskDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl lg:max-w-3xl w-full mx-auto overflow-hidden border-2 border-[#043399] my-auto">
        <!-- Header -->
        <div class="bg-[#043399] text-white px-6 sm:px-8 py-5 flex items-center justify-between">
            <div class="flex items-center gap-2 flex-wrap">
                <span id="detailTaskRole" class="px-3 py-1 rounded-full text-[11px] font-black uppercase bg-[#f59e0b] text-black">
                    ROLE
                </span>
                <span id="detailTaskPriority" class="px-2.5 py-1 rounded text-[11px] font-black uppercase bg-white text-black">
                    PRIORITY
                </span>
                <span id="detailTaskPoints" class="px-2.5 py-1 rounded text-[11px] font-black font-mono bg-blue-900 text-amber-300 border border-amber-300/40">
                    0 PTS
                </span>
                <span id="detailTaskId" class="text-xs font-mono text-slate-300">
                    task-id
                </span>
            </div>
            <button type="button" onclick="closeTaskDetail()" class="text-white hover:text-amber-300 font-bold text-3xl leading-none p-1">&times;</button>
        </div>

        <div class="p-6 sm:p-8 space-y-6">
            <!-- Title -->
            <div>
                <span class="text-xs uppercase font-black tracking-wider text-[#043399]">Judul Tiket Tugas:</span>
                <h2 id="detailTaskTitle" class="text-xl sm:text-2xl font-black text-black leading-snug mt-1"></h2>
            </div>

            <!-- Quick Geser Status Kolom -->
            <div class="bg-slate-50 p-4 rounded-xl border-2 border-slate-200 space-y-2.5">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <span class="text-xs font-black text-black flex items-center gap-1.5">
                        <span>Geser Kolom Status (Klik untuk Memindahkan):</span>
                    </span>
                    <span id="detailCurrentColumnName" class="text-xs font-black text-[#043399] bg-blue-100 px-2.5 py-0.5 rounded-full"></span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center" id="detailStatusButtons">
                    <!-- Filled dynamically -->
                </div>
            </div>

            <!-- Two-Column Meta Grid: Assignee & Dependencies -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Assignee Box -->
                <div class="p-4 rounded-xl border-2 border-slate-200 bg-white space-y-2.5">
                    <span class="text-xs font-black text-black block uppercase tracking-wider">Penerima Tugas (PIC)</span>
                    <div id="detailAssigneeDisplay" class="flex items-center gap-3">
                        <!-- Filled dynamically -->
                    </div>
                    <div id="detailAssigneeActions" class="pt-2 border-t border-slate-100 flex items-center gap-2">
                        <!-- Dynamic claim / release buttons -->
                    </div>
                </div>

                <!-- Dependency Box -->
                <div class="p-4 rounded-xl border-2 border-slate-200 bg-white space-y-2.5">
                    <span class="text-xs font-black text-black block uppercase tracking-wider">Ketergantungan (Dependency)</span>
                    <div id="detailDependencyDisplay" class="text-xs font-semibold text-black">
                        Tidak ada ketergantungan.
                    </div>
                </div>
            </div>

            <!-- Description & Acceptance Criteria -->
            <div class="space-y-2">
                <span class="text-xs font-black text-black block uppercase tracking-wider">Deskripsi & Kriteria Penerimaan (DoD)</span>
                <div id="detailTaskDescription" class="p-4 bg-slate-50 rounded-xl border-2 border-slate-200 text-xs font-medium text-black leading-relaxed whitespace-pre-wrap max-h-56 overflow-y-auto"></div>
            </div>

            <!-- Footer Meta Timestamps -->
            <div class="pt-3 border-t border-slate-200 flex flex-wrap items-center justify-between text-xs text-slate-500 font-semibold gap-2">
                <span id="detailCreatedAt">Dibuat: -</span>
                <span id="detailUpdatedAt">Diperbarui: -</span>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t-2 border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <button type="button" onclick="handleDeleteFromDetail()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-rose-700 hover:bg-rose-50 border-2 border-rose-200 font-bold text-xs transition active:scale-98">
                    <span>Hapus Tiket</span>
                </button>

                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeTaskDetail()" class="px-5 py-2.5 rounded-xl text-black font-bold text-xs hover:bg-slate-100 border border-slate-300 transition">
                        Tutup
                    </button>
                    <button type="button" onclick="switchToEditModal()" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-sm transition active:scale-98">
                        <span>Edit Tiket Ini</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. MODAL EDIT TIKET (DISEMPURNAKAN: LEBIH BESAR & DITENGAH) -->
<div id="editTaskModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl lg:max-w-3xl w-full mx-auto overflow-hidden border-2 border-[#043399] my-auto">
        <div class="bg-[#043399] text-white px-6 sm:px-8 py-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h3 class="font-black text-lg text-white">Edit Tiket Tugas Kanban</h3>
                    <p class="text-xs text-amber-300 font-semibold">Perbarui informasi tugas, estimasi poin, status, atau PIC</p>
                </div>
            </div>
            <button type="button" onclick="closeEditModal()" class="text-white hover:text-amber-300 font-bold text-3xl leading-none p-1">&times;</button>
        </div>

        <form id="editTaskForm" onsubmit="handleUpdateTask(event)" class="p-6 sm:p-8 space-y-5">
            <input type="hidden" name="id" id="editTaskId">
            <input type="hidden" name="team_id" value="<?= htmlspecialchars($currentTeamId) ?>">

            <div>
                <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Judul Tugas / User Story *</label>
                <input type="text" name="title" id="editTaskTitle" required class="w-full px-4 py-2.5 border-2 border-slate-300 rounded-xl text-sm font-bold text-black focus:outline-none focus:ring-2 focus:ring-[#043399]">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Peran Penanggung Jawab</label>
                    <select name="role_category" id="editTaskRole" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none bg-slate-50">
                        <option value="frontend">Frontend (FE)</option>
                        <option value="backend">Backend (BE)</option>
                        <option value="pm">Product Manager (PM)</option>
                        <option value="fullstack">Fullstack / Shared</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Story Points (Fibonacci)</label>
                    <select name="story_points" id="editTaskPoints" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-mono font-black text-black focus:outline-none bg-slate-50">
                        <option value="1">1 Poin (Sangat Mudah)</option>
                        <option value="2">2 Poin (Mudah)</option>
                        <option value="3">3 Poin (Standar)</option>
                        <option value="5">5 Poin (Kompleks)</option>
                        <option value="8">8 Poin (Sangat Kompleks)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Prioritas</label>
                    <select name="priority" id="editTaskPriority" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none bg-slate-50">
                        <option value="low">Rendah (Low)</option>
                        <option value="medium">Sedang (Medium)</option>
                        <option value="high">Tinggi (High)</option>
                        <option value="urgent">Mendesak (Urgent)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Kolom Status</label>
                    <select name="status" id="editTaskStatus" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-black text-black focus:outline-none bg-slate-50">
                        <option value="backlog">Product Backlog</option>
                        <option value="sprint_backlog">Sprint Backlog</option>
                        <option value="in_progress">In Progress</option>
                        <option value="in_review">In Review / QA</option>
                        <option value="done">Done</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Penerima Tugas (Assignee)</label>
                    <select name="assignee_id" id="editTaskAssignee" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none bg-slate-50">
                        <option value="">-- Bebas Dipilih Siswa --</option>
                        <?php foreach ($teamMembers as $m): 
                            $roleLabel = ($m['role'] === 'guru' || $m['role'] === 'super_admin') ? 'Guru' : 'Siswa';
                        ?>
                            <option value="<?= htmlspecialchars($m['id']) ?>" data-name="<?= htmlspecialchars($m['name']) ?>">
                                <?= htmlspecialchars($m['name']) ?> (<?= $roleLabel ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Ketergantungan (Opsional)</label>
                <select name="dependency_task_id" id="editTaskDependency" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-black focus:outline-none truncate bg-slate-50">
                    <option value="">-- Tidak ada --</option>
                    <?php foreach ($tasks as $t): ?>
                        <option value="<?= htmlspecialchars($t['id']) ?>" data-title="<?= htmlspecialchars($t['title']) ?>">
                            [<?= strtoupper($t['role_category']) ?>] <?= htmlspecialchars($t['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-black text-black mb-1.5 uppercase tracking-wider">Deskripsi & Kriteria Penerimaan (Acceptance Criteria / DoD)</label>
                <textarea name="description" id="editTaskDescription" rows="4" placeholder="Detail teknis dan kriteria penerimaan..." class="w-full p-3 border-2 border-slate-300 rounded-xl text-xs font-medium text-black focus:outline-none focus:ring-2 focus:ring-[#043399] leading-relaxed"></textarea>
            </div>

            <div class="pt-4 border-t-2 border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()" class="px-5 py-2.5 rounded-xl text-black font-bold text-xs hover:bg-slate-100 border border-slate-300 transition">Batal</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-sm transition active:scale-98">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Backup & Pulihkan Data Kanban (Solusi Serverless Vercel) -->
<div id="backupModal" class="fixed inset-0 z-50 bg-black/60 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white w-full max-w-lg rounded-2xl border-2 border-slate-300 shadow-2xl overflow-hidden p-6 space-y-4" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <span class="text-[10px] font-black text-[#043399] uppercase tracking-wider block">Ketahanan Data Cloud & Lokal</span>
                <h3 class="text-sm font-black text-black">Cadangkan & Pulihkan Tiket Papan</h3>
            </div>
            <button type="button" onclick="closeBackupModal()" class="text-slate-500 hover:text-black text-xl font-black px-2 leading-none" title="Tutup Modal">&times;</button>
        </div>

        <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900 leading-relaxed font-medium">
            💡 <b>Perlindungan Otomatis Aktif:</b> Setiap perubahan tiket di Kanban Board otomatis disimpan ke memori browser (Local Cache). Jika container serverless Vercel melakukan restart, data Anda dapat dipulihkan secara otomatis.
        </div>

        <div class="space-y-3">
            <!-- 1. Download Backup JSON -->
            <div class="p-3.5 border-2 border-slate-200 rounded-xl flex items-center justify-between gap-3 hover:border-slate-300 transition">
                <div>
                    <h4 class="text-xs font-black text-black">Unduh File Cadangan (JSON)</h4>
                    <p class="text-[11px] text-slate-600 mt-0.5">Simpan semua kartu tiket kelompok saat ini ke file di komputer Anda.</p>
                </div>
                <button type="button" onclick="exportTasksJSON()" class="px-3.5 py-2 bg-[#043399] hover:bg-[#021f5c] text-white text-xs font-bold rounded-lg shrink-0 shadow-xs transition">
                    Unduh JSON
                </button>
            </div>

            <!-- 2. Restore from JSON -->
            <div class="p-3.5 border-2 border-slate-200 rounded-xl flex items-center justify-between gap-3 hover:border-slate-300 transition">
                <div>
                    <h4 class="text-xs font-black text-black">Pulihkan dari File JSON</h4>
                    <p class="text-[11px] text-slate-600 mt-0.5">Unggah file cadangan JSON untuk memulihkan seluruh tiket ke papan.</p>
                </div>
                <label class="px-3.5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-lg shrink-0 shadow-xs transition cursor-pointer">
                    Pilih File
                    <input type="file" accept=".json" onchange="importTasksJSON(event)" class="hidden">
                </label>
            </div>

            <!-- 3. Manual Sync from LocalStorage -->
            <div class="p-3.5 border-2 border-slate-200 rounded-xl flex items-center justify-between gap-3 hover:border-slate-300 transition">
                <div>
                    <h4 class="text-xs font-black text-black">Sinkronkan Cache Browser ke Server</h4>
                    <p class="text-[11px] text-slate-600 mt-0.5">Kirim ulang data tiket yang tersimpan di browser Anda ke database server.</p>
                </div>
                <button type="button" onclick="manualSyncCache()" class="px-3.5 py-2 bg-slate-800 hover:bg-black text-white text-xs font-bold rounded-lg shrink-0 shadow-xs transition">
                    Sinkronkan
                </button>
            </div>
        </div>

        <div class="pt-3 border-t border-slate-200 flex items-center justify-between">
            <span id="backupCacheCount" class="text-[11px] font-bold text-slate-500">Memeriksa cache...</span>
            <button type="button" onclick="closeBackupModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-black rounded-lg text-xs font-bold transition">Tutup</button>
        </div>
    </div>
</div>

<script>
// Global state passed from PHP
window.tasksData = <?= json_encode($tasks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.teamMembers = <?= json_encode($teamMembers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.columnsDef = <?= json_encode($columns, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.currentStudent = <?= json_encode($currentStudent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.currentRole = '<?= htmlspecialchars($currentRole) ?>';
window.currentTeamId = '<?= htmlspecialchars($currentTeamId) ?>';

// --- Client-Side Persistent Cache & Auto-Sync for Serverless Environment ---
const taskStorageKey = 'scrumvibe_tasks_' + (window.currentTeamId || 'team-1');

// 1. If server returned tasks, update localStorage cache
if (window.tasksData && window.tasksData.length > 0) {
    try {
        localStorage.setItem(taskStorageKey, JSON.stringify(window.tasksData));
    } catch(e) {}
} else {
    // 2. If server has 0 tasks, check if browser has cached tasks from previous session!
    try {
        const cachedRaw = localStorage.getItem(taskStorageKey);
        if (cachedRaw) {
            const cachedList = JSON.parse(cachedRaw);
            if (Array.isArray(cachedList) && cachedList.length > 0) {
                console.log('Restoring ' + cachedList.length + ' tasks to serverless instance...');
                fetch('api.php?action=sync_tasks', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ team_id: window.currentTeamId, tasks: cachedList })
                }).then(r => r.json()).then(res => {
                    if (res.success) {
                        window.location.reload();
                    }
                }).catch(e => console.error('Sync error:', e));
            }
        }
    } catch(e) {}
}

let currentDetailTaskId = null;

function getTaskById(taskId) {
    return window.tasksData.find(t => t.id === taskId);
}

// 1. Open Create Task Modal with optional column preselection
function openCreateTaskModal(columnStatus = 'sprint_backlog') {
    const statusSelect = document.getElementById('createTaskStatus');
    if (statusSelect && columnStatus) {
        statusSelect.value = columnStatus;
    }
    const modal = document.getElementById('addTaskModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const titleInput = document.getElementById('createTaskTitle');
    if (titleInput) titleInput.focus();
}

function closeCreateTaskModal() {
    const modal = document.getElementById('addTaskModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function setCreateAssignee(studentId) {
    const assSelect = document.getElementById('assigneeSelect');
    if (assSelect && studentId) {
        assSelect.value = studentId;
    }
}

// 2. Card click handler (checks if card was dragged)
function handleCardClick(taskId) {
    if (window.isDraggingCard) return;
    openTaskDetail(taskId);
}

// 3. Open Task Detail Pop-Up Modal
function openTaskDetail(taskId) {
    const task = getTaskById(taskId);
    if (!task) return;

    currentDetailTaskId = taskId;

    // Badges & ID
    const roleEl = document.getElementById('detailTaskRole');
    roleEl.innerText = (task.role_category || 'FULLSTACK').toUpperCase();
    
    const prioEl = document.getElementById('detailTaskPriority');
    prioEl.innerText = (task.priority || 'medium').toUpperCase();

    const ptsEl = document.getElementById('detailTaskPoints');
    ptsEl.innerText = (task.story_points || 1) + ' PTS';

    const idEl = document.getElementById('detailTaskId');
    idEl.innerText = task.id;

    // Title & Description
    document.getElementById('detailTaskTitle').innerText = task.title;
    document.getElementById('detailTaskDescription').innerText = task.description || 'Tidak ada deskripsi rinci untuk tugas ini.';

    // Current Column Name
    const colName = window.columnsDef[task.status] ? window.columnsDef[task.status].title : task.status;
    document.getElementById('detailCurrentColumnName').innerText = colName;

    // Render Quick Column Shift Buttons
    const statusBtnContainer = document.getElementById('detailStatusButtons');
    statusBtnContainer.innerHTML = '';
    Object.keys(window.columnsDef).forEach(colKey => {
        const isCurrent = task.status === colKey;
        const colTitle = window.columnsDef[colKey].title;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `px-2 py-1.5 rounded-lg text-[11px] font-black transition ${
            isCurrent ? 'bg-[#043399] text-white shadow-xs' : 'bg-white text-black hover:bg-slate-200 border border-slate-300'
        }`;
        btn.innerText = colTitle;
        if (!isCurrent) {
            btn.onclick = () => updateTaskStatus(taskId, colKey);
        }
        statusBtnContainer.appendChild(btn);
    });

    // Assignee Display
    const assDisplay = document.getElementById('detailAssigneeDisplay');
    const assActions = document.getElementById('detailAssigneeActions');
    if (task.assignee_name) {
        assDisplay.innerHTML = `
            <div class="w-7 h-7 rounded-full bg-blue-50 text-[#043399] border border-blue-200 flex items-center justify-center font-bold text-xs">${task.assignee_name ? task.assignee_name.charAt(0).toUpperCase() : "P"}</div>
            <div>
                <div class="text-xs font-black text-black">${task.assignee_name}</div>
                <div class="text-[10px] text-slate-500 font-semibold">Penanggung Jawab (PIC)</div>
            </div>
        `;

        if (window.currentRole === 'siswa' && window.currentStudent && task.assignee_id === window.currentStudent.id) {
            assActions.innerHTML = `
                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                    Tugas ini Anda ambil
                </span>
                <button type="button" onclick="claimTask('${taskId}', '', '')" class="text-[10px] text-rose-700 font-bold hover:underline">
                    Lepaskan Tugas
                </button>
            `;
        } else {
            assActions.innerHTML = `
                <button type="button" onclick="claimTask('${taskId}', '${window.currentStudent?.id || ''}', '${window.currentStudent?.name || ''}')" class="text-[10px] text-[#043399] font-bold hover:underline">
                    Ambil Alih Tugas Ini
                </button>
            `;
        }
    } else {
        assDisplay.innerHTML = `
            <div class="w-7 h-7 rounded-full bg-slate-100 text-slate-400 border border-slate-200 flex items-center justify-center font-bold text-xs">?</div>
            <div>
                <div class="text-xs font-bold text-slate-500 italic">Belum Ada PIC (Bebas)</div>
                <div class="text-[10px] text-slate-400">Dapat diambil oleh siapa saja</div>
            </div>
        `;
        if (window.currentRole === 'siswa' && window.currentStudent) {
            assActions.innerHTML = `
                <button type="button" onclick="claimTask('${taskId}', '${window.currentStudent.id}', '${window.currentStudent.name}')" class="px-2.5 py-1 bg-[#043399] hover:bg-[#021f5c] text-white rounded-lg text-xs font-black transition">
                    + Ambil Tugas Ini untuk Saya
                </button>
            `;
        } else {
            assActions.innerHTML = `<span class="text-[10px] text-slate-400 italic">Siswa dapat mengambil tugas ini di papan</span>`;
        }
    }

    // Dependency Display
    const depDisplay = document.getElementById('detailDependencyDisplay');
    if (task.dependency_task_id) {
        depDisplay.innerHTML = `
            <div class="flex items-center gap-1.5 text-amber-900 bg-amber-50 p-2 rounded-lg border border-amber-300">
                <span class="text-xs font-bold">${task.dependency_task_title || 'Tiket backend terkait'}</span>
            </div>
        `;
    } else {
        depDisplay.innerHTML = `<span class="text-xs text-slate-400 font-semibold italic">Tidak ada ketergantungan tiket lain.</span>`;
    }

    // Timestamps
    document.getElementById('detailCreatedAt').innerText = 'Dibuat: ' + (task.created_at || '-');
    document.getElementById('detailUpdatedAt').innerText = 'Diperbarui: ' + (task.updated_at || '-');

    // Show modal
    const modal = document.getElementById('taskDetailModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    }

function closeTaskDetail() {
    const modal = document.getElementById('taskDetailModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// 4. Edit Task Modal Handlers
function openEditTaskModal(taskId) {
    const task = getTaskById(taskId);
    if (!task) return;

    closeTaskDetail();

    document.getElementById('editTaskId').value = task.id;
    document.getElementById('editTaskTitle').value = task.title || '';
    document.getElementById('editTaskRole').value = task.role_category || 'frontend';
    document.getElementById('editTaskPoints').value = task.story_points || 3;
    document.getElementById('editTaskPriority').value = task.priority || 'medium';
    document.getElementById('editTaskStatus').value = task.status || 'sprint_backlog';
    document.getElementById('editTaskAssignee').value = task.assignee_id || '';
    document.getElementById('editTaskDependency').value = task.dependency_task_id || '';
    document.getElementById('editTaskDescription').value = task.description || '';

    const modal = document.getElementById('editTaskModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeEditModal() {
    const modal = document.getElementById('editTaskModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function switchToEditModal() {
    if (currentDetailTaskId) {
        openEditTaskModal(currentDetailTaskId);
    }
}

async function handleUpdateTask(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Assignee name
    const assSelect = document.getElementById('editTaskAssignee');
    const selectedAssOpt = assSelect.options[assSelect.selectedIndex];
    data.assignee_name = selectedAssOpt ? selectedAssOpt.dataset.name || '' : '';

    // Dependency title
    const depSelect = document.getElementById('editTaskDependency');
    const selectedDepOpt = depSelect.options[depSelect.selectedIndex];
    data.dependency_task_title = selectedDepOpt ? selectedDepOpt.dataset.title || '' : '';

    // Optimistically update localStorage
    try {
        const cachedRaw = localStorage.getItem(taskStorageKey);
        if (cachedRaw) {
            const list = JSON.parse(cachedRaw);
            const idx = list.findIndex(item => item.id === data.id);
            if (idx !== -1) {
                list[idx] = { ...list[idx], ...data, updated_at: new Date().toISOString().slice(0, 19).replace('T', ' ') };
                localStorage.setItem(taskStorageKey, JSON.stringify(list));
            }
        }
    } catch(err) {}

    try {
        const res = await fetch('api.php?action=update_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal memperbarui tiket');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

// 5. Delete Task from Detail Pop-Up
async function handleDeleteFromDetail() {
    if (!currentDetailTaskId) return;
    const ok = await showAppConfirm('Apakah kamu yakin ingin menghapus tiket ini dari Scrum Kanban Board?', {
        title: 'Konfirmasi Hapus Tiket',
        confirmText: 'Ya, Hapus Tiket',
        confirmColor: 'red'
    });
    if (!ok) return;

    // Optimistically remove from localStorage
    try {
        const cachedRaw = localStorage.getItem(taskStorageKey);
        if (cachedRaw) {
            const list = JSON.parse(cachedRaw).filter(item => item.id !== currentDetailTaskId);
            localStorage.setItem(taskStorageKey, JSON.stringify(list));
        }
    } catch(err) {}

    try {
        const res = await fetch('api.php?action=delete_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentDetailTaskId })
        });
        const result = await res.json();
        if (result.success) {
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal menghapus tiket');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

// 6. Handle Create Task Form
async function handleCreateTask(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Get assignee name
    const assSelect = document.getElementById('assigneeSelect');
    const selectedAssOpt = assSelect.options[assSelect.selectedIndex];
    data.assignee_name = selectedAssOpt ? selectedAssOpt.dataset.name || '' : '';

    // Get dependency title
    const depSelect = document.getElementById('dependencySelect');
    const selectedDepOpt = depSelect.options[depSelect.selectedIndex];
    data.dependency_task_title = selectedDepOpt ? selectedDepOpt.dataset.title || '' : '';

    try {
        const res = await fetch('api.php?action=create_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            // Save newly created task to localStorage immediately
            try {
                const newTask = {
                    id: result.id,
                    team_id: data.team_id || window.currentTeamId || 'team-1',
                    prd_id: data.prd_id || null,
                    title: data.title || 'Tugas Baru',
                    description: data.description || '',
                    status: data.status || 'sprint_backlog',
                    role_category: data.role_category || 'frontend',
                    story_points: parseInt(data.story_points || 3),
                    priority: data.priority || 'medium',
                    assignee_id: data.assignee_id || null,
                    assignee_name: data.assignee_name || null,
                    sprint_number: parseInt(data.sprint_number || 1),
                    dependency_task_id: data.dependency_task_id || null,
                    dependency_task_title: data.dependency_task_title || null,
                    created_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                    updated_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                };
                const cachedRaw = localStorage.getItem(taskStorageKey);
                const list = cachedRaw ? JSON.parse(cachedRaw) : [];
                list.push(newTask);
                localStorage.setItem(taskStorageKey, JSON.stringify(list));
            } catch(e) {}

            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal menyimpan tugas');
        }
    } catch (err) {
        await showAppAlert('Error: ' + err.message);
    }
}

// 7. Backup & Restore Functions (Serverless Resiliency)
function openBackupModal() {
    const modal = document.getElementById('backupModal');
    if (!modal) return;
    const cachedRaw = localStorage.getItem(taskStorageKey);
    const count = cachedRaw ? (JSON.parse(cachedRaw).length || 0) : 0;
    const label = document.getElementById('backupCacheCount');
    if (label) {
        label.innerText = `Tersimpan di Browser: ${count} Tiket (Kelompok ini)`;
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeBackupModal() {
    const modal = document.getElementById('backupModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function exportTasksJSON() {
    const cachedRaw = localStorage.getItem(taskStorageKey);
    const tasksToExport = (window.tasksData && window.tasksData.length > 0) 
        ? window.tasksData 
        : (cachedRaw ? JSON.parse(cachedRaw) : []);
    
    if (tasksToExport.length === 0) {
        showAppAlert('Tidak ada tiket tugas untuk diunduh.');
        return;
    }

    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(tasksToExport, null, 2));
    const dlAnchor = document.createElement('a');
    const dateStr = new Date().toISOString().slice(0, 10);
    dlAnchor.setAttribute("href", dataStr);
    dlAnchor.setAttribute("download", `scrumvibe_kanban_${window.currentTeamId || 'team'}_${dateStr}.json`);
    document.body.appendChild(dlAnchor);
    dlAnchor.click();
    dlAnchor.remove();
}

function importTasksJSON(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const parsed = JSON.parse(e.target.result);
            if (!Array.isArray(parsed)) {
                await showAppAlert('Format file JSON tidak valid. Harus berupa daftar array tiket.');
                return;
            }

            // Save to localStorage immediately
            localStorage.setItem(taskStorageKey, JSON.stringify(parsed));

            // Sync to server
            const res = await fetch('api.php?action=sync_tasks', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ team_id: window.currentTeamId, tasks: parsed })
            });
            const result = await res.json();
            if (result.success) {
                await showAppAlert(`Berhasil memulihkan ${parsed.length} tiket ke papan Kanban!`);
                window.location.reload();
            } else {
                await showAppAlert(result.error || 'Gagal menyinkronkan data ke server');
            }
        } catch(err) {
            await showAppAlert('Gagal membaca file JSON: ' + err.message);
        }
    };
    reader.readAsText(file);
}

async function manualSyncCache() {
    const cachedRaw = localStorage.getItem(taskStorageKey);
    if (!cachedRaw) {
        await showAppAlert('Tidak ada cache tiket yang tersimpan di browser.');
        return;
    }
    try {
        const tasks = JSON.parse(cachedRaw);
        const res = await fetch('api.php?action=sync_tasks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_id: window.currentTeamId, tasks: tasks })
        });
        const result = await res.json();
        if (result.success) {
            await showAppAlert(`Berhasil menyinkronkan ${tasks.length} tiket ke server!`);
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal sinkronisasi');
        }
    } catch(err) {
        await showAppAlert('Error: ' + err.message);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
