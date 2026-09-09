<?php
// includes/header.php - Clean corporate header with VINIX7 Branding (Royal Blue & Yellow), Role Switcher, and Nav
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Auth guard: redirect to login if not authenticated
if (empty($_SESSION['scrumvibe_logged_in']) && $currentPage !== 'login.php') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/whatsapp.php';

// Strict Session Authentication & Role Guard (No crossing accounts)
$currentRole = $_SESSION['scrumvibe_role'] ?? 'guru';
if ($currentRole === 'super_admin') $currentRole = 'guru';
if ($currentRole !== 'guru' && $currentRole !== 'siswa') $currentRole = 'siswa';

// Guard admin.php and reports.php strictly to Guru before any HTML output
if (($currentPage === 'admin.php' || $currentPage === 'reports.php') && $currentRole !== 'guru') {
    header('Location: index.php');
    exit;
}

$currentUserId = $_SESSION['scrumvibe_user_id'] ?? null;

if ($currentRole === 'siswa') {
    // Lock strictly to the logged in student
    $stmtStudent = $pdo->prepare("SELECT * FROM members WHERE id = ? LIMIT 1");
    $stmtStudent->execute([$currentUserId]);
    $currentStudent = $stmtStudent->fetch();

    if (!$currentStudent) {
        $stmtStudent = $pdo->prepare("SELECT * FROM members WHERE role = 'siswa' OR (role != 'guru' AND role != 'super_admin') LIMIT 1");
        $stmtStudent->execute();
        $currentStudent = $stmtStudent->fetch();
    }

    $currentStudentId = $currentStudent['id'] ?? 'mem-pm-1';
    $currentTeamId = $currentStudent['team_id'] ?: 'team-1';
    $_SESSION['scrumvibe_student_id'] = $currentStudentId;
    $_SESSION['scrumvibe_team_id'] = $currentTeamId;
    $currentGuru = null;
    $currentMember = $currentStudent;
} else {
    // Guru Role
    $stmtGuru = $pdo->prepare("SELECT * FROM members WHERE id = ? AND (role = 'guru' OR role = 'super_admin') LIMIT 1");
    $stmtGuru->execute([$currentUserId]);
    $currentGuru = $stmtGuru->fetch();

    if (!$currentGuru) {
        $stmtGuru = $pdo->query("SELECT * FROM members WHERE role = 'guru' OR role = 'super_admin' LIMIT 1");
        $currentGuru = $stmtGuru->fetch();
    }

    if (isset($_GET['team_id'])) {
        $_SESSION['scrumvibe_team_id'] = $_GET['team_id'];
    }
    $currentTeamId = $_SESSION['scrumvibe_team_id'] ?? 'team-1';
    $currentStudent = null;
    $currentStudentId = null;
    $currentMember = $currentGuru;
}

// Fetch all teams for team selector
$stmt = $pdo->query("SELECT * FROM teams ORDER BY created_at DESC");
$allTeams = $stmt->fetchAll();
$currentTeam = null;
foreach ($allTeams as $t) {
    if ($t['id'] === $currentTeamId) {
        $currentTeam = $t;
        break;
    }
}
if (!$currentTeam && count($allTeams) > 0) {
    $currentTeam = $allTeams[0];
    $currentTeamId = $currentTeam['id'];
}

// Fetch team students for current team
$stmt = $pdo->prepare("SELECT * FROM members WHERE team_id = ? AND (role = 'siswa' OR role != 'guru') ORDER BY name ASC");
$stmt->execute([$currentTeamId]);
$teamStudents = $stmt->fetchAll();

$rolesDef = [
    'guru' => ['label' => 'Guru / Instruktur', 'badge' => 'badge-role-admin', 'color' => '#581c87'],
    'siswa' => ['label' => 'Siswa (Anggota Kelompok)', 'badge' => 'badge-role-pm', 'color' => '#043399']
];

