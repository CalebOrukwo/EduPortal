<?php
/**
 * School Fee Structure Management
 * File: admin/fees.php
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
// POST HANDLERS: CREATE, UPDATE, DELETE FEE ITEMS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. CREATE NEW FEE ITEM
        if ($action === 'create_fee') {
            $title = trim($_POST['title'] ?? '');
            $termId = (int) ($_POST['term_id'] ?? 0);
            $classId = !empty($_POST['class_id']) ? (int) $_POST['class_id'] : null;
            $amount = (float) ($_POST['amount'] ?? 0);

            if (empty($title) || $termId <= 0 || $amount <= 0) {
                throw new Exception("Fee title, valid term selection, and a positive amount are required.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO sch_fees (title, term_id, class_id, amount) 
                VALUES (:title, :term_id, :class_id, :amount)
            ");
            $stmt->execute([
                ':title' => $title,
                ':term_id' => $termId,
                ':class_id' => $classId,
                ':amount' => $amount
            ]);

            setFlashMessage('success', "Fee item '{$title}' created successfully.");
            header('Location: ' . BASE_URL . 'admin/fees.php');
            exit();
        }

        // 2. UPDATE FEE ITEM
        if ($action === 'update_fee') {
            $feeId = (int) ($_POST['fee_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $termId = (int) ($_POST['term_id'] ?? 0);
            $classId = !empty($_POST['class_id']) ? (int) $_POST['class_id'] : null;
            $amount = (float) ($_POST['amount'] ?? 0);

            if ($feeId <= 0 || empty($title) || $termId <= 0 || $amount <= 0) {
                throw new Exception("Invalid parameter values provided for fee item update.");
            }

            $stmt = $pdo->prepare("
                UPDATE sch_fees 
                SET title = :title, term_id = :term_id, class_id = :class_id, amount = :amount 
                WHERE id = :id
            ");
            $stmt->execute([
                ':title' => $title,
                ':term_id' => $termId,
                ':class_id' => $classId,
                ':amount' => $amount,
                ':id' => $feeId
            ]);

            setFlashMessage('success', "Fee item updated successfully.");
            header('Location: ' . BASE_URL . 'admin/fees.php');
            exit();
        }

        // 3. DELETE FEE ITEM
        if ($action === 'delete_fee') {
            $feeId = (int) ($_POST['fee_id'] ?? 0);

            if ($feeId <= 0) {
                throw new Exception("Invalid fee item specified for deletion.");
            }

            $stmt = $pdo->prepare("DELETE FROM sch_fees WHERE id = :id");
            $stmt->execute([':id' => $feeId]);

            setFlashMessage('success', "Fee item removed successfully.");
            header('Location: ' . BASE_URL . 'admin/fees.php');
            exit();
        }

    } catch (Exception $e) {
        error_log("Fee Setup Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'admin/fees.php');
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL & FILTERS
// -------------------------------------------------------------------------
$filterTermId = isset($_GET['term_id']) ? (int) $_GET['term_id'] : 0;
$filterClassId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;

try {
    // Fetch terms with session name
    $termsStmt = $pdo->query("
        SELECT t.id, t.term_name, t.is_current, s.name AS session_name 
        FROM sch_terms t
        JOIN sch_sessions s ON t.session_id = s.id
        ORDER BY s.id DESC, t.id DESC
    ");
    $terms = $termsStmt->fetchAll();

    // Fetch classes for assignment options
    $classesStmt = $pdo->query("SELECT id, name FROM sch_classes ORDER BY id ASC");
    $classes = $classesStmt->fetchAll();

    // Query builder for sch_fees table
    $query = "
        SELECT 
            f.*,
            t.term_name,
            s.name AS session_name,
            c.name AS class_name
        FROM sch_fees f
        JOIN sch_terms t ON f.term_id = t.id
        JOIN sch_sessions s ON t.session_id = s.id
        LEFT JOIN sch_classes c ON f.class_id = c.id
        WHERE 1=1
    ";
    $params = [];

    if ($filterTermId > 0) {
        $query .= " AND f.term_id = :term_id";
        $params[':term_id'] = $filterTermId;
    }

    if ($filterClassId > 0) {
        $query .= " AND (f.class_id = :class_id OR f.class_id IS NULL)";
        $params[':class_id'] = $filterClassId;
    }

    $query .= " ORDER BY f.id DESC";

    $feesStmt = $pdo->prepare($query);
    $feesStmt->execute($params);
    $fees = $feesStmt->fetchAll();

} catch (Exception $e) {
    error_log("Fee Retrieval Error: " . $e->getMessage());
    $terms = [];
    $classes = [];
    $fees = [];
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
            <h1 class="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight">Fee Structure Setup</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-0.5">Define term-based billable items for individual classes or general school fees.</p>
        </div>
        <button type="button" onclick="openCreateFeeModal()" class="glow-button px-4 py-2.5 text-white rounded-xl text-xs font-bold transition-all flex items-center space-x-2 cursor-pointer shadow-sm active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Create Fee Item</span>
        </button>
    </div>

    <!-- Filter Toolbar -->
    <div class="glass-card p-4 sm:p-5">
        <form method="GET" action="" class="flex flex-col md:flex-row items-center justify-between gap-3 sm:gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3 w-full md:w-auto">
                <select name="term_id" onchange="this.form.submit()" class="border border-emeraldGlow/20 rounded-xl px-3 py-2.5 text-xs font-medium text-slate-200 focus:outline-none focus:border-emeraldGlow w-full md:w-64">
                    <option value="0">All Sessions & Terms</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $filterTermId === $t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['session_name'] . ' - ' . $t['term_name']); ?> <?php echo $t['is_current'] ? '(Current)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="class_id" onchange="this.form.submit()" class="border border-emeraldGlow/20 rounded-xl px-3 py-2.5 text-xs font-medium text-slate-200 focus:outline-none focus:border-emeraldGlow w-full md:w-56">
                    <option value="0">All Classes / Universal</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filterClassId === $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center space-x-2 text-xs text-slate-400 w-full md:w-auto justify-end pt-1 md:pt-0">
                <span>Total Fee Items: <strong class="text-emeraldGlow font-mono"><?php echo count($fees); ?></strong></span>
            </div>
        </form>
    </div>

    <!-- Section Heading -->
    <div class="flex items-center justify-between px-1">
        <div>
            <h2 class="text-base sm:text-lg font-bold text-slate-100">Configured Fees</h2>
            <p class="text-[11px] sm:text-xs text-slate-400">Manage payment parameters and class allocations.</p>
        </div>
    </div>

    <!-- Responsive Cards Grid -->
    <?php if (!empty($fees)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            <?php foreach ($fees as $f): ?>
                <div class="glass-card glass-card-hover p-4 sm:p-5 flex flex-col justify-between space-y-4 relative overflow-hidden group">
                    
                    <!-- Top Row: Title & Amount -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="pr-2">
                            <h3 class="font-bold text-base text-slate-100 tracking-tight leading-snug">
                                <?php echo htmlspecialchars($f['title']); ?>
                            </h3>
                            <div class="text-[11px] text-slate-400 mt-0.5">
                                <?php echo htmlspecialchars($f['session_name']); ?>
                            </div>
                        </div>

                        <!-- Fee Amount Display -->
                        <div class="text-right shrink-0">
                            <span class="text-base sm:text-lg font-bold font-mono text-emeraldGlow">
                                &#8358;<?php echo number_format($f['amount'], 2); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Middle Block: Term & Target Class -->
                    <div class="p-3 rounded-xl border border-emeraldGlow/10 bg-inputBg space-y-2">
                        <div class="grid grid-cols-2 gap-2 text-[11px]">
                            <div>
                                <span class="text-[10px] text-slate-500 uppercase font-semibold block">Academic Term</span>
                                <span class="text-cyanGlow font-semibold block mt-0.5 truncate">
                                    <?php echo htmlspecialchars($f['term_name']); ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500 uppercase font-semibold block">Target Scope</span>
                                <div class="mt-0.5">
                                    <?php if ($f['class_name']): ?>
                                        <span class="inline-block px-2 py-0.5 bg-emeraldGlow/10 text-cyanGlow border border-cyanGlow/30 rounded-md text-[10px] font-semibold truncate">
                                            <?php echo htmlspecialchars($f['class_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-0.5 bg-emeraldGlow/10 text-emeraldGlow border border-emeraldGlow/30 rounded-md text-[10px] font-semibold">
                                            All Classes
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2 pt-1 border-t border-emeraldGlow/10">
                        <button 
                            type="button" 
                            onclick='openEditFeeModal(<?php echo htmlspecialchars(json_encode($f), ENT_QUOTES, 'UTF-8'); ?>)'
                            class="px-3 py-1.5 bg-cardBg hover:bg-cardHover text-slate-200 border border-emeraldGlow/20 rounded-xl text-xs font-semibold transition-all cursor-pointer shadow-sm active:scale-95"
                        >
                            Edit
                        </button>
                        <button 
                            type="button" 
                            onclick='openDeleteFeeModal(<?php echo htmlspecialchars(json_encode($f), ENT_QUOTES, 'UTF-8'); ?>)'
                            class="px-3 py-1.5 bg-roseGlow/10 hover:bg-roseGlow/20 text-roseGlow border border-roseGlow/30 rounded-xl text-xs font-semibold transition-all cursor-pointer shadow-sm active:scale-95"
                        >
                            Delete
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card text-center py-12 px-4 border border-dashed border-emeraldGlow/20">
            <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-slate-400">No fee structures configured matching your filter choices.</p>
        </div>
    <?php endif; ?>

</div>

<!-- CREATE / EDIT FEE MODAL -->
<div id="feeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-canvas/80 backdrop-blur-md hidden" style="display: none;" onclick="handleBackdropClick(event, 'feeModal')">
    <div class="glass-card max-w-md w-full p-5 sm:p-6 space-y-5 shadow-2xl relative border border-emeraldGlow/30" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between border-b border-emeraldGlow/15 pb-3">
            <h3 id="modalTitle" class="text-base sm:text-lg font-bold text-slate-100">Create Fee Item</h3>
            <button type="button" onclick="closeModal('feeModal')" class="text-slate-400 hover:text-white text-2xl font-bold transition-colors p-1 cursor-pointer" aria-label="Close modal">&times;</button>
        </div>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" id="formAction" value="create_fee">
            <input type="hidden" name="fee_id" id="feeId" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Fee Item Title</label>
                <input type="text" name="title" id="feeTitle" required placeholder="e.g. Tuition Fee, Exam Fee, Uniform" class="w-full border border-emeraldGlow/20 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Academic Session & Term</label>
                <select name="term_id" id="feeTermId" required class="w-full border border-emeraldGlow/20 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow">
                    <option value="">-- Select Term --</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?php echo $t['id']; ?>">
                            <?php echo htmlspecialchars($t['session_name'] . ' - ' . $t['term_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Target Class (Optional)</label>
                <select name="class_id" id="feeClassId" class="w-full border border-emeraldGlow/20 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow">
                    <option value="">All Classes (Universal Fee)</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-500 mt-1">Leave empty if the fee applies to all students across all classes.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Amount (&#8358;)</label>
                <input type="number" step="0.01" min="0" name="amount" id="feeAmount" required placeholder="0.00" class="w-full border border-emeraldGlow/20 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 font-mono focus:outline-none focus:border-emeraldGlow">
            </div>

            <div class="flex items-center justify-end space-x-3 pt-3 border-t border-emeraldGlow/15">
                <button type="button" onclick="closeModal('feeModal')" class="px-4 py-2.5 bg-cardBg hover:bg-cardHover text-slate-400 border border-emeraldGlow/20 rounded-xl text-xs font-semibold transition-colors cursor-pointer">Cancel</button>
                <button type="submit" class="glow-button px-5 py-2.5 text-white text-xs font-bold rounded-xl cursor-pointer">Save Fee Item</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-canvas/80 backdrop-blur-md hidden" style="display: none;" onclick="handleBackdropClick(event, 'deleteModal')">
    <div class="glass-card max-w-sm w-full p-5 sm:p-6 space-y-4 relative border border-roseGlow/30 shadow-2xl" onclick="event.stopPropagation()">
        <h3 class="text-base sm:text-lg font-bold text-slate-100">Confirm Deletion</h3>
        <p class="text-xs text-slate-400 leading-relaxed">Are you sure you want to delete <strong id="deleteFeeTitle" class="text-slate-100"></strong>? Existing payment records linked to this fee item may be affected.</p>
        
        <form method="POST" action="" class="flex items-center justify-end space-x-3 pt-2">
            <input type="hidden" name="action" value="delete_fee">
            <input type="hidden" name="fee_id" id="deleteFeeId" value="">
            
            <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2.5 bg-cardBg hover:bg-cardHover text-slate-400 border border-emeraldGlow/20 rounded-xl text-xs font-semibold transition-colors cursor-pointer">Cancel</button>
            <button type="submit" class="px-4 py-2.5 bg-roseGlow/20 hover:bg-roseGlow/30 text-roseGlow border border-roseGlow/40 rounded-xl text-xs font-bold transition-all cursor-pointer">Delete Fee</button>
        </form>
    </div>
</div>

<script>
function openCreateFeeModal() {
    document.getElementById('formAction').value = 'create_fee';
    document.getElementById('modalTitle').innerText = 'Create Fee Item';
    document.getElementById('feeId').value = '';
    document.getElementById('feeTitle').value = '';
    document.getElementById('feeTermId').value = '';
    document.getElementById('feeClassId').value = '';
    document.getElementById('feeAmount').value = '';
    
    const modal = document.getElementById('feeModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function openEditFeeModal(fee) {
    document.getElementById('formAction').value = 'update_fee';
    document.getElementById('modalTitle').innerText = 'Edit Fee Item';
    document.getElementById('feeId').value = fee.id;
    document.getElementById('feeTitle').value = fee.title;
    document.getElementById('feeTermId').value = fee.term_id;
    document.getElementById('feeClassId').value = fee.class_id || '';
    document.getElementById('feeAmount').value = fee.amount;
    
    const modal = document.getElementById('feeModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function openDeleteFeeModal(fee) {
    document.getElementById('deleteFeeId').value = fee.id;
    document.getElementById('deleteFeeTitle').innerText = fee.title;
    
    const modal = document.getElementById('deleteModal');
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
        closeModal('feeModal');
        closeModal('deleteModal');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>