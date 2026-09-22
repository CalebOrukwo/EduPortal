<?php
/**
 * Staff Portal Dashboard
 * File: staff/index.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Ensure logged in user is Staff or Admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'admin'])) {
    setFlashMessage('error', 'Access denied. Staff privileges required.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$staffId  = (int)($_SESSION['staff_id'] ?? $userId);
$userRole = $_SESSION['role'] ?? 'staff';

// Fetch User Info
$userStmt = $pdo->prepare("SELECT full_name, email FROM sch_users WHERE id = :id LIMIT 1");
$userStmt->execute([':id' => $userId]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

// -------------------------------------------------------------------------
// 1. FETCH CURRENT ACTIVE SESSION & TERM
// -------------------------------------------------------------------------
$termQuery = $pdo->query("
    SELECT t.id, t.term_name, t.start_date, t.end_date, t.is_promotional, s.name AS session_name
    FROM sch_terms t
    INNER JOIN sch_sessions s ON t.session_id = s.id
    WHERE t.is_current = 1 OR s.is_active = 1
    ORDER BY t.is_current DESC, t.id DESC
    LIMIT 1
");
$currentTerm = $termQuery->fetch(PDO::FETCH_ASSOC);

if (!$currentTerm) {
    $currentTerm = $pdo->query("
        SELECT t.id, t.term_name, t.start_date, t.end_date, t.is_promotional, s.name AS session_name
        FROM sch_terms t
        INNER JOIN sch_sessions s ON t.session_id = s.id
        ORDER BY t.id DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
}

$currentTermId = $currentTerm ? (int)$currentTerm['id'] : 0;

// -------------------------------------------------------------------------
// 2. FETCH ASSIGNED CLASSES & FORM TEACHER STATUS
// -------------------------------------------------------------------------
$assignedClassesStmt = $pdo->prepare("
    SELECT c.id, c.name, sc.is_form_teacher
    FROM sch_staff_classes sc
    INNER JOIN sch_classes c ON sc.class_id = c.id
    WHERE sc.staff_id = :staff_id OR sc.staff_id = :user_id
    ORDER BY c.id ASC
");
$assignedClassesStmt->execute([
    ':staff_id' => $staffId,
    ':user_id'  => $userId
]);
$assignedClasses = $assignedClassesStmt->fetchAll(PDO::FETCH_ASSOC);

$totalClasses   = count($assignedClasses);
$formClasses    = array_filter($assignedClasses, fn($c) => (int)$c['is_form_teacher'] === 1);
$isFormTeacher  = !empty($formClasses);

// -------------------------------------------------------------------------
// 3. FETCH SUBJECTS TAUGHT
// -------------------------------------------------------------------------
$assignedClassIds = array_column($assignedClasses, 'id');

$assignedSubjects = [];
if (!empty($assignedClassIds)) {
    $inClause = implode(',', array_map('intval', $assignedClassIds));
    $subjectsStmt = $pdo->query("
        SELECT DISTINCT sub.id, sub.name, sub.code
        FROM sch_class_subjects cs
        INNER JOIN sch_subjects sub ON cs.subject_id = sub.id
        WHERE cs.class_id IN ($inClause)
        ORDER BY sub.name ASC
    ");
    $assignedSubjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);
}
$totalSubjects = count($assignedSubjects);

// -------------------------------------------------------------------------
// 4. FETCH PENDING CURRICULUM DRAFTS
// -------------------------------------------------------------------------
$curriculumStmt = $pdo->prepare("
    SELECT 
        cur.id,
        cur.status,
        c.name AS class_name,
        sub.name AS subject_name,
        t.term_name
    FROM sch_curricula cur
    INNER JOIN sch_classes c ON cur.class_id = c.id
    INNER JOIN sch_subjects sub ON cur.subject_id = sub.id
    INNER JOIN sch_terms t ON cur.term_id = t.id
    WHERE cur.staff_id = :staff_id
    ORDER BY cur.id DESC
");
$curriculumStmt->execute([':staff_id' => $userId]);
$curricula = $curriculumStmt->fetchAll(PDO::FETCH_ASSOC);

$pendingDrafts = array_filter($curricula, fn($curr) => $curr['status'] === 'draft');
$pendingDraftsCount = count($pendingDrafts);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Hero / Welcome Header -->
    <div class="glass-card p-6 md:p-8 rounded-2xl flex flex-col md:flex-row justify-between items-start md:items-center gap-6" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div>
            <div class="flex items-center space-x-2 text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--accent-cyan, #38bdf8);">
                <span>Staff Portal</span>
                <span>&bull;</span>
                <span>Dashboard Overview</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight" style="color: var(--text-primary);">
                Welcome back, <?php echo htmlspecialchars($currentUser['full_name'] ?? 'Staff Member'); ?>
            </h1>
            <p class="text-xs md:text-sm mt-1" style="color: var(--text-secondary);">
                Academic Session: <span class="font-semibold" style="color: var(--text-primary);"><?php echo htmlspecialchars($currentTerm['session_name'] ?? 'N/A'); ?></span> | 
                Term: <span class="font-semibold" style="color: var(--text-primary);"><?php echo htmlspecialchars($currentTerm['term_name'] ?? 'N/A'); ?></span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <?php if (!empty($currentTerm['is_promotional'])): ?>
                <a href="<?php echo BASE_URL; ?>staff/promotions.php" class="glow-button px-4 py-2 text-xs font-bold rounded-xl flex items-center space-x-2" style="background: var(--accent-gradient, linear-gradient(135deg, #06b6d4, #3b82f6)); color: #ffffff;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    <span>Promotion Module</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Overview Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Assigned Classes Card -->
        <div class="glass-card p-5 rounded-2xl flex items-center justify-between" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Assigned Classes</p>
                <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);"><?php echo $totalClasses; ?></h3>
                <p class="text-[11px] mt-1" style="color: var(--accent-cyan, #38bdf8);">Active class allocations</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--accent-cyan, #38bdf8);">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>

        <!-- Subjects Taught Card -->
        <div class="glass-card p-5 rounded-2xl flex items-center justify-between" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Subjects Taught</p>
                <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);"><?php echo $totalSubjects; ?></h3>
                <p class="text-[11px] mt-1" style="color: var(--text-secondary);">Across assigned classes</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--accent-blue, #60a5fa);">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
        </div>

        <!-- Form Teacher Status Card -->
        <div class="glass-card p-5 rounded-2xl flex items-center justify-between" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Form Teacher</p>
                <h3 class="text-lg font-bold mt-1" style="color: var(--text-primary);">
                    <?php echo $isFormTeacher ? 'Assigned' : 'Not Assigned'; ?>
                </h3>
                <p class="text-[11px] mt-1" style="color: <?php echo $isFormTeacher ? 'var(--status-success, #4ade80)' : 'var(--text-secondary)'; ?>;">
                    <?php echo $isFormTeacher ? count($formClasses) . ' Form Class(es)' : 'Subject Teacher Only'; ?>
                </p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: <?php echo $isFormTeacher ? 'var(--status-success, #4ade80)' : 'var(--text-secondary)'; ?>;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
        </div>

        <!-- Pending Curriculum Drafts Card -->
        <div class="glass-card p-5 rounded-2xl flex items-center justify-between" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Pending Drafts</p>
                <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);"><?php echo $pendingDraftsCount; ?></h3>
                <p class="text-[11px] mt-1" style="color: var(--status-warning, #facc15);">Curriculum submissions</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--status-warning, #facc15);">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </div>
        </div>

    </div>

    <!-- Main Content Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Column 1 & 2: Assigned Classes & Subjects -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- Assigned Classes List (Responsive Grid Layout) -->
            <div class="glass-card p-6 rounded-2xl space-y-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                <div class="flex items-center justify-between pb-3" style="border-bottom: 1px solid var(--border-subtle);">
                    <h2 class="text-base font-bold flex items-center space-x-2" style="color: var(--text-primary);">
                        <svg class="w-5 h-5" style="color: var(--accent-cyan, #38bdf8);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span>Assigned Classes</span>
                    </h2>
                    <span class="text-xs" style="color: var(--text-secondary);">Total: <?php echo $totalClasses; ?></span>
                </div>

                <?php if (!empty($assignedClasses)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($assignedClasses as $ac): ?>
                            <div class="p-4 rounded-xl flex items-center justify-between transition" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                                <div>
                                    <h4 class="text-sm font-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($ac['name']); ?></h4>
                                    <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                        Role: 
                                        <?php if ((int)$ac['is_form_teacher'] === 1): ?>
                                            <span class="font-semibold" style="color: var(--status-success, #4ade80);">Form Teacher</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">Subject Teacher</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php if ((int)$ac['is_form_teacher'] === 1 && !empty($currentTerm['is_promotional'])): ?>
                                    <a href="<?php echo BASE_URL; ?>staff/promotions.php?class_id=<?php echo $ac['id']; ?>" class="px-3 py-1 text-xs rounded-lg transition" style="background: var(--bg-card); color: var(--accent-cyan, #38bdf8); border: 1px solid var(--border-subtle);">
                                        Promotions
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-xs" style="color: var(--text-secondary);">
                        No class assignments found in system records.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Subjects Taught List (Responsive Card Grid) -->
            <div class="glass-card p-6 rounded-2xl space-y-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                <div class="flex items-center justify-between pb-3" style="border-bottom: 1px solid var(--border-subtle);">
                    <h2 class="text-base font-bold flex items-center space-x-2" style="color: var(--text-primary);">
                        <svg class="w-5 h-5" style="color: var(--accent-blue, #60a5fa);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        <span>Subjects Taught</span>
                    </h2>
                    <span class="text-xs" style="color: var(--text-secondary);">Total: <?php echo $totalSubjects; ?></span>
                </div>

                <?php if (!empty($assignedSubjects)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <?php foreach ($assignedSubjects as $sub): ?>
                            <div class="p-3.5 rounded-xl text-center" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                                <span class="text-xs font-mono font-bold block" style="color: var(--accent-cyan, #38bdf8);"><?php echo htmlspecialchars($sub['code'] ?? 'SUB'); ?></span>
                                <span class="text-xs font-bold block mt-0.5" style="color: var(--text-primary);"><?php echo htmlspecialchars($sub['name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-xs" style="color: var(--text-secondary);">
                        No subject allocations mapped to your assigned classes.
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Column 3: Deadlines & Curriculum Status -->
        <div class="space-y-8">

            <!-- Term Deadlines & Information Card -->
            <div class="glass-card p-6 rounded-2xl space-y-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                <h2 class="text-base font-bold pb-3 flex items-center space-x-2" style="color: var(--text-primary); border-bottom: 1px solid var(--border-subtle);">
                    <svg class="w-5 h-5" style="color: var(--status-warning, #facc15);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Term Deadlines & Info</span>
                </h2>

                <div class="space-y-3 text-xs">
                    <div class="p-3.5 rounded-xl flex justify-between items-center" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                        <span style="color: var(--text-secondary);">Term Start Date</span>
                        <span class="font-mono font-medium" style="color: var(--text-primary);">
                            <?php echo !empty($currentTerm['start_date']) ? date('M d, Y', strtotime($currentTerm['start_date'])) : 'N/A'; ?>
                        </span>
                    </div>

                    <div class="p-3.5 rounded-xl flex justify-between items-center" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                        <span style="color: var(--text-secondary);">Term End / Closing</span>
                        <span class="font-mono font-medium" style="color: var(--text-primary);">
                            <?php echo !empty($currentTerm['end_date']) ? date('M d, Y', strtotime($currentTerm['end_date'])) : 'N/A'; ?>
                        </span>
                    </div>

                    <div class="p-3.5 rounded-xl flex justify-between items-center" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                        <span style="color: var(--text-secondary);">Promotion Status</span>
                        <?php if (!empty($currentTerm['is_promotional'])): ?>
                            <span class="font-semibold flex items-center" style="color: var(--status-success, #4ade80);">
                                <span class="w-2 h-2 rounded-full mr-1.5 animate-pulse" style="background-color: var(--status-success, #4ade80);"></span>
                                Active
                            </span>
                        <?php else: ?>
                            <span class="font-semibold" style="color: var(--status-warning, #facc15);">Regular Term</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Curriculum Draft Status Overview -->
            <div class="glass-card p-6 rounded-2xl space-y-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                <h2 class="text-base font-bold pb-3 flex items-center space-x-2" style="color: var(--text-primary); border-bottom: 1px solid var(--border-subtle);">
                    <svg class="w-5 h-5" style="color: var(--accent-cyan, #38bdf8);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Curriculum Submissions</span>
                </h2>

                <?php if (!empty($curricula)): ?>
                    <div class="space-y-2.5 max-h-64 overflow-y-auto pr-1">
                        <?php foreach (array_slice($curricula, 0, 5) as $cur): ?>
                            <div class="p-3 rounded-xl flex items-center justify-between text-xs" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                                <div>
                                    <p class="font-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($cur['subject_name']); ?></p>
                                    <p class="text-[11px]" style="color: var(--text-secondary);"><?php echo htmlspecialchars($cur['class_name']); ?></p>
                                </div>
                                <div>
                                    <?php if ($cur['status'] === 'approved'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: var(--bg-card); color: var(--status-success, #4ade80); border: 1px solid var(--border-subtle);">Approved</span>
                                    <?php elseif ($cur['status'] === 'rejected'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: var(--bg-card); color: var(--status-danger, #f87171); border: 1px solid var(--border-subtle);">Rejected</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: var(--bg-card); color: var(--status-warning, #facc15); border: 1px solid var(--border-subtle);">Draft</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-xs" style="color: var(--text-secondary);">
                        No curriculum records submitted yet.
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>