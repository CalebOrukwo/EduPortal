<?php
/**
 * Student Curriculum & Syllabus Viewer Page
 * File: student/curriculum.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Student, Admin, or Staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['student', 'admin', 'staff'])) {
    setFlashMessage('error', 'Access denied. Please log in.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? 'student';

// Resolve Student Information & Current Class
$student = null;
if ($userRole === 'student') {
    $stStmt = $pdo->prepare("
        SELECT s.id, s.student_code, s.first_name, s.last_name, s.current_class_id, c.name AS class_name
        FROM sch_students s
        INNER JOIN sch_classes c ON s.current_class_id = c.id
        WHERE s.id = :user_id LIMIT 1
    ");
    $stStmt->execute([':user_id' => $userId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Admin or Staff viewing a specific student's curriculum
    $studentIdParam = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    if ($studentIdParam > 0) {
        $stStmt = $pdo->prepare("
            SELECT s.id, s.student_code, s.first_name, s.last_name, s.current_class_id, c.name AS class_name
            FROM sch_students s
            INNER JOIN sch_classes c ON s.current_class_id = c.id
            WHERE s.id = :student_id LIMIT 1
        ");
        $stStmt->execute([':student_id' => $studentIdParam]);
        $student = $stStmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!$student) {
    setFlashMessage('error', 'Student record not found.');
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$currentClassId = (int)$student['current_class_id'];

// Fetch Active Term
$currentTermStmt = $pdo->query("
    SELECT t.id, t.term_name, s.name AS session_name 
    FROM sch_terms t
    INNER JOIN sch_sessions s ON t.session_id = s.id
    WHERE t.is_current = 1 LIMIT 1
");
$currentTerm = $currentTermStmt->fetch(PDO::FETCH_ASSOC);
$currentTermId = $currentTerm ? (int)$currentTerm['id'] : 0;

// Filter Term Selection (defaults to current term if available)
$selectedTermId = isset($_GET['term_id']) ? (int)$_GET['term_id'] : $currentTermId;

// Fetch all available terms for dropdown filter
$allTermsStmt = $pdo->query("
    SELECT t.id, t.term_name, s.name AS session_name, t.is_current
    FROM sch_terms t
    INNER JOIN sch_sessions s ON t.session_id = s.id
    ORDER BY s.id DESC, t.id ASC
");
$termsList = $allTermsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Approved Curricula for Student's Class and Selected Term
$curricula = [];
if ($selectedTermId > 0 && $currentClassId > 0) {
    $currStmt = $pdo->prepare("
        SELECT 
            c.id, c.content, c.status,
            sub.name AS subject_name, sub.code AS subject_code,
            u.full_name AS teacher_name
        FROM sch_curricula c
        INNER JOIN sch_subjects sub ON c.subject_id = sub.id
        INNER JOIN sch_users u ON c.staff_id = u.id
        WHERE c.class_id = :class_id 
          AND c.term_id = :term_id 
          AND c.status = 'approved'
        ORDER BY sub.name ASC
    ");
    $currStmt->execute([
        ':class_id' => $currentClassId,
        ':term_id'  => $selectedTermId
    ]);
    $curricula = $currStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header Section -->
    <div class="glass-card p-6 rounded-2xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold flex items-center gap-2" style="color: var(--text-primary);">
                <svg class="w-7 h-7" style="color: var(--accent-cyan, #38bdf8);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <span>Learning Syllabus & Scheme</span>
            </h1>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                Class: <strong style="color: var(--accent-cyan, #38bdf8);"><?php echo htmlspecialchars($student['class_name']); ?></strong> | 
                Student: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
            </p>
        </div>

        <!-- Term Selector Filter -->
        <form method="GET" action="" class="flex items-center space-x-2">
            <?php if ($userRole !== 'student'): ?>
                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
            <?php endif; ?>
            <label for="term_id" class="text-xs whitespace-nowrap" style="color: var(--text-secondary);">Academic Term:</label>
            <select name="term_id" id="term_id" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-xs font-medium focus:outline-none" style="background-color: var(--bg-input, rgba(15, 23, 42, 0.6)); border: 1px solid var(--border-subtle, rgba(255, 255, 255, 0.1)); color: var(--text-primary);">
                <?php foreach ($termsList as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $selectedTermId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['session_name'] . ' - ' . $t['term_name']); ?>
                        <?php echo $t['is_current'] ? ' (Active)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Curriculum Content Grid -->
    <?php if (!empty($curricula)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($curricula as $item): ?>
                <div class="glass-card p-6 rounded-2xl space-y-4 flex flex-col justify-between" style="border: 1px solid var(--border-subtle, rgba(255, 255, 255, 0.08));">
                    <div>
                        <!-- Subject Title & Teacher Info -->
                        <div class="flex justify-between items-start pb-3 mb-4" style="border-bottom: 1px solid var(--border-subtle, rgba(255, 255, 255, 0.1));">
                            <div>
                                <h2 class="text-lg font-bold flex items-center gap-2" style="color: var(--text-primary);">
                                    <span><?php echo htmlspecialchars($item['subject_name']); ?></span>
                                    <?php if (!empty($item['subject_code'])): ?>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-mono" style="background: rgba(56, 189, 248, 0.1); color: var(--accent-cyan, #38bdf8); border: 1px solid rgba(56, 189, 248, 0.2);">
                                            <?php echo htmlspecialchars($item['subject_code']); ?>
                                        </span>
                                    <?php endif; ?>
                                </h2>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Instructor: <span class="font-medium" style="color: var(--text-primary);"><?php echo htmlspecialchars($item['teacher_name']); ?></span>
                                </p>
                            </div>
                            <span class="px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider rounded-full" style="background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2);">
                                Approved
                            </span>
                        </div>

                        <!-- Scheme Content -->
                        <div class="text-xs leading-relaxed space-y-2 whitespace-pre-line" style="color: var(--text-primary);">
                            <?php echo nl2br(htmlspecialchars($item['content'])); ?>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-between items-center text-[11px]" style="border-top: 1px solid var(--border-subtle, rgba(255, 255, 255, 0.08)); color: var(--text-secondary);">
                        <span>Read-only syllabus</span>
                        <span class="font-mono" style="color: var(--accent-cyan, #38bdf8);">Class: <?php echo htmlspecialchars($student['class_name']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card p-12 rounded-2xl text-center space-y-4">
            <svg class="w-12 h-12 mx-auto" style="color: var(--text-secondary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-base font-medium" style="color: var(--text-primary);">No Approved Syllabus Found</h3>
            <p class="text-xs max-w-md mx-auto" style="color: var(--text-secondary);">
                There are currently no approved lesson schemes or curricula uploaded for <?php echo htmlspecialchars($student['class_name']); ?> for the selected academic term.
            </p>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>