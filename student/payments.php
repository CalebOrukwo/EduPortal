<?php
/**
 * Fee Statement and Payment Upload Page
 * File: student/payments.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Valid roles are 'student', 'admin', or 'staff'
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['student', 'admin', 'staff'])) {
    setFlashMessage('error', 'Access denied. Please log in.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? 'student';

// Resolve Student ID and Details
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
    // Admin or Staff viewing student payments
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

$studentId      = (int)$student['id'];
$currentClassId = (int)$student['current_class_id'];

// -------------------------------------------------------------------------
// FETCH CURRENT ACTIVE TERM
// -------------------------------------------------------------------------
$currentTermStmt = $pdo->query("
    SELECT t.id, t.term_name, s.name AS session_name 
    FROM sch_terms t
    INNER JOIN sch_sessions s ON t.session_id = s.id
    WHERE t.is_current = 1 LIMIT 1
");
$currentTerm = $currentTermStmt->fetch(PDO::FETCH_ASSOC);
$currentTermId = $currentTerm ? (int)$currentTerm['id'] : 0;

// -------------------------------------------------------------------------
// HANDLE PROOF OF PAYMENT UPLOAD POST ACTION
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_payment') {
    $feeId      = (int)($_POST['fee_id'] ?? 0);
    $amountPaid = (float)($_POST['amount_paid'] ?? 0);

    if ($feeId <= 0 || $amountPaid <= 0) {
        setFlashMessage('error', 'Please select a valid fee item and enter an amount.');
    } elseif (!isset($_FILES['proof_of_payment']) || $_FILES['proof_of_payment']['error'] !== UPLOAD_ERR_OK) {
        setFlashMessage('error', 'Please attach a valid file as proof of payment.');
    } else {
        $file = $_FILES['proof_of_payment'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedExtensions)) {
            setFlashMessage('error', 'Invalid file type. Only JPG, PNG, and PDF files are allowed.');
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            setFlashMessage('error', 'File size exceeds the 5MB limit.');
        } else {
            $uploadDir = __DIR__ . '/../uploads/payments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = 'proof_' . $studentId . '_' . $feeId . '_' . time() . '.' . $fileExt;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $insStmt = $pdo->prepare("
                    INSERT INTO sch_payments (student_id, fee_id, amount_paid, proof_of_payment, status)
                    VALUES (:student_id, :fee_id, :amount_paid, :proof_of_payment, 'pending')
                ");
                $insStmt->execute([
                    ':student_id'       => $studentId,
                    ':fee_id'           => $feeId,
                    ':amount_paid'      => $amountPaid,
                    ':proof_of_payment' => $newFileName
                ]);

                setFlashMessage('success', 'Proof of payment submitted successfully! Awaiting verification.');
                header('Location: ' . BASE_URL . 'student/payments.php' . ($userRole !== 'student' ? '?student_id=' . $studentId : ''));
                exit();
            } else {
                setFlashMessage('error', 'Failed to upload the file to the server. Please try again.');
            }
        }
    }
}

// -------------------------------------------------------------------------
// FETCH APPLICABLE FEE BILLS FOR STUDENT'S CLASS & CURRENT TERM
// -------------------------------------------------------------------------
$feeBills = [];
if ($currentTermId > 0) {
    $feeStmt = $pdo->prepare("
        SELECT id, title, amount, term_id, class_id
        FROM sch_fees
        WHERE term_id = :term_id 
          AND (class_id = :class_id OR class_id IS NULL)
        ORDER BY id ASC
    ");
    $feeStmt->execute([
        ':term_id'  => $currentTermId,
        ':class_id' => $currentClassId
    ]);
    $feeBills = $feeStmt->fetchAll(PDO::FETCH_ASSOC);
}

// -------------------------------------------------------------------------
// FETCH PAYMENT TRANSACTION HISTORY
// -------------------------------------------------------------------------
$paymentsStmt = $pdo->prepare("
    SELECT 
        p.id, p.amount_paid, p.proof_of_payment, p.status, p.created_at,
        f.title AS fee_title, f.amount AS fee_amount,
        t.term_name, s.name AS session_name
    FROM sch_payments p
    INNER JOIN sch_fees f ON p.fee_id = f.id
    INNER JOIN sch_terms t ON f.term_id = t.id
    INNER JOIN sch_sessions s ON t.session_id = s.id
    WHERE p.student_id = :student_id
    ORDER BY p.created_at DESC
");
$paymentsStmt->execute([':student_id' => $studentId]);
$paymentHistory = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Approved Payments per Fee
$paidTotalsByFee = [];
foreach ($paymentHistory as $pay) {
    if ($pay['status'] === 'approved') {
        $fTitle = $pay['fee_title'];
        if (!isset($paidTotalsByFee[$fTitle])) {
            $paidTotalsByFee[$fTitle] = 0;
        }
        $paidTotalsByFee[$fTitle] += (float)$pay['amount_paid'];
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Page Title & Student Meta -->
    <div class="glow-card glass-card p-6 rounded-2xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Fee Statement & Payments</h1>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                Student: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?></strong> 
                (<?php echo htmlspecialchars($student['student_code']); ?>) | Class: <strong style="color: var(--accent-color, #22d3ee);"><?php echo htmlspecialchars($student['class_name']); ?></strong>
            </p>
        </div>
        <div class="text-right text-xs" style="color: var(--text-secondary);">
            <span class="block">Active Session / Term</span>
            <strong style="color: var(--text-primary);">
                <?php echo $currentTerm ? htmlspecialchars($currentTerm['session_name'] . ' - ' . $currentTerm['term_name']) : 'No Active Term'; ?>
            </strong>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Column 1 & 2: Current Fee Bills & Payment History -->
        <div class="lg:col-span-2 space-y-8">

            <!-- Active Term Fee Bills Cards Grid -->
            <div class="glow-card glass-card p-6 rounded-2xl space-y-4" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
                <h2 class="text-base font-bold flex items-center space-x-2" style="color: var(--text-primary);">
                    <svg class="w-5 h-5" style="color: var(--accent-color, #22d3ee);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Current Term Fee Bills</span>
                </h2>

                <?php if (!empty($feeBills)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($feeBills as $bill): ?>
                            <?php 
                                $billedAmount = (float)$bill['amount'];
                                $approvedPaid = $paidTotalsByFee[$bill['title']] ?? 0.00;
                                $balanceDue   = max(0, $billedAmount - $approvedPaid);
                            ?>
                            <div class="p-4 rounded-xl space-y-3" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle);">
                                <div class="font-semibold text-sm pb-2" style="color: var(--text-primary); border-bottom: 1px solid var(--border-subtle);">
                                    <?php echo htmlspecialchars($bill['title']); ?>
                                </div>
                                <div class="space-y-1.5 text-xs">
                                    <div class="flex justify-between items-center">
                                        <span style="color: var(--text-secondary);">Billed Amount:</span>
                                        <span class="font-mono font-medium" style="color: var(--text-primary);">&#8358;<?php echo number_format($billedAmount, 2); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span style="color: var(--text-secondary);">Approved Paid:</span>
                                        <span class="font-mono font-medium" style="color: var(--text-success, #34d399);">&#8358;<?php echo number_format($approvedPaid, 2); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center pt-1" style="border-top: 1px dashed var(--border-subtle);">
                                        <span class="font-semibold" style="color: var(--text-secondary);">Balance Due:</span>
                                        <span class="font-mono font-bold" style="color: <?php echo $balanceDue > 0 ? 'var(--text-danger, #f87171)' : 'var(--accent-color, #22d3ee)'; ?>;">
                                            &#8358;<?php echo number_format($balanceDue, 2); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 rounded-xl" style="border: 1px dashed var(--border-subtle);">
                        <p class="text-xs" style="color: var(--text-secondary);">No fee schedule published for this term.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Transaction & Upload History Cards Grid -->
            <div class="glow-card glass-card p-6 rounded-2xl space-y-4" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
                <h2 class="text-base font-bold flex items-center space-x-2" style="color: var(--text-primary);">
                    <svg class="w-5 h-5" style="color: var(--accent-color, #22d3ee);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Payment Upload History</span>
                </h2>

                <?php if (!empty($paymentHistory)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($paymentHistory as $pay): ?>
                            <div class="p-4 rounded-xl space-y-3 flex flex-col justify-between" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle);">
                                <div>
                                    <div class="flex justify-between items-start mb-2 pb-2" style="border-bottom: 1px solid var(--border-subtle);">
                                        <div>
                                            <span class="font-semibold text-xs block" style="color: var(--text-primary);">
                                                <?php echo htmlspecialchars($pay['fee_title']); ?>
                                            </span>
                                            <span class="text-[10px] block mt-0.5" style="color: var(--text-secondary);">
                                                <?php echo htmlspecialchars($pay['session_name'] . ' - ' . $pay['term_name']); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <?php if ($pay['status'] === 'approved'): ?>
                                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-full" style="background-color: rgba(52, 211, 153, 0.1); color: var(--text-success, #34d399); border: 1px solid rgba(52, 211, 153, 0.2);">
                                                    Approved
                                                </span>
                                            <?php elseif ($pay['status'] === 'declined'): ?>
                                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-full" style="background-color: rgba(248, 113, 113, 0.1); color: var(--text-danger, #f87171); border: 1px solid rgba(248, 113, 113, 0.2);">
                                                    Declined
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-full" style="background-color: rgba(251, 191, 36, 0.1); color: var(--text-warning, #fbbf24); border: 1px solid rgba(251, 191, 36, 0.2);">
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="space-y-1.5 text-xs">
                                        <div class="flex justify-between items-center">
                                            <span style="color: var(--text-secondary);">Amount Paid:</span>
                                            <span class="font-mono font-bold" style="color: var(--text-primary);">&#8358;<?php echo number_format((float)$pay['amount_paid'], 2); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span style="color: var(--text-secondary);">Date:</span>
                                            <span class="font-mono text-[11px]" style="color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($pay['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-2 flex justify-between items-center text-xs" style="border-top: 1px dashed var(--border-subtle);">
                                    <span style="color: var(--text-secondary);">Proof Document:</span>
                                    <?php if (!empty($pay['proof_of_payment'])): ?>
                                        <a href="<?php echo BASE_URL . 'uploads/payments/' . htmlspecialchars($pay['proof_of_payment']); ?>" target="_blank" class="font-medium hover:underline flex items-center space-x-1" style="color: var(--accent-color, #22d3ee);">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span>View File</span>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">N/A</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 rounded-xl" style="border: 1px dashed var(--border-subtle);">
                        <p class="text-xs" style="color: var(--text-secondary);">No payment uploads submitted yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Column 3: Payment Upload Form -->
        <div class="space-y-6">
            <div class="glow-card glass-card p-6 rounded-2xl space-y-6" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
                <div>
                    <h2 class="text-base font-bold flex items-center space-x-2" style="color: var(--text-primary);">
                        <svg class="w-5 h-5" style="color: var(--accent-color, #22d3ee);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <span>Submit Payment Proof</span>
                    </h2>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Upload bank transfer receipts or deposit tellers for verification.</p>
                </div>

                <form method="POST" action="" enctype="multipart/form-data" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="upload_payment">

                    <div>
                        <label class="block font-medium mb-1" style="color: var(--text-primary);">Select Fee Item <span style="color: var(--text-danger, #f87171);">*</span></label>
                        <select name="fee_id" required class="w-full rounded-xl px-3 py-2.5 focus:outline-none transition" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                            <option value="">-- Choose Fee Bill --</option>
                            <?php foreach ($feeBills as $bill): ?>
                                <option value="<?php echo $bill['id']; ?>">
                                    <?php echo htmlspecialchars($bill['title']); ?> (&#8358;<?php echo number_format((float)$bill['amount'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium mb-1" style="color: var(--text-primary);">Amount Paid (&#8358;) <span style="color: var(--text-danger, #f87171);">*</span></label>
                        <input type="number" step="0.01" name="amount_paid" required placeholder="e.g. 20000.00" class="w-full rounded-xl px-3 py-2.5 focus:outline-none font-mono transition" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    </div>

                    <div>
                        <label class="block font-medium mb-1" style="color: var(--text-primary);">Proof File (JPG, PNG, PDF) <span style="color: var(--text-danger, #f87171);">*</span></label>
                        <input type="file" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf" required class="w-full rounded-xl px-3 py-2 focus:outline-none file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold transition" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-secondary);">
                        <span class="text-[10px] block mt-1" style="color: var(--text-secondary);">Maximum allowed file size is 5MB.</span>
                    </div>

                    <button type="submit" class="w-full glow-button py-3 text-xs font-bold rounded-xl shadow-lg transition duration-200" style="background-color: var(--accent-color, #22d3ee); color: #000;">
                        Submit for Verification
                    </button>
                </form>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>