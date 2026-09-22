<?php
/**
 * Academic Sessions & Terms Management
 * File: admin/sessions-terms.php
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
// POST HANDLERS: CREATE / UPDATE / TOGGLE FOR SESSIONS & TERMS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. ADD NEW SESSION
        if ($action === 'create_session') {
            $sessionName = trim($_POST['session_name'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($sessionName)) {
                throw new Exception("Session name (e.g. 2026/2027) is required.");
            }

            $pdo->beginTransaction();

            if ($isActive === 1) {
                // Deactivate all other sessions
                $pdo->exec("UPDATE sch_sessions SET is_active = 0");
            }

            $stmt = $pdo->prepare("INSERT INTO sch_sessions (name, is_active) VALUES (:name, :is_active)");
            $stmt->execute([
                ':name' => $sessionName,
                ':is_active' => $isActive
            ]);

            $pdo->commit();
            setFlashMessage('success', "Academic session '$sessionName' created successfully.");
            header('Location: ' . BASE_URL . 'admin/sessions-terms.php');
            exit();
        }

        // 1b. EDIT EXISTING SESSION
        if ($action === 'edit_session') {
            $sessionId = (int) ($_POST['session_id'] ?? 0);
            $sessionName = trim($_POST['session_name'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($sessionId <= 0 || empty($sessionName)) {
                throw new Exception("Invalid session data provided.");
            }

            $pdo->beginTransaction();

            if ($isActive === 1) {
                // Deactivate all other sessions
                $pdo->exec("UPDATE sch_sessions SET is_active = 0");
            }

            $stmt = $pdo->prepare("UPDATE sch_sessions SET name = :name, is_active = :is_active WHERE id = :id");
            $stmt->execute([
                ':name' => $sessionName,
                ':is_active' => $isActive,
                ':id' => $sessionId
            ]);

            $pdo->commit();
            setFlashMessage('success', "Academic session updated successfully.");
            header('Location: ' . BASE_URL . 'admin/sessions-terms.php');
            exit();
        }

        // 2. TOGGLE SESSION ACTIVE STATUS
        if ($action === 'activate_session') {
            $sessionId = (int) ($_POST['session_id'] ?? 0);

            if ($sessionId > 0) {
                $pdo->beginTransaction();
                // Deactivate all
                $pdo->exec("UPDATE sch_sessions SET is_active = 0");
                // Activate selected
                $stmt = $pdo->prepare("UPDATE sch_sessions SET is_active = 1 WHERE id = :id");
                $stmt->execute([':id' => $sessionId]);
                $pdo->commit();

                setFlashMessage('success', "Active academic session updated.");
            }
            header('Location: ' . BASE_URL . 'admin/sessions-terms.php');
            exit();
        }

        // 3. ADD NEW TERM
        if ($action === 'create_term') {
            $sessionId = (int) ($_POST['session_id'] ?? 0);
            $termName = trim($_POST['term_name'] ?? '');
            $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $isPromotional = isset($_POST['is_promotional']) ? 1 : 0;
            $isCurrent = isset($_POST['is_current']) ? 1 : 0;

            if ($sessionId <= 0 || empty($termName)) {
                throw new Exception("Please select a session and provide a valid term name.");
            }

            $pdo->beginTransaction();

            if ($isCurrent === 1) {
                // Set all other terms to not current
                $pdo->exec("UPDATE sch_terms SET is_current = 0");
            }

            $stmt = $pdo->prepare("
                INSERT INTO sch_terms (session_id, term_name, start_date, end_date, is_promotional, is_current)
                VALUES (:session_id, :term_name, :start_date, :end_date, :is_promotional, :is_current)
            ");
            $stmt->execute([
                ':session_id' => $sessionId,
                ':term_name' => $termName,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':is_promotional' => $isPromotional,
                ':is_current' => $isCurrent
            ]);

            $pdo->commit();
            setFlashMessage('success', "Academic term '$termName' created successfully.");
            header('Location: ' . BASE_URL . 'admin/sessions-terms.php');
            exit();
        }

        // 3b. EDIT EXISTING TERM
        if ($action === 'edit_term') {
            $termId = (int) ($_POST['term_id'] ?? 0);
            $sessionId = (int) ($_POST['session_id'] ?? 0);
            $termName = trim($_POST['term_name'] ?? '');
            $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $isPromotional = isset($_POST['is_promotional']) ? 1 : 0;
            $isCurrent = isset($_POST['is_current']) ? 1 : 0;

            if ($termId <= 0 || $sessionId <= 0 || empty($termName)) {
                throw new Exception("Invalid term parameters provided.");
            }

            $pdo->beginTransaction();

            if ($isCurrent === 1) {
                $pdo->exec("UPDATE sch_terms SET is_current = 0");
            }

            $stmt = $pdo->prepare("
                UPDATE sch_terms 
                SET session_id = :session_id,
                    term_name = :term_name,
                    start_date = :start_date,
                    end_date = :end_date,
                    is_promotional = :is_promotional,
                    is_current = :is_current
                WHERE id = :id
            ");
            $stmt->execute([
                ':session_id' => $sessionId,
                ':term_name' => $termName,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':is_promotional' => $isPromotional,
                ':is_current' => $isCurrent,
                ':id' => $termId
            ]);

            $pdo->commit();
            setFlashMessage('success', "Academic term updated successfully.");
            header('Location: ' . BASE_URL . 'admin/sessions-terms.php');
            exit();
        }

        // 4. TOGGLE TERM SETTINGS (is_current / is_promotional)
        if ($action === 'update_term_status') {
            $termId = (int) ($_POST['term_id'] ?? 0);
            $type = $_POST['type'] ?? '';

            if ($termId > 0) {
                if ($type === 'set_current') {
                    $pdo->beginTransaction();
                    $pdo->exec("UPDATE sch_terms SET is_current = 0");
                    $stmt = $pdo->prepare("UPDATE sch_terms SET is_current = 1 WHERE id = :id");
                    $stmt->execute([':id' => $termId]);
                    $pdo->commit();
                    setFlashMessage('success', "Current active term updated.");
                } elseif ($type === 'toggle_promotional') {
                    $stmt = $pdo->prepare("UPDATE sch_terms SET is_promotional = NOT is_promotional WHERE id = :id");
                    $stmt->execute([':id' => $termId]);
                    setFlashMessage('success', "Promotional status updated.");
                }
            }
            header('Location: ' . BASE_URL . 'admin/sessions-terms.php');
            exit();
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Session/Term Admin Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'admin/sessions-terms.php');
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL
// -------------------------------------------------------------------------
try {
    // Retrieve all sessions
    $sessionsStmt = $pdo->query("SELECT * FROM sch_sessions ORDER BY id DESC");
    $sessions = $sessionsStmt->fetchAll();

    // Retrieve all terms with session details
    $termsStmt = $pdo->query("
        SELECT 
            t.*, 
            s.name AS session_name,
            s.is_active AS session_is_active
        FROM sch_terms t
        JOIN sch_sessions s ON t.session_id = s.id
        ORDER BY s.id DESC, t.id ASC
    ");
    $terms = $termsStmt->fetchAll();

} catch (Exception $e) {
    error_log("Session/Term Fetch Error: " . $e->getMessage());
    $sessions = [];
    $terms = [];
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
                <span>Academic Calendar</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight">Academic Sessions & Terms</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-0.5">Manage academic years, set active terms, and enable promotion flags.</p>
        </div>
        <div class="flex items-center gap-2.5 sm:gap-3 w-full md:w-auto">
            <button onclick="openAddSessionModal()" class="flex-1 md:flex-none px-3.5 py-2.5 bg-inputBg hover:bg-emeraldGlow/10 text-cyanGlow border border-cyanGlow/30 rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-1.5 cursor-pointer shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Session</span>
            </button>
            <button onclick="openAddTermModal()" class="flex-1 md:flex-none px-3.5 py-2.5 bg-emeraldGlow/10 hover:bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/30 rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-1.5 cursor-pointer shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Term</span>
            </button>
        </div>
    </div>

    <!-- Sessions Section -->
    <div class="glass-card p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-emeraldGlow/10 pb-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-100">Academic Sessions</h2>
                <p class="text-[11px] sm:text-xs text-slate-400">Select the current active academic year for school operations.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($sessions as $session): ?>
                <div class="p-4 bg-inputBg border <?php echo $session['is_active'] ? 'border-cyanGlow/50 shadow-md shadow-cyanGlow/5' : 'border-emeraldGlow/10'; ?> rounded-xl flex items-center justify-between gap-2">
                    <div>
                        <div class="text-base font-bold text-slate-100 font-mono"><?php echo htmlspecialchars($session['name']); ?></div>
                        <div class="text-xs mt-1">
                            <?php if ($session['is_active']): ?>
                                <span class="text-emeraldGlow font-semibold inline-flex items-center">
                                    <span class="w-2 h-2 rounded-full bg-emeraldGlow mr-1.5 animate-pulse"></span> Active Session
                                </span>
                            <?php else: ?>
                                <span class="text-slate-500 font-medium">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Edit Button -->
                        <button onclick='openEditSessionModal(<?php echo json_encode($session, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' 
                                class="p-1.5 bg-cardBg hover:bg-cyanGlow/10 text-cyanGlow border border-cyanGlow/20 rounded-lg transition-all cursor-pointer" 
                                title="Edit Session">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>

                        <?php if (!$session['is_active']): ?>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="activate_session">
                                <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                <button type="submit" class="px-3 py-1.5 bg-cardBg hover:bg-emeraldGlow/10 text-xs font-semibold text-slate-300 border border-emeraldGlow/20 rounded-lg transition-all cursor-pointer">
                                    Set Active
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="px-2.5 py-1 bg-cyanGlow/10 text-cyanGlow border border-cyanGlow/30 rounded-lg text-xs font-bold">
                                Current
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Terms Management Section (Responsive DIV Grid) -->
    <div class="glass-card p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-emeraldGlow/10 pb-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-100">Academic Terms</h2>
                <p class="text-[11px] sm:text-xs text-slate-400">Configure current terms and flag promotional terms for student progression.</p>
            </div>
        </div>

        <?php if (!empty($terms)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($terms as $term): ?>
                    <div class="bg-inputBg border <?php echo $term['is_current'] ? 'border-emeraldGlow/50 shadow-md shadow-emeraldGlow/5' : 'border-emeraldGlow/10'; ?> rounded-xl p-4 space-y-3.5 flex flex-col justify-between">
                        
                        <!-- Card Top: Session & Term Info -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-[10px] uppercase tracking-wider font-mono font-semibold text-slate-400">Session</span>
                                <h3 class="text-sm font-mono font-bold text-slate-100"><?php echo htmlspecialchars($term['session_name']); ?></h3>
                                <div class="text-base font-bold text-cyanGlow mt-0.5"><?php echo htmlspecialchars($term['term_name']); ?></div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <!-- Edit Button -->
                                <button onclick='openEditTermModal(<?php echo json_encode($term, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' 
                                        class="p-1.5 bg-cardBg/80 hover:bg-cyanGlow/10 text-cyanGlow border border-cyanGlow/20 rounded-lg transition-all cursor-pointer" 
                                        title="Edit Term">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <span class="text-[10px] text-slate-500 font-mono bg-cardBg/60 px-2 py-1 rounded border border-emeraldGlow/10">
                                    #<?php echo $term['id']; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Term Dates -->
                        <div class="bg-cardBg/50 p-2.5 rounded-lg border border-emeraldGlow/5 space-y-1">
                            <div class="text-[10px] uppercase font-semibold text-slate-400">Duration</div>
                            <div class="text-xs text-slate-300 font-medium">
                                <?php if ($term['start_date'] && $term['end_date']): ?>
                                    <?php echo date('M d, Y', strtotime($term['start_date'])); ?> &mdash; <?php echo date('M d, Y', strtotime($term['end_date'])); ?>
                                <?php else: ?>
                                    <span class="text-slate-500 italic">Dates Not Specified</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Actions & Badges -->
                        <div class="pt-2 border-t border-emeraldGlow/10 flex items-center justify-between gap-2">
                            <!-- Active Status -->
                            <div>
                                <?php if ($term['is_current']): ?>
                                    <span class="px-2.5 py-1 bg-emeraldGlow/10 text-emeraldGlow border border-emeraldGlow/30 rounded-full text-[11px] font-bold inline-flex items-center">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emeraldGlow mr-1.5 animate-pulse"></span> Active Term
                                    </span>
                                <?php else: ?>
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="action" value="update_term_status">
                                        <input type="hidden" name="type" value="set_current">
                                        <input type="hidden" name="term_id" value="<?php echo $term['id']; ?>">
                                        <button type="submit" class="text-xs text-slate-400 hover:text-emeraldGlow transition-colors font-semibold cursor-pointer">
                                            Set Active
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Promotional Toggle -->
                            <div>
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="action" value="update_term_status">
                                    <input type="hidden" name="type" value="toggle_promotional">
                                    <input type="hidden" name="term_id" value="<?php echo $term['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition-all cursor-pointer <?php echo $term['is_promotional'] ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-cardBg text-slate-400 border-emeraldGlow/10 hover:border-emeraldGlow/30'; ?>">
                                        <?php echo $term['is_promotional'] ? 'Promotional' : 'Standard'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-slate-500 text-xs sm:text-sm bg-inputBg border border-emeraldGlow/10 rounded-xl">
                No academic terms found. Create one to begin.
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL: ADD / EDIT SESSION -->
<div id="sessionModal" class="fixed inset-0 z-50 hidden bg-cardBg/80 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card max-w-md w-full p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-emeraldGlow/10 pb-3">
            <h3 id="sessionModalTitle" class="text-lg font-bold text-slate-100">Create Academic Session</h3>
            <button onclick="toggleModal('sessionModal')" class="text-slate-400 hover:text-slate-100 text-xl font-bold cursor-pointer">&times;</button>
        </div>
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" id="session_action" name="action" value="create_session">
            <input type="hidden" id="session_id" name="session_id" value="">
            
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Session Name (e.g., 2026/2027)</label>
                <input type="text" id="session_name" name="session_name" required placeholder="2026/2027" class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emeraldGlow placeholder:text-slate-600">
            </div>
            <div class="flex items-center space-x-2">
                <input type="checkbox" id="is_active_session" name="is_active" value="1" class="rounded bg-inputBg border-emeraldGlow/20 text-cyanGlow focus:ring-0">
                <label for="is_active_session" class="text-xs text-slate-300 font-medium cursor-pointer">Set as current active session</label>
            </div>
            <div class="flex justify-end space-x-3 pt-3">
                <button type="button" onclick="toggleModal('sessionModal')" class="px-4 py-2 bg-inputBg text-slate-400 rounded-xl text-xs font-semibold hover:text-slate-200 cursor-pointer">Cancel</button>
                <button type="submit" id="sessionSubmitBtn" class="px-5 py-2 bg-emeraldGlow/10 hover:bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/30 rounded-xl text-xs font-bold transition-all cursor-pointer">Save Session</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD / EDIT TERM -->
<div id="termModal" class="fixed inset-0 z-50 hidden bg-cardBg/80 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card max-w-lg w-full p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-emeraldGlow/10 pb-3">
            <h3 id="termModalTitle" class="text-lg font-bold text-slate-100">Create Academic Term</h3>
            <button onclick="toggleModal('termModal')" class="text-slate-400 hover:text-slate-100 text-xl font-bold cursor-pointer">&times;</button>
        </div>
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" id="term_action" name="action" value="create_term">
            <input type="hidden" id="term_id" name="term_id" value="">
            
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Select Academic Session</label>
                <select id="term_session_id" name="session_id" required class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emeraldGlow">
                    <option value="">-- Choose Session --</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Term Name</label>
                <input type="text" id="term_name" name="term_name" required placeholder="1st Term, 2nd Term, or 3rd Term" class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emeraldGlow placeholder:text-slate-600">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="w-full bg-inputBg border border-emeraldGlow/20 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-emeraldGlow">
                </div>
            </div>
            <div class="space-y-2 pt-2">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="is_current" name="is_current" value="1" class="rounded bg-inputBg border-emeraldGlow/20 text-cyanGlow focus:ring-0">
                    <label for="is_current" class="text-xs text-slate-300 font-medium cursor-pointer">Set as current active term</label>
                </div>
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="is_promotional" name="is_promotional" value="1" class="rounded bg-inputBg border-emeraldGlow/20 text-amber-500 focus:ring-0">
                    <label for="is_promotional" class="text-xs text-slate-300 font-medium cursor-pointer">Promotional Term (Triggers end-of-year promotion)</label>
                </div>
            </div>
            <div class="flex justify-end space-x-3 pt-3">
                <button type="button" onclick="toggleModal('termModal')" class="px-4 py-2 bg-inputBg text-slate-400 rounded-xl text-xs font-semibold hover:text-slate-200 cursor-pointer">Cancel</button>
                <button type="submit" id="termSubmitBtn" class="px-5 py-2 bg-emeraldGlow/10 hover:bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/30 rounded-xl text-xs font-bold transition-all cursor-pointer">Save Term</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.toggle('hidden');
    }
}

// Session Handlers
function openAddSessionModal() {
    document.getElementById('sessionModalTitle').innerText = 'Create Academic Session';
    document.getElementById('session_action').value = 'create_session';
    document.getElementById('session_id').value = '';
    document.getElementById('session_name').value = '';
    document.getElementById('is_active_session').checked = false;
    document.getElementById('sessionSubmitBtn').innerText = 'Save Session';
    toggleModal('sessionModal');
}

function openEditSessionModal(session) {
    document.getElementById('sessionModalTitle').innerText = 'Edit Academic Session';
    document.getElementById('session_action').value = 'edit_session';
    document.getElementById('session_id').value = session.id;
    document.getElementById('session_name').value = session.name;
    document.getElementById('is_active_session').checked = parseInt(session.is_active) === 1;
    document.getElementById('sessionSubmitBtn').innerText = 'Update Session';
    toggleModal('sessionModal');
}

// Term Handlers
function openAddTermModal() {
    document.getElementById('termModalTitle').innerText = 'Create Academic Term';
    document.getElementById('term_action').value = 'create_term';
    document.getElementById('term_id').value = '';
    document.getElementById('term_session_id').value = '';
    document.getElementById('term_name').value = '';
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    document.getElementById('is_current').checked = false;
    document.getElementById('is_promotional').checked = false;
    document.getElementById('termSubmitBtn').innerText = 'Save Term';
    toggleModal('termModal');
}

function openEditTermModal(term) {
    document.getElementById('termModalTitle').innerText = 'Edit Academic Term';
    document.getElementById('term_action').value = 'edit_term';
    document.getElementById('term_id').value = term.id;
    document.getElementById('term_session_id').value = term.session_id;
    document.getElementById('term_name').value = term.term_name;
    document.getElementById('start_date').value = term.start_date || '';
    document.getElementById('end_date').value = term.end_date || '';
    document.getElementById('is_current').checked = parseInt(term.is_current) === 1;
    document.getElementById('is_promotional').checked = parseInt(term.is_promotional) === 1;
    document.getElementById('termSubmitBtn').innerText = 'Update Term';
    toggleModal('termModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>