<?php
/**
 * Payment Audit & Receipt Verification Portal
 * File: admin/payments.php
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

// -------------------------------------------------------------------------
// POST HANDLERS: VERIFY / APPROVE / DECLINE PAYMENTS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_payment_status') {
            $paymentId = (int) ($_POST['payment_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if ($paymentId <= 0 || !in_array($status, ['approved', 'declined', 'pending'])) {
                throw new Exception("Invalid parameters provided for payment verification.");
            }

            $stmt = $pdo->prepare("UPDATE sch_payments SET status = :status WHERE id = :id");
            $stmt->execute([
                ':status' => $status,
                ':id' => $paymentId
            ]);

            setFlashMessage('success', "Payment transaction ID #{$paymentId} status updated to " . strtoupper($status) . ".");
            header('Location: ' . BASE_URL . 'admin/payments.php');
            exit();
        }
    } catch (Exception $e) {
        error_log("Payment Audit Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'admin/payments.php');
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL & FILTERS
// -------------------------------------------------------------------------
$filterStatus = $_GET['status'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');

try {
    $query = "
        SELECT 
            p.*,
            st.student_code,
            st.first_name,
            st.last_name,
            f.title AS fee_title,
            f.amount AS fee_total_amount,
            t.term_name,
            s.name AS session_name
        FROM sch_payments p
        JOIN sch_students st ON p.student_id = st.id
        JOIN sch_fees f ON p.fee_id = f.id
        JOIN sch_terms t ON f.term_id = t.id
        JOIN sch_sessions s ON t.session_id = s.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filterStatus) && in_array($filterStatus, ['pending', 'approved', 'declined'])) {
        $query .= " AND p.status = :status";
        $params[':status'] = $filterStatus;
    }

    if (!empty($searchQuery)) {
        $query .= " AND (st.student_code LIKE :search OR st.first_name LIKE :search OR st.last_name LIKE :search OR f.title LIKE :search)";
        $params[':search'] = '%' . $searchQuery . '%';
    }

    $query .= " ORDER BY p.id DESC";

    $paymentsStmt = $pdo->prepare($query);
    $paymentsStmt->execute($params);
    $payments = $paymentsStmt->fetchAll();

} catch (Exception $e) {
    error_log("Payment Retrieval Error: " . $e->getMessage());
    $payments = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="space-y-6 sm:space-y-8">

    <!-- Header Section -->
    <div class="glass-card p-5 sm:p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-[10px] sm:text-xs font-semibold text-cyanGlow uppercase tracking-wider mb-1">
                <span>Admin Panel</span>
                <span>&bull;</span>
                <span>Financial Management</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight">Payment Audit & Verification</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-0.5">Review uploaded receipt proofs and verify student fee payment submissions.</p>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="glass-card p-4 sm:p-5">
        <form method="GET" action="" class="flex flex-col md:flex-row items-center justify-between gap-3 sm:gap-4">
            <div class="flex flex-col md:flex-row items-center gap-2.5 sm:gap-3 w-full md:w-auto">
                <select name="status" onchange="this.form.submit()" class="border border-emeraldGlow/20 rounded-xl px-3 py-2.5 text-xs font-medium text-slate-200 focus:outline-none focus:border-emeraldGlow w-full md:w-48">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending Verification</option>
                    <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="declined" <?php echo $filterStatus === 'declined' ? 'selected' : ''; ?>>Declined</option>
                </select>

                <div class="relative w-full md:w-72">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search student code, name, fee..." class="w-full border border-emeraldGlow/20 rounded-xl pl-9 pr-3 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow placeholder:text-slate-500">
                    <svg class="w-4 h-4 text-slate-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <div class="flex items-center space-x-2 text-xs text-slate-400 w-full md:w-auto justify-end pt-1 md:pt-0">
                <span>Total Submissions: <strong class="text-emeraldGlow font-mono"><?php echo count($payments); ?></strong></span>
            </div>
        </form>
    </div>

    <!-- Section Heading -->
    <div class="flex items-center justify-between px-1">
        <div>
            <h2 class="text-base sm:text-lg font-bold text-slate-100">Payment Audit Log</h2>
            <p class="text-[11px] sm:text-xs text-slate-400">Review receipts and take verification actions.</p>
        </div>
    </div>

    <!-- Responsive Cards Grid -->
    <?php if (!empty($payments)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            <?php foreach ($payments as $p): ?>
                <div class="glass-card glass-card-hover p-4 sm:p-5 flex flex-col justify-between space-y-4 relative overflow-hidden group">
                    
                    <!-- Top Row: Student Details & Status -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="pr-2">
                            <h3 class="font-bold text-base text-slate-100 tracking-tight leading-snug">
                                <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                            </h3>
                            <div class="text-[11px] font-mono text-cyanGlow mt-0.5">
                                <?php echo htmlspecialchars($p['student_code']); ?>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="shrink-0">
                            <?php if ($p['status'] === 'approved'): ?>
                                <span class="px-2.5 py-1 bg-emeraldGlow/10 text-emeraldGlow border border-emeraldGlow/30 rounded-md text-[10px] font-semibold">
                                    Approved
                                </span>
                            <?php elseif ($p['status'] === 'declined'): ?>
                                <span class="px-2.5 py-1 bg-roseGlow/10 text-roseGlow border border-roseGlow/30 rounded-md text-[10px] font-semibold">
                                    Declined
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/30 rounded-md text-[10px] font-semibold">
                                    Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Middle Block: Fee Details & Amount Paid -->
                    <div class="p-3 rounded-xl border border-emeraldGlow/10 bg-inputBg space-y-2">
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase font-semibold block">Fee Title</span>
                            <span class="text-xs font-bold text-slate-200 block mt-0.5 truncate">
                                <?php echo htmlspecialchars($p['fee_title']); ?>
                            </span>
                            <span class="text-[10px] text-slate-400 block mt-0.5">
                                <?php echo htmlspecialchars($p['session_name'] . ' - ' . $p['term_name']); ?>
                            </span>
                        </div>

                        <div class="pt-2 border-t border-emeraldGlow/10 flex items-center justify-between">
                            <span class="text-[10px] text-slate-500 uppercase font-semibold">Amount Paid</span>
                            <div class="text-right">
                                <span class="text-sm font-bold font-mono text-emeraldGlow">
                                    &#8358;<?php echo number_format($p['amount_paid'], 2); ?>
                                </span>
                                <span class="text-[10px] text-slate-500 block">
                                    of &#8358;<?php echo number_format($p['fee_total_amount'], 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Proof & Verification Actions -->
                    <div class="space-y-3 pt-1 border-t border-emeraldGlow/10">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-medium">Receipt Document:</span>
                            <?php if (!empty($p['proof_of_payment'])): ?>
                                <button 
                                    type="button" 
                                    onclick="openProofModal('<?php echo BASE_URL . htmlspecialchars($p['proof_of_payment']); ?>', '<?php echo htmlspecialchars($p['student_code']); ?>')"
                                    class="inline-flex items-center space-x-1.5 px-2.5 py-1 bg-cardBg hover:bg-cardHover text-cyanGlow border border-cyanGlow/30 rounded-xl text-xs font-semibold transition-all cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>View Proof</span>
                                </button>
                            <?php else: ?>
                                <span class="text-xs text-slate-500 italic">No proof attached</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <?php if ($p['status'] !== 'approved'): ?>
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="action" value="update_payment_status">
                                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="px-3 py-1.5 bg-emeraldGlow/10 hover:bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/30 rounded-xl text-xs font-semibold transition-all cursor-pointer shadow-sm active:scale-95">
                                        Approve
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($p['status'] !== 'declined'): ?>
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="action" value="update_payment_status">
                                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="status" value="declined">
                                    <button type="submit" class="px-3 py-1.5 bg-roseGlow/10 hover:bg-roseGlow/20 text-roseGlow border border-roseGlow/30 rounded-xl text-xs font-semibold transition-all cursor-pointer shadow-sm active:scale-95">
                                        Decline
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card text-center py-12 px-4 border border-dashed border-emeraldGlow/20">
            <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-slate-400">No payment records found matching your filter criteria.</p>
        </div>
    <?php endif; ?>

</div>

<!-- PROOF OF PAYMENT PREVIEW MODAL -->
<div id="proofModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-canvas/80 backdrop-blur-md hidden" style="display: none;" onclick="handleBackdropClick(event, 'proofModal')">
    <div class="glass-card max-w-2xl w-full p-5 sm:p-6 space-y-4 shadow-2xl relative border border-emeraldGlow/30" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between border-b border-emeraldGlow/15 pb-3">
            <h3 class="text-base sm:text-lg font-bold text-slate-100">Payment Receipt Document</h3>
            <button type="button" onclick="closeModal('proofModal')" class="text-slate-400 hover:text-white text-2xl font-bold transition-colors p-1 cursor-pointer" aria-label="Close modal">&times;</button>
        </div>
        
        <div class="flex items-center justify-center bg-inputBg border border-emeraldGlow/15 rounded-xl p-2 min-h-[300px] max-h-[70vh] overflow-auto">
            <img id="proofImage" src="" alt="Proof of Payment" class="max-w-full h-auto rounded-lg object-contain">
        </div>

        <div class="flex justify-between items-center pt-2">
            <a id="downloadProofLink" href="" download target="_blank" class="text-xs text-cyanGlow hover:underline flex items-center space-x-1 font-semibold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Download Document</span>
            </a>
            <button type="button" onclick="closeModal('proofModal')" class="px-4 py-2 bg-cardBg hover:bg-cardHover text-slate-300 border border-emeraldGlow/20 rounded-xl text-xs font-semibold transition-colors cursor-pointer">Close</button>
        </div>
    </div>
</div>

<script>
function openProofModal(imageUrl, studentCode) {
    document.getElementById('proofImage').src = imageUrl;
    document.getElementById('downloadProofLink').href = imageUrl;
    
    const modal = document.getElementById('proofModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
}

function handleBackdropClick(event, modalId) {
    if (event.target === document.getElementById(modalId)) {
        closeModal(modalId);
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('proofModal');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>