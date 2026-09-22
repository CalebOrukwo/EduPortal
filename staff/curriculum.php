<?php
/**
 * Scheme of Work / Curriculum Authoring Page
 * File: staff/curriculum.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Staff privileges required
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    setFlashMessage('error', 'Access denied. Staff privileges required.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$staffId = (int) $_SESSION['user_id'];

// -------------------------------------------------------------------------
// POST HANDLERS: SAVE / SUBMIT CURRICULUM
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_curriculum') {
            $curriculumId = (int) ($_POST['curriculum_id'] ?? 0);
            $classId = (int) ($_POST['class_id'] ?? 0);
            $subjectId = (int) ($_POST['subject_id'] ?? 0);
            $termId = (int) ($_POST['term_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $saveType = $_POST['save_type'] ?? 'draft'; // 'draft' or 'submit'

            if ($classId <= 0 || $subjectId <= 0 || $termId <= 0) {
                throw new Exception("Class, Subject, and Term selection are required.");
            }

            if (empty($content)) {
                throw new Exception("Curriculum content cannot be empty.");
            }

            // Set status to 'draft' for both saves and submission requests so an admin can review it
            $status = 'draft';

            if ($curriculumId > 0) {
                // Verify ownership before updating
                $checkStmt = $pdo->prepare("SELECT id, status FROM sch_curricula WHERE id = :id AND staff_id = :staff_id");
                $checkStmt->execute([':id' => $curriculumId, ':staff_id' => $staffId]);
                $existing = $checkStmt->fetch();

                if (!$existing) {
                    throw new Exception("Curriculum record not found or permission denied.");
                }

                $updateStmt = $pdo->prepare("
                    UPDATE sch_curricula 
                    SET class_id = :class_id,
                        subject_id = :subject_id,
                        term_id = :term_id,
                        content = :content,
                        status = :status
                    WHERE id = :id AND staff_id = :staff_id
                ");
                $updateStmt->execute([
                    ':class_id' => $classId,
                    ':subject_id' => $subjectId,
                    ':term_id' => $termId,
                    ':content' => $content,
                    ':status' => $status,
                    ':id' => $curriculumId,
                    ':staff_id' => $staffId
                ]);

                $msg = ($saveType === 'submit') 
                    ? "Curriculum submitted for admin approval successfully." 
                    : "Curriculum draft saved successfully.";
            } else {
                // Insert new curriculum entry
                $insertStmt = $pdo->prepare("
                    INSERT INTO sch_curricula (class_id, subject_id, term_id, staff_id, content, status)
                    VALUES (:class_id, :subject_id, :term_id, :staff_id, :content, :status)
                ");
                $insertStmt->execute([
                    ':class_id' => $classId,
                    ':subject_id' => $subjectId,
                    ':term_id' => $termId,
                    ':staff_id' => $staffId,
                    ':content' => $content,
                    ':status' => $status
                ]);

                $msg = ($saveType === 'submit') 
                    ? "Curriculum created and submitted for admin approval." 
                    : "Curriculum draft saved successfully.";
            }

            setFlashMessage('success', $msg);
            header('Location: ' . BASE_URL . 'staff/curriculum.php');
            exit();
        }

        // DELETE CURRICULUM DRAFT
        if ($action === 'delete_curriculum') {
            $curriculumId = (int) ($_POST['curriculum_id'] ?? 0);

            if ($curriculumId <= 0) {
                throw new Exception("Invalid curriculum record.");
            }

            $delStmt = $pdo->prepare("DELETE FROM sch_curricula WHERE id = :id AND staff_id = :staff_id AND status = 'draft'");
            $delStmt->execute([':id' => $curriculumId, ':staff_id' => $staffId]);

            setFlashMessage('success', "Curriculum draft deleted successfully.");
            header('Location: ' . BASE_URL . 'staff/curriculum.php');
            exit();
        }

    } catch (Exception $e) {
        error_log("Curriculum Save Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'staff/curriculum.php');
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL FOR ASSIGNED CLASSES, SUBJECTS, TERMS, AND CURRICULA
// -------------------------------------------------------------------------
try {
    // Fetch classes assigned to this staff member
    $assignedClassesStmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name 
        FROM sch_classes c
        INNER JOIN sch_staff_classes sc ON c.id = sc.class_id
        WHERE sc.staff_id = :staff_id
        ORDER BY c.id ASC
    ");
    $assignedClassesStmt->execute([':staff_id' => $staffId]);
    $assignedClasses = $assignedClassesStmt->fetchAll();

    // Fetch all available subjects
    $subjectsStmt = $pdo->query("SELECT id, name, code FROM sch_subjects ORDER BY name ASC");
    $subjects = $subjectsStmt->fetchAll();

    // Fetch terms with session name
    $termsStmt = $pdo->query("
        SELECT t.id, t.term_name, t.is_current, s.name AS session_name 
        FROM sch_terms t
        INNER JOIN sch_sessions s ON t.session_id = s.id
        ORDER BY s.is_active DESC, t.id ASC
    ");
    $terms = $termsStmt->fetchAll();

    // Fetch all curricula authored by this staff member
    $curriculaStmt = $pdo->prepare("
        SELECT 
            cur.*,
            c.name AS class_name,
            sub.name AS subject_name,
            sub.code AS subject_code,
            t.term_name,
            s.name AS session_name
        FROM sch_curricula cur
        INNER JOIN sch_classes c ON cur.class_id = c.id
        INNER JOIN sch_subjects sub ON cur.subject_id = sub.id
        INNER JOIN sch_terms t ON cur.term_id = t.id
        INNER JOIN sch_sessions s ON t.session_id = s.id
        WHERE cur.staff_id = :staff_id
        ORDER BY cur.id DESC
    ");
    $curriculaStmt->execute([':staff_id' => $staffId]);
    $curriculaList = $curriculaStmt->fetchAll();

} catch (Exception $e) {
    error_log("Curriculum Data Fetch Error: " . $e->getMessage());
    $assignedClasses = [];
    $subjects = [];
    $terms = [];
    $curriculaList = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header Section -->
    <div class="glass-card p-6 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border border-[var(--border-subtle)] bg-[var(--bg-card)] text-[var(--text-primary)]">
        <div>
            <div class="flex items-center space-x-2 text-xs font-semibold uppercase tracking-wider mb-1 text-[var(--text-accent,#34d399)]">
                <span>Staff Workspace</span>
                <span>&bull;</span>
                <span>Academic Planning</span>
            </div>
            <h1 class="text-2xl font-bold text-[var(--text-primary)]">Curriculum & Scheme of Work</h1>
            <p class="text-sm mt-0.5 text-[var(--text-secondary)]">Author and manage termly syllabus outlines for assigned classes and subjects.</p>
        </div>
        <button onclick="openCurriculumModal()" class="glow-button px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2 bg-[var(--accent-primary,#10b981)] hover:bg-[var(--accent-hover,#34d399)] text-[var(--bg-card)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Create Scheme of Work</span>
        </button>
    </div>

    <!-- Authored Curricula Overview Grid -->
    <div class="glass-card p-6 rounded-2xl space-y-4 border border-[var(--border-subtle)] bg-[var(--bg-card)]">
        <div class="flex items-center justify-between border-b border-[var(--border-subtle)] pb-4">
            <h2 class="text-lg font-bold text-[var(--text-primary)]">Your Curriculum Submissions</h2>
            <span class="text-xs text-[var(--text-secondary)]">Total: <strong class="text-[var(--text-primary)]"><?php echo count($curriculaList); ?></strong></span>
        </div>

        <?php if (!empty($curriculaList)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($curriculaList as $item): ?>
                    <div class="p-4 rounded-xl border border-[var(--border-subtle)] bg-[var(--bg-input)] space-y-3 flex flex-col justify-between transition-all hover:border-[var(--accent-primary,#10b981)]/50">
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-base font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($item['class_name']); ?></h3>
                                    <p class="text-xs font-semibold text-[var(--text-accent,#34d399)]">
                                        <?php echo htmlspecialchars($item['subject_name']); ?>
                                        <span class="font-mono text-[var(--text-secondary)]">(<?php echo htmlspecialchars($item['subject_code'] ?? 'N/A'); ?>)</span>
                                    </p>
                                </div>
                                <div>
                                    <?php if ($item['status'] === 'approved'): ?>
                                        <span class="px-2.5 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 rounded-full text-[10px] font-semibold">
                                            Approved
                                        </span>
                                    <?php elseif ($item['status'] === 'rejected'): ?>
                                        <span class="px-2.5 py-0.5 bg-rose-500/10 text-rose-400 border border-rose-500/30 rounded-full text-[10px] font-semibold">
                                            Rejected
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 bg-amber-500/10 text-amber-400 border border-amber-500/30 rounded-full text-[10px] font-semibold">
                                            Draft / Pending
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-xs text-[var(--text-secondary)]">
                                <?php echo htmlspecialchars($item['session_name'] . ' - ' . $item['term_name']); ?>
                            </p>
                        </div>

                        <div class="pt-3 border-t border-[var(--border-subtle)] flex items-center justify-end space-x-2">
                            <button 
                                type="button" 
                                onclick='viewCurriculum(<?php echo json_encode($item); ?>)'
                                class="px-3 py-1 bg-[var(--bg-card)] text-[var(--text-accent,#34d399)] border border-[var(--border-subtle)] rounded-lg text-xs font-semibold transition-all hover:border-[var(--accent-primary,#10b981)]"
                            >
                                View
                            </button>
                            <button 
                                type="button" 
                                onclick='editCurriculum(<?php echo json_encode($item); ?>)'
                                class="px-3 py-1 bg-[var(--bg-card)] text-[var(--text-primary)] border border-[var(--border-subtle)] rounded-lg text-xs font-semibold transition-all hover:border-[var(--accent-primary,#10b981)]"
                            >
                                Edit
                            </button>
                            <?php if ($item['status'] === 'draft'): ?>
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Delete this draft permanently?');">
                                    <input type="hidden" name="action" value="delete_curriculum">
                                    <input type="hidden" name="curriculum_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="px-3 py-1 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 rounded-lg text-xs font-semibold transition-all">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 text-[var(--text-secondary)] text-sm">
                No curricula found. Click 'Create Scheme of Work' to start authoring.
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- AUTHORING / EDIT MODAL -->
<div id="curriculumModal" class="fixed inset-0 z-50 hidden bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-card bg-[var(--bg-card)] border border-[var(--border-subtle)] rounded-2xl max-w-2xl w-full p-6 space-y-5 max-h-[90vh] overflow-y-auto text-[var(--text-primary)]">
        <div class="flex items-center justify-between border-b border-[var(--border-subtle)] pb-3">
            <h3 id="modalTitle" class="text-lg font-bold text-[var(--text-primary)]">Create Scheme of Work</h3>
            <button onclick="closeModal('curriculumModal')" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]">&times;</button>
        </div>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="save_curriculum">
            <input type="hidden" name="curriculum_id" id="curriculumId" value="">
            <input type="hidden" name="save_type" id="saveType" value="draft">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1">Class</label>
                    <select name="class_id" id="classId" required class="w-full bg-[var(--bg-input)] border border-[var(--border-subtle)] rounded-xl px-3.5 py-2 text-xs text-[var(--text-primary)] focus:outline-none focus:border-[var(--accent-primary,#10b981)]">
                        <option value="">-- Select Class --</option>
                        <?php foreach ($assignedClasses as $ac): ?>
                            <option value="<?php echo $ac['id']; ?>"><?php echo htmlspecialchars($ac['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1">Subject</label>
                    <select name="subject_id" id="subjectId" required class="w-full bg-[var(--bg-input)] border border-[var(--border-subtle)] rounded-xl px-3.5 py-2 text-xs text-[var(--text-primary)] focus:outline-none focus:border-[var(--accent-primary,#10b981)]">
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1">Term</label>
                    <select name="term_id" id="termId" required class="w-full bg-[var(--bg-input)] border border-[var(--border-subtle)] rounded-xl px-3.5 py-2 text-xs text-[var(--text-primary)] focus:outline-none focus:border-[var(--accent-primary,#10b981)]">
                        <option value="">-- Select Term --</option>
                        <?php foreach ($terms as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $t['is_current'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['session_name'] . ' - ' . $t['term_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1">Curriculum Content / Weekly Topics</label>
                <textarea name="content" id="curriculumContent" rows="10" required placeholder="Week 1: Introduction to topic...&#10;Week 2: Core Concepts...&#10;Week 3: Practical applications..." class="w-full bg-[var(--bg-input)] border border-[var(--border-subtle)] rounded-xl p-3 text-xs text-[var(--text-primary)] focus:outline-none focus:border-[var(--accent-primary,#10b981)] font-mono"></textarea>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-[var(--border-subtle)]">
                <button type="button" onclick="closeModal('curriculumModal')" class="px-4 py-2 bg-[var(--bg-input)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] rounded-xl text-xs font-semibold">Cancel</button>
                <div class="flex space-x-3">
                    <button type="submit" onclick="setSaveType('draft')" class="px-4 py-2 bg-[var(--bg-input)] text-[var(--text-primary)] border border-[var(--border-subtle)] rounded-xl text-xs font-semibold hover:border-[var(--accent-primary,#10b981)]">
                        Save Draft
                    </button>
                    <button type="submit" onclick="setSaveType('submit')" class="glow-button px-5 py-2 text-[var(--bg-card)] bg-[var(--accent-primary,#10b981)] hover:bg-[var(--accent-hover,#34d399)] text-xs font-bold rounded-xl">
                        Submit for Approval
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- VIEW DETAILS MODAL -->
<div id="viewModal" class="fixed inset-0 z-50 hidden bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-card bg-[var(--bg-card)] border border-[var(--border-subtle)] rounded-2xl max-w-2xl w-full p-6 space-y-5 max-h-[90vh] overflow-y-auto text-[var(--text-primary)]">
        <div class="flex items-center justify-between border-b border-[var(--border-subtle)] pb-3">
            <div>
                <h3 id="viewSubjectTitle" class="text-lg font-bold text-[var(--text-primary)]"></h3>
                <p id="viewMetaText" class="text-xs text-[var(--text-secondary)]"></p>
            </div>
            <button onclick="closeModal('viewModal')" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]">&times;</button>
        </div>

        <div class="bg-[var(--bg-input)] p-4 rounded-xl border border-[var(--border-subtle)]">
            <pre id="viewContent" class="text-xs text-[var(--text-primary)] font-mono whitespace-pre-wrap leading-relaxed"></pre>
        </div>

        <div class="flex justify-end pt-2">
            <button type="button" onclick="closeModal('viewModal')" class="px-4 py-2 bg-[var(--bg-input)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] rounded-xl text-xs font-semibold">Close</button>
        </div>
    </div>
</div>

<script>
function openCurriculumModal() {
    document.getElementById('modalTitle').innerText = 'Create Scheme of Work';
    document.getElementById('curriculumId').value = '';
    document.getElementById('classId').value = '';
    document.getElementById('subjectId').value = '';
    document.getElementById('curriculumContent').value = '';
    document.getElementById('curriculumModal').classList.remove('hidden');
}

function editCurriculum(data) {
    document.getElementById('modalTitle').innerText = 'Edit Scheme of Work';
    document.getElementById('curriculumId').value = data.id;
    document.getElementById('classId').value = data.class_id;
    document.getElementById('subjectId').value = data.subject_id;
    document.getElementById('termId').value = data.term_id;
    document.getElementById('curriculumContent').value = data.content;
    document.getElementById('curriculumModal').classList.remove('hidden');
}

function viewCurriculum(data) {
    document.getElementById('viewSubjectTitle').innerText = data.subject_name + ' (' + data.class_name + ')';
    document.getElementById('viewMetaText').innerText = data.session_name + ' | ' + data.term_name + ' | Status: ' + data.status.toUpperCase();
    document.getElementById('viewContent').innerText = data.content;
    document.getElementById('viewModal').classList.remove('hidden');
}

function setSaveType(type) {
    document.getElementById('saveType').value = type;
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>