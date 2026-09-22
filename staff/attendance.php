<?php
/**
 * Staff Attendance Register
 * File: staff/attendance.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'admin'])) {
    setFlashMessage('error', 'Access denied.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Map User / Staff Identifiers
$userId   = (int)($_SESSION['user_id'] ?? 0);
$staffId  = (int)($_SESSION['staff_id'] ?? $userId);
$userRole = $_SESSION['role'] ?? 'staff';

// Input Filters
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedDate    = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// -------------------------------------------------------------------------
// POST HANDLER: SAVE ATTENDANCE
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    try {
        $classId    = (int)($_POST['class_id'] ?? 0);
        $termId     = (int)($_POST['term_id'] ?? 0);
        $attDate    = trim($_POST['date'] ?? '');
        $attendance = $_POST['attendance'] ?? [];

        if ($classId <= 0 || $termId <= 0 || empty($attDate)) {
            throw new Exception("Invalid parameters submitted.");
        }

        $pdo->beginTransaction();

        $checkStmt = $pdo->prepare("
            SELECT id FROM sch_attendance 
            WHERE student_id = :student_id AND class_id = :class_id AND term_id = :term_id AND date = :date 
            LIMIT 1
        ");

        $insertStmt = $pdo->prepare("
            INSERT INTO sch_attendance (student_id, class_id, term_id, date, status)
            VALUES (:student_id, :class_id, :term_id, :date, :status)
        ");

        $updateStmt = $pdo->prepare("
            UPDATE sch_attendance 
            SET status = :status
            WHERE id = :id
        ");

        $validStatuses = ['present', 'absent', 'late'];

        foreach ($attendance as $studentId => $status) {
            $studentId = (int)$studentId;
            $status = strtolower(trim($status));
            if (!in_array($status, $validStatuses)) {
                $status = 'absent';
            }

            $checkStmt->execute([
                ':student_id' => $studentId,
                ':class_id'   => $classId,
                ':term_id'    => $termId,
                ':date'       => $attDate
            ]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updateStmt->execute([
                    ':status' => $status,
                    ':id'     => $existing['id']
                ]);
            } else {
                $insertStmt->execute([
                    ':student_id' => $studentId,
                    ':class_id'   => $classId,
                    ':term_id'    => $termId,
                    ':date'       => $attDate,
                    ':status'     => $status
                ]);
            }
        }

        $pdo->commit();
        setFlashMessage('success', 'Attendance saved successfully.');
        header("Location: " . BASE_URL . "staff/attendance.php?class_id={$classId}&date={$attDate}");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        header("Location: " . BASE_URL . "staff/attendance.php?class_id={$selectedClassId}&date={$selectedDate}");
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL
// -------------------------------------------------------------------------

// 1. Fetch Active Term
$currentTermId = 0;
$termQuery = $pdo->query("
    SELECT t.id, t.term_name 
    FROM sch_terms t
    LEFT JOIN sch_sessions s ON t.session_id = s.id
    WHERE t.is_current = 1 OR t.is_current = '1' OR s.is_active = 1
    ORDER BY t.is_current DESC, t.id DESC 
    LIMIT 1
");
$termRow = $termQuery->fetch(PDO::FETCH_ASSOC);

if ($termRow) {
    $currentTermId = (int)$termRow['id'];
} else {
    $fallbackTerm = $pdo->query("SELECT id FROM sch_terms ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($fallbackTerm) {
        $currentTermId = (int)$fallbackTerm['id'];
    }
}

// 2. Fetch Classes Assigned to User/Staff
$classes = [];
if ($userRole === 'admin') {
    $classesStmt = $pdo->query("SELECT id, name FROM sch_classes ORDER BY id ASC");
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $classesStmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name 
        FROM sch_classes c
        INNER JOIN sch_staff_classes sc ON sc.class_id = c.id
        WHERE sc.staff_id = :staff_id OR sc.staff_id = :user_id
        ORDER BY c.id ASC
    ");
    $classesStmt->execute([
        ':staff_id' => $staffId,
        ':user_id'  => $userId
    ]);
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Auto-select first class if none selected or valid
$availableClassIds = array_column($classes, 'id');
if (($selectedClassId === 0 || !in_array($selectedClassId, $availableClassIds)) && !empty($classes)) {
    $selectedClassId = (int)$classes[0]['id'];
}

// 3. Fetch Students & Existing Attendance
$students = [];
if ($selectedClassId > 0 && $currentTermId > 0) {
    $studentsStmt = $pdo->prepare("
        SELECT 
            st.id AS student_id,
            st.student_code,
            st.first_name,
            st.last_name,
            att.status AS attendance_status
        FROM sch_students st
        LEFT JOIN sch_attendance att ON st.id = att.student_id 
            AND att.class_id = :att_class_id 
            AND att.term_id = :att_term_id 
            AND att.date = :att_date
        WHERE st.current_class_id = :where_class_id
          AND st.status = 'active'
        ORDER BY st.last_name ASC, st.first_name ASC
    ");
    $studentsStmt->execute([
        ':att_class_id'   => $selectedClassId,
        ':att_term_id'    => $currentTermId,
        ':att_date'       => $selectedDate,
        ':where_class_id' => $selectedClassId
    ]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header -->
    <div class="glass-card p-6 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div>
            <div class="flex items-center space-x-2 text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--accent-color, #38bdf8);">
                <span>Staff Portal</span>
                <span>&bull;</span>
                <span>Attendance</span>
            </div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Daily Attendance Register</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">Record and manage daily student attendance status.</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="glass-card p-5 rounded-2xl" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Date</label>
                <input 
                    type="date" 
                    name="date" 
                    value="<?php echo htmlspecialchars($selectedDate); ?>" 
                    onchange="this.form.submit()"
                    class="w-full rounded-xl px-4 py-2.5 text-xs font-medium focus:outline-none transition-colors"
                    style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"
                >
            </div>

            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Class</label>
                <select name="class_id" onchange="this.form.submit()" class="w-full rounded-xl px-4 py-2.5 text-xs font-medium focus:outline-none transition-colors" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selectedClassId === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="0">No Classes Assigned</option>
                    <?php endif; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Register Body -->
    <div>
        <?php if ($currentTermId === 0): ?>
            <div class="glass-card text-center py-8 rounded-2xl text-sm" style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: #facc15;">
                No active term found in database.
            </div>
        <?php elseif (empty($classes)): ?>
            <div class="glass-card text-center py-8 rounded-2xl text-sm" style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary);">
                No classes assigned to your user account (User ID: <?php echo $userId; ?>).
            </div>
        <?php elseif (!empty($students)): ?>
            <form method="POST" action="" class="space-y-6">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
                <input type="hidden" name="term_id" value="<?php echo $currentTermId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">

                <!-- Quick Action Bar -->
                <div class="glass-card p-4 rounded-2xl flex items-center justify-between flex-wrap gap-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                    <span class="text-xs font-medium" style="color: var(--text-secondary);">Batch Action: Mark all as</span>
                    <div class="flex items-center space-x-2 text-xs">
                        <button type="button" onclick="setAll('present')" class="px-3 py-1.5 rounded-lg font-medium transition-colors border" style="background: rgba(34, 197, 94, 0.1); color: #4ade80; border-color: rgba(34, 197, 94, 0.2);">Present</button>
                        <button type="button" onclick="setAll('absent')" class="px-3 py-1.5 rounded-lg font-medium transition-colors border" style="background: rgba(239, 68, 68, 0.1); color: #f87171; border-color: rgba(239, 68, 68, 0.2);">Absent</button>
                        <button type="button" onclick="setAll('late')" class="px-3 py-1.5 rounded-lg font-medium transition-colors border" style="background: rgba(234, 179, 8, 0.1); color: #facc15; border-color: rgba(234, 179, 8, 0.2);">Late</button>
                    </div>
                </div>

                <!-- Responsive Card Grid Layout -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($students as $idx => $st): ?>
                        <?php $status = $st['attendance_status'] ?? 'present'; ?>
                        <div class="glass-card p-5 rounded-2xl flex flex-col justify-between transition-all duration-300 hover:shadow-lg" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                            
                            <!-- Student Meta -->
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
                            </div>

                            <!-- Attendance Radio Selection Options -->
                            <div class="grid grid-cols-3 gap-2">
                                <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border cursor-pointer transition-all" style="background: var(--bg-input); border-color: var(--border-subtle);">
                                    <input type="radio" name="attendance[<?php echo $st['student_id']; ?>]" value="present" class="att-radio mb-1" <?php echo $status === 'present' ? 'checked' : ''; ?>>
                                    <span class="text-xs font-semibold" style="color: #4ade80;">Present</span>
                                </label>

                                <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border cursor-pointer transition-all" style="background: var(--bg-input); border-color: var(--border-subtle);">
                                    <input type="radio" name="attendance[<?php echo $st['student_id']; ?>]" value="absent" class="att-radio mb-1" <?php echo $status === 'absent' ? 'checked' : ''; ?>>
                                    <span class="text-xs font-semibold" style="color: #f87171;">Absent</span>
                                </label>

                                <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border cursor-pointer transition-all" style="background: var(--bg-input); border-color: var(--border-subtle);">
                                    <input type="radio" name="attendance[<?php echo $st['student_id']; ?>]" value="late" class="att-radio mb-1" <?php echo $status === 'late' ? 'checked' : ''; ?>>
                                    <span class="text-xs font-semibold" style="color: #facc15;">Late</span>
                                </label>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Sticky Action Bar / Submit Button -->
                <div class="sticky bottom-4 glass-card p-4 rounded-2xl flex justify-end shadow-2xl" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                    <button type="submit" class="glow-button px-6 py-2.5 text-white text-xs font-bold rounded-xl flex items-center space-x-2 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Save Register</span>
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="glass-card text-center py-12 rounded-2xl text-sm" style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary);">
                No active students found in this class.
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function setAll(statusVal) {
    const radios = document.querySelectorAll(`.att-radio[value="${statusVal}"]`);
    radios.forEach(radio => radio.checked = true);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>