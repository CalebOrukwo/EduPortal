<?php
/**
 * Subject Inventory & Class Assignment
 * File: admin/subjects.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Admin privileges required
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    setFlashMessage('error', 'Access denied. Administrator privileges required.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Filter state for class mapping view
$selectedClassId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;

// -------------------------------------------------------------------------
// POST HANDLERS: SUBJECT CRUD & CLASS MAPPING
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. ADD NEW SUBJECT TO INVENTORY
        if ($action === 'create_subject') {
            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));

            if (empty($name) || empty($code)) {
                throw new Exception("Subject name and code are required.");
            }

            $stmt = $pdo->prepare("INSERT INTO sch_subjects (name, code) VALUES (:name, :code)");
            $stmt->execute([
                ':name' => $name,
                ':code' => $code
            ]);

            setFlashMessage('success', "Subject '$name ($code)' created successfully.");
            header('Location: ' . BASE_URL . 'admin/subjects.php' . ($selectedClassId ? '?class_id=' . $selectedClassId : ''));
            exit();
        }

        // 2. UPDATE SUBJECT INVENTORY
        if ($action === 'update_subject') {
            $subjectId = (int) ($_POST['subject_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));

            if ($subjectId <= 0 || empty($name) || empty($code)) {
                throw new Exception("Invalid parameters provided for subject update.");
            }

            $stmt = $pdo->prepare("UPDATE sch_subjects SET name = :name, code = :code WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':code' => $code,
                ':id' => $subjectId
            ]);

            setFlashMessage('success', "Subject updated successfully.");
            header('Location: ' . BASE_URL . 'admin/subjects.php' . ($selectedClassId ? '?class_id=' . $selectedClassId : ''));
            exit();
        }

        // 3. MAP SUBJECT TO CLASS
        if ($action === 'map_subject') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            $subjectId = (int) ($_POST['subject_id'] ?? 0);

            if ($classId <= 0 || $subjectId <= 0) {
                throw new Exception("Please select a valid class and subject.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO sch_class_subjects (class_id, subject_id)
                VALUES (:class_id, :subject_id)
                ON DUPLICATE KEY UPDATE class_id = class_id
            ");
            $stmt->execute([
                ':class_id' => $classId,
                ':subject_id' => $subjectId
            ]);

            setFlashMessage('success', "Subject mapped to class successfully.");
            header('Location: ' . BASE_URL . 'admin/subjects.php?class_id=' . $classId);
            exit();
        }

        // 4. UNMAP SUBJECT FROM CLASS
        if ($action === 'unmap_subject') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            $subjectId = (int) ($_POST['subject_id'] ?? 0);

            if ($classId > 0 && $subjectId > 0) {
                $stmt = $pdo->prepare("DELETE FROM sch_class_subjects WHERE class_id = :class_id AND subject_id = :subject_id");
                $stmt->execute([
                    ':class_id' => $classId,
                    ':subject_id' => $subjectId
                ]);
                setFlashMessage('success', "Subject removed from class.");
            }
            header('Location: ' . BASE_URL . 'admin/subjects.php?class_id=' . $classId);
            exit();
        }

    } catch (Exception $e) {
        error_log("Subject Admin Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'admin/subjects.php' . ($selectedClassId ? '?class_id=' . $selectedClassId : ''));
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL
// -------------------------------------------------------------------------
try {
    // Fetch all classes for dropdown mapping
    $classesStmt = $pdo->query("SELECT id, name FROM sch_classes ORDER BY id ASC");
    $classes = $classesStmt->fetchAll();

    // Fetch master subject list with assigned class counts
    $subjectsStmt = $pdo->query("
        SELECT 
            s.*,
            COUNT(cs.class_id) AS mapped_classes_count
        FROM sch_subjects s
        LEFT JOIN sch_class_subjects cs ON s.id = cs.subject_id
        GROUP BY s.id
        ORDER BY s.name ASC
    ");
    $subjects = $subjectsStmt->fetchAll();

    // If a class filter is active, fetch subjects mapped to that class
    $mappedSubjects = [];
    $unmappedSubjects = [];
    if ($selectedClassId > 0) {
        $mappedStmt = $pdo->prepare("
            SELECT s.* 
            FROM sch_subjects s
            JOIN sch_class_subjects cs ON s.id = cs.subject_id
            WHERE cs.class_id = :class_id
            ORDER BY s.name ASC
        ");
        $mappedStmt->execute([':class_id' => $selectedClassId]);
        $mappedSubjects = $mappedStmt->fetchAll();

        $unmappedStmt = $pdo->prepare("
            SELECT s.* 
            FROM sch_subjects s
            WHERE s.id NOT IN (
                SELECT subject_id FROM sch_class_subjects WHERE class_id = :class_id
            )
            ORDER BY s.name ASC
        ");
        $unmappedStmt->execute([':class_id' => $selectedClassId]);
        $unmappedSubjects = $unmappedStmt->fetchAll();
    }

} catch (Exception $e) {
    error_log("Subject Fetch Error: " . $e->getMessage());
    $classes = [];
    $subjects = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header Section -->
    <div class="glow-card glass-card p-6 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div>
            <div class="flex items-center space-x-2 text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--accent-cyan, #22d3ee);">
                <span>Admin Panel</span>
                <span>&bull;</span>
                <span>Curriculum Management</span>
            </div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Subject Inventory & Class Allocation</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">Define subject offerings and map them to appropriate class levels.</p>
        </div>
        <button onclick="openCreateSubjectModal()" class="glow-button px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2" style="background: var(--btn-primary-bg, #0284c7); color: var(--btn-primary-text, #ffffff);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Create New Subject</span>
        </button>
    </div>

    <!-- Filter & Class Subject Mapping Section -->
    <div class="glow-card glass-card p-6 rounded-2xl space-y-6" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 pb-4" style="border-bottom: 1px solid var(--border-subtle);">
            <div>
                <h2 class="text-lg font-bold" style="color: var(--text-primary);">Class Subject Allocation</h2>
                <p class="text-xs" style="color: var(--text-secondary);">Select a class level to view and manage assigned curriculum subjects.</p>
            </div>
            <form method="GET" action="" class="flex items-center gap-2 w-full md:w-auto">
                <select name="class_id" onchange="this.form.submit()" class="rounded-xl px-4 py-2 text-xs font-medium focus:outline-none w-full md:w-64" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <option value="0">-- Select Class to Filter --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClassId === $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedClassId > 0): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Assigned Subjects -->
                <div class="p-5 rounded-xl space-y-4" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                    <div class="flex items-center justify-between pb-2" style="border-bottom: 1px solid var(--border-subtle);">
                        <h3 class="text-sm font-bold uppercase tracking-wider" style="color: var(--accent-emerald, #10b981);">Assigned Subjects</h3>
                        <span class="px-2 py-0.5 rounded text-xs font-mono font-bold" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald, #10b981);">
                            <?php echo count($mappedSubjects); ?> Active
                        </span>
                    </div>

                    <?php if (!empty($mappedSubjects)): ?>
                        <div class="space-y-2">
                            <?php foreach ($mappedSubjects as $mSub): ?>
                                <div class="p-3 rounded-lg flex items-center justify-between" style="border-bottom: 1px solid var(--border-subtle);">
                                    <div>
                                        <div class="text-sm font-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($mSub['name']); ?></div>
                                        <div class="text-xs font-mono" style="color: var(--accent-cyan, #22d3ee);"><?php echo htmlspecialchars($mSub['code']); ?></div>
                                    </div>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="unmap_subject">
                                        <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
                                        <input type="hidden" name="subject_id" value="<?php echo $mSub['id']; ?>">
                                        <button type="submit" class="text-xs font-semibold px-2.5 py-1 rounded-lg transition-all" style="color: var(--accent-rose, #f43f5e); border: 1px solid rgba(244, 63, 94, 0.3); background: rgba(244, 63, 94, 0.1);">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-xs py-4 text-center" style="color: var(--text-muted);">No subjects currently mapped to this class.</p>
                    <?php endif; ?>
                </div>

                <!-- Unmapped / Available Subjects -->
                <div class="p-5 rounded-xl space-y-4" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                    <div class="flex items-center justify-between pb-2" style="border-bottom: 1px solid var(--border-subtle);">
                        <h3 class="text-sm font-bold uppercase tracking-wider" style="color: var(--accent-cyan, #22d3ee);">Available Subjects</h3>
                        <span class="px-2 py-0.5 rounded text-xs font-mono font-bold" style="background: rgba(34, 211, 238, 0.1); color: var(--accent-cyan, #22d3ee);">
                            <?php echo count($unmappedSubjects); ?> Available
                        </span>
                    </div>

                    <?php if (!empty($unmappedSubjects)): ?>
                        <div class="space-y-2">
                            <?php foreach ($unmappedSubjects as $uSub): ?>
                                <div class="p-3 rounded-lg flex items-center justify-between" style="border-bottom: 1px solid var(--border-subtle);">
                                    <div>
                                        <div class="text-sm font-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($uSub['name']); ?></div>
                                        <div class="text-xs font-mono" style="color: var(--accent-cyan, #22d3ee);"><?php echo htmlspecialchars($uSub['code']); ?></div>
                                    </div>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="map_subject">
                                        <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
                                        <input type="hidden" name="subject_id" value="<?php echo $uSub['id']; ?>">
                                        <button type="submit" class="text-xs font-semibold px-2.5 py-1 rounded-lg transition-all" style="color: var(--accent-cyan, #22d3ee); border: 1px solid rgba(34, 211, 238, 0.3); background: rgba(34, 211, 238, 0.1);">
                                            + Assign to Class
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-xs py-4 text-center" style="color: var(--text-muted);">All available subjects are already assigned to this class.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="p-8 border border-dashed rounded-xl text-center space-y-2" style="border-color: var(--border-subtle);">
                <svg class="w-10 h-10 mx-auto" style="color: var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <p class="text-sm" style="color: var(--text-secondary);">Select a class from the dropdown filter above to manage its subject mapping.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Master Subject Inventory Grid -->
    <div class="glow-card glass-card p-6 rounded-2xl space-y-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div class="flex items-center justify-between pb-4" style="border-bottom: 1px solid var(--border-subtle);">
            <div>
                <h2 class="text-lg font-bold" style="color: var(--text-primary);">Master Subject Inventory</h2>
                <p class="text-xs" style="color: var(--text-secondary);">Complete listing of all registered subjects in the school system.</p>
            </div>
            <div class="text-xs font-mono font-semibold" style="color: var(--accent-cyan, #22d3ee);">
                Total Subjects: <?php echo count($subjects); ?>
            </div>
        </div>

        <?php if (!empty($subjects)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($subjects as $s): ?>
                    <div class="p-4 rounded-xl flex flex-col justify-between space-y-4 transition-all hover:translate-y-[-2px]" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="font-bold text-base" style="color: var(--text-primary);">
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </h3>
                                <span class="inline-block text-xs font-mono font-semibold px-2 py-0.5 rounded mt-1" style="background: rgba(34, 211, 238, 0.1); color: var(--accent-cyan, #22d3ee); border: 1px solid rgba(34, 211, 238, 0.2);">
                                    <?php echo htmlspecialchars($s['code']); ?>
                                </span>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-mono" style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary);">
                                <?php echo number_format($s['mapped_classes_count']); ?> Classes
                            </span>
                        </div>

                        <div class="pt-3 flex justify-end" style="border-top: 1px solid var(--border-subtle);">
                            <button 
                                type="button" 
                                onclick='openEditSubjectModal(<?php echo json_encode($s); ?>)'
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                                style="background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border-subtle);"
                            >
                                Edit Subject
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8" style="color: var(--text-muted);">
                No subjects registered yet. Click 'Create New Subject' to add one.
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- SUBJECT FORM MODAL (CREATE & EDIT) -->
<div id="subjectModal" class="fixed inset-0 z-50 hidden backdrop-blur-sm flex items-center justify-center p-4" style="background: rgba(0, 0, 0, 0.7);">
    <div class="rounded-2xl max-w-md w-full p-6 space-y-5" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div class="flex items-center justify-between pb-3" style="border-bottom: 1px solid var(--border-subtle);">
            <h3 id="modalTitle" class="text-lg font-bold" style="color: var(--text-primary);">Create Subject</h3>
            <button onclick="closeSubjectModal()" class="text-xl leading-none" style="color: var(--text-secondary);">&times;</button>
        </div>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" id="formAction" value="create_subject">
            <input type="hidden" name="subject_id" id="subjectId" value="">

            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Subject Name</label>
                <input type="text" name="name" id="subjectName" required placeholder="e.g. Mathematics, English Language" class="w-full rounded-xl px-4 py-2.5 text-sm focus:outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
            </div>

            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Subject Code</label>
                <input type="text" name="code" id="subjectCode" required placeholder="e.g. MATH, ENG, PHY" class="w-full rounded-xl px-4 py-2.5 text-sm uppercase font-mono focus:outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
            </div>

            <div class="flex justify-end space-x-3 pt-3" style="border-top: 1px solid var(--border-subtle);">
                <button type="button" onclick="closeSubjectModal()" class="px-4 py-2 rounded-xl text-xs font-semibold" style="background: var(--bg-input); color: var(--text-secondary);">Cancel</button>
                <button type="submit" class="glow-button px-5 py-2 text-xs font-bold rounded-xl" style="background: var(--btn-primary-bg, #0284c7); color: var(--btn-primary-text, #ffffff);">Save Subject</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateSubjectModal() {
    document.getElementById('formAction').value = 'create_subject';
    document.getElementById('modalTitle').innerText = 'Create Subject';
    document.getElementById('subjectId').value = '';
    document.getElementById('subjectName').value = '';
    document.getElementById('subjectCode').value = '';
    document.getElementById('subjectModal').classList.remove('hidden');
}

function openEditSubjectModal(subject) {
    document.getElementById('formAction').value = 'update_subject';
    document.getElementById('modalTitle').innerText = 'Edit Subject';
    document.getElementById('subjectId').value = subject.id;
    document.getElementById('subjectName').value = subject.name;
    document.getElementById('subjectCode').value = subject.code;
    document.getElementById('subjectModal').classList.remove('hidden');
}

function closeSubjectModal() {
    document.getElementById('subjectModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>