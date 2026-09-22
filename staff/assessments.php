<?php
/**
 * Continuous Assessment (CA) & Exam Score Entry Portal
 * File: staff/assessments.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Staff or Admin privileges required
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'staff', 'admin'])) {
    setFlashMessage('error', 'Access denied. Staff privileges required.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$staffId = $_SESSION['user_id'];

// Selected filters from Query String
$selectedTermId    = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;
$selectedClassId   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedSubjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

// -------------------------------------------------------------------------
// POST HANDLER: SAVE / UPDATE SCORES
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scores') {
    try {
        $termId    = (int)($_POST['term_id'] ?? 0);
        $classId   = (int)($_POST['class_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $scores    = $_POST['scores'] ?? [];

        if ($termId <= 0 || $classId <= 0 || $subjectId <= 0) {
            throw new Exception("Please select a valid Term, Class, and Subject.");
        }

        $pdo->beginTransaction();

        // Statements for checking existing record, inserting, or updating
        $checkStmt = $pdo->prepare("
            SELECT id FROM sch_assessments 
            WHERE student_id = :student_id AND subject_id = :subject_id AND term_id = :term_id 
            LIMIT 1
        ");

        $insertStmt = $pdo->prepare("
            INSERT INTO sch_assessments (student_id, subject_id, term_id, ca1_score, ca2_score, ca3_score, exam_score)
            VALUES (:student_id, :subject_id, :term_id, :ca1_score, :ca2_score, :ca3_score, :exam_score)
        ");

        $updateStmt = $pdo->prepare("
            UPDATE sch_assessments 
            SET ca1_score = :ca1_score,
                ca2_score = :ca2_score,
                ca3_score = :ca3_score,
                exam_score = :exam_score
            WHERE id = :id
        ");

        foreach ($scores as $studentId => $scoreData) {
            $studentId = (int)$studentId;
            $ca1  = (isset($scoreData['ca1']) && $scoreData['ca1'] !== '') ? (float)$scoreData['ca1'] : 0.00;
            $ca2  = (isset($scoreData['ca2']) && $scoreData['ca2'] !== '') ? (float)$scoreData['ca2'] : 0.00;
            $ca3  = (isset($scoreData['ca3']) && $scoreData['ca3'] !== '') ? (float)$scoreData['ca3'] : 0.00;
            $exam = (isset($scoreData['exam']) && $scoreData['exam'] !== '') ? (float)$scoreData['exam'] : 0.00;

            // Check if record exists for this student + subject + term combination
            $checkStmt->execute([
                ':student_id' => $studentId,
                ':subject_id' => $subjectId,
                ':term_id'    => $termId
            ]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record
                $updateStmt->execute([
                    ':ca1_score'  => $ca1,
                    ':ca2_score'  => $ca2,
                    ':ca3_score'  => $ca3,
                    ':exam_score' => $exam,
                    ':id'         => $existing['id']
                ]);
            } else {
                // Insert new assessment record
                $insertStmt->execute([
                    ':student_id' => $studentId,
                    ':subject_id' => $subjectId,
                    ':term_id'    => $termId,
                    ':ca1_score'  => $ca1,
                    ':ca2_score'  => $ca2,
                    ':ca3_score'  => $ca3,
                    ':exam_score' => $exam
                ]);
            }
        }

        $pdo->commit();

        setFlashMessage('success', 'Assessment scores updated successfully.');
        header("Location: " . BASE_URL . "staff/assessments.php?term_id={$termId}&class_id={$classId}&subject_id={$subjectId}");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Assessment Save Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header("Location: " . BASE_URL . "staff/assessments.php?term_id={$selectedTermId}&class_id={$selectedClassId}&subject_id={$selectedSubjectId}");
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL
// -------------------------------------------------------------------------
try {
    // 1. Fetch Academic Terms
    $termsStmt = $pdo->query("
        SELECT t.id, t.term_name, t.is_current, s.name AS session_name 
        FROM sch_terms t
        JOIN sch_sessions s ON t.session_id = s.id
        ORDER BY s.id DESC, t.id DESC
    ");
    $terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Default to active current term if not selected
    if ($selectedTermId === 0 && !empty($terms)) {
        foreach ($terms as $t) {
            if ($t['is_current'] == 1) {
                $selectedTermId = (int)$t['id'];
                break;
            }
        }
        if ($selectedTermId === 0) {
            $selectedTermId = (int)$terms[0]['id'];
        }
    }

    // 2. Fetch Classes Assigned to Staff (or all classes for Admin)
    if ($_SESSION['role'] === 'admin') {
        $classesStmt = $pdo->query("SELECT id, name FROM sch_classes ORDER BY name ASC");
    } else {
        $classesStmt = $pdo->prepare("
            SELECT DISTINCT c.id, c.name 
            FROM sch_staff_classes sc
            JOIN sch_classes c ON sc.class_id = c.id
            WHERE sc.staff_id = :staff_id
            ORDER BY c.name ASC
        ");
        $classesStmt->execute([':staff_id' => $staffId]);
    }
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($selectedClassId === 0 && !empty($classes)) {
        $selectedClassId = (int)$classes[0]['id'];
    }

    // 3. Fetch Subjects linked to the selected Class via sch_class_subjects
    $subjects = [];
    if ($selectedClassId > 0) {
        $subjectsStmt = $pdo->prepare("
            SELECT sub.id, sub.name, sub.code 
            FROM sch_class_subjects cs
            JOIN sch_subjects sub ON cs.subject_id = sub.id
            WHERE cs.class_id = :class_id
            ORDER BY sub.name ASC
        ");
        $subjectsStmt->execute([':class_id' => $selectedClassId]);
        $subjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($selectedSubjectId === 0 && !empty($subjects)) {
        $selectedSubjectId = (int)$subjects[0]['id'];
    }

    // 4. Fetch Active Students in selected Class & Existing Scores
    $students = [];
    if ($selectedClassId > 0 && $selectedSubjectId > 0 && $selectedTermId > 0) {
        $studentsStmt = $pdo->prepare("
            SELECT 
                st.id AS student_id,
                st.student_code,
                st.first_name,
                st.last_name,
                a.ca1_score,
                a.ca2_score,
                a.ca3_score,
                a.exam_score,
                a.total_score
            FROM sch_students st
            LEFT JOIN sch_assessments a ON st.id = a.student_id 
                AND a.subject_id = :subject_id 
                AND a.term_id = :term_id
            WHERE st.current_class_id = :class_id
              AND st.status = 'active'
            ORDER BY st.last_name ASC, st.first_name ASC
        ");
        $studentsStmt->execute([
            ':subject_id' => $selectedSubjectId,
            ':term_id'    => $selectedTermId,
            ':class_id'   => $selectedClassId
        ]);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    error_log("Assessments Load Error: " . $e->getMessage());
    $terms = [];
    $classes = [];
    $subjects = [];
    $students = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Page Header -->
    <div class="glass-card p-6 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div>
            <div class="flex items-center space-x-2 text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--accent-color, #38bdf8);">
                <span>Staff Portal</span>
                <span>&bull;</span>
                <span>Academic Assessment</span>
            </div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Continuous Assessment & Exam Scores</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">Input CA tests and term end examination scores for assigned classes.</p>
        </div>
    </div>

    <!-- Filter Control Bar -->
    <div class="glass-card p-5 rounded-2xl" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Term Selector -->
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Academic Term</label>
                <select name="term_id" onchange="this.form.submit()" class="w-full rounded-xl px-4 py-2.5 text-xs font-medium focus:outline-none transition-colors" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <?php foreach ($terms as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $selectedTermId === (int)$t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['session_name'] . ' — ' . $t['term_name']); ?> <?php echo $t['is_current'] ? '(Current)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Class Selector -->
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Assigned Class</label>
                <select name="class_id" onchange="this.form.submit()" class="w-full rounded-xl px-4 py-2.5 text-xs font-medium focus:outline-none transition-colors" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selectedClassId === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="0">No Assigned Classes</option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Subject Selector -->
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Subject</label>
                <select name="subject_id" onchange="this.form.submit()" class="w-full rounded-xl px-4 py-2.5 text-xs font-medium focus:outline-none transition-colors" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <?php if (!empty($subjects)): ?>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>" <?php echo $selectedSubjectId === (int)$sub['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sub['name'] . ($sub['code'] ? ' (' . $sub['code'] . ')' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="0">No Subjects Configured</option>
                    <?php endif; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Scores Entry Section -->
    <div>
        <?php if (!empty($students)): ?>
            <form method="POST" action="" class="space-y-6">
                <input type="hidden" name="action" value="save_scores">
                <input type="hidden" name="term_id" value="<?php echo $selectedTermId; ?>">
                <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $selectedSubjectId; ?>">

                <!-- Responsive Card Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($students as $idx => $st): ?>
                        <?php 
                            $ca1   = (float)($st['ca1_score'] ?? 0);
                            $ca2   = (float)($st['ca2_score'] ?? 0);
                            $ca3   = (float)($st['ca3_score'] ?? 0);
                            $exam  = (float)($st['exam_score'] ?? 0);
                            $total = $st['total_score'] !== null ? (float)$st['total_score'] : ($ca1 + $ca2 + $ca3 + $exam);
                        ?>
                        <div class="score-card glass-card p-5 rounded-2xl flex flex-col justify-between transition-all duration-300 hover:shadow-lg" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                            
                            <!-- Card Header: Student Info & Total -->
                            <div>
                                <div class="flex items-start justify-between gap-3 pb-3 mb-4 border-b" style="border-color: var(--border-subtle);">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs font-bold px-2 py-0.5 rounded-full" style="background: var(--bg-input); color: var(--text-secondary);">
                                                #<?php echo $idx + 1; ?>
                                            </span>
                                            <h3 class="font-bold text-base" style="color: var(--text-primary);">
                                                <?php echo htmlspecialchars($st['last_name'] . ' ' . $st['first_name']); ?>
                                            </h3>
                                        </div>
                                        <p class="text-xs font-mono mt-1" style="color: var(--accent-color, #38bdf8);">
                                            <?php echo htmlspecialchars($st['student_code']); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[10px] uppercase font-semibold block" style="color: var(--text-secondary);">Total</span>
                                        <span class="row-total text-xl font-mono font-bold" style="color: var(--accent-color, #38bdf8);">
                                            <?php echo number_format($total, 2); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Assessment Inputs Grid -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-medium mb-1" style="color: var(--text-secondary);">CA 1 (10)</label>
                                        <input 
                                            type="number" 
                                            step="0.01" 
                                            min="0" 
                                            max="10" 
                                            name="scores[<?php echo $st['student_id']; ?>][ca1]" 
                                            value="<?php echo $st['ca1_score'] !== null ? htmlspecialchars($st['ca1_score']) : ''; ?>" 
                                            class="score-input w-full rounded-xl px-3 py-2 text-center text-xs font-mono focus:outline-none transition-colors"
                                            style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"
                                            oninput="calculateCardTotal(this)"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-medium mb-1" style="color: var(--text-secondary);">CA 2 (10)</label>
                                        <input 
                                            type="number" 
                                            step="0.01" 
                                            min="0" 
                                            max="10" 
                                            name="scores[<?php echo $st['student_id']; ?>][ca2]" 
                                            value="<?php echo $st['ca2_score'] !== null ? htmlspecialchars($st['ca2_score']) : ''; ?>" 
                                            class="score-input w-full rounded-xl px-3 py-2 text-center text-xs font-mono focus:outline-none transition-colors"
                                            style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"
                                            oninput="calculateCardTotal(this)"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-medium mb-1" style="color: var(--text-secondary);">CA 3 (10)</label>
                                        <input 
                                            type="number" 
                                            step="0.01" 
                                            min="0" 
                                            max="10" 
                                            name="scores[<?php echo $st['student_id']; ?>][ca3]" 
                                            value="<?php echo $st['ca3_score'] !== null ? htmlspecialchars($st['ca3_score']) : ''; ?>" 
                                            class="score-input w-full rounded-xl px-3 py-2 text-center text-xs font-mono focus:outline-none transition-colors"
                                            style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"
                                            oninput="calculateCardTotal(this)"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-medium mb-1" style="color: var(--text-secondary);">Exam (70)</label>
                                        <input 
                                            type="number" 
                                            step="0.01" 
                                            min="0" 
                                            max="70" 
                                            name="scores[<?php echo $st['student_id']; ?>][exam]" 
                                            value="<?php echo $st['exam_score'] !== null ? htmlspecialchars($st['exam_score']) : ''; ?>" 
                                            class="score-input w-full rounded-xl px-3 py-2 text-center text-xs font-mono focus:outline-none transition-colors"
                                            style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"
                                            oninput="calculateCardTotal(this)"
                                        >
                                    </div>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Sticky Action Bar / Submit Button -->
                <div class="sticky bottom-4 glass-card p-4 rounded-2xl flex justify-end shadow-2xl" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                    <button type="submit" class="glow-button px-6 py-2.5 text-white text-xs font-bold rounded-xl flex items-center space-x-2 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Save Scores</span>
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="glass-card text-center py-12 rounded-2xl" style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary);">
                <p>No active students or subjects found for the selected filter parameters.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function calculateCardTotal(input) {
    const card = input.closest('.score-card');
    const inputs = card.querySelectorAll('.score-input');
    let total = 0;
    
    inputs.forEach(inp => {
        const val = parseFloat(inp.value);
        if (!isNaN(val)) {
            total += val;
        }
    });

    const totalCell = card.querySelector('.row-total');
    if (totalCell) {
        totalCell.innerText = total.toFixed(2);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>