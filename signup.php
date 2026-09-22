<?php
/**
 * User Registration Page
 * File: signup.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName        = sanitizeInput($_POST['full_name'] ?? '');
    $email           = sanitizeInput($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Verify email uniqueness
        $checkStmt = $pdo->prepare("SELECT id FROM sch_users WHERE email = :email LIMIT 1");
        $checkStmt->execute([':email' => $email]);

        if ($checkStmt->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            try {
                // Ensure PDO throws exceptions for easy debugging
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->beginTransaction();

                // Hash password securely
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                // Default role assigned to public registration is 'student'
                $role = 'student';

                $insertUserSql = "INSERT INTO sch_users (full_name, email, password, role, status) 
                                 VALUES (:full_name, :email, :password, :role, 'active')";
                $userStmt = $pdo->prepare($insertUserSql);
                $userStmt->execute([
                    ':full_name' => $fullName,
                    ':email'     => $email,
                    ':password'  => $hashedPassword,
                    ':role'      => $role
                ]);

                $newUserId = $pdo->lastInsertId();

                // Split full name for student table requirement
                $nameParts = explode(' ', $fullName, 2);
                $firstName = $nameParts[0];
                $lastName  = $nameParts[1] ?? 'Student';
                $studentCode = generateStudentCode();

                // Safely retrieve an active default class ID from DB instead of hardcoding 1
                $classStmt = $pdo->query("SELECT id FROM sch_classes LIMIT 1");
                $defaultClass = $classStmt->fetch();
                $defaultClassId = $defaultClass ? $defaultClass['id'] : NULL;

                $insertStudentSql = "INSERT INTO sch_students (id, student_code, first_name, last_name, current_class_id, status) 
                                     VALUES (:id, :code, :first_name, :last_name, :class_id, 'active')";
                $studentStmt = $pdo->prepare($insertStudentSql);
                $studentStmt->execute([
                    ':id'         => $newUserId,
                    ':code'       => $studentCode,
                    ':first_name' => $firstName,
                    ':last_name'  => $lastName,
                    ':class_id'   => $defaultClassId
                ]);

                $pdo->commit();

                setFlashMessage('success', "Account created successfully! Your Student Code is: {$studentCode}. Please log in.");
                header('Location: ' . BASE_URL . 'login.php');
                exit();

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Signup SQL Error: " . $e->getMessage());
                // Displays explicit SQL error message directly for debugging
                $error = 'Database Error: ' . $e->getMessage();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Signup General Error: " . $e->getMessage());
                $error = 'System Error: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/alerts.php';
?>

<div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full glow-card p-8 rounded-2xl border border-gray-800">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-extrabold text-white">Create Account</h2>
            <p class="text-sm text-gray-400 mt-2">Register for student portal access</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-950/80 border border-rose-500/50 text-rose-200 text-sm break-words">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE_URL; ?>signup.php" class="space-y-5">
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-300 mb-1">Full Name</label>
                <input type="text" id="full_name" name="full_name" required placeholder="John Doe"
                       class="w-full px-4 py-2.5 bg-navy-950 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500 transition-colors">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="student@example.com"
                       class="w-full px-4 py-2.5 bg-navy-950 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500 transition-colors">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••"
                       class="w-full px-4 py-2.5 bg-navy-950 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500 transition-colors">
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-1">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••"
                       class="w-full px-4 py-2.5 bg-navy-950 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500 transition-colors">
            </div>

            <button type="submit" class="w-full py-3 px-4 glow-button text-white font-bold rounded-xl transition-all mt-2">
                Register Account
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-400">
            Already registered? 
            <a href="<?php echo BASE_URL; ?>login.php" class="text-cyan-400 hover:text-cyan-300 font-semibold">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>