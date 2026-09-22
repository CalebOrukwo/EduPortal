<?php
/**
 * Admin Dashboard
 * File: admin/index.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Ensure user is logged in and holds the 'admin' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    setFlashMessage('error', 'Access denied. Administrator privilege required.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

try {
    // 1. Fetch Total Active Students
    $studentStmt = $pdo->query("SELECT COUNT(*) FROM sch_students WHERE status = 'active'");
    $totalActiveStudents = (int) $studentStmt->fetchColumn();

    // 2. Fetch Pending Payment Approvals
    $paymentStmt = $pdo->query("SELECT COUNT(*) FROM sch_payments WHERE status = 'pending'");
    $pendingPayments = (int) $paymentStmt->fetchColumn();

    // 3. Fetch Pending Promotion Requests
    $promotionStmt = $pdo->query("SELECT COUNT(*) FROM sch_promotion_requests WHERE status = 'pending'");
    $pendingPromotions = (int) $promotionStmt->fetchColumn();

    // 4. Fetch Active Academic Session & Term Details
    $termStmt = $pdo->query("
        SELECT 
            t.id AS term_id, 
            t.term_name, 
            t.is_promotional,
            s.id AS session_id,
            s.name AS session_name
        FROM sch_terms t
        JOIN sch_sessions s ON t.session_id = s.id
        WHERE t.is_current = 1 AND s.is_active = 1
        LIMIT 1
    ");
    $activeTerm = $termStmt->fetch();

    // 5. Fetch Recent Pending Payments for Quick Approval
    $recentPaymentsStmt = $pdo->query("
        SELECT 
            p.id AS payment_id,
            p.amount_paid,
            p.created_at,
            f.title AS fee_title,
            s.first_name,
            s.last_name,
            s.student_code
        FROM sch_payments p
        JOIN sch_students s ON p.student_id = s.id
        JOIN sch_fees f ON p.fee_id = f.id
        WHERE p.status = 'pending'
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $recentPayments = $recentPaymentsStmt->fetchAll();

} catch (Exception $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    $errorMsg = "An error occurred while loading administrative metrics.";
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 sm:space-y-8">

    <!-- Header / Welcome Banner -->
    <div class="glass-card p-5 sm:p-8 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-5 sm:gap-6">
        <div>
            <div class="flex items-center space-x-2.5 mb-2">
                <span class="px-2.5 py-0.5 sm:px-3 sm:py-1 bg-emeraldGlow/10 text-emeraldGlow border border-emeraldGlow/30 rounded-full text-[10px] sm:text-xs font-semibold uppercase tracking-wider">
                    Admin Portal
                </span>
                <span class="inline-flex items-center text-[10px] sm:text-xs text-mintGlow font-medium">
                    <span class="w-2 h-2 rounded-full bg-emeraldGlow mr-1.5 animate-pulse"></span> System Active
                </span>
            </div>
            <h1 class="text-xl sm:text-3xl font-black text-slate-100 tracking-tight">
                Administrator Overview
            </h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1">
                Monitor key portal metrics, manage academic sessions, and approve requests.
            </p>
        </div>

        <!-- Quick Action Buttons -->
        <div class="flex items-center gap-2.5 sm:gap-3 w-full md:w-auto">
            <a href="<?php echo BASE_URL; ?>admin/sessions-terms.php" class="flex-1 md:flex-none px-3.5 py-2.5 bg-cardBg hover:bg-cardHover text-slate-200 border border-emeraldGlow/20 hover:border-emeraldGlow/40 rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-1.5 shadow-sm">
                <svg class="w-4 h-4 text-emeraldGlow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <span class="truncate">Sessions</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/payments.php" class="flex-1 md:flex-none glow-button px-3.5 py-2.5 text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="truncate">Payments</span>
            </a>
        </div>
    </div>

    <!-- Active Term & Session Banner -->
    <div class="glass-card border-emeraldGlow/30 p-4 sm:p-5 rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
        <div class="flex items-center space-x-3">
            <div class="p-2.5 sm:p-3 bg-emeraldGlow/10 rounded-lg text-emeraldGlow border border-emeraldGlow/20 shrink-0">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0h4" />
                </svg>
            </div>
            <div>
                <div class="text-[10px] sm:text-xs text-emeraldGlow uppercase font-bold tracking-wider">Current Academic Context</div>
                <div class="text-sm sm:text-lg font-bold text-slate-100 flex flex-wrap items-center gap-2">
                    <?php if ($activeTerm): ?>
                        <span><?php echo htmlspecialchars($activeTerm['session_name']); ?> &mdash; <?php echo htmlspecialchars($activeTerm['term_name']); ?></span>
                        <?php if ($activeTerm['is_promotional']): ?>
                            <span class="px-2 py-0.5 bg-amberGlow/15 text-amberGlow border border-amberGlow/30 rounded text-[10px] sm:text-xs font-bold">
                                Promotional Term
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-rose-400">No active session/term configured!</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>admin/sessions-terms.php" class="text-xs text-emeraldGlow hover:text-mintGlow font-semibold underline self-end sm:self-center">
            Manage Sessions &rarr;
        </a>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">

        <!-- Active Students Card -->
        <div class="glass-card p-5 sm:p-6 rounded-2xl flex items-center justify-between">
            <div>
                <div class="text-[11px] sm:text-xs text-slate-400 uppercase tracking-wider font-semibold">Active Students</div>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-100 mt-1.5 font-mono tracking-tight">
                    <?php echo number_format($totalActiveStudents); ?>
                </div>
                <div class="text-[10px] sm:text-xs text-slate-500 mt-1">Enrolled and active</div>
            </div>
            <div class="p-3.5 sm:p-4 bg-appBg/60 border border-emeraldGlow/20 rounded-xl text-emeraldGlow shadow-inner">
                <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
        </div>

        <!-- Pending Payments Card -->
        <div class="glass-card p-5 sm:p-6 rounded-2xl flex items-center justify-between">
            <div>
                <div class="text-[11px] sm:text-xs text-slate-400 uppercase tracking-wider font-semibold">Pending Payments</div>
                <div class="text-2xl sm:text-3xl font-extrabold <?php echo $pendingPayments > 0 ? 'text-amberGlow' : 'text-slate-100'; ?> mt-1.5 font-mono tracking-tight">
                    <?php echo number_format($pendingPayments); ?>
                </div>
                <div class="text-[10px] sm:text-xs text-slate-500 mt-1">Awaiting approval</div>
            </div>
            <div class="p-3.5 sm:p-4 bg-appBg/60 border border-amberGlow/20 rounded-xl text-amberGlow shadow-inner">
                <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
        </div>

        <!-- Pending Promotions Card -->
        <div class="glass-card p-5 sm:p-6 rounded-2xl flex items-center justify-between sm:col-span-2 lg:col-span-1">
            <div>
                <div class="text-[11px] sm:text-xs text-slate-400 uppercase tracking-wider font-semibold">Promotion Requests</div>
                <div class="text-2xl sm:text-3xl font-extrabold <?php echo $pendingPromotions > 0 ? 'text-mintGlow' : 'text-slate-100'; ?> mt-1.5 font-mono tracking-tight">
                    <?php echo number_format($pendingPromotions); ?>
                </div>
                <div class="text-[10px] sm:text-xs text-slate-500 mt-1">Class transfer approvals</div>
            </div>
            <div class="p-3.5 sm:p-4 bg-appBg/60 border border-cyanGlow/20 rounded-xl text-cyanGlow shadow-inner">
                <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
        </div>

    </div>

    <!-- Recent Pending Payment Submissions -->
    <div class="glass-card p-4 sm:p-6 rounded-2xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-100">Recent Payment Submissions</h2>
                <p class="text-[11px] sm:text-xs text-slate-400">Proofs of payment awaiting verification</p>
            </div>
            <a href="<?php echo BASE_URL; ?>admin/payments.php" class="text-xs text-emeraldGlow hover:text-mintGlow font-semibold">
                View All &rarr;
            </a>
        </div>

        <?php if (!empty($recentPayments)): ?>
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left text-xs sm:text-sm text-slate-300">
                    <thead class="bg-appBg/80 text-[10px] sm:text-xs text-slate-400 uppercase tracking-wider border-b border-emeraldGlow/10">
                        <tr>
                            <th class="py-3 px-3 sm:px-4">Student</th>
                            <th class="py-3 px-3 sm:px-4">Fee Item</th>
                            <th class="py-3 px-3 sm:px-4 text-center">Amount Paid</th>
                            <th class="py-3 px-3 sm:px-4 text-center hidden sm:table-cell">Submitted On</th>
                            <th class="py-3 px-3 sm:px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-emeraldGlow/10">
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr class="hover:bg-cardHover/50 transition-colors">
                                <td class="py-3 px-3 sm:px-4 font-medium text-slate-100">
                                    <div class="truncate max-w-[140px] sm:max-w-none"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                                    <div class="text-[10px] font-mono text-emeraldGlow"><?php echo htmlspecialchars($payment['student_code']); ?></div>
                                </td>
                                <td class="py-3 px-3 sm:px-4 text-slate-300">
                                    <?php echo htmlspecialchars($payment['fee_title']); ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 text-center font-mono font-bold text-slate-100 whitespace-nowrap">
                                    &#8358;<?php echo number_format($payment['amount_paid'], 2); ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 text-center text-[11px] text-slate-400 hidden sm:table-cell whitespace-nowrap">
                                    <?php echo date('M d, Y h:i A', strtotime($payment['created_at'])); ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 text-right whitespace-nowrap">
                                    <a href="<?php echo BASE_URL; ?>admin/payments.php?id=<?php echo $payment['payment_id']; ?>" class="px-2.5 py-1 bg-emeraldGlow/10 hover:bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/30 rounded text-xs font-semibold transition-all">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 sm:py-10 border border-dashed border-emeraldGlow/20 rounded-xl">
                <svg class="w-10 h-10 sm:w-12 sm:h-12 text-slate-500 mx-auto mb-2 sm:mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-xs sm:text-sm text-slate-400">No pending payment verifications at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>