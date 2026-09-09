<?php
// includes/db.php - Database connection & schema migration using PDO SQLite

$dbPath = __DIR__ . '/../scrumvibe.db';

// Support Vercel serverless environment (root directory is read-only)
if (getenv('VERCEL') || getenv('NOW_REGION')) {
    $tmpDir = sys_get_temp_dir();
    $tmpDb = $tmpDir . '/scrumvibe.db';
    if (!file_exists($tmpDb) && file_exists($dbPath)) {
        @copy($dbPath, $tmpDb);
    }
    $dbPath = $tmpDb;
}

$isNew = !file_exists($dbPath);

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create Tables if not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teams (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            project_title TEXT NOT NULL,
            description TEXT,
            sprint_number INTEGER DEFAULT 1,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS members (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            role TEXT NOT NULL,
            team_id TEXT,
            phone TEXT,
            email TEXT,
            university TEXT,
            major TEXT,
            token TEXT UNIQUE NOT NULL,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS prds (
            id TEXT PRIMARY KEY,
            team_id TEXT NOT NULL,
            title TEXT NOT NULL,
            version TEXT DEFAULT '1.0',
            status TEXT DEFAULT 'draft',
            problem_statement TEXT,
            user_personas TEXT,
            user_stories TEXT,
            technical_notes TEXT,
            feedback_teacher TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS tasks (
            id TEXT PRIMARY KEY,
            team_id TEXT NOT NULL,
            prd_id TEXT,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT NOT NULL,
            role_category TEXT NOT NULL,
            story_points INTEGER DEFAULT 1,
            priority TEXT DEFAULT 'medium',
            assignee_id TEXT,
            assignee_name TEXT,
            sprint_number INTEGER DEFAULT 1,
            dependency_task_id TEXT,
            dependency_task_title TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS logbooks (
            id TEXT PRIMARY KEY,
            team_id TEXT NOT NULL,
            student_id TEXT NOT NULL,
            student_name TEXT NOT NULL,
            role TEXT NOT NULL,
            university TEXT,
            major TEXT,
            week_number INTEGER NOT NULL,
            entry_date TEXT NOT NULL,
            description TEXT NOT NULL,
            proof_link TEXT,
            blockers TEXT,
            teacher_notes TEXT,
            status TEXT DEFAULT 'submitted',
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS lms_modules (
            id TEXT PRIMARY KEY,
            week_number INTEGER NOT NULL,
            title TEXT NOT NULL,
            summary TEXT NOT NULL,
            content TEXT NOT NULL,
            objectives TEXT NOT NULL,
            deliverables TEXT NOT NULL,
            external_links TEXT NOT NULL,
            is_published INTEGER DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS system_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    ");

    // Migration: add password_hash column to members (for password feature)
    $cols = $pdo->query("PRAGMA table_info(members)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('password_hash', $colNames)) {
        $pdo->exec("ALTER TABLE members ADD COLUMN password_hash TEXT DEFAULT ''");
    }
    if (!in_array('invite_used', $colNames)) {
        $pdo->exec("ALTER TABLE members ADD COLUMN invite_used INTEGER DEFAULT 0");
    }

    // Check if seeded
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teams");
    $rowCount = (int)$stmt->fetch()['count'];

    if ($rowCount === 0) {
        seedInitialData($pdo);
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function seedInitialData($pdo) {
    $now = date('Y-m-d H:i:s');

    // 1. Default Team
    $stmt = $pdo->prepare("INSERT INTO teams (id, name, project_title, description, sprint_number, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['team-1', 'kelompok 2 - fastrack september 2026', '', '', 1, $now]);

    // 2. Guru / Super Admin Member
    $stmt = $pdo->prepare("INSERT INTO members (id, name, role, team_id, phone, email, university, major, token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['mem-guru', 'Rendi Yusuf Azhari', 'guru', '', '6285234332322', 'ligerrendy@gmail.com', 'PT VINIX SEVEN AURUM', 'Program Fast Track', 'guru-master-token', $now]);
    // 3. LMS Modules
    $modules = [
        [
            'lms-w1', 1, 'Pertemuan 1: Fondasi Agile, Scrum, & Menyusun PRD Berkualitas',
            'Memahami pola pikir Agile, siklus hidup Scrum, pembagian peran nyata (PM, BE, FE), dan teknik menulis Product Requirement Document.',
            json_encode([
                "Memahami perbedaan metodologi Agile vs Waterfall dalam pengembangan software",
                "Mengenal tanggung jawab spesifik PM, Backend Engineer, dan Frontend Engineer",
                "Mampu menyusun PRD lengkap dengan Problem Statement, User Stories, dan Acceptance Criteria"
            ]),
            json_encode([
                "Dokumen PRD versi 1.0 yang disetujui Guru/Instruktur",
                "Daftar User Persona dan Acceptance Criteria awal",
                "Logbook Harian Pertemuan 1"
            ]),
            json_encode([
                ["title" => "Panduan Agile Manifesto", "url" => "https://agilemanifesto.org/"],
                ["title" => "Contoh Template PRD Industri", "url" => "https://www.atlassian.com/agile/product-management/requirements"]
            ]),
            "### Mengapa Agile & Scrum Penting?\nDalam dunia industri modern, kebutuhan pengguna berubah sangat cepat. Pendekatan lama (Waterfall) membutuhkan waktu berbulan-bulan sebelum pengguna bisa melihat produk jadi. Scrum membagi pekerjaan ke dalam siklus pendek (**Sprint**, biasanya 1-2 minggu) sehingga tim bisa merilis fitur secara bertahap dan mendapatkan umpan balik langsung.\n\n### 3 Peran Kunci dalam Tim Kita:\n1. **Product Manager (PM)**:\n   - Jembatan antara kebutuhan bisnis/pengguna dengan tim pengembang teknis.\n   - Menulis **PRD (Product Requirement Document)** yang jelas dan terukur.\n   - Memastikan tim tahu *mengapa* fitur tersebut dibangun (*The Why & What*).\n\n2. **Backend Engineer (BE)**:\n   - Merancang struktur data (Database Schema) dan logika bisnis.\n   - Membuat **API Endpoint (REST / JSON)** yang aman, cepat, dan teruji.\n   - Menyediakan kontrak API (*API Contract*) agar FE bisa melakukan integrasi.\n\n3. **Frontend Engineer (FE)**:\n   - Menerjemahkan spesifikasi PRD dan desain wireframe menjadi antarmuka interaktif yang nyaman digunakan (*User Experience*).\n   - Menghubungkan antarmuka ke API yang disediakan oleh Backend Engineer.\n   - Menjaga responsivitas di berbagai ukuran layar."
        ],
        [
            'lms-w2', 2, 'Pertemuan 2: Sprint Planning, Story Points, & Backlog Breakdown',
            'Mengubah dokumen PRD menjadi tiket kerja teknis di Scrum Board, estimasi kompleksitas dengan Fibonacci Story Points, dan komitmen Sprint Backlog.',
            json_encode([
                "Mampu memecah poin PRD menjadi tiket spesifik Frontend dan Backend",
                "Memahami estimasi Story Points menggunakan deret Fibonacci (1, 2, 3, 5, 8)",
                "Menjalankan sesi simulasi Sprint Planning bersama seluruh anggota tim"
            ]),
            json_encode([
                "Papan Scrum Board terisi tiket Backlog terperinci",
                "Semua tiket memiliki Story Points dan estimasi waktu yang jelas",
                "Logbook Harian Pertemuan 2"
            ]),
            json_encode([
                ["title" => "Story Points & Planning Poker Guide", "url" => "https://www.mountaingoatsoftware.com/agile/planning-poker"]
            ]),
            "### Apa itu Story Points?\nStory Points adalah satuan ukuran relatif untuk memperkirakan usaha (*effort*), kompleksitas teknis, dan ketidakpastian dalam menyelesaikan suatu tugas. Story Points **bukan** jam kerja mutlak, melainkan perbandingan bobot.\n\n### Skala Fibonacci yang Digunakan:\n- **1 Poin**: Tugas sangat sederhana (contoh: mengubah teks tombol, update konfigurasi env).\n- **2 Poin**: Tugas mudah dan jelas (contoh: slicing satu komponen kartu sederhana).\n- **3 Poin**: Tugas standar dengan logic moderat (contoh: form validasi login, endpoint CRUD tunggal).\n- **5 Poin**: Tugas kompleks yang butuh integrasi lebih dalam (contoh: dashboard dengan agregasi database dan grafik).\n- **8 Poin**: Tugas sangat besar yang berisiko; sebaiknya dipecah menjadi dua tugas lebih kecil.\n\n### Kolaborasi FE & BE saat Breakdown:\nSebelum sprint dimulai, FE dan BE harus menyepakati **API Contract**:\n- Format URL (misal: POST /api/auth/login)\n- Request Body JSON\n- Response JSON sukses dan format error code."
        ],
        [
            'lms-w3', 3, 'Pertemuan 3: Sprint Execution, Daily Standup, & Sinkronisasi API',
            'Fase eksekusi aktif, menjalankan Daily Standup singkat, sinkronisasi integrasi Frontend-Backend, dan mengatasi blockers.',
            json_encode([
                "Melakukan Daily Standup 3 pertanyaan harian (kemarin, hari ini, kendala)",
                "Melakukan integrasi API antara FE dan BE secara lancar",
                "Mendokumentasikan hambatan (blockers) di Logbook secara jujur dan solutif"
            ]),
            json_encode([
                "Fitur utama berstatus In Review / Testing",
                "Integrasi antarmuka dan endpoint backend berhasil tanpa error",
                "Logbook Harian Pertemuan 3"
            ]),
            json_encode([
                ["title" => "Effective Daily Standup Tips", "url" => "https://martinfowler.com/articles/itsNotJustStandingUp.html"]
            ]),
            "### 3 Pertanyaan Sakti Daily Standup (10-15 Menit):\n1. **Apa yang sudah saya selesaikan kemarin?**\n2. **Apa yang akan saya kerjakan hari ini?**\n3. **Apakah ada kendala (*blocker*) yang menghambat pekerjaan saya?**\n\n### Menghadapi Ketergantungan (Dependency Block):\nSeringkali Frontend terhambat karena Backend belum menyelesaikan endpoint. Bagaimana solusinya?\n- **Mock Data**: FE membuat data tiruan (dummy JSON) terlebih dahulu sehingga pengerjaan UI tidak perlu berhenti.\n- **Komunikasi Aktif**: BE memberi kabar segera setelah endpoint siap diuji di staging/local."
        ],
        [
            'lms-w4', 4, 'Pertemuan 4: Sprint Review (Demo), Retrospective, & Weekly Report',
            'Melakukan demo hasil karya produk kepada Guru/Stakeholder, evaluasi Sprint Retrospective, dan pembuatan Laporan Mingguan otomatis.',
            json_encode([
                "Mempresentasikan demo fitur fungsional sesuai Acceptance Criteria",
                "Menjalankan Sprint Retrospective (What went well, What can be improved)",
                "Mengevaluasi Velocity tim dan kepatuhan pengisian Logbook"
            ]),
            json_encode([
                "Aplikasi berfungsi dan teruji end-to-end",
                "Catatan Sprint Retrospective tim",
                "Laporan Mingguan Otomatis (Weekly Report) yang diekspor"
            ]),
            json_encode([
                ["title" => "Sprint Retrospective Primer", "url" => "https://www.scrum.org/resources/what-is-a-sprint-retrospective"]
            ]),
            "### Sprint Review vs Sprint Retrospective:\n- **Sprint Review**: Fokus pada **PRODUK** (Apakah fitur yang dibuat sesuai dengan kebutuhan dan berfungsi dengan benar? Diikuti dengan demo langsung).\n- **Sprint Retrospective**: Fokus pada **PROSES & TIM** (Bagaimana cara kerja kita bersama? Apa komunikasi yang kurang? Bagaimana meningkatkan kecepatan di sprint berikutnya?).\n\n### Pertanyaan Evaluasi Retrospective:\n1. **Mad / Sad / Glad**: Apa yang membuat tim frustrasi, sedih, atau bangga?\n2. **Action Item**: 1 atau 2 perbaikan konkret untuk sprint berikutnya."
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO lms_modules (id, week_number, title, summary, objectives, deliverables, external_links, content, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($modules as $m) {
        $stmt->execute($m);
    }

    // 7. System Settings
    $settings = [
        ['waGatewayUrl', 'https://api.fonnte.com/send'],
        ['waApiToken', ''],
        ['waSenderNumber', ''],
        ['invitationTemplate', "Halo {nama}!\n\nKamu telah diundang oleh {guru} untuk bergabung ke Tim Proyek Scrum *{tim}* sebagai *{role}*.\n\nSilakan klik tautan berikut untuk mengatur kata sandi dan masuk ke aplikasi:\n{invite_link}\n\nSelamat belajar dan berkolaborasi!"],
        ['weeklyReportTemplate', "*LAPORAN MINGGUAN SCRUM (WEEK {week})*\nKelompok: *{tim}*\nProyek: *{proyek}*\n\n*Progress & Velocity:*\n• Story Points Selesai: {completedPoints}/{totalPoints} ({percent}%)\n• Status Tiket: {doneCount} Selesai, {inProgressCount} Dikerjakan, {todoCount} Menunggu\n\n*Kontribusi Tim:*\n• PM: {pmStatus}\n• Backend: {beStatus}\n• Frontend: {feStatus}\n\n*Kepatuhan Logbook:*\n{logbookStatus}\n\nAkses Laporan: {link}"]
    ];

    $stmt = $pdo->prepare("INSERT OR REPLACE INTO system_settings (key, value) VALUES (?, ?)");
    foreach ($settings as $s) {
        $stmt->execute($s);
    }
}
?>
