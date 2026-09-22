<?php
/**
 * Staff Management & Class Allocation
 * File: admin/staff.php
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
// POST HANDLERS: STAFF CREATION & CLASS ASSIGNMENTS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. CREATE NEW STAFF USER IN sch_users
        if ($action === 'create_staff') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
                throw new Exception("First name, last name, email, and password are required.");
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address format.");
            }

            // Check email uniqueness
            $checkStmt = $pdo->prepare("SELECT id FROM sch_users WHERE email = :email");
            $checkStmt->execute([':email' => $email]);
            if ($checkStmt->fetch()) {
                throw new Exception("A user with this email address already exists.");
            }

            $fullName = $firstName . ' ' . $lastName;
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Corrected INSERT statement matching sch_users schema
            $stmt = $pdo->prepare("
                INSERT INTO sch_users (full_name, email, password, role, status)
                VALUES (:full_name, :email, :password, 'staff', 'active')
            ");
            $stmt->execute([
                ':full_name' => $fullName,
                ':email'     => $email,
                ':password'  => $passwordHash
            ]);

            setFlashMessage('success', "Staff account for {$fullName} created successfully.");
            header('Location: ' . BASE_URL . 'admin/staff.php');
            exit();
        }

        // 2. ASSIGN CLASS / FORM TEACHER STATUS TO STAFF
        if ($action === 'assign_class') {
            $staffId = (int) ($_POST['staff_id'] ?? 0);
            $classId = (int) ($_POST['class_id'] ?? 0);
            $isFormTeacher = isset($_POST['is_form_teacher']) ? 1 : 0;

            if ($staffId <= 0 || $classId <= 0) {
                throw new Exception("Please select a valid staff member and class.");
            }

            $pdo->beginTransaction();

            // Clear existing form teacher designation for this class if assigning a new one
            if ($isFormTeacher === 1) {
                $clearFormStmt = $pdo->prepare("UPDATE sch_staff_classes SET is_form_teacher = 0 WHERE class_id = :class_id");
                $clearFormStmt->execute([':class_id' => $classId]);
            }

            // Assign or update allocation using distinct placeholder names
            $stmt = $pdo->prepare("
                INSERT INTO sch_staff_classes (staff_id, class_id, is_form_teacher)
                VALUES (:staff_id, :class_id, :is_form_teacher)
                ON DUPLICATE KEY UPDATE is_form_teacher = :update_is_form_teacher
            ");
            $stmt->execute([
                ':staff_id'               => $staffId,
                ':class_id'               => $classId,
                ':is_form_teacher'        => $isFormTeacher,
                ':update_is_form_teacher' => $isFormTeacher
            ]);

            $pdo->commit();
            setFlashMessage('success', "Staff class assignment updated successfully.");
            header('Location: ' . BASE_URL . 'admin/staff.php');
            exit();
        }

        // 3. REMOVE CLASS ASSIGNMENT
        if ($action === 'remove_assignment') {
            $staffId = (int) ($_POST['staff_id'] ?? 0);
            $classId = (int) ($_POST['class_id'] ?? 0);

            if ($staffId > 0 && $classId > 0) {
                $stmt = $pdo->prepare("DELETE FROM sch_staff_classes WHERE staff_id = :staff_id AND class_id = :class_id");
                $stmt->execute([':staff_id' => $staffId, ':class_id' => $classId]);
                setFlashMessage('success', "Class assignment removed.");
            }
            header('Location: ' . BASE_URL . 'admin/staff.php');
            exit();
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Staff Admin Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'admin/staff.php');
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL
// -------------------------------------------------------------------------
try {
    // Fetch classes for modal dropdown
    $classesStmt = $pdo->query("SELECT id, name FROM sch_classes ORDER BY id ASC");
    $classes = $classesStmt->fetchAll();

    // Fetch staff list matching sch_users schema
    $staffStmt = $pdo->query("
        SELECT 
            u.id AS staff_id,
            u.full_name,
            u.email,
            u.status,
            GROUP_CONCAT(
                CONCAT(c.id, ':', c.name, ':', sc.is_form_teacher) 
                SEPARATOR '||'
            ) AS class_allocations
        FROM sch_users u
        LEFT JOIN sch_staff_classes sc ON u.id = sc.staff_id
        LEFT JOIN sch_classes c ON sc.class_id = c.id
        WHERE u.role = 'staff'
        GROUP BY u.id
        ORDER BY u.full_name ASC
    ");
    $staffMembers = $staffStmt->fetchAll();

} catch (Exception $e) {
    error_log("Staff Fetch Error: " . $e->getMessage());
    $classes = [];
    $staffMembers = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="space-y-6 sm:space-y-8">

    <div class="glass-card p-5 sm:p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-[10px] sm:text-xs font-semibold text-cyanGlow uppercase tracking-wider mb-1">
                <span>Admin Panel</span>
                <span>&bull;</span>
                <span>Staff Management</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight">Staff Inventory & Class Allocation</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-0.5">Manage academic staff accounts, assign classes, and set Form Teachers.</p>
        </div>
        <button onclick="openCreateStaffModal()" class="w-full md:w-auto px-4 py-2.5 bg-emeraldGlow/10 hover:bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/30 rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-2 cursor-pointer shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Add Staff Member</span>
        </button>
    </div>

    <div class="glass-card p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-emeraldGlow/10 pb-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-100">Academic Staff Directory</h2>
                <p class="text-[11px] sm:text-xs text-slate-400">View staff contact details, assigned classes, and form teacher status.</p>
            </div>
            <div class="text-xs text-cyanGlow font-mono font-semibold">
                Total: <?php echo count($staffMembers); ?>
            </div>
        </div>

        <?php if (!empty($staffMembers)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($staffMembers as $staff): ?>
                    <div class="bg-inputBg border border-emeraldGlow/10 rounded-xl p-4 space-y-3.5 flex flex-col justify-between">
                        
                        <div class="space-y-1">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-base font-bold text-slate-100"><?php echo htmlspecialchars($staff['full_name']); ?></h3>
                                    <p class="text-xs text-slate-400 break-all"><?php echo htmlspecialchars($staff['email']); ?></p>
                                </div>
                                <span class="px-2.5 py-1 bg-emeraldGlow/10 text-emeraldGlow border border-emeraldGlow/30 rounded-full text-[10px] font-bold uppercase tracking-wider shrink-0">
                                    <?php echo ucfirst($staff['status']); ?>
                                </span>
                            </div>
                            <div class="text-[11px] font-mono text-cyanGlow">ID: #<?php echo $staff['staff_id']; ?></div>
                        </div>

                        <div class="bg-cardBg/50 p-3 rounded-lg border border-emeraldGlow/5 space-y-2">
                            <div class="text-[10px] uppercase font-semibold text-slate-400 tracking-wider">Class Allocations</div>
                            
                            <?php if (!empty($staff['class_allocations'])): ?>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php 
                                    $allocations = explode('||', $staff['class_allocations']);
                                    foreach ($allocations as $alloc):
                                        list($clsId, $clsName, $isForm) = explode(':', $alloc);
                                    ?>
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-inputBg border border-emeraldGlow/20 text-slate-200">
                                            <span><?php echo htmlspecialchars($clsName); ?></span>
                                            <?php if ($isForm == '1'): ?>
                                                <span class="px-1.5 py-0.5 bg-amber-500/10 text-amber-400 border border-amber-500/30 rounded text-[9px] font-bold uppercase">
                                                    Form Teacher
                                                </span>
                                            <?php endif; ?>
                                            <form method="POST" action="" class="inline ml-1">
                                                <input type="hidden" name="action" value="remove_assignment">
                                                <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                                <input type="hidden" name="class_id" value="<?php echo $clsId; ?>">
                                                <button type="submit" title="Remove class" class="text-slate-500 hover:text-rose-400 font-bold cursor-pointer transition-colors">&times;</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-xs text-slate-500 italic">No classes assigned</span>
                            <?php endif; ?>
                        </div>

                        <div class="pt-2 border-t border-emeraldGlow/10 flex items-center justify-end">
                            <button 
                                type="button" 
                                onclick='openAssignModal(<?php echo $staff['staff_id']; ?>, "<?php echo htmlspecialchars($staff['full_name']); ?>")'
                                class="w-full sm:w-auto px-3 py-1.5 bg-cardBg hover:bg-cyanGlow/10 text-cyanGlow border border-cyanGlow/30 rounded-lg text-xs font-semibold transition-all cursor-pointer text-center"
                            >
                                + Assign Class
                            </button>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-slate-500 text-xs sm:text-sm bg-inputBg border border-emeraldGlow/10 rounded-xl">
                No staff members found. Click 'Add Staff Member' to register staff.
            </div>
        <?php endif; ?>
    </div>

</div>

<div id="createStaffModal" class="fixed inset-0 z-50 hidden bg-cardBg/80 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card max-w-md w-full p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-emeraldGlow/10 pb-3">
            <h3 class="text-lg font-bold text-slate-100">Add Academic Staff</h3>
            <button onclick="closeModal('createStaffModal')" class="text-slate-400 hover:text-slate-100 text-xl font-bold cursor-pointer">&times;</button>
        </div>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="create_staff">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">First Name</label>
                    <input type="text" name="first_name" required placeholder="John" class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-3.5 py-2 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow placeholder:text-slate-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Last Name</label>
                    <input type="text" name="last_name" required placeholder="Doe" class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-3.5 py-2 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow placeholder:text-slate-600">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Email Address</label>
                <input type="email" name="email" required placeholder="staff@school.com" class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-3.5 py-2 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow placeholder:text-slate-600">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Initial Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-3.5 py-2 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow placeholder:text-slate-600">
            </div>

            <div class="flex justify-end space-x-3 pt-3 border-t border-emeraldGlow/10">
                <button type="button" onclick="closeModal('createStaffModal')" class="px-4 py-2 bg-inputBg text-slate-400 rounded-xl text-xs font-semibold hover:text-slate-200 cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-emeraldGlow/10 hover:bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/30 rounded-xl text-xs font-bold transition-all cursor-pointer">Save Staff Account</button>
            </div>
        </form>
    </div>
</div>

<div id="assignClassModal" class="fixed inset-0 z-50 hidden bg-cardBg/80 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card max-w-md w-full p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-emeraldGlow/10 pb-3">
            <h3 class="text-lg font-bold text-slate-100">Assign Class Allocation</h3>
            <button onclick="closeModal('assignClassModal')" class="text-slate-400 hover:text-slate-100 text-xl font-bold cursor-pointer">&times;</button>
        </div>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="assign_class">
            <input type="hidden" name="staff_id" id="modalStaffId" value="">

            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Staff Member</label>
                <div id="modalStaffName" class="text-sm font-bold text-slate-100 bg-inputBg px-4 py-2.5 border border-emeraldGlow/20 rounded-xl"></div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Select Class</label>
                <select name="class_id" required class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emeraldGlow">
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center space-x-2 pt-2">
                <input type="checkbox" id="is_form_teacher" name="is_form_teacher" value="1" class="rounded bg-inputBg border-emeraldGlow/20 text-amber-500 focus:ring-0">
                <label for="is_form_teacher" class="text-xs text-slate-300 font-medium cursor-pointer">Designate as Form Teacher for this class</label>
            </div>

            <div class="flex justify-end space-x-3 pt-3 border-t border-emeraldGlow/10">
                <button type="button" onclick="closeModal('assignClassModal')" class="px-4 py-2 bg-inputBg text-slate-400 rounded-xl text-xs font-semibold hover:text-slate-200 cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-emeraldGlow/10 hover:bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/30 rounded-xl text-xs font-bold transition-all cursor-pointer">Confirm Allocation</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateStaffModal() {
    document.getElementById('createStaffModal').classList.remove('hidden');
}

function openAssignModal(staffId, staffName) {
    document.getElementById('modalStaffId').value = staffId;
    document.getElementById('modalStaffName').innerText = staffName;
    document.getElementById('assignClassModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>