<?php
/**
 * Student Management & Account Control
 * File: admin/students.php
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

// Generate CSRF Token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -------------------------------------------------------------------------
// POST HANDLERS: STUDENT MANAGEMENT & STATUS TOGGLES
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF Token
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        setFlashMessage('error', 'Invalid security token provided. Please try again.');
        header('Location: ' . BASE_URL . 'admin/students.php');
        exit();
    }

    $action = $_POST['action'] ?? '';

    try {
        // 1. CREATE NEW STUDENT PROFILE & USER ACCOUNT
        if ($action === 'create_student') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $gender = $_POST['gender'] ?? 'Other';
            $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
            $classId = (int) ($_POST['current_class_id'] ?? 0);
            $guardianName = trim($_POST['guardian_name'] ?? '');
            $guardianPhone = trim($_POST['guardian_phone'] ?? '');

            if (empty($firstName) || empty($lastName) || $classId <= 0) {
                throw new Exception("First name, last name, and class assignment are required.");
            }

            $studentCode = generateStudentCode($pdo);
            $fullName = $firstName . ' ' . $lastName;
            $studentEmail = strtolower($studentCode) . '@school.internal';
            $defaultPasswordHash = password_hash('school000', PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            // Insert into sch_users
            $userStmt = $pdo->prepare("
                INSERT INTO sch_users (full_name, email, password, role, status)
                VALUES (:full_name, :email, :password, 'student', 'active')
            ");
            $userStmt->execute([
                ':full_name' => $fullName,
                ':email' => $studentEmail,
                ':password' => $defaultPasswordHash
            ]);

            // Insert into sch_students
            $studentStmt = $pdo->prepare("
                INSERT INTO sch_students (student_code, first_name, last_name, gender, dob, current_class_id, guardian_name, guardian_phone, status)
                VALUES (:student_code, :first_name, :last_name, :gender, :dob, :class_id, :guardian_name, :guardian_phone, 'active')
            ");

            $studentStmt->execute([
                ':student_code' => $studentCode,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':gender' => $gender,
                ':dob' => $dob,
                ':class_id' => $classId,
                ':guardian_name' => !empty($guardianName) ? $guardianName : null,
                ':guardian_phone' => !empty($guardianPhone) ? $guardianPhone : null
            ]);

            $pdo->commit();

            setFlashMessage('success', "Student registered successfully. Code: {$studentCode} | Login Email: {$studentEmail} | Default Password: 'school000'.");
            header('Location: ' . BASE_URL . 'admin/students.php');
            exit();
        }

        // 2. UPDATE STUDENT PROFILE & USER ACCOUNT
        if ($action === 'update_student') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $gender = $_POST['gender'] ?? 'Other';
            $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
            $classId = (int) ($_POST['current_class_id'] ?? 0);
            $guardianName = trim($_POST['guardian_name'] ?? '');
            $guardianPhone = trim($_POST['guardian_phone'] ?? '');

            if ($studentId <= 0 || empty($firstName) || empty($lastName) || $classId <= 0) {
                throw new Exception("Invalid parameters provided for student update.");
            }

            $pdo->beginTransaction();

            // Fetch existing student code to map user email
            $codeStmt = $pdo->prepare("SELECT student_code FROM sch_students WHERE id = :id");
            $codeStmt->execute([':id' => $studentId]);
            $existingStudent = $codeStmt->fetch();

            if (!$existingStudent) {
                throw new Exception("Student profile not found.");
            }

            $stmt = $pdo->prepare("
                UPDATE sch_students SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    gender = :gender,
                    dob = :dob,
                    current_class_id = :class_id,
                    guardian_name = :guardian_name,
                    guardian_phone = :guardian_phone
                WHERE id = :id
            ");

            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':gender' => $gender,
                ':dob' => $dob,
                ':class_id' => $classId,
                ':guardian_name' => !empty($guardianName) ? $guardianName : null,
                ':guardian_phone' => !empty($guardianPhone) ? $guardianPhone : null,
                ':id' => $studentId
            ]);

            // Sync user full name
            $studentEmail = strtolower($existingStudent['student_code']) . '@school.internal';
            $userStmt = $pdo->prepare("UPDATE sch_users SET full_name = :full_name WHERE email = :email");
            $userStmt->execute([
                ':full_name' => $firstName . ' ' . $lastName,
                ':email' => $studentEmail
            ]);

            $pdo->commit();

            setFlashMessage('success', "Student profile updated successfully.");
            header('Location: ' . BASE_URL . 'admin/students.php');
            exit();
        }

        // 3. BAN / RESTORE STUDENT & USER ACCOUNT
        if ($action === 'update_status') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $status = $_POST['status'] ?? 'active';
            $banReason = trim($_POST['ban_reason'] ?? '');

            if ($studentId <= 0 || !in_array($status, ['active', 'banned'])) {
                throw new Exception("Invalid status selection.");
            }

            if ($status === 'banned' && empty($banReason)) {
                throw new Exception("A reason must be provided when banning a student.");
            }

            $pdo->beginTransaction();

            $codeStmt = $pdo->prepare("SELECT student_code FROM sch_students WHERE id = :id");
            $codeStmt->execute([':id' => $studentId]);
            $existingStudent = $codeStmt->fetch();

            if (!$existingStudent) {
                throw new Exception("Student record not found.");
            }

            $stmt = $pdo->prepare("
                UPDATE sch_students 
                SET status = :status, ban_reason = :ban_reason 
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':ban_reason' => ($status === 'active') ? null : $banReason,
                ':id' => $studentId
            ]);

            // Sync user account login permission status
            $studentEmail = strtolower($existingStudent['student_code']) . '@school.internal';
            $userStatus = ($status === 'active') ? 'active' : 'banned';
            $userStmt = $pdo->prepare("UPDATE sch_users SET status = :status WHERE email = :email");
            $userStmt->execute([
                ':status' => $userStatus,
                ':email' => $studentEmail
            ]);

            $pdo->commit();

            setFlashMessage('success', "Student status updated to " . strtoupper($status) . ".");
            header('Location: ' . BASE_URL . 'admin/students.php');
            exit();
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Student Admin Error: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        header('Location: ' . BASE_URL . 'admin/students.php');
        exit();
    }
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL & FILTERS WITH PAGINATION
// -------------------------------------------------------------------------
$selectedClassId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
$searchQuery = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    $classesStmt = $pdo->query("SELECT id, name FROM sch_classes ORDER BY id ASC");
    $classes = $classesStmt->fetchAll();

    $whereClauses = ["1=1"];
    $params = [];

    if ($selectedClassId > 0) {
        $whereClauses[] = "st.current_class_id = :class_id";
        $params[':class_id'] = $selectedClassId;
    }

    if (!empty($searchQuery)) {
        $whereClauses[] = "(st.student_code LIKE :search OR st.first_name LIKE :search OR st.last_name LIKE :search OR st.guardian_name LIKE :search OR st.guardian_phone LIKE :search)";
        $params[':search'] = '%' . $searchQuery . '%';
    }

    $whereSql = implode(" AND ", $whereClauses);

    // Get Total Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sch_students st WHERE $whereSql");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // Fetch Records
    $query = "
        SELECT 
            st.*,
            c.name AS class_name
        FROM sch_students st
        LEFT JOIN sch_classes c ON st.current_class_id = c.id
        WHERE $whereSql
        ORDER BY st.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $studentsStmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $studentsStmt->bindValue($key, $val);
    }
    $studentsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $studentsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $studentsStmt->execute();
    $students = $studentsStmt->fetchAll();

} catch (Exception $e) {
    error_log("Student Fetch Error: " . $e->getMessage());
    $classes = [];
    $students = [];
    $totalRecords = 0;
    $totalPages = 1;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header Section -->
    <div class="glass-card p-6 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--text-accent);">
                <span>Admin Panel</span>
                <span>&bull;</span>
                <span>Student Management</span>
            </div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Student Directory & Control</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">Manage student profiles, class placements, and system access.</p>
        </div>
        <button onclick="openCreateStudentModal()" class="glow-button px-4 py-2.5 text-white rounded-xl text-xs font-bold transition-all flex items-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Register New Student</span>
        </button>
    </div>

    <!-- Filter Toolbar -->
    <div class="glass-card p-5 rounded-2xl">
        <form method="GET" action="" class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex flex-col md:flex-row items-center gap-3 w-full md:w-auto">
                <select name="class_id" onchange="this.form.submit()" class="rounded-xl px-4 py-2 text-xs font-medium focus:outline-none focus:border-cyan-500 w-full md:w-56" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClassId === $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="relative w-full md:w-72">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search student code, name, guardian..." class="w-full rounded-xl pl-9 pr-4 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <svg class="w-4 h-4 absolute left-3 top-2.5" style="color: var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <div class="flex items-center space-x-2 text-xs w-full md:w-auto justify-end" style="color: var(--text-secondary);">
                <span>Showing <strong style="color: var(--text-primary);"><?php echo count($students); ?></strong> of <strong style="color: var(--text-primary);"><?php echo $totalRecords; ?></strong> records</span>
            </div>
        </form>
    </div>

    <!-- Student Responsive Grid Container -->
    <div class="space-y-4">
        <?php if (!empty($students)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($students as $stu): ?>
                    <div class="glass-card p-5 rounded-2xl flex flex-col justify-between space-y-4 transition-all hover:scale-[1.01]">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between border-b pb-3" style="border-color: var(--border-subtle);">
                                <div>
                                    <span class="font-mono text-xs font-semibold" style="color: var(--text-accent);">
                                        <?php echo htmlspecialchars($stu['student_code']); ?>
                                    </span>
                                    <h3 class="text-base font-bold mt-0.5" style="color: var(--text-primary);">
                                        <?php echo htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']); ?>
                                    </h3>
                                </div>
                                <div>
                                    <?php if ($stu['status'] === 'active'): ?>
                                        <span class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 rounded-full text-xs font-semibold">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 bg-rose-500/10 text-rose-400 border border-rose-500/30 rounded-full text-xs font-semibold" title="<?php echo htmlspecialchars($stu['ban_reason'] ?? ''); ?>">
                                            Banned
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="block font-semibold" style="color: var(--text-muted);">Class</span>
                                    <span class="font-medium" style="color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($stu['class_name'] ?: 'Unassigned'); ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="block font-semibold" style="color: var(--text-muted);">Gender & DOB</span>
                                    <span class="font-medium" style="color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($stu['gender'] ?? 'N/A'); ?> &bull; <?php echo $stu['dob'] ? htmlspecialchars(date('M d, Y', strtotime($stu['dob']))) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="pt-2 border-t text-xs" style="border-color: var(--border-subtle);">
                                <span class="block font-semibold" style="color: var(--text-muted);">Guardian Details</span>
                                <div class="font-medium mt-0.5" style="color: var(--text-primary);"><?php echo htmlspecialchars($stu['guardian_name'] ?: 'N/A'); ?></div>
                                <div class="font-mono text-[11px]" style="color: var(--text-secondary);"><?php echo htmlspecialchars($stu['guardian_phone'] ?: 'N/A'); ?></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end space-x-2 pt-3 border-t" style="border-color: var(--border-subtle);">
                            <button 
                                type="button" 
                                onclick='openEditStudentModal(<?php echo htmlspecialchars(json_encode($stu), ENT_QUOTES, "UTF-8"); ?>)'
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                                style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"
                            >
                                Edit
                            </button>
                            <button 
                                type="button" 
                                onclick='openStatusModal(<?php echo htmlspecialchars(json_encode($stu), ENT_QUOTES, "UTF-8"); ?>)'
                                class="px-3 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-lg text-xs font-semibold transition-all"
                            >
                                Status
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="glass-card p-12 text-center rounded-2xl" style="color: var(--text-muted);">
                No student records found. Click 'Register New Student' to create one.
            </div>
        <?php endif; ?>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="glass-card p-4 rounded-2xl flex items-center justify-between text-xs">
                <div style="color: var(--text-secondary);">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?class_id=<?php echo $selectedClassId; ?>&search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page - 1; ?>" class="px-3 py-1.5 rounded-lg transition-all" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?class_id=<?php echo $selectedClassId; ?>&search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page + 1; ?>" class="px-3 py-1.5 rounded-lg transition-all" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- STUDENT CREATE / EDIT MODAL -->
<div id="studentModal" class="fixed inset-0 z-50 hidden bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-card max-w-lg w-full p-6 space-y-5 rounded-2xl" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div class="flex items-center justify-between border-b pb-3" style="border-color: var(--border-subtle);">
            <h3 id="modalTitle" class="text-lg font-bold" style="color: var(--text-primary);">Register Student Profile</h3>
            <button onclick="closeModal('studentModal')" style="color: var(--text-muted);">&times;</button>
        </div>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" id="formAction" value="create_student">
            <input type="hidden" name="student_id" id="studentId" value="">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">First Name</label>
                    <input type="text" name="first_name" id="firstName" required placeholder="John" class="w-full rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Last Name</label>
                    <input type="text" name="last_name" id="lastName" required placeholder="Doe" class="w-full rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Gender</label>
                    <select name="gender" id="gender" class="w-full rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Date of Birth</label>
                    <input type="date" name="dob" id="dob" class="w-full rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Current Class Assignment</label>
                <select name="current_class_id" id="currentClassId" required class="w-full rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Guardian Name</label>
                    <input type="text" name="guardian_name" id="guardianName" placeholder="Parent/Guardian Full Name" class="w-full rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Guardian Phone</label>
                    <input type="text" name="guardian_phone" id="guardianPhone" placeholder="+234..." class="w-full rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-3 border-t" style="border-color: var(--border-subtle);">
                <button type="button" onclick="closeModal('studentModal')" class="px-4 py-2 rounded-xl text-xs font-semibold" style="background-color: var(--bg-input); color: var(--text-muted);">Cancel</button>
                <button type="submit" class="glow-button px-5 py-2 text-white text-xs font-bold rounded-xl">Save Student</button>
            </div>
        </form>
    </div>
</div>

<!-- STATUS / BAN MODAL -->
<div id="statusModal" class="fixed inset-0 z-50 hidden bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-card max-w-md w-full p-6 space-y-5 rounded-2xl" style="background-color: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div class="flex items-center justify-between border-b pb-3" style="border-color: var(--border-subtle);">
            <h3 class="text-lg font-bold" style="color: var(--text-primary);">Account Status Control</h3>
            <button onclick="closeModal('statusModal')" style="color: var(--text-muted);">&times;</button>
        </div>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="student_id" id="statusStudentId" value="">

            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Student</label>
                <div id="statusStudentName" class="text-sm font-bold px-4 py-2 rounded-xl" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"></div>
            </div>

            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Select Status</label>
                <select name="status" id="statusSelect" onchange="toggleBanReasonField()" required class="w-full rounded-xl px-4 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <option value="active">Active</option>
                    <option value="banned">Banned</option>
                </select>
            </div>

            <div id="banReasonContainer" class="hidden">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Reason for Ban</label>
                <textarea name="ban_reason" id="banReason" rows="3" placeholder="Specify reason..." class="w-full rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-cyan-500" style="background-color: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"></textarea>
            </div>

            <div class="flex justify-end space-x-3 pt-3 border-t" style="border-color: var(--border-subtle);">
                <button type="button" onclick="closeModal('statusModal')" class="px-4 py-2 rounded-xl text-xs font-semibold" style="background-color: var(--bg-input); color: var(--text-muted);">Cancel</button>
                <button type="submit" class="glow-button px-5 py-2 text-white text-xs font-bold rounded-xl">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateStudentModal() {
    document.getElementById('formAction').value = 'create_student';
    document.getElementById('modalTitle').innerText = 'Register Student Profile';
    document.getElementById('studentId').value = '';
    document.getElementById('firstName').value = '';
    document.getElementById('lastName').value = '';
    document.getElementById('gender').value = 'Other';
    document.getElementById('dob').value = '';
    document.getElementById('currentClassId').value = '';
    document.getElementById('guardianName').value = '';
    document.getElementById('guardianPhone').value = '';
    document.getElementById('studentModal').classList.remove('hidden');
}

function openEditStudentModal(student) {
    document.getElementById('formAction').value = 'update_student';
    document.getElementById('modalTitle').innerText = 'Edit Student Profile';
    document.getElementById('studentId').value = student.id;
    document.getElementById('firstName').value = student.first_name;
    document.getElementById('lastName').value = student.last_name;
    document.getElementById('gender').value = student.gender || 'Other';
    document.getElementById('dob').value = student.dob || '';
    document.getElementById('currentClassId').value = student.current_class_id || '';
    document.getElementById('guardianName').value = student.guardian_name || '';
    document.getElementById('guardianPhone').value = student.guardian_phone || '';
    document.getElementById('studentModal').classList.remove('hidden');
}

function openStatusModal(student) {
    document.getElementById('statusStudentId').value = student.id;
    document.getElementById('statusStudentName').innerText = student.first_name + ' ' + student.last_name + ' (' + student.student_code + ')';
    document.getElementById('statusSelect').value = student.status || 'active';
    document.getElementById('banReason').value = student.ban_reason || '';
    toggleBanReasonField();
    document.getElementById('statusModal').classList.remove('hidden');
}

function toggleBanReasonField() {
    const status = document.getElementById('statusSelect').value;
    const container = document.getElementById('banReasonContainer');
    if (status === 'banned') {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>