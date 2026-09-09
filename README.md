# Web Development - Program Fast Track

Aplikasi manajemen proyek dan pembelajaran metodologi **Scrum** berbasis **Native PHP 8+ & SQLite**, dirancang dengan identitas visual **VINIX7** (**Deep Royal Blue & Yellow Accent**, dengan **teks hitam pekat** yang nyaman dibaca dan kontras tinggi).

Mengadopsi format **Program Fast Track PT VINIX SEVEN AURUM**.

## 🎨 Tema & Desain Identitas Brand
- **Logo**: Logo **VINIX7** (`logo/LOGO VINIX.png`).
- **Warna Utama (Primary)**: **Deep Royal Blue** (`#0B3C95`) pada header, tombol primer, dan navigasi.
- **Warna Aksen (Accent)**: **Vibrant Gold/Yellow** (`#FFB800`) untuk penanda aktif, highlight, dan aksen brand.
- **Warna Teks (Text)**: **Hitam Solid / True Black** (`#000000` / `#111827`) pada seluruh paragraf, judul, tabel, dan kartu tugas untuk keterbacaan optimal saat diproyeksikan di kelas.

---

## 👥 4 Peran Pengguna (Roles)
1. **Super Admin (Guru / Instruktur)**: Membuat kelompok, menetapkan peran, mengirim undangan WhatsApp (via Gateway API / `wa.me`), memantau semua tim, dan memeriksa logbook.
2. **Product Manager (Siswa PM)**: Menulis dokumen PRD terpandu dan melakukan *One-Click Breakdown* user story menjadi tiket tugas.
3. **Backend Engineer (Siswa BE)**: Mengambil tiket tugas BE (API/Database), mencatat logbook harian.
4. **Frontend Engineer (Siswa FE)**: Mengambil tiket tugas FE (UI/API Integration), mencatat logbook harian.
- **Simulation Bar**: Pilihan cepat di bar atas untuk beralih sudut pandang peran dalam 1 klik saat mengajar di kelas.

---

## 🚀 Fitur & Modul Utama

1. **📌 Scrum Kanban Board (`board.php`)**:
   - 5 Kolom Scrum: *Product Backlog*, *Sprint Backlog*, *In Progress*, *In Review/QA*, *Done*.
   - Drag-and-drop interaktif & tombol geser cepat (kiri/kanan).
   - Filter peran: *Semua*, *Frontend View*, *Backend View*, *PM Specs*.
   - Bobot *Story Points* (deret Fibonacci 1, 2, 3, 5, 8).
   - Indikator peringatan dependensi (tiket FE menunggu BE).

2. **📄 Modul Dokumen PRD (`prd.php`)**:
   - Template spesifikasi produk lengkap: Latar Belakang (Problem Statement), Target Pengguna (Persona), User Stories, Acceptance Criteria (DoD), dan Catatan Kontrak API FE-BE.
   - **One-Click Breakdown**: Mengonversi butir User Story langsung menjadi kartu tiket di Scrum Board.

3. **📖 Log Book Kegiatan Siswa (`logbook.php`)**:
   - Format standar **Program Fast Track PT VINIX SEVEN AURUM**.
   - **Tab Contoh Panduan**: 3 contoh nyata pengisian log book yang baik untuk PM, BE, dan FE.
   - Form input harian, link bukti tugas (GitHub/Figma/Docs), dan catatan blocker.
   - Catatan review & paraf evaluasi Guru.
   - **Mode Cetak / PDF**: Tampilan lembar kertas formal siap cetak langsung (`window.print()`).

4. **🎓 Materi Pembelajaran LMS (`lms.php`)**:
   - Kurikulum terstruktur 4 pertemuan (Pertemuan 1: Fondasi Scrum & PRD, Pertemuan 2: Planning & Story Points, Pertemuan 3: Eksekusi & Integrasi API, Pertemuan 4: Testing, Review & Retrospective).

5. **📊 Laporan Otomatis Mingguan (`reports.php`)**:
   - Menghitung otomatis: *Sprint Velocity*, perbandingan Story Points selesai vs rencana, progres peran FE vs BE, tingkat kepatuhan logbook, dan deteksi blocker.
   - Generator format WhatsApp otomatis siap kirim.

6. **⚙️ Panel Super Admin (`admin.php`)**:
   - Guru membuatkan kelompok baru.
   - Pendaftaran siswa & penugasan peran.
   - Tombol **"Undang WA"** (mendukung 1-Click direct `wa.me` dan API Gateway Fonnte/Wablas).

---

## 💻 Cara Menjalankan Aplikasi

### 1. Menjalankan Langsung dengan PHP (Paling Cepat & Mudah)
Pastikan PHP 8.0+ terpasang di komputer/laptop Anda:
```bash
php -S localhost:8000
```
Buka browser di **http://localhost:8000**. Database SQLite (`scrumvibe.db`) akan terbuat dan terisi data awal secara otomatis!

---

### 2. Menjalankan Menggunakan Docker Container
Jika ingin menjalankan dalam kontainer Docker:
```bash
docker-compose up --build -d
```
Aplikasi akan aktif di **http://localhost:8080**.

---

### 3. Deploy ke Vercel
Proyek telah dilengkapi berkas konfigurasi `vercel.json` menggunakan runtime PHP:
```bash
vercel --prod
```
Atau hubungkan repositori Git ini ke dashboard Vercel Anda.
