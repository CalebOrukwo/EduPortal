<?php
/**
 * Academic Promotion Workflow Management
 * File: admin/promotions.php
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
// POST HANDLERS: APPROVE OR DECLINE PROMOTION REQUESTS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'process_promotion') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if ($requestId <= 0 || !in_array($status, ['approved', 'declined'])) {
                throw new Exception("Invalid promotion parameters provided.");
            }

            // Fetch promotion request details
            $stmt = $pdo->prepare("
                SELECT student_id, to_class_id, status 
                FROM sch_promotion_requests 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $requestId]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new Exception("Promotion request not found.");
            }

            if ($request['status'] !== 'pending') {
                throw new Exception("This promotion request has already been processed.");
            }

            $pdo->beginTransaction();

            if ($status === 'approved') {
                // 1. Update the student's current class to the target class ID
                $updateStudentStmt = $pdo->prepare("
                    UPDATE sch_students 
                    SET current_class_id = :to_class_id 
                    WHERE id = :student_id
                ");
                $updateStudentStmt->execute([
                    ':to_class_id' => $request['to_class_id'],
                    ':student_id' => $request['student_id']
                ]);
            }

            // 2. Update promotion request status
            $updateRequestStmt = $pdo->prepare("
                UPDATE sch_promotion_requests 
                SET status = :status 
                WHERE id = :id
            ");
            $updateRequestStmt->execute([
                ':status' => $status,
                ':id' => $requestId
            ]);

            $pdo->commit();

            $msg = ($status === 'approved') 
                ? "Promotion request approved successfully. Student class updated." 
                : "Promotion request has been declined.";

            setFlashMessage('success', $msg);
            header('Location: ' . BASE_URL . 'admin/promotions.php');
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Promotion Processing Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'admin/promotions.php');
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL & FILTERS
// -------------------------------------------------------------------------
$filterStatus = $_GET['status'] ?? 'pending';
$searchQuery = trim($_GET['search'] ?? '');

try {
    $query = "
        SELECT 
            pr.*,
            st.student_code,
            st.first_name,
            st.last_name,
            c1.name AS from_class_name,
            c2.name AS to_class_name,
            u.full_name AS staff_name,
            t.term_name,
            s.name AS session_name
        FROM sch_promotion_requests pr
        JOIN sch_students st ON pr.student_id = st.id
        JOIN sch_classes c1 ON pr.from_class_id = c1.id
        JOIN sch_classes c2 ON pr.to_class_id = c2.id
        JOIN sch_users u ON pr.staff_id = u.id
        JOIN sch_terms t ON pr.term_id = t.id
        JOIN sch_sessions s ON t.session_id = s.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filterStatus) && in_array($filterStatus, ['pending', 'approved', 'declined'])) {
        $query .= " AND pr.status = :status";
        $params[':status'] = $filterStatus;
    }

    if (!empty($searchQuery)) {
        $query .= " AND (st.student_code LIKE :search OR st.first_name LIKE :search OR st.last_name LIKE :search OR u.full_name LIKE :search)";
        $params[':search'] = '%' . $searchQuery . '%';
    }

    $query .= " ORDER BY pr.id DESC";

    $requestsStmt = $pdo->prepare($query);
    $requestsStmt->execute($params);
    $requests = $requestsStmt->fetchAll();

} catch (Exception $e) {
    error_log("Promotion Fetch Error: " . $e->getMessage());
    $requests = [];
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
                <span>Academic Workflows</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight">Promotion Requests Review</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-0.5">Evaluate class promotion submissions submitted by form teachers.</p>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="glass-card p-4 sm:p-5">
        <form method="GET" action="" class="flex flex-col md:flex-row items-center justify-between gap-3 sm:gap-4">
            <div class="flex flex-col md:flex-row items-center gap-2.5 sm:gap-3 w-full md:w-auto">
                <select name="status" onchange="this.form.submit()" class="border border-emeraldGlow/20 rounded-xl px-3 py-2.5 text-xs font-medium text-slate-200 focus:outline-none focus:border-emeraldGlow w-full md:w-48">
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending Requests</option>
                    <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="declined" <?php echo $filterStatus === 'declined' ? 'selected' : ''; ?>>Declined</option>
                </select>

                <div class="relative w-full md:w-72">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search student name, code, teacher..." class="w-full border border-emeraldGlow/20 rounded-xl pl-9 pr-3 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow placeholder:text-slate-500">
                    <svg class="w-4 h-4 text-slate-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <div class="flex items-center space-x-2 text-xs text-slate-400 w-full md:w-auto justify-end pt-1 md:pt-0">
                <span>Total Submissions: <strong class="text-emeraldGlow font-mono"><?php echo count($requests); ?></strong></span>
            </div>
        </form>
    </div>

    <!-- Section Heading -->
    <div class="flex items-center justify-between px-1">
        <div>
            <h2 class="text-base sm:text-lg font-bold text-slate-100">Workflow Requests</h2>
            <p class="text-[11px] sm:text-xs text-slate-400">Review student transitions and grant approval status.</p>
        </div>
    </div>

    <!-- Responsive Cards Grid -->
    <?php if (!empty($requests)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            <?php foreach ($requests as $r): ?>
                <div class="glass-card glass-card-hover p-4 sm:p-5 flex flex-col justify-between space-y-4 relative overflow-hidden group">
                    
                    <!-- Top Row: Student Details & Status Badge -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="pr-2">
                            <h3 class="font-bold text-base text-slate-100 tracking-tight leading-snug">
                                <?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?>
                            </h3>
                            <div class="text-[11px] font-mono text-cyanGlow mt-0.5">
                                <?php echo htmlspecialchars($r['student_code']); ?>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="shrink-0">
                            <?php if ($r['status'] === 'approved'): ?>
                                <span class="px-2.5 py-1 bg-emeraldGlow/10 text-emeraldGlow border border-emeraldGlow/30 rounded-md text-[10px] font-semibold">
                                    Approved
                                </span>
                            <?php elseif ($r['status'] === 'declined'): ?>
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

                    <!-- Middle Block: Promotion Transition & Session Info -->
                    <div class="p-3 rounded-xl border border-emeraldGlow/10 bg-inputBg space-y-2.5">
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase font-semibold block mb-1">Class Transition</span>
                            <div class="flex items-center space-x-2 text-xs">
                                <span class="px-2 py-0.5 bg-cardBg text-slate-300 rounded border border-emeraldGlow/20 font-medium">
                                    <?php echo htmlspecialchars($r['from_class_name']); ?>
                                </span>
                                <svg class="w-3.5 h-3.5 text-cyanGlow shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                <span class="px-2 py-0.5 bg-cyanGlow/10 text-cyanGlow rounded border border-cyanGlow/30 font-semibold">
                                    <?php echo htmlspecialchars($r['to_class_name']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-emeraldGlow/10 flex items-center justify-between text-[11px]">
                            <div>
                                <span class="text-slate-500 font-medium block">Academic Period</span>
                                <span class="text-slate-200 font-semibold block">
                                    <?php echo htmlspecialchars($r['session_name']); ?> &bull; <?php echo htmlspecialchars($r['term_name']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Details & Actions -->
                    <div class="space-y-3 pt-1 border-t border-emeraldGlow/10">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-medium">Submitted By:</span>
                            <span class="text-slate-200 font-semibold"><?php echo htmlspecialchars($r['staff_name']); ?></span>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="action" value="process_promotion">
                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" onclick="return confirm('Approve promotion to <?php echo htmlspecialchars($r['to_class_name']); ?>?')" class="px-3 py-1.5 bg-emeraldGlow/10 hover:bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/30 rounded-xl text-xs font-semibold transition-all cursor-pointer shadow-sm active:scale-95">
                                        Approve
                                    </button>
                                </form>

                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="action" value="process_promotion">
                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="status" value="declined">
                                    <button type="submit" onclick="return confirm('Decline this promotion request?')" class="px-3 py-1.5 bg-roseGlow/10 hover:bg-roseGlow/20 text-roseGlow border border-roseGlow/30 rounded-xl text-xs font-semibold transition-all cursor-pointer shadow-sm active:scale-95">
                                        Decline
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-500 italic">Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card text-center py-12 px-4 border border-dashed border-emeraldGlow/20">
            <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <p class="text-sm text-slate-400">No promotion requests found for this filter criteria.</p>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>