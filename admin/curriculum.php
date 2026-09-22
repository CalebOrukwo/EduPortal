<?php
/**
 * Curriculum Oversight Dashboard
 * File: admin/curriculum.php
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
// POST HANDLERS: APPROVE OR REJECT CURRICULUM SUBMISSIONS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_status') {
            $curriculumId = (int) ($_POST['curriculum_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if ($curriculumId <= 0 || !in_array($status, ['approved', 'rejected', 'draft'])) {
                throw new Exception("Invalid parameters provided for curriculum review.");
            }

            $stmt = $pdo->prepare("
                UPDATE sch_curricula 
                SET status = :status, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':id' => $curriculumId
            ]);

            setFlashMessage('success', "Curriculum scheme status updated to " . strtoupper($status) . ".");
            header('Location: ' . BASE_URL . 'admin/curriculum.php');
            exit();
        }
    } catch (Exception $e) {
        error_log("Curriculum Oversight Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'admin/curriculum.php');
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL & FILTERS
// -------------------------------------------------------------------------
$filterStatus = $_GET['status'] ?? '';
$filterClassId = (int) ($_GET['class_id'] ?? 0);
$filterSubjectId = (int) ($_GET['subject_id'] ?? 0);

try {
    // Fetch classes and subjects for filter dropdowns
    $classesStmt = $pdo->query("SELECT id, name FROM sch_classes ORDER BY id ASC");
    $classes = $classesStmt->fetchAll();

    $subjectsStmt = $pdo->query("SELECT id, name, code FROM sch_subjects ORDER BY name ASC");
    $subjects = $subjectsStmt->fetchAll();

    // Query builder for sch_curricula table
    $query = "
        SELECT 
            c.*,
            sub.name AS subject_name,
            sub.code AS subject_code,
            cls.name AS class_name,
            u.full_name AS teacher_name,
            t.term_name,
            s.name AS session_name
        FROM sch_curricula c
        JOIN sch_subjects sub ON c.subject_id = sub.id
        JOIN sch_classes cls ON c.class_id = cls.id
        JOIN sch_users u ON c.staff_id = u.id
        JOIN sch_terms t ON c.term_id = t.id
        JOIN sch_sessions s ON t.session_id = s.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filterStatus) && in_array($filterStatus, ['draft', 'pending', 'approved', 'rejected'])) {
        $query .= " AND c.status = :status";
        $params[':status'] = $filterStatus;
    }

    if ($filterClassId > 0) {
        $query .= " AND c.class_id = :class_id";
        $params[':class_id'] = $filterClassId;
    }

    if ($filterSubjectId > 0) {
        $query .= " AND c.subject_id = :subject_id";
        $params[':subject_id'] = $filterSubjectId;
    }

    $query .= " ORDER BY c.id DESC";

    $curriculaStmt = $pdo->prepare($query);
    $curriculaStmt->execute($params);
    $curricula = $curriculaStmt->fetchAll();

} catch (Exception $e) {
    error_log("Curriculum Fetch Error: " . $e->getMessage());
    $classes = [];
    $subjects = [];
    $curricula = [];
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
                <span>Academic Standards</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight">Curriculum Oversight</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-0.5">Review, approve, or request revisions for teacher lesson schemes and topic outlines.</p>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="glass-card p-4 sm:p-5">
        <form method="GET" action="" class="flex flex-col md:flex-row items-center justify-between gap-3 sm:gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3 w-full md:w-auto">
                <!-- Status Filter -->
                <select name="status" onchange="this.form.submit()" class="border border-emeraldGlow/20 rounded-xl px-3 py-2.5 text-xs font-medium text-slate-200 focus:outline-none focus:border-emeraldGlow w-full">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                    <option value="draft" <?php echo $filterStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $filterStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>

                <!-- Class Filter -->
                <select name="class_id" onchange="this.form.submit()" class="border border-emeraldGlow/20 rounded-xl px-3 py-2.5 text-xs font-medium text-slate-200 focus:outline-none focus:border-emeraldGlow w-full">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?php echo $cls['id']; ?>" <?php echo $filterClassId === (int)$cls['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cls['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Subject Filter -->
                <select name="subject_id" onchange="this.form.submit()" class="border border-emeraldGlow/20 rounded-xl px-3 py-2.5 text-xs font-medium text-slate-200 focus:outline-none focus:border-emeraldGlow w-full">
                    <option value="0">All Subjects</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?php echo $sub['id']; ?>" <?php echo $filterSubjectId === (int)$sub['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sub['name'] . ' (' . $sub['code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center space-x-2 text-xs text-slate-400 w-full md:w-auto justify-end pt-1 md:pt-0">
                <span>Total Submissions: <strong class="text-emeraldGlow font-mono"><?php echo count($curricula); ?></strong></span>
            </div>
        </form>
    </div>

    <!-- Section Heading -->
    <div class="flex items-center justify-between px-1">
        <div>
            <h2 class="text-base sm:text-lg font-bold text-slate-100">Curriculum Schemes</h2>
            <p class="text-[11px] sm:text-xs text-slate-400">Review teacher outlines and update publication status.</p>
        </div>
    </div>

    <!-- Responsive Cards Grid -->
    <?php if (!empty($curricula)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            <?php foreach ($curricula as $curr): ?>
                <div class="glass-card glass-card-hover p-4 sm:p-5 flex flex-col justify-between space-y-4 relative overflow-hidden group">
                    
                    <!-- Top Row: Subject, Class & Status Badge -->
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-bold text-base text-slate-100 tracking-tight">
                                <?php echo htmlspecialchars($curr['subject_name']); ?>
                            </div>
                            <div class="text-xs text-emeraldGlow font-semibold mt-0.5">
                                <?php echo htmlspecialchars($curr['class_name']); ?>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="shrink-0">
                            <?php if ($curr['status'] === 'approved'): ?>
                                <span class="inline-block px-2.5 py-0.5 bg-emeraldGlow/10 text-emeraldGlow border border-emeraldGlow/30 rounded-full text-[10px] sm:text-xs font-semibold">
                                    Approved
                                </span>
                            <?php elseif ($curr['status'] === 'rejected'): ?>
                                <span class="inline-block px-2.5 py-0.5 bg-roseGlow/10 text-roseGlow border border-roseGlow/30 rounded-full text-[10px] sm:text-xs font-semibold">
                                    Rejected
                                </span>
                            <?php elseif ($curr['status'] === 'pending'): ?>
                                <span class="inline-block px-2.5 py-0.5 bg-amberGlow/10 text-amberGlow border border-amberGlow/30 rounded-full text-[10px] sm:text-xs font-semibold">
                                    Pending
                                </span>
                            <?php else: ?>
                                <span class="inline-block px-2.5 py-0.5 bg-slate-500/10 text-slate-400 border border-slate-500/30 rounded-full text-[10px] sm:text-xs font-semibold">
                                    Draft
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Middle Content Block -->
                    <div class="p-3 rounded-xl border border-emeraldGlow/10 bg-inputBg space-y-2">
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase font-semibold block">Topic / Title</span>
                            <p class="text-xs font-bold text-slate-200 line-clamp-1 mt-0.5">
                                <?php echo htmlspecialchars($curr['title'] ?? $curr['topic'] ?? 'Lesson Scheme'); ?>
                            </p>
                            <p class="text-[11px] text-slate-400 line-clamp-2 mt-0.5">
                                <?php echo htmlspecialchars($curr['description'] ?? $curr['content'] ?? 'No detailed description provided.'); ?>
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-emeraldGlow/10 text-[11px]">
                            <div>
                                <span class="text-[10px] text-slate-500 uppercase font-semibold block">Submitted By</span>
                                <span class="text-slate-300 font-medium truncate block mt-0.5">
                                    <?php echo htmlspecialchars($curr['teacher_name']); ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500 uppercase font-semibold block">Term / Session</span>
                                <span class="text-slate-300 font-medium truncate block mt-0.5">
                                    <?php echo htmlspecialchars($curr['term_name']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="pt-1">
                        <button 
                            type="button" 
                            data-curriculum='<?php echo htmlspecialchars(json_encode($curr), ENT_QUOTES, 'UTF-8'); ?>'
                            onclick="handleReviewClick(this)"
                            class="w-full py-2.5 bg-cardBg hover:bg-cardHover text-cyanGlow border border-cyanGlow/30 rounded-xl text-xs font-bold transition-all shadow-sm active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <svg class="w-4 h-4 text-cyanGlow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span>Review Scheme</span>
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card text-center py-12 px-4 border border-dashed border-emeraldGlow/20">
            <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <p class="text-sm text-slate-400">No curriculum schemes found matching your search filters.</p>
        </div>
    <?php endif; ?>

</div>

<!-- CURRICULUM REVIEW MODAL -->
<div id="reviewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-canvas/80 backdrop-blur-md hidden" style="display: none;" onclick="handleBackdropClick(event)">
    <div class="glass-card max-w-2xl w-full p-5 sm:p-6 space-y-5 shadow-2xl relative border border-emeraldGlow/30" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between border-b border-emeraldGlow/15 pb-3">
            <div>
                <h3 id="modalSubjectClass" class="text-base sm:text-lg font-bold text-slate-100">Curriculum Review</h3>
                <p id="modalTeacher" class="text-xs text-slate-400 mt-0.5"></p>
            </div>
            <button type="button" onclick="closeModal('reviewModal')" class="text-slate-400 hover:text-white text-2xl font-bold transition-colors p-1 cursor-pointer" aria-label="Close modal">&times;</button>
        </div>

        <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-1">
            <div>
                <label class="block text-[10px] sm:text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Title / Topic</label>
                <div id="modalTitle" class="text-xs sm:text-sm font-bold text-slate-100 p-3 rounded-xl border border-emeraldGlow/15 bg-inputBg"></div>
            </div>

            <div>
                <label class="block text-[10px] sm:text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Content / Outline</label>
                <div id="modalContent" class="text-xs text-slate-300 p-3.5 rounded-xl border border-emeraldGlow/15 bg-inputBg whitespace-pre-wrap leading-relaxed min-h-[100px]"></div>
            </div>
        </div>

        <form method="POST" action="" class="pt-3 border-t border-emeraldGlow/15">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="curriculum_id" id="modalCurriculumId" value="">

            <div class="flex flex-col sm:flex-row items-center justify-end gap-2.5 sm:gap-3 pt-2">
                <button type="button" onclick="closeModal('reviewModal')" class="w-full sm:w-auto px-4 py-2.5 bg-cardBg hover:bg-cardHover text-slate-400 border border-emeraldGlow/20 rounded-xl text-xs font-semibold transition-colors cursor-pointer">Cancel</button>
                <button type="submit" name="status" value="rejected" class="w-full sm:w-auto px-4 py-2.5 bg-roseGlow/15 hover:bg-roseGlow/30 text-roseGlow border border-roseGlow/30 rounded-xl text-xs font-bold transition-all cursor-pointer">Reject Scheme</button>
                <button type="submit" name="status" value="approved" class="glow-button w-full sm:w-auto px-5 py-2.5 text-white text-xs font-bold rounded-xl cursor-pointer">Approve Scheme</button>
            </div>
        </form>
    </div>
</div>

<script>
function handleReviewClick(btn) {
    try {
        const rawData = btn.getAttribute('data-curriculum');
        if (!rawData) return;
        const curr = JSON.parse(rawData);
        openReviewModal(curr);
    } catch (e) {
        console.error("Failed to parse curriculum JSON:", e);
    }
}

function openReviewModal(curr) {
    document.getElementById('modalCurriculumId').value = curr.id || '';
    document.getElementById('modalSubjectClass').innerText = (curr.subject_name || 'Subject') + ' - ' + (curr.class_name || 'Class');
    document.getElementById('modalTeacher').innerText = 'Submitted by: ' + (curr.teacher_name || 'Teacher') + ' (' + (curr.session_name || '') + ' - ' + (curr.term_name || '') + ')';
    document.getElementById('modalTitle').innerText = curr.title || curr.topic || 'Untitled Scheme';
    document.getElementById('modalContent').innerText = curr.content || curr.description || 'No detailed content provided.';
    
    const modal = document.getElementById('reviewModal');
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

function handleBackdropClick(event) {
    if (event.target === document.getElementById('reviewModal')) {
        closeModal('reviewModal');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('reviewModal');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>