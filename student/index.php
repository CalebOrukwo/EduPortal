<?php
/**
 * Student Dashboard
 * File: student/index.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Ensure user is logged in and holds the 'student' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    setFlashMessage('error', 'Access denied. Please log in as a student.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$studentId = $_SESSION['user_id'];

try {
    // 1. Fetch Student Profile & Class Info
    $studentStmt = $pdo->prepare("
        SELECT 
            s.id, 
            s.student_code, 
            s.first_name, 
            s.last_name, 
            s.status AS student_status,
            c.name AS class_name
        FROM sch_students s
        LEFT JOIN sch_classes c ON s.current_class_id = c.id
        WHERE s.id = :student_id 
        LIMIT 1
    ");
    $studentStmt->execute([':student_id' => $studentId]);
    $student = $studentStmt->fetch();

    if (!$student) {
        throw new Exception("Student profile details not found.");
    }

    // 2. Fetch Current Active Academic Term & Session
    $termStmt = $pdo->prepare("
        SELECT 
            t.id AS term_id, 
            t.term_name, 
            t.start_date, 
            t.end_date, 
            s.name AS session_name
        FROM sch_terms t
        JOIN sch_sessions s ON t.session_id = s.id
        WHERE t.is_current = 1 
        LIMIT 1
    ");
    $termStmt->execute();
    $activeTerm = $termStmt->fetch();

    // 3. Fetch Fee Payment Summary for Active Term
    $totalFees = 0.00;
    $totalPaid = 0.00;
    $pendingPaymentsCount = 0;

    if ($activeTerm) {
        // Calculate Total Billed Fees for current term (class-specific or general)
        $feeStmt = $pdo->prepare("
            SELECT SUM(amount) AS total_amount 
            FROM sch_fees 
            WHERE term_id = :term_id 
              AND (class_id = (SELECT current_class_id FROM sch_students WHERE id = :student_id) OR class_id IS NULL)
        ");
        $feeStmt->execute([
            ':term_id' => $activeTerm['term_id'],
            ':student_id' => $studentId
        ]);
        $totalFees = (float) ($feeStmt->fetchColumn() ?? 0.00);

        // Calculate Approved Payments Made by Student
        $paidStmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN p.status = 'approved' THEN p.amount_paid ELSE 0 END) AS approved_total,
                SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) AS pending_count
            FROM sch_payments p
            JOIN sch_fees f ON p.fee_id = f.id
            WHERE p.student_id = :student_id AND f.term_id = :term_id
        ");
        $paidStmt->execute([
            ':student_id' => $studentId,
            ':term_id' => $activeTerm['term_id']
        ]);
        $paymentSummary = $paidStmt->fetch();
        $totalPaid = (float) ($paymentSummary['approved_total'] ?? 0.00);
        $pendingPaymentsCount = (int) ($paymentSummary['pending_count'] ?? 0);
    }

    $feeBalance = max(0.00, $totalFees - $totalPaid);

    // 4. Fetch Recent Performance Highlights
    $performanceResults = [];
    if ($activeTerm) {
        $perfStmt = $pdo->prepare("
            SELECT 
                sub.name AS subject_name,
                a.ca1_score,
                a.ca2_score,
                a.ca3_score,
                a.exam_score,
                a.total_score
            FROM sch_assessments a
            JOIN sch_subjects sub ON a.subject_id = sub.id
            WHERE a.student_id = :student_id AND a.term_id = :term_id
            ORDER BY sub.name ASC
            LIMIT 5
        ");
        $perfStmt->execute([
            ':student_id' => $studentId,
            ':term_id' => $activeTerm['term_id']
        ]);
        $performanceResults = $perfStmt->fetchAll();
    }

} catch (Exception $e) {
    error_log("Student Dashboard Error: " . $e->getMessage());
    $errorMsg = "Unable to load dashboard details at this time.";
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header / Welcome Banner -->
    <div class="glow-card glass-card p-6 md:p-8 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div>
            <div class="flex items-center space-x-3 mb-2">
                <span class="px-3 py-1 text-xs font-semibold uppercase tracking-wider rounded-full" style="background-color: rgba(6, 182, 212, 0.1); color: var(--accent-color, #22d3ee); border: 1px solid rgba(6, 182, 212, 0.3);">
                    Student Portal
                </span>
                <?php if ($student['student_status'] === 'active'): ?>
                    <span class="inline-flex items-center text-xs font-medium" style="color: var(--text-success, #34d399);">
                        <span class="w-2 h-2 rounded-full mr-1.5 animate-pulse" style="background-color: var(--text-success, #34d399);"></span> Active
                    </span>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold" style="color: var(--text-primary);">
                Welcome back, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>!
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Track your academic progress, schedules, and fee records.
            </p>
        </div>

        <!-- Student Profile Badges -->
        <div class="flex items-center space-x-3 w-full md:w-auto">
            <div class="px-4 py-2.5 rounded-xl text-center flex-1 md:flex-initial" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle);">
                <div class="text-xs font-medium" style="color: var(--text-secondary);">Student Code</div>
                <div class="text-sm font-bold tracking-wider font-mono" style="color: var(--accent-color, #22d3ee);"><?php echo htmlspecialchars($student['student_code']); ?></div>
            </div>
            <div class="px-4 py-2.5 rounded-xl text-center flex-1 md:flex-initial" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle);">
                <div class="text-xs font-medium" style="color: var(--text-secondary);">Current Class</div>
                <div class="text-sm font-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($student['class_name'] ?? 'Unassigned'); ?></div>
            </div>
        </div>
    </div>

    <!-- Active Term & Session Banner -->
    <div class="p-5 rounded-xl flex flex-col sm:flex-row items-center justify-between gap-4" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div class="flex items-center space-x-3">
            <div class="p-3 rounded-lg" style="background-color: rgba(6, 182, 212, 0.1); color: var(--accent-color, #22d3ee);">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <div class="text-xs uppercase font-semibold tracking-wider" style="color: var(--accent-color, #22d3ee);">Active Academic Period</div>
                <div class="text-lg font-bold" style="color: var(--text-primary);">
                    <?php if ($activeTerm): ?>
                        <?php echo htmlspecialchars($activeTerm['session_name']); ?> &mdash; <?php echo htmlspecialchars($activeTerm['term_name']); ?>
                    <?php else: ?>
                        No active academic term configured.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($activeTerm && $activeTerm['end_date']): ?>
            <div class="text-xs px-3 py-1.5 rounded-lg" style="color: var(--text-secondary); background-color: var(--bg-input); border: 1px solid var(--border-subtle);">
                Term Ends: <span class="font-medium" style="color: var(--text-primary);"><?php echo date('M d, Y', strtotime($activeTerm['end_date'])); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Financial & Summary Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Total Billed Card -->
        <div class="glow-card glass-card p-6 rounded-2xl" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="text-xs uppercase tracking-wider font-medium" style="color: var(--text-secondary);">Total Fees Billed</div>
            <div class="text-2xl font-extrabold mt-2 font-mono" style="color: var(--text-primary);">
                &#8358;<?php echo number_format($totalFees, 2); ?>
            </div>
            <div class="text-xs mt-2" style="color: var(--text-muted, var(--text-secondary));">Current academic term fees</div>
        </div>

        <!-- Total Paid Card -->
        <div class="glow-card glass-card p-6 rounded-2xl" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="text-xs uppercase tracking-wider font-medium" style="color: var(--text-secondary);">Total Amount Paid</div>
            <div class="text-2xl font-extrabold mt-2 font-mono" style="color: var(--text-success, #34d399);">
                &#8358;<?php echo number_format($totalPaid, 2); ?>
            </div>
            <div class="text-xs mt-2" style="color: var(--text-muted, var(--text-secondary));">Approved payment transactions</div>
        </div>

        <!-- Balance Due Card -->
        <div class="glow-card glass-card p-6 rounded-2xl" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="text-xs uppercase tracking-wider font-medium" style="color: var(--text-secondary);">Outstanding Balance</div>
            <div class="text-2xl font-extrabold mt-2 font-mono" style="color: <?php echo $feeBalance > 0 ? 'var(--text-danger, #f87171)' : 'var(--accent-color, #22d3ee)'; ?>;">
                &#8358;<?php echo number_format($feeBalance, 2); ?>
            </div>
            <div class="mt-2 flex items-center justify-between text-xs">
                <?php if ($pendingPaymentsCount > 0): ?>
                    <span class="font-medium" style="color: var(--text-warning, #fbbf24);"><?php echo $pendingPaymentsCount; ?> pending approval</span>
                <?php else: ?>
                    <span style="color: var(--text-secondary);">No pending payments</span>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>student/fees.php" class="font-semibold underline" style="color: var(--accent-color, #22d3ee);">Manage Fees &rarr;</a>
            </div>
        </div>

    </div>

    <!-- Recent Academic Performance Highlights -->
    <div class="glow-card glass-card p-6 rounded-2xl" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-lg font-bold" style="color: var(--text-primary);">Recent Performance Highlights</h2>
                <p class="text-xs" style="color: var(--text-secondary);">Continuous Assessment & Examination breakdown for current term</p>
            </div>
            <a href="<?php echo BASE_URL; ?>student/results.php" class="text-xs font-semibold" style="color: var(--accent-color, #22d3ee);">
                View Full Result &rarr;
            </a>
        </div>

        <?php if (!empty($performanceResults)): ?>
            <!-- Responsive Card Grid Layout -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($performanceResults as $row): ?>
                    <div class="p-4 rounded-xl flex flex-col justify-between space-y-4" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle);">
                        <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid var(--border-subtle);">
                            <span class="font-semibold text-base" style="color: var(--text-primary);">
                                <?php echo htmlspecialchars($row['subject_name']); ?>
                            </span>
                            <div class="text-right">
                                <span class="text-xs font-semibold uppercase tracking-wider block" style="color: var(--text-secondary);">Total</span>
                                <span class="text-lg font-bold font-mono" style="color: var(--accent-color, #22d3ee);">
                                    <?php echo number_format($row['total_score'], 1); ?>
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-4 gap-2 text-center text-xs">
                            <div class="p-2 rounded-lg" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
                                <span class="block text-2xs font-medium" style="color: var(--text-secondary);">CA 1</span>
                                <span class="font-mono font-semibold mt-0.5 block" style="color: var(--text-primary);"><?php echo number_format($row['ca1_score'], 1); ?></span>
                            </div>
                            <div class="p-2 rounded-lg" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
                                <span class="block text-2xs font-medium" style="color: var(--text-secondary);">CA 2</span>
                                <span class="font-mono font-semibold mt-0.5 block" style="color: var(--text-primary);"><?php echo number_format($row['ca2_score'], 1); ?></span>
                            </div>
                            <div class="p-2 rounded-lg" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
                                <span class="block text-2xs font-medium" style="color: var(--text-secondary);">CA 3</span>
                                <span class="font-mono font-semibold mt-0.5 block" style="color: var(--text-primary);"><?php echo number_format($row['ca3_score'], 1); ?></span>
                            </div>
                            <div class="p-2 rounded-lg" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
                                <span class="block text-2xs font-medium" style="color: var(--text-secondary);">Exam</span>
                                <span class="font-mono font-semibold mt-0.5 block" style="color: var(--text-primary);"><?php echo number_format($row['exam_score'], 1); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-10 rounded-xl" style="border: 1px dashed var(--border-subtle);">
                <svg class="w-12 h-12 mx-auto mb-3" style="color: var(--text-secondary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm" style="color: var(--text-secondary);">No assessment scores recorded for the active term yet.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>