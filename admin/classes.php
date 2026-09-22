<?php
/**
 * Class Setup & Progression Configuration
 * File: admin/classes.php
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
// POST HANDLERS: CREATE / UPDATE CLASS & PROGRESSION
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. ADD NEW CLASS
        if ($action === 'create_class') {
            $className = trim($_POST['name'] ?? '');
            $teachingMode = $_POST['teaching_mode'] ?? 'single_teacher';
            $nextClassId = !empty($_POST['next_class_id']) ? (int) $_POST['next_class_id'] : null;

            if (empty($className)) {
                throw new Exception("Class name is required.");
            }

            if (!in_array($teachingMode, ['single_teacher', 'subject_teacher'])) {
                $teachingMode = 'single_teacher';
            }

            $stmt = $pdo->prepare("
                INSERT INTO sch_classes (name, teaching_mode, next_class_id)
                VALUES (:name, :teaching_mode, :next_class_id)
            ");
            $stmt->execute([
                ':name' => $className,
                ':teaching_mode' => $teachingMode,
                ':next_class_id' => $nextClassId
            ]);

            setFlashMessage('success', "Class '$className' added successfully.");
            header('Location: ' . BASE_URL . 'admin/classes.php');
            exit();
        }

        // 2. UPDATE EXISTING CLASS & PROGRESSION PATH
        if ($action === 'update_class') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            $className = trim($_POST['name'] ?? '');
            $teachingMode = $_POST['teaching_mode'] ?? 'single_teacher';
            $nextClassId = !empty($_POST['next_class_id']) ? (int) $_POST['next_class_id'] : null;

            if ($classId <= 0 || empty($className)) {
                throw new Exception("Invalid class parameters provided.");
            }

            // Circular reference guard
            if ($nextClassId === $classId) {
                throw new Exception("A class cannot set itself as its own next progression class.");
            }

            if (!in_array($teachingMode, ['single_teacher', 'subject_teacher'])) {
                $teachingMode = 'single_teacher';
            }

            $stmt = $pdo->prepare("
                UPDATE sch_classes 
                SET name = :name, teaching_mode = :teaching_mode, next_class_id = :next_class_id
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $className,
                ':teaching_mode' => $teachingMode,
                ':next_class_id' => $nextClassId,
                ':id' => $classId
            ]);

            setFlashMessage('success', "Class '$className' updated successfully.");
            header('Location: ' . BASE_URL . 'admin/classes.php');
            exit();
        }

    } catch (Exception $e) {
        error_log("Class Management Admin Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'admin/classes.php');
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL
// -------------------------------------------------------------------------
try {
    // Fetch all classes along with next class progression name
    $classesStmt = $pdo->query("
        SELECT 
            c.id,
            c.name,
            c.teaching_mode,
            c.next_class_id,
            nc.name AS next_class_name,
            (SELECT COUNT(*) FROM sch_students s WHERE s.current_class_id = c.id AND s.status = 'active') AS student_count
        FROM sch_classes c
        LEFT JOIN sch_classes nc ON c.next_class_id = nc.id
        ORDER BY c.id ASC
    ");
    $classes = $classesStmt->fetchAll();

} catch (Exception $e) {
    error_log("Class Retrieval Error: " . $e->getMessage());
    $classes = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 sm:space-y-8">

    <!-- Header Section -->
    <div class="glass-card p-5 sm:p-8 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-5 sm:gap-6">
        <div>
            <div class="flex items-center space-x-2 text-[10px] sm:text-xs font-semibold text-emeraldGlow uppercase tracking-wider mb-1.5">
                <span>Admin Panel</span>
                <span>&bull;</span>
                <span>Academic Structure</span>
            </div>
            <h1 class="text-xl sm:text-3xl font-black text-slate-100 tracking-tight">Class & Progression Configuration</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1">Manage classes, set teaching delivery models, and configure student promotion flows.</p>
        </div>
        <button type="button" onclick="openCreateModal()" class="glow-button px-4 py-2.5 text-white rounded-xl text-xs font-bold transition-all flex items-center space-x-2 w-full md:w-auto justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Add New Class</span>
        </button>
    </div>

    <!-- Section Heading & Counter Bar -->
    <div class="flex items-center justify-between px-1">
        <div>
            <h2 class="text-base sm:text-lg font-bold text-slate-100">Configured Classes</h2>
            <p class="text-[11px] sm:text-xs text-slate-400">Class levels, active students, teaching models, and promotion targets.</p>
        </div>
        <div class="text-xs text-mintGlow font-mono font-semibold bg-emeraldGlow/10 border border-emeraldGlow/30 px-3 py-1 rounded-full shadow-sm">
            Total: <?php echo count($classes); ?>
        </div>
    </div>

    <!-- Responsive Class Cards Grid -->
    <?php if (!empty($classes)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            <?php foreach ($classes as $c): ?>
                <div class="glass-card p-4 sm:p-5 rounded-2xl flex flex-col justify-between space-y-4 relative overflow-hidden group hover:border-emeraldGlow/40 transition-all">
                    
                    <!-- Top Row: Class Name & Edit Button -->
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-slate-100 tracking-tight">
                                <?php echo htmlspecialchars($c['name']); ?>
                            </h3>
                            <div class="mt-1.5">
                                <?php if ($c['teaching_mode'] === 'single_teacher'): ?>
                                    <span class="inline-block px-2.5 py-0.5 bg-emeraldGlow/10 text-emeraldGlow border border-emeraldGlow/30 rounded-full text-[10px] sm:text-xs font-semibold">
                                        Single Teacher Mode
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-2.5 py-0.5 bg-cyanGlow/10 text-cyanGlow border border-cyanGlow/30 rounded-full text-[10px] sm:text-xs font-semibold">
                                        Subject Teacher Mode
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Touch-friendly Edit Action Button -->
                        <button 
                            type="button" 
                            onclick='openEditModal(<?php echo json_encode($c); ?>)'
                            class="px-3 py-1.5 bg-cardBg hover:bg-cardHover text-slate-200 border border-emeraldGlow/20 hover:border-emeraldGlow/40 rounded-xl text-xs font-bold transition-all shadow-sm active:scale-95 shrink-0 flex items-center gap-1.5 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5 text-emeraldGlow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>Edit</span>
                        </button>
                    </div>

                    <!-- Middle Info Cards -->
                    <div class="grid grid-cols-2 gap-2 bg-appBg/60 p-3 rounded-xl border border-emeraldGlow/10 text-xs shadow-inner">
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Active Students</span>
                            <span class="text-sm font-mono font-bold text-mintGlow mt-0.5 block">
                                <?php echo number_format($c['student_count']); ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Promotes To</span>
                            <div class="mt-0.5 font-medium truncate">
                                <?php if ($c['next_class_name']): ?>
                                    <span class="text-emeraldGlow inline-flex items-center gap-1 text-xs font-bold">
                                        <span class="truncate"><?php echo htmlspecialchars($c['next_class_name']); ?></span>
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-500 text-[11px] italic">Terminal / Grad</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card text-center py-12 px-4 rounded-2xl border border-dashed border-emeraldGlow/20">
            <svg class="w-12 h-12 text-slate-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <p class="text-xs sm:text-sm text-slate-400">No classes configured. Click 'Add New Class' above to create your first class level.</p>
        </div>
    <?php endif; ?>

</div>

<!-- CLASS FORM MODAL (CREATE & EDIT) -->
<div id="classModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-appBg/80 backdrop-blur-sm hidden" onclick="handleBackdropClick(event)">
    <div class="glass-card max-w-md w-full p-5 sm:p-6 space-y-5 shadow-2xl relative border-emeraldGlow/30" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between border-b border-emeraldGlow/10 pb-3">
            <h3 id="modalTitle" class="text-base sm:text-lg font-bold text-slate-100">Configure Class</h3>
            <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-100 text-2xl font-bold transition-colors p-1 cursor-pointer" aria-label="Close modal">&times;</button>
        </div>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" id="formAction" value="create_class">
            <input type="hidden" name="class_id" id="classId" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Class Name (e.g. JSS 1, SS 2 Alpha)</label>
                <input type="text" name="name" id="className" required placeholder="JSS 1" class="w-full bg-appBg/80 border border-emeraldGlow/20 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emeraldGlow transition-colors">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Teaching Delivery Mode</label>
                <select name="teaching_mode" id="teachingMode" required class="w-full bg-appBg/80 border border-emeraldGlow/20 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emeraldGlow transition-colors">
                    <option value="single_teacher">Single Teacher Mode (Primary/Nursery standard)</option>
                    <option value="subject_teacher">Subject Teacher Mode (Secondary standard)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Next Progression Class</label>
                <select name="next_class_id" id="nextClassId" class="w-full bg-appBg/80 border border-emeraldGlow/20 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emeraldGlow transition-colors">
                    <option value="">-- Terminal Class (Graduation Level) --</option>
                    <?php foreach ($classes as $opt): ?>
                        <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Defines where students in this class promote to during session rollover.</p>
            </div>

            <div class="flex justify-end space-x-3 pt-3 border-t border-emeraldGlow/10">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 bg-cardBg hover:bg-cardHover text-slate-300 hover:text-slate-100 rounded-xl text-xs font-semibold transition-colors cursor-pointer border border-emeraldGlow/20">Cancel</button>
                <button type="submit" class="glow-button px-5 py-2.5 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer">Save Class Setup</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('formAction').value = 'create_class';
    document.getElementById('modalTitle').innerText = 'Add New Class';
    document.getElementById('classId').value = '';
    document.getElementById('className').value = '';
    document.getElementById('teachingMode').value = 'single_teacher';
    document.getElementById('nextClassId').value = '';
    
    // Enable all options
    const options = document.querySelectorAll('#nextClassId option');
    options.forEach(opt => opt.disabled = false);

    const modal = document.getElementById('classModal');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function openEditModal(classData) {
    document.getElementById('formAction').value = 'update_class';
    document.getElementById('modalTitle').innerText = 'Edit Class Configuration';
    document.getElementById('classId').value = classData.id;
    document.getElementById('className').value = classData.name;
    document.getElementById('teachingMode').value = classData.teaching_mode;
    document.getElementById('nextClassId').value = classData.next_class_id || '';

    // Disable selecting self as next class
    const options = document.querySelectorAll('#nextClassId option');
    options.forEach(opt => {
        if (parseInt(opt.value) === parseInt(classData.id)) {
            opt.disabled = true;
        } else {
            opt.disabled = false;
        }
    });

    const modal = document.getElementById('classModal');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function closeModal() {
    const modal = document.getElementById('classModal');
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

function handleBackdropClick(event) {
    if (event.target === document.getElementById('classModal')) {
        closeModal();
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>