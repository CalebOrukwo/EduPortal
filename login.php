<?php
/**
 * Centralized Authentication Form
 * File: login.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

// Redirect if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/index.php');
            exit();
        case 'staff':
            header('Location: staff/index.php');
            exit();
        case 'student':
            header('Location: student/index.php');
            exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = sanitizeInput($_POST['login_input'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($loginInput) || empty($password)) {
        $error = 'Please fill in all credentials.';
    } else {
        // Authenticate via email OR via student code
        $sql = "SELECT u.id, u.full_name, u.email, u.password, u.role, u.status 
                FROM sch_users u 
                LEFT JOIN sch_students s ON u.id = s.id 
                WHERE u.email = :input1 OR s.student_code = :input2 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':input1' => $loginInput,
            ':input2' => $loginInput
        ]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Contact administrator.';
            } else {
                // Populate session data
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['email']     = $user['email'];

                setFlashMessage('success', 'Welcome back, ' . $user['full_name'] . '!');

                // Dynamic routing based on role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin/index.php');
                        exit();
                    case 'staff':
                        header('Location: staff/index.php');
                        exit();
                    case 'student':
                        header('Location: student/index.php');
                        exit();
                    default:
                        header('Location: index.php');
                        exit();
                }
            }
        } else {
            $error = 'Invalid email/student code or password.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/alerts.php';
?>

<div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full glow-card p-8 rounded-2xl border border-gray-800">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-extrabold text-white">Portal Sign In</h2>
            <p class="text-sm text-gray-400 mt-2">Enter your credentials to access your account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-950/80 border border-rose-500/50 text-rose-200 text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-6">
            <div>
                <label for="login_input" class="block text-sm font-medium text-gray-300 mb-2">
                    Email Address or Student Code
                </label>
                <input type="text" id="login_input" name="login_input" required 
                       placeholder="e.g. user@school.com or ST1234"
                       class="w-full px-4 py-3 bg-navy-950 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500 transition-colors">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                    Password
                </label>
                <input type="password" id="password" name="password" required 
                       placeholder="••••••••"
                       class="w-full px-4 py-3 bg-navy-950 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500 transition-colors">
            </div>

            <button type="submit" class="w-full py-3.5 px-4 glow-button text-white font-bold rounded-xl transition-all">
                Sign In
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-400">
            Don't have an account? 
            <a href="signup.php" class="text-cyan-400 hover:text-cyan-300 font-semibold">Register here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>