$roleInfo = $rolesDef[$currentRole] ?? $rolesDef['guru'];
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Development - Program Fast Track</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: [
                            'SF Pro Display',
                            '-apple-system',
                            'BlinkMacSystemFont',
                            'Segoe UI',
                            'Roboto',
                            'Helvetica',
                            'Arial',
                            'sans-serif'
                        ]
                    },
                    colors: {
                        vinix: {
                            blue: '#043399',
                            darkblue: '#021f5c',
                            yellow: '#f59e0b',
                            darkyellow: '#d97706'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Canvas Confetti -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* Base typography matching Facebook / Meta clean aesthetic */
        body {
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1c1e21;
            background-color: #f0f2f5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        h1, h2, h3, h4, h5, h6, p, span, td, th, label, div, input, textarea, select {
            color: inherit;
        }
        .text-white {
            color: #ffffff !important;
        }
        .text-black {
            color: #1c1e21 !important;
        }
        .text-yellow-accent {
            color: #f59e0b !important;
        }
        .text-blue-accent {
            color: #043399 !important;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-[#f0f2f5] text-[#1c1e21] antialiased font-sans">

    <!-- MAIN NAVBAR with VINIX7 Logo & Corporate Navy Border -->
    <header class="no-print bg-white border-b-2 border-[#043399] sticky top-0 z-40 shadow-xs">
        <div class="w-full max-w-[1600px] mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between min-h-[4rem] sm:min-h-[4.25rem] py-1.5 sm:py-2 gap-2">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                    <a href="index.php" class="flex items-center gap-2.5 sm:gap-3 group shrink-0">
                        <?php if (file_exists(__DIR__ . '/../logo/LOGO VINIX.png')): ?>
                            <img src="logo/LOGO VINIX.png" alt="VINIX7" class="h-10 sm:h-12 w-auto object-contain">
                        <?php else: ?>
                            <div class="h-10 px-3 bg-[#043399] text-white font-black text-lg rounded-lg flex items-center justify-center">
                                VINIX<span class="text-[#f59e0b] ml-0.5">7</span>
                            </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-sm sm:text-base font-black tracking-tight text-slate-900 leading-tight block whitespace-nowrap">
                                Web Development
                            </span>
                            <span class="block text-[10px] sm:text-[11px] text-slate-600 font-semibold tracking-tight whitespace-nowrap">
                                Program Fast Track
                            </span>
                        </div>
                    </a>

                    <!-- Team Selector (Clean Text Only - Guru only) -->
                    <?php if ($currentRole === 'guru' && count($allTeams) > 0): ?>
                        <div class="hidden xl:flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1 text-xs shrink-0">
                            <span class="text-slate-600 font-medium whitespace-nowrap">Kelompok:</span>
                            <select onchange="switchTeam(this.value)" class="bg-transparent font-bold text-slate-900 focus:outline-none cursor-pointer max-w-[200px] truncate">
                                <?php foreach ($allTeams as $t): ?>
                                    <option value="<?= htmlspecialchars($t['id']) ?>" <?= $t['id'] === $currentTeamId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Nav Items (Clean Corporate Text Only, Never wrap text) -->
                    <nav class="flex items-center gap-1 sm:gap-1.5 flex-nowrap">
                        <a href="board.php" class="px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-bold whitespace-nowrap transition <?= $currentPage === 'board.php' ? 'bg-[#043399] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-[#043399]' ?>">
                            Board
                        </a>
                        <a href="prd.php" class="px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-bold whitespace-nowrap transition <?= $currentPage === 'prd.php' ? 'bg-[#043399] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-[#043399]' ?>">
                            PRD
                        </a>
                        <a href="logbook.php" class="px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-bold whitespace-nowrap transition <?= $currentPage === 'logbook.php' ? 'bg-[#043399] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-[#043399]' ?>">
                            Log Book
                        </a>
                        <a href="lms.php" class="px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-bold whitespace-nowrap transition <?= $currentPage === 'lms.php' ? 'bg-[#043399] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-[#043399]' ?>">
                            Materi Pembelajaran
                        </a>
                        <?php if ($currentRole === 'guru'): ?>
                        <a href="reports.php" class="px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-bold whitespace-nowrap transition <?= $currentPage === 'reports.php' ? 'bg-[#043399] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-[#043399]' ?>">
                            Report
                        </a>
                        <a href="admin.php" class="px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-bold whitespace-nowrap transition <?= $currentPage === 'admin.php' ? 'bg-[#043399] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-[#043399]' ?>">
                            Admin Area
                        </a>
                        <?php endif; ?>
                    </nav>

                    <!-- Account Dropdown (OUTSIDE NAV, never clipped, high z-index) -->
                    <div class="relative shrink-0" id="accountDropdownContainer">
                        <button type="button" id="accountBtn" onclick="toggleAccountDropdown(event)" class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-bold text-slate-700 hover:bg-slate-100 hover:text-[#043399] whitespace-nowrap transition cursor-pointer select-none">
                            <span>Account</span>
                            <span class="text-[9px] text-slate-500 leading-none">&#9660;</span>
                        </button>

                        <div id="accountDropdownMenu" style="display: none;" class="absolute right-0 top-full mt-1.5 w-64 bg-white rounded-none shadow-xl border border-slate-300 z-[9999] text-slate-900">
                            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                                <p class="text-xs font-black text-slate-900 truncate"><?= htmlspecialchars($_SESSION['scrumvibe_user_name'] ?? ($currentMember['name'] ?? 'Akun')) ?></p>
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mt-0.5">
                                    <?= $currentRole === 'guru' ? 'Guru / Instruktur' : 'Siswa' ?>
                                    <?php if ($currentRole === 'siswa' && $currentTeam): ?>
                                        &bull; <?= htmlspecialchars($currentTeam['name']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="py-1">
                                <button type="button" onclick="openEditAccountModal(); toggleAccountDropdown();" class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-black transition flex items-center justify-between cursor-pointer">
                                    <span>Edit Account</span>
                                </button>
                                <div class="border-t border-slate-100 my-0.5"></div>
                                <a href="logout.php" class="block w-full text-left px-4 py-2.5 text-xs font-black text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition">
                                    Keluar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <script>
    function toggleAccountDropdown(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        var menu = document.getElementById('accountDropdownMenu');
        if (!menu) return;
        if (menu.style.display === 'none' || menu.style.display === '') {
            menu.style.display = 'block';
        } else {
            menu.style.display = 'none';
        }
    }
    document.addEventListener('click', function(e) {
        var container = document.getElementById('accountDropdownContainer');
        var menu = document.getElementById('accountDropdownMenu');
        if (container && menu && !container.contains(e.target)) {
            menu.style.display = 'none';
        }
    });
    </script>

    <main class="flex-1 max-w-[1600px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
