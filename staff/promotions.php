<?php
/**
 * End-of-Session Student Promotion Recommendations
 * File: staff/promotions.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Valid roles are 'admin' or 'staff'
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'admin'])) {
    setFlashMessage('error', 'Access denied. Staff privileges required.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$staffId  = (int)($_SESSION['staff_id'] ?? $userId);
$userRole = $_SESSION['role'] ?? 'staff';

$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// -------------------------------------------------------------------------
// 1. ACTIVE PROMOTIONAL TERM CHECK
// -------------------------------------------------------------------------
$termQuery = $pdo->query("
    SELECT t.id, t.term_name, s.name AS session_name, t.is_promotional 
    FROM sch_terms t
    INNER JOIN sch_sessions s ON t.session_id = s.id
    WHERE (t.is_current = 1 OR s.is_active = 1)
    ORDER BY t.is_current DESC, t.id DESC 
    LIMIT 1
");
$currentTerm = $termQuery->fetch(PDO::FETCH_ASSOC);

if (!$currentTerm) {
    $currentTerm = $pdo->query("
        SELECT t.id, t.term_name, s.name AS session_name, t.is_promotional 
        FROM sch_terms t
        INNER JOIN sch_sessions s ON t.session_id = s.id
        ORDER BY t.id DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
}

$currentTermId   = $currentTerm ? (int)$currentTerm['id'] : 0;
$isPromotional   = $currentTerm ? (int)$currentTerm['is_promotional'] === 1 : false;

// -------------------------------------------------------------------------
// POST HANDLER: BATCH SUBMIT PROMOTION RECOMMENDATIONS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_promotions') {
    try {
        if (!$isPromotional) {
            throw new Exception("Promotion recommendations can only be submitted during active promotional terms.");
        }

        $classId     = (int)($_POST['class_id'] ?? 0);
        $toClassId   = !empty($_POST['to_class_id']) ? (int)$_POST['to_class_id'] : NULL;
        $promotions  = $_POST['promotions'] ?? []; // Array of student_ids checked for promotion

        if ($classId <= 0) {
            throw new Exception("Invalid current class selected.");
        }

        // Verify Form Teacher Permissions
        if ($userRole !== 'admin') {
            $permCheck = $pdo->prepare("
                SELECT 1 FROM sch_staff_classes 
                WHERE (staff_id = :staff_id OR staff_id = :user_id) 
                  AND class_id = :class_id 
                  AND is_form_teacher = 1 
                LIMIT 1
            ");
            $permCheck->execute([
                ':staff_id' => $staffId,
                ':user_id'  => $userId,
                ':class_id' => $classId
            ]);
            if (!$permCheck->fetch()) {
                throw new Exception("You are only authorized to recommend promotions for classes where you are assigned as Form Teacher.");
            }
        }

        // Fetch target class (next_class_id) if not supplied
        if (empty($toClassId)) {
            $targetQuery = $pdo->prepare("SELECT next_class_id FROM sch_classes WHERE id = :class_id LIMIT 1");
            $targetQuery->execute([':class_id' => $classId]);
            $toClassId = $targetQuery->fetchColumn();
        }

        if (empty($toClassId)) {
            throw new Exception("This class does not have a designated higher class for promotion.");
        }

        // Fetch all active students in this class
        $studentsQuery = $pdo->prepare("SELECT id FROM sch_students WHERE current_class_id = :class_id AND status = 'active'");
        $studentsQuery->execute([':class_id' => $classId]);
        $allClassStudents = $studentsQuery->fetchAll(PDO::FETCH_COLUMN);

        if (empty($allClassStudents)) {
            throw new Exception("No active students found in this class.");
        }

        $pdo->beginTransaction();

        $checkExisting = $pdo->prepare("
            SELECT id FROM sch_promotion_requests 
            WHERE student_id = :student_id AND term_id = :term_id 
            LIMIT 1
        ");

        $insertStmt = $pdo->prepare("
            INSERT INTO sch_promotion_requests (student_id, from_class_id, to_class_id, term_id, staff_id, status)
            VALUES (:student_id, :from_class_id, :to_class_id, :term_id, :staff_id, 'pending')
        ");

        $updateStmt = $pdo->prepare("
            UPDATE sch_promotion_requests 
            SET from_class_id = :from_class_id, to_class_id = :to_class_id, staff_id = :staff_id, status = 'pending'
            WHERE id = :id
        ");

        $recommendedCount = 0;

        foreach ($allClassStudents as $stId) {
            // Only process students explicitly selected/checked in the form
            if (in_array($stId, $promotions)) {
                $checkExisting->execute([
                    ':student_id' => $stId,
                    ':term_id'    => $currentTermId
                ]);
                $existing = $checkExisting->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $updateStmt->execute([
                        ':from_class_id' => $classId,
                        ':to_class_id'   => $toClassId,
                        ':staff_id'      => $userId,
                        ':id'            => $existing['id']
                    ]);
                } else {
                    $insertStmt->execute([
                        ':student_id'    => $stId,
                        ':from_class_id' => $classId,
                        ':to_class_id'   => $toClassId,
                        ':term_id'       => $currentTermId,
                        ':staff_id'      => $userId
                    ]);
                }
                $recommendedCount++;
            }
        }

        $pdo->commit();

        setFlashMessage('success', "Successfully batch-submitted {$recommendedCount} promotion recommendations.");
        header("Location: " . BASE_URL . "staff/promotions.php?class_id={$classId}");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        header("Location: " . BASE_URL . "staff/promotions.php?class_id={$selectedClassId}");
        exit();
    }
}

// -------------------------------------------------------------------------
// 2. FETCH FORM TEACHER CLASSES
// -------------------------------------------------------------------------
$formClasses = [];
if ($userRole === 'admin') {
    $classesStmt = $pdo->query("
        SELECT c.id, c.name, c.next_class_id, nc.name AS next_class_name
        FROM sch_classes c
        LEFT JOIN sch_classes nc ON c.next_class_id = nc.id
        ORDER BY c.id ASC
    ");
    $formClasses = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $classesStmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name, c.next_class_id, nc.name AS next_class_name
        FROM sch_classes c
        INNER JOIN sch_staff_classes sc ON sc.class_id = c.id
        LEFT JOIN sch_classes nc ON c.next_class_id = nc.id
        WHERE (sc.staff_id = :staff_id OR sc.staff_id = :user_id) 
          AND sc.is_form_teacher = 1
        ORDER BY c.id ASC
    ");
    $classesStmt->execute([
        ':staff_id' => $staffId,
        ':user_id'  => $userId
    ]);
    $formClasses = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Auto-select first class if no explicit class chosen
$availableClassIds = array_column($formClasses, 'id');
if (($selectedClassId === 0 || !in_array($selectedClassId, $availableClassIds)) && !empty($formClasses)) {
    $selectedClassId = (int)$formClasses[0]['id'];
}

// Selected Class Metadata
$currentClassInfo = null;
foreach ($formClasses as $fc) {
    if ((int)$fc['id'] === $selectedClassId) {
        $currentClassInfo = $fc;
        break;
    }
}

// -------------------------------------------------------------------------
// 3. FETCH STUDENTS & ACADEMIC CUMULATIVE STATS
// -------------------------------------------------------------------------
$studentsData = [];
if ($selectedClassId > 0 && $currentTermId > 0 && $isPromotional) {
    $studentsStmt = $pdo->prepare("
        SELECT 
            st.id AS student_id,
            st.student_code,
            st.first_name,
            st.last_name,
            pr.status AS promotion_status,
            pr.id AS promotion_request_id,
            COALESCE(AVG(ass.total_score), 0) AS average_score
        FROM sch_students st
        LEFT JOIN sch_assessments ass ON st.id = ass.student_id AND ass.term_id = :ass_term_id
        LEFT JOIN sch_promotion_requests pr ON st.id = pr.student_id AND pr.term_id = :pr_term_id
        WHERE st.current_class_id = :where_class_id
          AND st.status = 'active'
        GROUP BY st.id
        ORDER BY st.last_name ASC, st.first_name ASC
    ");
    
    $studentsStmt->execute([
        ':ass_term_id'    => $currentTermId,
        ':pr_term_id'     => $currentTermId,
        ':where_class_id' => $selectedClassId
    ]);
    $studentsData = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header Block -->
    <div class="glass-card p-6 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs font-semibold tracking-wider mb-1" style="color: var(--accent-cyan, #38bdf8);">
                <span>Staff Portal</span>
                <span>&bull;</span>
                <span>Promotions</span>
            </div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Student Promotion Recommendations</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">
                Session: <span class="font-medium" style="color: var(--text-primary);"><?php echo htmlspecialchars($currentTerm['session_name'] ?? 'N/A'); ?></span> | 
                Term: <span class="font-medium" style="color: var(--text-primary);"><?php echo htmlspecialchars($currentTerm['term_name'] ?? 'N/A'); ?></span>
            </p>
        </div>
        
        <div>
            <?php if ($isPromotional): ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" style="background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2);">
                    <span class="w-2 h-2 rounded-full mr-2 animate-pulse" style="background-color: #4ade80;"></span>
                    Promotional Term Active
                </span>
            <?php else: ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" style="background: rgba(234, 179, 8, 0.1); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.2);">
                    Non-Promotional Term
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isPromotional): ?>
        <!-- Non-Promotional Warning Box -->
        <div class="glass-card p-8 rounded-2xl text-center space-y-3">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto" style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.2); color: #facc15;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 class="text-lg font-bold" style="color: var(--text-primary);">Promotion Module Locked</h3>
            <p class="text-xs max-w-md mx-auto" style="color: var(--text-secondary);">
                Student promotions are disabled during regular terms. This module automatically activates during promotional terms (<code style="color: var(--accent-cyan, #38bdf8);">is_promotional = 1</code>).
            </p>
        </div>
    <?php else: ?>

        <!-- Class Filter Selector -->
        <div class="glass-card p-5 rounded-2xl">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Your Form Classes</label>
                    <select name="class_id" onchange="this.form.submit()" class="w-full rounded-xl px-4 py-2.5 text-xs font-medium focus:outline-none" style="background-color: var(--bg-input, rgba(15, 23, 42, 0.6)); border: 1px solid var(--border-subtle, rgba(255, 255, 255, 0.1)); color: var(--text-primary);">
                        <?php if (!empty($formClasses)): ?>
                            <?php foreach ($formClasses as $fc): ?>
                                <option value="<?php echo $fc['id']; ?>" <?php echo $selectedClassId === (int)$fc['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fc['name']); ?> 
                                    <?php echo !empty($fc['next_class_name']) ? " &rarr; " . htmlspecialchars($fc['next_class_name']) : " (Terminal Class)"; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="0">No Form Classes Assigned</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="flex items-end">
                    <div class="text-xs" style="color: var(--text-secondary);">
                        Target Class: 
                        <strong class="font-mono text-sm block mt-0.5" style="color: var(--accent-cyan, #38bdf8);">
                            <?php echo htmlspecialchars($currentClassInfo['next_class_name'] ?? 'None (Graduation/Terminal)'); ?>
                        </strong>
                    </div>
                </div>
            </form>
        </div>

        <!-- Promotion Recommendation Form -->
        <div class="glass-card p-6 rounded-2xl">
            <?php if (empty($formClasses)): ?>
                <div class="text-center py-8 text-sm" style="color: var(--text-secondary);">
                    You are not assigned as a Form Teacher for any class in <code style="color: var(--accent-cyan, #38bdf8);">sch_staff_classes</code>.
                </div>
            <?php elseif (!empty($studentsData)): ?>
                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="action" value="submit_promotions">
                    <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
                    <input type="hidden" name="to_class_id" value="<?php echo (int)($currentClassInfo['next_class_id'] ?? 0); ?>">

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between text-xs pb-4 gap-3" style="border-bottom: 1px solid var(--border-subtle, rgba(255, 255, 255, 0.1));">
                        <div class="flex items-center space-x-3">
                            <span style="color: var(--text-secondary);">Quick Selection:</span>
                            <button type="button" onclick="selectAll(true)" class="px-3 py-1 rounded-lg transition text-xs font-medium" style="background: rgba(56, 189, 248, 0.1); color: var(--accent-cyan, #38bdf8); border: 1px solid rgba(56, 189, 248, 0.2);">Select All</button>
                            <button type="button" onclick="selectAll(false)" class="px-3 py-1 rounded-lg transition text-xs font-medium" style="background: var(--bg-input, rgba(15, 23, 42, 0.6)); color: var(--text-secondary); border: 1px solid var(--border-subtle, rgba(255, 255, 255, 0.1));">Deselect All</button>
                        </div>
                        <span style="color: var(--text-secondary);">Target Promotion Class: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($currentClassInfo['next_class_name'] ?? 'N/A'); ?></strong></span>
                    </div>

                    <!-- Responsive Card Grid Layout -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($studentsData as $st): ?>
                            <?php 
                                $isRecommended = !empty($st['promotion_status']);
                                $reqStatus = $st['promotion_status'] ?? 'None';
                            ?>
                            <div class="p-4 rounded-xl space-y-3 transition" style="background: var(--bg-card, rgba(30, 41, 59, 0.5)); border: 1px solid var(--border-subtle, rgba(255, 255, 255, 0.08));">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-mono font-bold" style="color: var(--accent-cyan, #38bdf8);">
                                        <?php echo htmlspecialchars($st['student_code']); ?>
                                    </span>
                                    <label class="flex items-center space-x-2 cursor-pointer text-xs font-medium" style="color: var(--text-secondary);">
                                        <span>Recommend</span>
                                        <input 
                                            type="checkbox" 
                                            name="promotions[]" 
                                            value="<?php echo $st['student_id']; ?>" 
                                            class="promo-checkbox w-4 h-4 rounded cursor-pointer"
                                            style="accent-color: var(--accent-cyan, #38bdf8);"
                                            <?php echo $isRecommended ? 'checked' : ''; ?>
                                        >
                                    </label>
                                </div>

                                <div class="font-bold text-sm" style="color: var(--text-primary);">
                                    <?php echo htmlspecialchars($st['last_name'] . ' ' . $st['first_name']); ?>
                                </div>

                                <div class="flex items-center justify-between text-xs pt-2" style="border-top: 1px dashed var(--border-subtle, rgba(255, 255, 255, 0.08));">
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider" style="color: var(--text-secondary);">Avg. Score</span>
                                        <span class="font-mono font-bold text-xs" style="color: var(--text-primary);"><?php echo number_format((float)$st['average_score'], 1); ?>%</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="block text-[10px] uppercase tracking-wider mb-0.5" style="color: var(--text-secondary);">Status</span>
                                        <?php if ($reqStatus === 'pending'): ?>
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: rgba(234, 179, 8, 0.1); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.2);">Pending</span>
                                        <?php elseif ($reqStatus === 'approved'): ?>
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2);">Approved</span>
                                        <?php elseif ($reqStatus === 'declined'): ?>
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2);">Declined</span>
                                        <?php else: ?>
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: var(--bg-input, rgba(15, 23, 42, 0.6)); color: var(--text-secondary);">Not Recommended</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex justify-end pt-4" style="border-top: 1px solid var(--border-subtle, rgba(255, 255, 255, 0.1));">
                        <button type="submit" class="glow-button px-6 py-2.5 text-white text-xs font-bold rounded-xl flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Submit Promotion Recommendations</span>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-12 text-sm" style="color: var(--text-secondary);">
                    No active students registered in this class.
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

<script>
function selectAll(checked) {
    const checkboxes = document.querySelectorAll('.promo-checkbox');
    checkboxes.forEach(cb => cb.checked = checked);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>