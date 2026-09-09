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
    } else {
        // Ensure 8 modules are populated if old database had only 4
        $stmtLms = $pdo->query("SELECT COUNT(*) as count FROM lms_modules");
        $lmsCount = (int)$stmtLms->fetch()['count'];
        if ($lmsCount < 8) {
            seedLmsModulesOnly($pdo);
        }
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
    // 3. LMS Modules (8 Pertemuan Kurikulum Fast Track Web)
    $modules = [
        [
            'lms-w1', 1, 'Pertemuan 1: Pengenalan Website & Product Requirement Document',
            'Memahami anatomi website modern (client, server, database, domain) dan menerjemahkan ide produk menjadi PRD yang siap dikerjakan tim.',
            json_encode([
                "Menjelaskan cara kerja website: browser, siklus request-response, serta beda client-side dan server-side",
                "Membedakan jenis website (statis, dinamis, SPA, e-commerce) beserta konsekuensi teknisnya",
                "Menyusun PRD lengkap: problem statement, user persona, user story, dan acceptance criteria"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Dokumen PRD versi 1.0 yang disetujui Guru/Instruktur",
                "Daftar 2 User Persona dan minimal 8 User Story beserta Acceptance Criteria",
                "Logbook Harian Pertemuan 1"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Panduan Menulis PRD (Atlassian)", "url" => "https://www.atlassian.com/agile/product-management/requirements"],
                ["title" => "Learn Web Development (MDN)", "url" => "https://developer.mozilla.org/en-US/docs/Learn_web_development"]
            ], JSON_UNESCAPED_UNICODE),
            "### Anatomi Sebuah Website\n1. **Client (Browser)**: Merender HTML, CSS, dan JavaScript menjadi tampilan yang dilihat pengguna.\n2. **Server**: Memproses logika bisnis dan mengembalikan data atau halaman.\n3. **Database**: Menyimpan data permanen seperti user, produk, dan transaksi.\n4. **Domain & DNS**: Alamat yang menerjemahkan nama website ke alamat IP server.\n\n### Struktur PRD yang Dipakai Industri\n1. **Problem Statement**: Masalah nyata yang sudah divalidasi, bukan asumsi tim.\n2. **Goal & Success Metric**: Ukuran keberhasilan yang bisa dihitung.\n3. **User Persona**: Profil pengguna target beserta kebutuhan dan hambatannya.\n4. **User Story**: Format \"Sebagai [peran], saya ingin [aksi], agar [manfaat]\".\n5. **Acceptance Criteria**: Syarat sebuah story boleh dinyatakan selesai.\n6. **Out of Scope**: Hal yang sengaja tidak dikerjakan pada rilis ini.\n\n> **Catatan Guru**: Tolak PRD yang hanya berisi daftar fitur tanpa problem statement."
        ],
        [
            'lms-w2', 2, 'Pertemuan 2: Dasar UI/UX & Design Thinking',
            'Mengubah kebutuhan di PRD menjadi rancangan antarmuka yang mudah dipakai, lewat lima tahap Design Thinking dan prinsip dasar desain visual.',
            json_encode([
                "Menjalankan lima tahap Design Thinking: empathize, define, ideate, prototype, test",
                "Membedakan peran UX (alur dan kemudahan) dengan UI (visual dan komponen)",
                "Membuat wireframe sampai prototype interaktif yang siap diserahkan ke frontend"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Wireframe low-fidelity untuk 5 halaman utama",
                "Mockup high-fidelity dan prototype interaktif di Figma",
                "Mini design system: palet warna, skala tipografi, dan komponen tombol"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Design Thinking 101 (Nielsen Norman Group)", "url" => "https://www.nngroup.com/articles/design-thinking/"],
                ["title" => "Material Design 3 Foundations", "url" => "https://m3.material.io/foundations"]
            ], JSON_UNESCAPED_UNICODE),
            "### Lima Tahap Design Thinking\n1. **Empathize**: Wawancara calon pengguna, catat kata-kata aslinya.\n2. **Define**: Rumuskan satu kalimat masalah yang paling layak dipecahkan.\n3. **Ideate**: Kumpulkan banyak alternatif solusi dulu, saring belakangan.\n4. **Prototype**: Buat versi murah yang bisa diklik, bukan versi sempurna.\n5. **Test**: Uji ke pengguna asli, perbaiki, lalu ulangi.\n\n### Prinsip Dasar UI\n1. **Hierarki Visual**: Ukuran, ketebalan, dan jarak menentukan apa yang dibaca lebih dulu.\n2. **Konsistensi**: Komponen yang sama berperilaku sama di semua halaman.\n3. **White Space**: Ruang kosong adalah alat baca, bukan ruang terbuang.\n4. **Kontras & Aksesibilitas**: Rasio kontras teks minimal 4.5:1 sesuai standar WCAG AA.\n\n### Alur Kerja Desain\nWireframe (low-fidelity) &rarr; Mockup (high-fidelity) &rarr; Prototype interaktif &rarr; Handoff ke Frontend Engineer."
        ],
        [
            'lms-w3', 3, 'Pertemuan 3: Dasar HTML dan CSS',
            'Menerjemahkan mockup menjadi halaman nyata: struktur semantik dengan HTML dan tata letak responsif dengan CSS, tanpa bantuan framework.',
            json_encode([
                "Menyusun struktur halaman dengan elemen semantik dan hierarki heading yang benar",
                "Menguasai box model, selector, dan cascade agar aturan gaya tidak saling menimpa",
                "Membangun tata letak responsif memakai Flexbox, Grid, dan media query mobile-first"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Satu halaman landing statis responsif memakai HTML dan CSS murni",
                "Repository Git berisi commit harian dengan pesan yang jelas",
                "Checklist uji tampilan pada lebar 360px, 768px, dan 1440px"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Referensi HTML (MDN)", "url" => "https://developer.mozilla.org/en-US/docs/Web/HTML"],
                ["title" => "Learn CSS (web.dev)", "url" => "https://web.dev/learn/css"]
            ], JSON_UNESCAPED_UNICODE),
            "### HTML: Struktur dan Semantik\n1. **Elemen semantik**: header, nav, main, section, article, footer.\n2. **Form dan input**: pasangan label-input, tipe input, dan validasi bawaan browser.\n3. **Aksesibilitas**: atribut alt pada gambar serta urutan heading h1 sampai h6 yang runtut.\n\n### CSS: Tampilan dan Tata Letak\n1. **Selector & Cascade**: Spesifisitas menentukan aturan mana yang menang.\n2. **Box Model**: content, padding, border, margin.\n3. **Flexbox**: Tata letak satu dimensi, untuk baris atau kolom.\n4. **Grid**: Tata letak dua dimensi, untuk kerangka halaman.\n5. **Responsive**: Unit relatif (rem, %, vw) digabung media query, dikerjakan mobile-first.\n\n> **Aturan praktik**: Dilarang memakai framework CSS pada pertemuan ini. Tujuannya melatih dasar, bukan mengejar kecepatan."
        ],
        [
            'lms-w4', 4, 'Pertemuan 4: Monolith dan Fullstack Website',
            'Menentukan bentuk arsitektur aplikasi tim: kenapa monolith biasanya pilihan paling masuk akal untuk tim kecil, dan bagaimana lapisan fullstack disusun.',
            json_encode([
                "Membandingkan monolith, modular monolith, dan microservices beserta biaya operasionalnya",
                "Memetakan lapisan aplikasi fullstack: presentation, business logic, data, dan API",
                "Merancang skema database dan memilih tech stack dengan alasan yang bisa dipertahankan"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Diagram arsitektur sistem satu halaman",
                "Skema database (ERD) minimal 4 tabel beserta relasinya",
                "Dokumen keputusan tech stack beserta alasan dan risikonya"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Monolith First (Martin Fowler)", "url" => "https://martinfowler.com/bliki/MonolithFirst.html"],
                ["title" => "Full Stack Roadmap", "url" => "https://roadmap.sh/full-stack"]
            ], JSON_UNESCAPED_UNICODE),
            "### Pilihan Arsitektur\n1. **Monolith**: Frontend, backend, dan database dalam satu basis kode dan satu proses deploy.\n2. **Modular Monolith**: Tetap satu deploy, tetapi modul dipisah rapi per domain.\n3. **Microservices**: Banyak servis kecil yang deploy sendiri-sendiri, biaya operasionalnya tinggi.\n\n### Kenapa Mulai dari Monolith?\nTim kecil dengan kebutuhan yang masih sering berubah paling murah dilayani monolith. Pecah menjadi servis terpisah hanya setelah batas antar domain terbukti stabil dan beban trafik benar-benar menuntutnya.\n\n### Lapisan Aplikasi Fullstack\n1. **Presentation Layer**: Halaman dan komponen antarmuka.\n2. **Business Logic Layer**: Aturan main aplikasi, misalnya syarat sebuah checkout boleh diproses.\n3. **Data Layer**: Model, query, dan migrasi database.\n4. **API**: Kontrak yang menyambungkan frontend dan backend, umumnya REST berformat JSON."
        ],
        [
            'lms-w5', 5, 'Pertemuan 5: Frontend dan Backend Engineer',
            'Membagi pekerjaan nyata antara frontend dan backend, lalu menyepakati kontrak API lebih dulu supaya kedua sisi bisa berjalan paralel.',
            json_encode([
                "Menjelaskan tanggung jawab spesifik Frontend Engineer dan Backend Engineer dalam satu fitur",
                "Menyusun kontrak API: endpoint, method, request body, response, dan kode status",
                "Menjalankan alur kolaborasi Git: branch per fitur, pull request, dan code review"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Dokumen kontrak API berisi minimal 6 endpoint",
                "Satu fitur utuh end-to-end: form di frontend, API di backend, data tersimpan di database",
                "Catatan hasil code review beserta perbaikan yang sudah dikerjakan"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Frontend Roadmap", "url" => "https://roadmap.sh/frontend"],
                ["title" => "Backend Roadmap", "url" => "https://roadmap.sh/backend"]
            ], JSON_UNESCAPED_UNICODE),
            "### Pembagian Peran\n1. **Frontend Engineer**: State antarmuka, integrasi API, performa render, dan aksesibilitas.\n2. **Backend Engineer**: Endpoint, validasi, autentikasi, query database, dan keamanan data.\n3. **Kontrak API Lebih Dulu**: Disepakati sebelum coding, supaya frontend bisa memakai data tiruan sambil menunggu backend siap.\n\n### Standar Kontrak API\n1. **Method**: GET untuk membaca, POST untuk membuat, PUT/PATCH untuk mengubah, DELETE untuk menghapus.\n2. **Kode Status**: 200 OK, 201 Created, 400 Bad Request, 401 Unauthorized, 404 Not Found, 500 Server Error.\n3. **Format Error Konsisten**: Selalu kembalikan objek error dengan field code dan message.\n\n### Aturan Kolaborasi\nBranch per fitur &rarr; Pull Request &rarr; Code Review &rarr; baru Merge. Tidak ada push langsung ke branch main."
        ],
        [
            'lms-w6', 6, 'Pertemuan 6: Testing dan Usability',
            'Membuktikan produk bukan sekadar jalan, tetapi juga benar dan mudah dipakai, lewat piramida testing dan usability testing bersama pengguna asli.',
            json_encode([
                "Menyusun test case yang diturunkan langsung dari acceptance criteria di PRD",
                "Membedakan unit test, integration test, dan end-to-end test beserta porsi idealnya",
                "Menjalankan usability testing berbasis tugas dan mengukur hasilnya secara objektif"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Test case dan hasil eksekusinya, minimal 15 kasus uji",
                "Laporan usability testing bersama 5 responden di luar tim",
                "Backlog perbaikan yang sudah diurutkan berdasarkan severity"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Usability Testing 101 (Nielsen Norman Group)", "url" => "https://www.nngroup.com/articles/usability-testing-101/"],
                ["title" => "Pengujian End-to-End dengan Playwright", "url" => "https://playwright.dev/docs/intro"]
            ], JSON_UNESCAPED_UNICODE),
            "### Piramida Testing\n1. **Unit Test**: Menguji satu fungsi kecil, jumlahnya banyak dan jalannya cepat.\n2. **Integration Test**: Menguji beberapa modul yang saling berbicara, misalnya API dengan database.\n3. **End-to-End Test**: Menguji alur pengguna di browser sungguhan, jumlahnya sedikit tapi paling meyakinkan.\n\n### Usability Testing\n1. Lima responden sudah cukup untuk menangkap sebagian besar masalah utama.\n2. **Berbasis Tugas**: Beri tugas nyata, jangan beri petunjuk cara mengerjakannya.\n3. **Yang Diukur**: Tingkat keberhasilan tugas, waktu penyelesaian, jumlah error, dan titik kebingungan.\n4. **Severity**: Kategorikan temuan menjadi kritis, mayor, atau minor sebelum masuk backlog.\n\n### Definition of Done\nSebuah fitur baru dianggap selesai jika lolos test, lolos code review, dan tidak menurunkan hasil usability."
        ],
        [
            'lms-w7', 7, 'Pertemuan 7: Hosting, Domain, dan Deployment',
            'Menaikkan aplikasi dari laptop ke internet: membeli domain, mengatur DNS, mengamankan dengan HTTPS, dan merilis lewat pipeline otomatis.',
            json_encode([
                "Menjelaskan hubungan domain, DNS, hosting, dan sertifikat SSL dalam satu proses rilis",
                "Membedakan environment development, staging, dan production beserta pengelolaan rahasianya",
                "Menjalankan pipeline CI/CD dari commit sampai production, lengkap dengan rencana rollback"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Website tayang di domain publik dengan HTTPS aktif",
                "Pipeline CI/CD yang berjalan otomatis setiap ada commit baru",
                "Runbook deployment dan rollback satu halaman"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Apa itu DNS (Cloudflare Learning)", "url" => "https://www.cloudflare.com/learning/dns/what-is-dns/"],
                ["title" => "Dokumentasi Deployments (Vercel)", "url" => "https://vercel.com/docs/deployments"]
            ], JSON_UNESCAPED_UNICODE),
            "### Komponen Sebuah Rilis\n1. **Domain**: Nama alamat yang disewa dari registrar, misalnya .com, .id, atau .my.id.\n2. **DNS**: Penerjemah nama domain ke alamat IP, diatur lewat record A, CNAME, dan MX.\n3. **Hosting**: Tempat aplikasi berjalan, bisa shared hosting, VPS, atau platform PaaS.\n4. **SSL/HTTPS**: Sertifikat wajib. Tanpa itu browser menandai situs sebagai tidak aman.\n\n### Alur Deployment\nCommit &rarr; Build &rarr; Test Otomatis (CI) &rarr; Deploy ke Staging &rarr; Verifikasi &rarr; Deploy ke Production (CD).\n\n### Tiga Environment\n1. **Development**: Laptop masing-masing anggota tim.\n2. **Staging**: Semirip mungkin dengan production, dipakai untuk uji akhir sebelum rilis.\n3. **Production**: Dipakai pengguna asli. Semua rahasia disimpan di environment variable, tidak pernah di dalam kode.\n\n> **Aturan Wajib**: Siapkan cara kembali ke versi sebelumnya (*rollback plan*) sebelum menekan tombol rilis."
        ],
        [
            'lms-w8', 8, 'Pertemuan 8: Website Builder No-Code, Low-Code, dan Coding with AI',
            'Memilih jalur pembuatan website yang paling masuk akal untuk situasi tertentu, dan memakai AI sebagai alat bantu coding tanpa kehilangan kendali mutu.',
            json_encode([
                "Membandingkan jalur no-code, low-code, dan coding dari sisi biaya, kecepatan, dan batasannya",
                "Menentukan jalur yang tepat berdasarkan kompleksitas logika bisnis dan target waktu rilis",
                "Memakai AI untuk scaffolding, refactor, dan dokumentasi dengan disiplin review yang jelas"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Satu landing page versi no-code yang sudah tayang",
                "Tabel perbandingan tiga jalur: biaya, waktu kerja, batasan, dan risiko vendor lock-in",
                "Demo akhir dan presentasi produk tim di depan Guru/Instruktur"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Webflow University", "url" => "https://university.webflow.com/"],
                ["title" => "Panduan Claude Code", "url" => "https://docs.claude.com/en/docs/claude-code/overview"]
            ], JSON_UNESCAPED_UNICODE),
            "### Tiga Jalur Membangun Website\n1. **No-Code**: Merakit secara visual, misalnya Webflow, Framer, atau Wix. Paling cepat untuk landing page dan uji pasar.\n2. **Low-Code**: Visual ditambah sedikit logika atau kode, misalnya Bubble atau Retool.\n3. **Coding with AI**: Tetap menulis kode sendiri, dengan AI membantu scaffolding, refactor, penulisan test, dan dokumentasi.\n\n### Cara Memilih Jalur\n1. Butuh cepat memvalidasi pasar dan logikanya sederhana &rarr; pilih no-code.\n2. Ada alur data dan pembagian role pengguna tetapi tim kecil &rarr; pilih low-code.\n3. Logika bisnis unik dan butuh skala serta kepemilikan penuh &rarr; pilih coding dibantu AI.\n\n### Disiplin Memakai AI\n1. **Beri Konteks**: Sertakan PRD, kontrak API, dan standar kode tim di dalam prompt.\n2. **Selalu Review**: AI bisa salah, dan kode tetap menjadi tanggung jawab engineer.\n3. **Uji Sebelum Merge**: Hasil AI wajib lolos test yang sama dengan kode buatan manusia.\n4. **Waspadai Vendor Lock-in**: Sebelum memilih platform no-code, pastikan data masih bisa diekspor."
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

function seedLmsModulesOnly($pdo) {
    $allModules = [
        [
            'lms-w1', 1, 'Pertemuan 1: Pengenalan Website & Product Requirement Document',
            'Memahami anatomi website modern (client, server, database, domain) dan menerjemahkan ide produk menjadi PRD yang siap dikerjakan tim.',
            json_encode([
                "Menjelaskan cara kerja website: browser, siklus request-response, serta beda client-side dan server-side",
                "Membedakan jenis website (statis, dinamis, SPA, e-commerce) beserta konsekuensi teknisnya",
                "Menyusun PRD lengkap: problem statement, user persona, user story, dan acceptance criteria"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Dokumen PRD versi 1.0 yang disetujui Guru/Instruktur",
                "Daftar 2 User Persona dan minimal 8 User Story beserta Acceptance Criteria",
                "Logbook Harian Pertemuan 1"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Panduan Menulis PRD (Atlassian)", "url" => "https://www.atlassian.com/agile/product-management/requirements"],
                ["title" => "Learn Web Development (MDN)", "url" => "https://developer.mozilla.org/en-US/docs/Learn_web_development"]
            ], JSON_UNESCAPED_UNICODE),
            "### Anatomi Sebuah Website\n1. **Client (Browser)**: Merender HTML, CSS, dan JavaScript menjadi tampilan yang dilihat pengguna.\n2. **Server**: Memproses logika bisnis dan mengembalikan data atau halaman.\n3. **Database**: Menyimpan data permanen seperti user, produk, dan transaksi.\n4. **Domain & DNS**: Alamat yang menerjemahkan nama website ke alamat IP server.\n\n### Struktur PRD yang Dipakai Industri\n1. **Problem Statement**: Masalah nyata yang sudah divalidasi, bukan asumsi tim.\n2. **Goal & Success Metric**: Ukuran keberhasilan yang bisa dihitung.\n3. **User Persona**: Profil pengguna target beserta kebutuhan dan hambatannya.\n4. **User Story**: Format \"Sebagai [peran], saya ingin [aksi], agar [manfaat]\".\n5. **Acceptance Criteria**: Syarat sebuah story boleh dinyatakan selesai.\n6. **Out of Scope**: Hal yang sengaja tidak dikerjakan pada rilis ini.\n\n> **Catatan Guru**: Tolak PRD yang hanya berisi daftar fitur tanpa problem statement."
        ],
        [
            'lms-w2', 2, 'Pertemuan 2: Dasar UI/UX & Design Thinking',
            'Mengubah kebutuhan di PRD menjadi rancangan antarmuka yang mudah dipakai, lewat lima tahap Design Thinking dan prinsip dasar desain visual.',
            json_encode([
                "Menjalankan lima tahap Design Thinking: empathize, define, ideate, prototype, test",
                "Membedakan peran UX (alur dan kemudahan) dengan UI (visual dan komponen)",
                "Membuat wireframe sampai prototype interaktif yang siap diserahkan ke frontend"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Wireframe low-fidelity untuk 5 halaman utama",
                "Mockup high-fidelity dan prototype interaktif di Figma",
                "Mini design system: palet warna, skala tipografi, dan komponen tombol"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Design Thinking 101 (Nielsen Norman Group)", "url" => "https://www.nngroup.com/articles/design-thinking/"],
                ["title" => "Material Design 3 Foundations", "url" => "https://m3.material.io/foundations"]
            ], JSON_UNESCAPED_UNICODE),
            "### Lima Tahap Design Thinking\n1. **Empathize**: Wawancara calon pengguna, catat kata-kata aslinya.\n2. **Define**: Rumuskan satu kalimat masalah yang paling layak dipecahkan.\n3. **Ideate**: Kumpulkan banyak alternatif solusi dulu, saring belakangan.\n4. **Prototype**: Buat versi murah yang bisa diklik, bukan versi sempurna.\n5. **Test**: Uji ke pengguna asli, perbaiki, lalu ulangi.\n\n### Prinsip Dasar UI\n1. **Hierarki Visual**: Ukuran, ketebalan, dan jarak menentukan apa yang dibaca lebih dulu.\n2. **Konsistensi**: Komponen yang sama berperilaku sama di semua halaman.\n3. **White Space**: Ruang kosong adalah alat baca, bukan ruang terbuang.\n4. **Kontras & Aksesibilitas**: Rasio kontras teks minimal 4.5:1 sesuai standar WCAG AA.\n\n### Alur Kerja Desain\nWireframe (low-fidelity) &rarr; Mockup (high-fidelity) &rarr; Prototype interaktif &rarr; Handoff ke Frontend Engineer."
        ],
        [
            'lms-w3', 3, 'Pertemuan 3: Dasar HTML dan CSS',
            'Menerjemahkan mockup menjadi halaman nyata: struktur semantik dengan HTML dan tata letak responsif dengan CSS, tanpa bantuan framework.',
            json_encode([
                "Menyusun struktur halaman dengan elemen semantik dan hierarki heading yang benar",
                "Menguasai box model, selector, dan cascade agar aturan gaya tidak saling menimpa",
                "Membangun tata letak responsif memakai Flexbox, Grid, dan media query mobile-first"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Satu halaman landing statis responsif memakai HTML dan CSS murni",
                "Repository Git berisi commit harian dengan pesan yang jelas",
                "Checklist uji tampilan pada lebar 360px, 768px, dan 1440px"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Referensi HTML (MDN)", "url" => "https://developer.mozilla.org/en-US/docs/Web/HTML"],
                ["title" => "Learn CSS (web.dev)", "url" => "https://web.dev/learn/css"]
            ], JSON_UNESCAPED_UNICODE),
            "### HTML: Struktur dan Semantik\n1. **Elemen semantik**: header, nav, main, section, article, footer.\n2. **Form dan input**: pasangan label-input, tipe input, dan validasi bawaan browser.\n3. **Aksesibilitas**: atribut alt pada gambar serta urutan heading h1 sampai h6 yang runtut.\n\n### CSS: Tampilan dan Tata Letak\n1. **Selector & Cascade**: Spesifisitas menentukan aturan mana yang menang.\n2. **Box Model**: content, padding, border, margin.\n3. **Flexbox**: Tata letak satu dimensi, untuk baris atau kolom.\n4. **Grid**: Tata letak dua dimensi, untuk kerangka halaman.\n5. **Responsive**: Unit relatif (rem, %, vw) digabung media query, dikerjakan mobile-first.\n\n> **Aturan praktik**: Dilarang memakai framework CSS pada pertemuan ini. Tujuannya melatih dasar, bukan mengejar kecepatan."
        ],
        [
            'lms-w4', 4, 'Pertemuan 4: Monolith dan Fullstack Website',
            'Menentukan bentuk arsitektur aplikasi tim: kenapa monolith biasanya pilihan paling masuk akal untuk tim kecil, dan bagaimana lapisan fullstack disusun.',
            json_encode([
                "Membandingkan monolith, modular monolith, dan microservices beserta biaya operasionalnya",
                "Memetakan lapisan aplikasi fullstack: presentation, business logic, data, dan API",
                "Merancang skema database dan memilih tech stack dengan alasan yang bisa dipertahankan"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Diagram arsitektur sistem satu halaman",
                "Skema database (ERD) minimal 4 tabel beserta relasinya",
                "Dokumen keputusan tech stack beserta alasan dan risikonya"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Monolith First (Martin Fowler)", "url" => "https://martinfowler.com/bliki/MonolithFirst.html"],
                ["title" => "Full Stack Roadmap", "url" => "https://roadmap.sh/full-stack"]
            ], JSON_UNESCAPED_UNICODE),
            "### Pilihan Arsitektur\n1. **Monolith**: Frontend, backend, dan database dalam satu basis kode dan satu proses deploy.\n2. **Modular Monolith**: Tetap satu deploy, tetapi modul dipisah rapi per domain.\n3. **Microservices**: Banyak servis kecil yang deploy sendiri-sendiri, biaya operasionalnya tinggi.\n\n### Kenapa Mulai dari Monolith?\nTim kecil dengan kebutuhan yang masih sering berubah paling murah dilayani monolith. Pecah menjadi servis terpisah hanya setelah batas antar domain terbukti stabil dan beban trafik benar-benar menuntutnya.\n\n### Lapisan Aplikasi Fullstack\n1. **Presentation Layer**: Halaman dan komponen antarmuka.\n2. **Business Logic Layer**: Aturan main aplikasi, misalnya syarat sebuah checkout boleh diproses.\n3. **Data Layer**: Model, query, dan migrasi database.\n4. **API**: Kontrak yang menyambungkan frontend dan backend, umumnya REST berformat JSON."
        ],
        [
            'lms-w5', 5, 'Pertemuan 5: Frontend dan Backend Engineer',
            'Membagi pekerjaan nyata antara frontend dan backend, lalu menyepakati kontrak API lebih dulu supaya kedua sisi bisa berjalan paralel.',
            json_encode([
                "Menjelaskan tanggung jawab spesifik Frontend Engineer dan Backend Engineer dalam satu fitur",
                "Menyusun kontrak API: endpoint, method, request body, response, dan kode status",
                "Menjalankan alur kolaborasi Git: branch per fitur, pull request, dan code review"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Dokumen kontrak API berisi minimal 6 endpoint",
                "Satu fitur utuh end-to-end: form di frontend, API di backend, data tersimpan di database",
                "Catatan hasil code review beserta perbaikan yang sudah dikerjakan"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Frontend Roadmap", "url" => "https://roadmap.sh/frontend"],
                ["title" => "Backend Roadmap", "url" => "https://roadmap.sh/backend"]
            ], JSON_UNESCAPED_UNICODE),
            "### Pembagian Peran\n1. **Frontend Engineer**: State antarmuka, integrasi API, performa render, dan aksesibilitas.\n2. **Backend Engineer**: Endpoint, validasi, autentikasi, query database, dan keamanan data.\n3. **Kontrak API Lebih Dulu**: Disepakati sebelum coding, supaya frontend bisa memakai data tiruan sambil menunggu backend siap.\n\n### Standar Kontrak API\n1. **Method**: GET untuk membaca, POST untuk membuat, PUT/PATCH untuk mengubah, DELETE untuk menghapus.\n2. **Kode Status**: 200 OK, 201 Created, 400 Bad Request, 401 Unauthorized, 404 Not Found, 500 Server Error.\n3. **Format Error Konsisten**: Selalu kembalikan objek error dengan field code dan message.\n\n### Aturan Kolaborasi\nBranch per fitur &rarr; Pull Request &rarr; Code Review &rarr; baru Merge. Tidak ada push langsung ke branch main."
        ],
        [
            'lms-w6', 6, 'Pertemuan 6: Testing dan Usability',
            'Membuktikan produk bukan sekadar jalan, tetapi juga benar dan mudah dipakai, lewat piramida testing dan usability testing bersama pengguna asli.',
            json_encode([
                "Menyusun test case yang diturunkan langsung dari acceptance criteria di PRD",
                "Membedakan unit test, integration test, dan end-to-end test beserta porsi idealnya",
                "Menjalankan usability testing berbasis tugas dan mengukur hasilnya secara objektif"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Test case dan hasil eksekusinya, minimal 15 kasus uji",
                "Laporan usability testing bersama 5 responden di luar tim",
                "Backlog perbaikan yang sudah diurutkan berdasarkan severity"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Usability Testing 101 (Nielsen Norman Group)", "url" => "https://www.nngroup.com/articles/usability-testing-101/"],
                ["title" => "Pengujian End-to-End dengan Playwright", "url" => "https://playwright.dev/docs/intro"]
            ], JSON_UNESCAPED_UNICODE),
            "### Piramida Testing\n1. **Unit Test**: Menguji satu fungsi kecil, jumlahnya banyak dan jalannya cepat.\n2. **Integration Test**: Menguji beberapa modul yang saling berbicara, misalnya API dengan database.\n3. **End-to-End Test**: Menguji alur pengguna di browser sungguhan, jumlahnya sedikit tapi paling meyakinkan.\n\n### Usability Testing\n1. Lima responden sudah cukup untuk menangkap sebagian besar masalah utama.\n2. **Berbasis Tugas**: Beri tugas nyata, jangan beri petunjuk cara mengerjakannya.\n3. **Yang Diukur**: Tingkat keberhasilan tugas, waktu penyelesaian, jumlah error, dan titik kebingungan.\n4. **Severity**: Kategorikan temuan menjadi kritis, mayor, atau minor sebelum masuk backlog.\n\n### Definition of Done\nSebuah fitur baru dianggap selesai jika lolos test, lolos code review, dan tidak menurunkan hasil usability."
        ],
        [
            'lms-w7', 7, 'Pertemuan 7: Hosting, Domain, dan Deployment',
            'Menaikkan aplikasi dari laptop ke internet: membeli domain, mengatur DNS, mengamankan dengan HTTPS, dan merilis lewat pipeline otomatis.',
            json_encode([
                "Menjelaskan hubungan domain, DNS, hosting, dan sertifikat SSL dalam satu proses rilis",
                "Membedakan environment development, staging, dan production beserta pengelolaan rahasianya",
                "Menjalankan pipeline CI/CD dari commit sampai production, lengkap dengan rencana rollback"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Website tayang di domain publik dengan HTTPS aktif",
                "Pipeline CI/CD yang berjalan otomatis setiap ada commit baru",
                "Runbook deployment dan rollback satu halaman"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Apa itu DNS (Cloudflare Learning)", "url" => "https://www.cloudflare.com/learning/dns/what-is-dns/"],
                ["title" => "Dokumentasi Deployments (Vercel)", "url" => "https://vercel.com/docs/deployments"]
            ], JSON_UNESCAPED_UNICODE),
            "### Komponen Sebuah Rilis\n1. **Domain**: Nama alamat yang disewa dari registrar, misalnya .com, .id, atau .my.id.\n2. **DNS**: Penerjemah nama domain ke alamat IP, diatur lewat record A, CNAME, dan MX.\n3. **Hosting**: Tempat aplikasi berjalan, bisa shared hosting, VPS, atau platform PaaS.\n4. **SSL/HTTPS**: Sertifikat wajib. Tanpa itu browser menandai situs sebagai tidak aman.\n\n### Alur Deployment\nCommit &rarr; Build &rarr; Test Otomatis (CI) &rarr; Deploy ke Staging &rarr; Verifikasi &rarr; Deploy ke Production (CD).\n\n### Tiga Environment\n1. **Development**: Laptop masing-masing anggota tim.\n2. **Staging**: Semirip mungkin dengan production, dipakai untuk uji akhir sebelum rilis.\n3. **Production**: Dipakai pengguna asli. Semua rahasia disimpan di environment variable, tidak pernah di dalam kode.\n\n> **Aturan Wajib**: Siapkan cara kembali ke versi sebelumnya (*rollback plan*) sebelum menekan tombol rilis."
        ],
        [
            'lms-w8', 8, 'Pertemuan 8: Website Builder No-Code, Low-Code, dan Coding with AI',
            'Memilih jalur pembuatan website yang paling masuk akal untuk situasi tertentu, dan memakai AI sebagai alat bantu coding tanpa kehilangan kendali mutu.',
            json_encode([
                "Membandingkan jalur no-code, low-code, dan coding dari sisi biaya, kecepatan, dan batasannya",
                "Menentukan jalur yang tepat berdasarkan kompleksitas logika bisnis dan target waktu rilis",
                "Memakai AI untuk scaffolding, refactor, dan dokumentasi dengan disiplin review yang jelas"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                "Satu landing page versi no-code yang sudah tayang",
                "Tabel perbandingan tiga jalur: biaya, waktu kerja, batasan, dan risiko vendor lock-in",
                "Demo akhir dan presentasi produk tim di depan Guru/Instruktur"
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                ["title" => "Webflow University", "url" => "https://university.webflow.com/"],
                ["title" => "Panduan Claude Code", "url" => "https://docs.claude.com/en/docs/claude-code/overview"]
            ], JSON_UNESCAPED_UNICODE),
            "### Tiga Jalur Membangun Website\n1. **No-Code**: Merakit secara visual, misalnya Webflow, Framer, atau Wix. Paling cepat untuk landing page dan uji pasar.\n2. **Low-Code**: Visual ditambah sedikit logika atau kode, misalnya Bubble atau Retool.\n3. **Coding with AI**: Tetap menulis kode sendiri, dengan AI membantu scaffolding, refactor, penulisan test, dan dokumentasi.\n\n### Cara Memilih Jalur\n1. Butuh cepat memvalidasi pasar dan logikanya sederhana &rarr; pilih no-code.\n2. Ada alur data dan pembagian role pengguna tetapi tim kecil &rarr; pilih low-code.\n3. Logika bisnis unik dan butuh skala serta kepemilikan penuh &rarr; pilih coding dibantu AI.\n\n### Disiplin Memakai AI\n1. **Beri Konteks**: Sertakan PRD, kontrak API, dan standar kode tim di dalam prompt.\n2. **Selalu Review**: AI bisa salah, dan kode tetap menjadi tanggung jawab engineer.\n3. **Uji Sebelum Merge**: Hasil AI wajib lolos test yang sama dengan kode buatan manusia.\n4. **Waspadai Vendor Lock-in**: Sebelum memilih platform no-code, pastikan data masih bisa diekspor."
        ]
    ];

    $pdo->exec("DELETE FROM lms_modules");
    $stmt = $pdo->prepare("
        INSERT INTO lms_modules (id, week_number, title, summary, objectives, deliverables, external_links, content, is_published)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    foreach ($allModules as $m) {
        $stmt->execute($m);
    }
}
?>
