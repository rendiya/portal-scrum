<?php
// api.php - Central JSON API handler for ScrumVibe AJAX requests
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/whatsapp.php';

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];

try {
    switch ($action) {
        // 1. Update Task Status
        case 'update_task_status':
            $taskId = $input['id'] ?? '';
            $status = $input['status'] ?? '';
            if (!$taskId || !$status) {
                echo json_encode(['success' => false, 'error' => 'Task ID dan Status wajib diisi']);
                exit;
            }
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$status, $now, $taskId]);
            echo json_encode(['success' => true, 'message' => 'Status tugas berhasil diperbarui']);
            break;

        // 2. Create Task
        case 'create_task':
            $taskId = 'task-' . round(microtime(true) * 1000);
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                INSERT INTO tasks (id, team_id, prd_id, title, description, status, role_category, story_points, priority, assignee_id, assignee_name, sprint_number, dependency_task_id, dependency_task_title, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $taskId,
                $input['team_id'] ?? 'team-1',
                $input['prd_id'] ?? null,
                $input['title'] ?? 'Tugas Baru',
                $input['description'] ?? '',
                $input['status'] ?? 'sprint_backlog',
                $input['role_category'] ?? 'frontend',
                (int)($input['story_points'] ?? 3),
                $input['priority'] ?? 'medium',
                !empty($input['assignee_id']) ? $input['assignee_id'] : null,
                !empty($input['assignee_name']) ? $input['assignee_name'] : null,
                (int)($input['sprint_number'] ?? 1),
                !empty($input['dependency_task_id']) ? $input['dependency_task_id'] : null,
                !empty($input['dependency_task_title']) ? $input['dependency_task_title'] : null,
                $now,
                $now
            ]);
            echo json_encode(['success' => true, 'message' => 'Tugas berhasil dibuat', 'id' => $taskId]);
            break;

        // 2b. Claim Task / Self-Assign by Student
        case 'claim_task':
            $taskId = $input['id'] ?? '';
            $assigneeId = $input['assignee_id'] ?? null;
            $assigneeName = $input['assignee_name'] ?? null;
            if (!$taskId) {
                echo json_encode(['success' => false, 'error' => 'Task ID wajib diisi']);
                exit;
            }
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("UPDATE tasks SET assignee_id = ?, assignee_name = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$assigneeId, $assigneeName, $now, $taskId]);
            echo json_encode(['success' => true, 'message' => 'Tugas berhasil diambil oleh ' . ($assigneeName ?: 'Siswa')]);
            break;

        // 2c. Update Task (Edit all fields by Student / Guru)
        case 'update_task':
            $taskId = $input['id'] ?? '';
            if (!$taskId) {
                echo json_encode(['success' => false, 'error' => 'Task ID wajib diisi']);
                exit;
            }
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                UPDATE tasks SET
                    title = ?,
                    description = ?,
                    status = ?,
                    role_category = ?,
                    story_points = ?,
                    priority = ?,
                    assignee_id = ?,
                    assignee_name = ?,
                    dependency_task_id = ?,
                    dependency_task_title = ?,
                    updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $input['title'] ?? 'Tugas',
                $input['description'] ?? '',
                $input['status'] ?? 'sprint_backlog',
                $input['role_category'] ?? 'frontend',
                (int)($input['story_points'] ?? 3),
                $input['priority'] ?? 'medium',
                !empty($input['assignee_id']) ? $input['assignee_id'] : null,
                !empty($input['assignee_name']) ? $input['assignee_name'] : null,
                !empty($input['dependency_task_id']) ? $input['dependency_task_id'] : null,
                !empty($input['dependency_task_title']) ? $input['dependency_task_title'] : null,
                $now,
                $taskId
            ]);
            echo json_encode(['success' => true, 'message' => 'Tiket tugas berhasil diperbarui']);
            break;

        // 2d. Get Single Task Details
        case 'get_task':
            $taskId = $_GET['id'] ?? $input['id'] ?? '';
            if (!$taskId) {
                echo json_encode(['success' => false, 'error' => 'Task ID wajib diisi']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? LIMIT 1");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($task) {
                echo json_encode(['success' => true, 'task' => $task]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Tugas tidak ditemukan']);
            }
            break;

        // 3. Delete Task
        case 'delete_task':
            $taskId = $input['id'] ?? $_GET['id'] ?? '';
            if (!$taskId) {
                echo json_encode(['success' => false, 'error' => 'Task ID wajib diisi']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            echo json_encode(['success' => true, 'message' => 'Tugas berhasil dihapus']);
            break;

        // 4. Breakdown PRD into Tasks
        case 'breakdown_prd':
            $prdId = $input['prd_id'] ?? '';
            $teamId = $input['team_id'] ?? 'team-1';
            $stories = $input['user_stories'] ?? [];

            if (empty($stories)) {
                echo json_encode(['success' => false, 'error' => 'Tidak ada user story untuk di-breakdown']);
                exit;
            }

            $now = date('Y-m-d H:i:s');
            $createdCount = 0;

            foreach ($stories as $st) {
                $roleCat = 'fullstack';
                if (($st['role'] ?? '') === 'FE') $roleCat = 'frontend';
                elseif (($st['role'] ?? '') === 'BE') $roleCat = 'backend';

                $taskId = 'task-prd-' . round(microtime(true) * 1000) . '-' . substr(md5(rand()), 0, 4);
                $title = strlen($st['story']) > 80 ? substr($st['story'], 0, 80) . '...' : $st['story'];
                $desc = "**User Story:**\n" . $st['story'] . "\n\n**Acceptance Criteria:**\n" . ($st['acceptanceCriteria'] ?? 'Mengikuti standar DoD tim.');

                $stmt = $pdo->prepare("
                    INSERT INTO tasks (id, team_id, prd_id, title, description, status, role_category, story_points, priority, sprint_number, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'sprint_backlog', ?, ?, 'medium', 1, ?, ?)
                ");
                $stmt->execute([$taskId, $teamId, $prdId, $title, $desc, $roleCat, (int)($st['points'] ?? 3), $now, $now]);
                $createdCount++;
            }

            if ($prdId) {
                $stmt = $pdo->prepare("UPDATE prds SET status = 'in_development', updated_at = ? WHERE id = ?");
                $stmt->execute([$now, $prdId]);
            }

            echo json_encode(['success' => true, 'message' => "Berhasil memecah {$createdCount} User Story menjadi tiket di Scrum Board!"]);
            break;

        // 5. Save PRD
        case 'save_prd':
            $prdId = $input['id'] ?? 'prd-' . round(microtime(true) * 1000);
            $now = date('Y-m-d H:i:s');
            $teamId = $input['team_id'] ?? 'team-1';
            $title = trim($input['title'] ?? '');
            $storiesJson = is_string($input['user_stories']) ? $input['user_stories'] : json_encode($input['user_stories'] ?? []);

            $stmt = $pdo->prepare("
                INSERT INTO prds (id, team_id, title, version, status, problem_statement, user_personas, user_stories, technical_notes, feedback_teacher, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT(id) DO UPDATE SET
                    title = excluded.title,
                    version = excluded.version,
                    status = excluded.status,
                    problem_statement = excluded.problem_statement,
                    user_personas = excluded.user_personas,
                    user_stories = excluded.user_stories,
                    technical_notes = excluded.technical_notes,
                    feedback_teacher = excluded.feedback_teacher,
                    updated_at = excluded.updated_at
            ");
            $stmt->execute([
                $prdId,
                $teamId,
                $title ?: 'PRD Proyek Mahasiswa',
                $input['version'] ?? '1.0',
                $input['status'] ?? 'draft',
                $input['problem_statement'] ?? '',
                $input['user_personas'] ?? '',
                $storiesJson,
                $input['technical_notes'] ?? '',
                $input['feedback_teacher'] ?? '',
                $now,
                $now
            ]);

            // Sinkronkan judul proyek mahasiswa ke tabel teams
            if (!empty($title)) {
                $stmtTeam = $pdo->prepare("UPDATE teams SET project_title = ? WHERE id = ?");
                $stmtTeam->execute([$title, $teamId]);
            }

            echo json_encode(['success' => true, 'message' => 'PRD dan Judul Proyek berhasil disimpan!']);
            break;

        // 5b. Update Project Title (Diisi Langsung oleh Mahasiswa di PRD)
        case 'update_project_title':
            $teamId = trim($input['team_id'] ?? '');
            $title = trim($input['title'] ?? '');
            if (!$teamId) {
                echo json_encode(['success' => false, 'error' => 'ID Kelompok tidak valid']);
                exit;
            }
            if (empty($title)) {
                echo json_encode(['success' => false, 'error' => 'Judul proyek tidak boleh kosong']);
                exit;
            }

            // Update teams
            $stmt = $pdo->prepare("UPDATE teams SET project_title = ? WHERE id = ?");
            $stmt->execute([$title, $teamId]);

            // Update or insert PRD
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("SELECT id FROM prds WHERE team_id = ? LIMIT 1");
            $stmt->execute([$teamId]);
            $existingPrd = $stmt->fetch();
            if ($existingPrd) {
                $stmtPrd = $pdo->prepare("UPDATE prds SET title = ?, updated_at = ? WHERE id = ?");
                $stmtPrd->execute([$title, $now, $existingPrd['id']]);
            } else {
                $prdId = 'prd-' . $teamId;
                $stmtPrd = $pdo->prepare("INSERT INTO prds (id, team_id, title, version, status, problem_statement, user_personas, user_stories, technical_notes, feedback_teacher, created_at, updated_at) VALUES (?, ?, ?, '1.0', 'draft', '', '', '[]', '', '', ?, ?)");
                $stmtPrd->execute([$prdId, $teamId, $title, $now, $now]);
            }

            echo json_encode(['success' => true, 'message' => 'Judul proyek berhasil disimpan oleh mahasiswa!']);
            break;

        // 6. Add Logbook Entry
        case 'add_logbook':
            $logId = 'log-' . round(microtime(true) * 1000);
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                INSERT INTO logbooks (id, team_id, student_id, student_name, role, university, major, week_number, entry_date, description, proof_link, blockers, teacher_notes, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', 'submitted', ?)
            ");
            $stmt->execute([
                $logId,
                $input['team_id'] ?? 'team-1',
                $input['student_id'] ?? 'mem-pm-1',
                $input['student_name'] ?? 'Siswa',
                $input['role'] ?? 'siswa',
                $input['university'] ?? '',
                $input['major'] ?? '',
                (int)($input['week_number'] ?? 1),
                $input['entry_date'] ?? date('j F Y'),
                $input['description'] ?? '',
                $input['proof_link'] ?? '',
                $input['blockers'] ?? '',
                $now
            ]);
            echo json_encode(['success' => true, 'message' => 'Logbook berhasil disimpan']);
            break;

        // 7. Teacher Review Logbook
        case 'review_logbook':
            $logId = $input['id'] ?? '';
            $teacherNotes = $input['teacher_notes'] ?? '';
            $stmt = $pdo->prepare("UPDATE logbooks SET teacher_notes = ?, status = 'reviewed' WHERE id = ?");
            $stmt->execute([$teacherNotes, $logId]);
            echo json_encode(['success' => true, 'message' => 'Review guru berhasil disimpan']);
            break;

        // 7b. Batch Teacher Review for Student Week
        case 'review_logbook_week':
            $studentId = $input['student_id'] ?? '';
            $week = (int)($input['week_number'] ?? 1);
            $teacherNotes = $input['teacher_notes'] ?? '';
            $stmt = $pdo->prepare("UPDATE logbooks SET teacher_notes = ?, status = 'reviewed' WHERE student_id = ? AND week_number = ?");
            $stmt->execute([$teacherNotes, $studentId, $week]);
            echo json_encode(['success' => true, 'message' => 'Paraf & catatan review guru pertemuan ini berhasil disimpan']);
            break;

        // 7c. Delete Logbook Entry
        case 'delete_logbook':
            $logId = $input['id'] ?? '';
            if (!$logId) {
                echo json_encode(['success' => false, 'error' => 'ID catatan tidak valid']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM logbooks WHERE id = ?");
            $stmt->execute([$logId]);
            echo json_encode(['success' => true, 'message' => 'Catatan logbook berhasil dihapus']);
            break;

        // 8. WhatsApp Sending
        case 'send_whatsapp':
            $phone = $input['phone'] ?? '';
            $message = $input['message'] ?? '';
            $useGateway = !empty($input['use_gateway']);

            if (!$phone || !$message) {
                echo json_encode(['success' => false, 'error' => 'Nomor HP dan pesan wajib diisi']);
                exit;
            }

            if ($useGateway) {
                $res = sendViaWaGateway($pdo, $phone, $message);
                echo json_encode($res);
            } else {
                $waMeLink = generateWaMeLink($phone, $message);
                echo json_encode(['success' => true, 'link' => $waMeLink]);
            }
            break;

        // 9. Create Team
        case 'create_team':
            $teamId = 'team-' . round(microtime(true) * 1000);
            $now = date('Y-m-d H:i:s');
            $teamName = trim($input['name'] ?? 'Kelompok Baru');
            $projectTitle = trim($input['project_title'] ?? '') ?: 'Belum Ditentukan (Diisi di PRD)';
            $description = trim($input['description'] ?? '');

            $stmt = $pdo->prepare("INSERT INTO teams (id, name, project_title, description, sprint_number, created_at) VALUES (?, ?, ?, ?, 1, ?)");
            $stmt->execute([
                $teamId,
                $teamName,
                $projectTitle,
                $description,
                $now
            ]);

            // Inisialisasi dokumen PRD kosong untuk kelompok baru agar langsung dapat diisi oleh mahasiswa
            $prdId = 'prd-' . $teamId;
            $stmtPrd = $pdo->prepare("
                INSERT INTO prds (id, team_id, title, version, status, problem_statement, user_personas, user_stories, technical_notes, feedback_teacher, created_at, updated_at)
                VALUES (?, ?, '', '1.0', 'draft', '', '', '[]', '', '', ?, ?)
            ");
            $stmtPrd->execute([
                $prdId,
                $teamId,
                $now,
                $now
            ]);

            echo json_encode(['success' => true, 'message' => 'Kelompok berhasil dibuat. Mahasiswa dapat mengisi judul proyek di modul PRD.', 'team_id' => $teamId]);
            break;

        // 9b. Delete Team
        case 'delete_team':
            $teamId = trim($input['id'] ?? $_GET['id'] ?? '');
            if (!$teamId) {
                echo json_encode(['success' => false, 'error' => 'ID Kelompok tidak ditemukan']);
                exit;
            }

            // Unassign members from this team (preserve student accounts)
            $stmt = $pdo->prepare("UPDATE members SET team_id = NULL WHERE team_id = ?");
            $stmt->execute([$teamId]);

            // Clean up tasks, prds, logbooks belonging to this team
            $pdo->prepare("DELETE FROM tasks WHERE team_id = ?")->execute([$teamId]);
            $pdo->prepare("DELETE FROM prds WHERE team_id = ?")->execute([$teamId]);
            $pdo->prepare("DELETE FROM logbooks WHERE team_id = ?")->execute([$teamId]);

            // Delete the team record
            $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
            $stmt->execute([$teamId]);

            // If active session team was this team, reassign to another existing team
            if (session_status() === PHP_SESSION_NONE) session_start();
            if (($_SESSION['scrumvibe_team_id'] ?? '') === $teamId) {
                $rem = $pdo->query("SELECT id FROM teams ORDER BY created_at DESC LIMIT 1")->fetch();
                $_SESSION['scrumvibe_team_id'] = $rem['id'] ?? 'team-1';
            }

            echo json_encode(['success' => true, 'message' => 'Kelompok berhasil dihapus! Siswa di dalamnya kini berstatus belum ada kelompok.']);
            break;

        // 10. Register Student
        case 'add_member':
            $memId = 'mem-' . round(microtime(true) * 1000) . '-' . substr(md5(rand()), 0, 4);
            $token = 'ft-' . bin2hex(random_bytes(6));
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                INSERT INTO members (id, name, role, team_id, phone, email, university, major, token, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $memId,
                $input['name'] ?? 'Siswa',
                'siswa',
                $input['team_id'] ?? 'team-1',
                $input['phone'] ?? '',
                $input['email'] ?? '',
                $input['university'] ?? '',
                $input['major'] ?? '',
                $token,
                $now
            ]);
            echo json_encode(['success' => true, 'message' => 'Siswa berhasil didaftarkan']);
            break;

        // 11. Delete Member
        case 'delete_member':
            $memId = $input['id'] ?? $_GET['id'] ?? '';
            $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
            $stmt->execute([$memId]);
            echo json_encode(['success' => true, 'message' => 'Anggota berhasil dihapus']);
            break;

        // 11b. Update Member & Group Assignment
        case 'update_member':
            $memId = trim($input['id'] ?? '');
            if (!$memId) {
                echo json_encode(['success' => false, 'error' => 'ID Anggota tidak ditemukan']);
                exit;
            }

            // Fetch existing member to preserve fields not provided
            $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
            $stmt->execute([$memId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                echo json_encode(['success' => false, 'error' => 'Data anggota tidak ditemukan']);
                exit;
            }

            $name = isset($input['name']) && trim($input['name']) !== '' ? trim($input['name']) : $existing['name'];
            $phone = isset($input['phone']) ? trim($input['phone']) : $existing['phone'];
            $teamId = array_key_exists('team_id', $input) ? (trim($input['team_id']) ?: null) : $existing['team_id'];
            $university = isset($input['university']) ? trim($input['university']) : $existing['university'];
            $major = isset($input['major']) ? trim($input['major']) : $existing['major'];

            $stmt = $pdo->prepare("UPDATE members SET name = ?, phone = ?, team_id = ?, university = ?, major = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $teamId, $university, $major, $memId]);

            echo json_encode(['success' => true, 'message' => 'Data siswa dan kelompok berhasil diperbarui!']);
            break;

        // 12. Save Settings
        case 'save_settings':
            $settings = [
                'waGatewayUrl' => $input['waGatewayUrl'] ?? '',
                'waApiToken' => $input['waApiToken'] ?? '',
                'waSenderNumber' => $input['waSenderNumber'] ?? '',
                'invitationTemplate' => $input['invitationTemplate'] ?? '',
                'weeklyReportTemplate' => $input['weeklyReportTemplate'] ?? ''
            ];
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO system_settings (key, value) VALUES (?, ?)");
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            echo json_encode(['success' => true, 'message' => 'Pengaturan berhasil disimpan']);
            break;

        // 13. Save or Update LMS Module
        case 'save_lms_module':
            $id = trim($input['id'] ?? '');
            $weekNumber = (int)($input['week_number'] ?? 1);
            $title = trim($input['title'] ?? '');
            $summary = trim($input['summary'] ?? '');
            $content = trim($input['content'] ?? '');
            $isPublished = isset($input['is_published']) ? (int)$input['is_published'] : 1;

            if (empty($title)) {
                echo json_encode(['success' => false, 'error' => 'Judul materi pembelajaran wajib diisi']);
                exit;
            }

            // Process objectives
            $objectivesRaw = $input['objectives'] ?? '';
            if (is_array($objectivesRaw)) {
                $objectivesArray = array_values(array_filter(array_map('trim', $objectivesRaw)));
            } else {
                $lines = explode("\n", str_replace("\r", "", (string)$objectivesRaw));
                $objectivesArray = array_values(array_filter(array_map('trim', $lines)));
            }
            $objectivesJson = json_encode($objectivesArray, JSON_UNESCAPED_UNICODE);

            // Process deliverables
            $deliverablesRaw = $input['deliverables'] ?? '';
            if (is_array($deliverablesRaw)) {
                $deliverablesArray = array_values(array_filter(array_map('trim', $deliverablesRaw)));
            } else {
                $lines = explode("\n", str_replace("\r", "", (string)$deliverablesRaw));
                $deliverablesArray = array_values(array_filter(array_map('trim', $lines)));
            }
            $deliverablesJson = json_encode($deliverablesArray, JSON_UNESCAPED_UNICODE);

            // Process external links: each line format "Title | URL" or array
            $linksRaw = $input['external_links'] ?? '';
            $linksArray = [];
            if (is_array($linksRaw)) {
                $linksArray = $linksRaw;
            } else {
                $lines = explode("\n", str_replace("\r", "", (string)$linksRaw));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    if (strpos($line, '|') !== false) {
                        list($lTitle, $lUrl) = explode('|', $line, 2);
                        $linksArray[] = [
                            'title' => trim($lTitle),
                            'url' => trim($lUrl)
                        ];
                    } else {
                        $linksArray[] = [
                            'title' => $line,
                            'url' => $line
                        ];
                    }
                }
            }
            $linksJson = json_encode($linksArray, JSON_UNESCAPED_UNICODE);

            if (empty($id)) {
                $id = 'lms-w' . $weekNumber . '-' . round(microtime(true) * 1000);
                $stmt = $pdo->prepare("
                    INSERT INTO lms_modules (id, week_number, title, summary, objectives, deliverables, external_links, content, is_published)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$id, $weekNumber, $title, $summary, $objectivesJson, $deliverablesJson, $linksJson, $content, $isPublished]);
                echo json_encode(['success' => true, 'message' => 'Materi pembelajaran baru berhasil disimpan', 'id' => $id, 'week_number' => $weekNumber]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM lms_modules WHERE id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("
                        UPDATE lms_modules
                        SET week_number = ?, title = ?, summary = ?, objectives = ?, deliverables = ?, external_links = ?, content = ?, is_published = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$weekNumber, $title, $summary, $objectivesJson, $deliverablesJson, $linksJson, $content, $isPublished, $id]);
                    echo json_encode(['success' => true, 'message' => 'Materi pembelajaran berhasil diperbarui', 'id' => $id, 'week_number' => $weekNumber]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO lms_modules (id, week_number, title, summary, objectives, deliverables, external_links, content, is_published)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$id, $weekNumber, $title, $summary, $objectivesJson, $deliverablesJson, $linksJson, $content, $isPublished]);
                    echo json_encode(['success' => true, 'message' => 'Materi pembelajaran berhasil disimpan', 'id' => $id, 'week_number' => $weekNumber]);
                }
            }
            break;

        // 14. Delete LMS Module
        case 'delete_lms_module':
            $id = trim($input['id'] ?? $_GET['id'] ?? '');
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID Modul tidak ditemukan']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM lms_modules WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Modul materi berhasil dihapus']);
            break;

        // 15. Edit Account Profile & Password
        case 'edit_account':
            if (session_status() === PHP_SESSION_NONE) session_start();
            $userId = $_SESSION['scrumvibe_user_id'] ?? null;
            if (!$userId) {
                echo json_encode(['success' => false, 'error' => 'Sesi login tidak valid']);
                exit;
            }
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $university = trim($input['university'] ?? '');
            $major = trim($input['major'] ?? '');
            $currentPassword = $input['current_password'] ?? '';
            $newPassword = $input['new_password'] ?? '';
            $confirmPassword = $input['confirm_password'] ?? '';

            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'Nama lengkap tidak boleh kosong']);
                exit;
            }

            // Fetch member
            $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $member = $stmt->fetch();
            if (!$member) {
                echo json_encode(['success' => false, 'error' => 'Data akun tidak ditemukan']);
                exit;
            }

            $passwordUpdated = false;
            if (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    echo json_encode(['success' => false, 'error' => 'Kata sandi baru minimal harus 6 karakter']);
                    exit;
                }
                if ($newPassword !== $confirmPassword) {
                    echo json_encode(['success' => false, 'error' => 'Konfirmasi kata sandi baru tidak cocok']);
                    exit;
                }
                if (!empty($member['password_hash'])) {
                    if (empty($currentPassword) || !password_verify($currentPassword, $member['password_hash'])) {
                        echo json_encode(['success' => false, 'error' => 'Kata sandi saat ini (lama) salah']);
                        exit;
                    }
                }

                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtPass = $pdo->prepare("UPDATE members SET password_hash = ? WHERE id = ?");
                $stmtPass->execute([$newHash, $userId]);
                $passwordUpdated = true;
            }

            $stmt = $pdo->prepare("UPDATE members SET name = ?, email = ?, phone = ?, university = ?, major = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $university, $major, $userId]);

            $_SESSION['scrumvibe_user_name'] = $name;

            $message = $passwordUpdated 
                ? 'Profil dan kata sandi berhasil diperbarui!' 
                : 'Profil akun berhasil diperbarui!';
            echo json_encode(['success' => true, 'message' => $message]);
            break;

        // Reset Invite Link - Admin resets student's password setup link
        case 'reset_invite':
            $memberId = $input['member_id'] ?? '';
            if (!$memberId) {
                echo json_encode(['success' => false, 'error' => 'ID anggota tidak valid']);
                exit;
            }
            // Only guru can do this
            if (($_SESSION['scrumvibe_role'] ?? '') !== 'guru') {
                echo json_encode(['success' => false, 'error' => 'Akses ditolak']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ? AND (role = 'siswa' OR (role != 'guru' AND role != 'super_admin')) LIMIT 1");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch();
            if (!$member) {
                echo json_encode(['success' => false, 'error' => 'Anggota tidak ditemukan']);
                exit;
            }
            // Reset: clear password_hash and invite_used so student can set new password
            $pdo->prepare("UPDATE members SET password_hash = '', invite_used = 0 WHERE id = ?")->execute([$memberId]);

            // Build invite link
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            $baseUrl = $scheme . '://' . $host . $dir;
            $inviteLink = $baseUrl . '/invite.php?token=' . urlencode($member['token']);

            echo json_encode([
                'success' => true,
                'invite_link' => $inviteLink,
                'member_name' => $member['name'],
                'message' => 'Link undangan berhasil direset!'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenali']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
