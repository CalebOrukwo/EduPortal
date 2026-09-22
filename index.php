<?php
/**
 * Portal Entrance & Landing Page
 * File: index.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

// Redirect authenticated users directly to their respective portal dashboards
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

// Fetch currently active term and session information
$currentTermInfo = getCurrentTerm($pdo);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/alerts.php';
?>

<div class="relative overflow-hidden py-8 sm:py-16">
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-80 h-80 sm:w-[30rem] sm:h-[30rem] bg-emeraldGlow/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center max-w-3xl mx-auto">
            <span class="px-3.5 py-1.5 rounded-full text-[11px] sm:text-xs font-semibold bg-emeraldGlow/10 text-emeraldGlow border border-emeraldGlow/30 uppercase tracking-widest inline-block mb-4">
                School Management System
            </span>
            <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-slate-100 mb-4 sm:mb-6 leading-tight">
                Streamlined Academic Operations for Modern Excellence
            </h1>
            <p class="text-sm sm:text-lg text-slate-400 mb-8 leading-relaxed max-w-2xl mx-auto">
                Welcome to our school portal. Seamlessly manage academic records, continuous assessment scores, fee billing, and school operations in one secure platform.
            </p>

            <?php if ($currentTermInfo): ?>
                <div class="inline-flex items-center gap-3 px-4 py-2.5 sm:px-5 sm:py-3 rounded-2xl glass-card mb-8 sm:mb-10 text-left">
                    <div class="w-3 h-3 rounded-full bg-emeraldGlow animate-pulse"></div>
                    <div>
                        <div class="text-[10px] sm:text-xs text-slate-400 uppercase font-bold tracking-wider">Active Academic Period</div>
                        <div class="text-xs sm:text-sm font-bold text-mintGlow">
                            <?php echo htmlspecialchars($currentTermInfo['session_name']); ?> &bull; <?php echo htmlspecialchars($currentTermInfo['term_name']); ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="inline-flex items-center gap-3 px-4 py-2.5 sm:px-5 sm:py-3 rounded-2xl glass-card mb-8 sm:mb-10 text-left">
                    <div class="w-3 h-3 rounded-full bg-amberGlow"></div>
                    <div>
                        <div class="text-[10px] sm:text-xs text-slate-400 uppercase font-bold tracking-wider">Academic Calendar</div>
                        <div class="text-xs sm:text-sm font-bold text-amberGlow">No active term currently set</div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5 sm:gap-4 max-w-md mx-auto sm:max-w-none">
                <a href="login.php" class="w-full sm:w-auto px-8 py-3.5 rounded-xl glow-button text-white font-bold text-center transition-all text-sm sm:text-base">
                    Login
                </a>
                <a href="signup.php" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-cardBg hover:bg-cardHover text-slate-200 border border-emeraldGlow/20 hover:border-emeraldGlow/40 font-semibold text-center transition-all text-sm sm:text-base">
                    Register Account
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 sm:gap-6 mt-12 sm:mt-16">
            <div class="p-6 rounded-2xl glass-card glass-card-hover transition-all">
                <div class="w-12 h-12 rounded-xl bg-emeraldGlow/10 border border-emeraldGlow/30 text-emeraldGlow flex items-center justify-center font-black text-xl mb-4 shadow-inner">
                    <svg class="w-6 h-6 text-emeraldGlow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <h3 class="text-lg sm:text-xl font-bold text-slate-100 mb-2">Administrators</h3>
                <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                    Manage sessions, setup class schedules, verify payment tellers, approve promotions, and oversee staff access.
                </p>
            </div>

            <div class="p-6 rounded-2xl glass-card glass-card-hover transition-all">
                <div class="w-12 h-12 rounded-xl bg-cyanGlow/10 border border-cyanGlow/30 text-cyanGlow flex items-center justify-center font-black text-xl mb-4 shadow-inner">
                    <svg class="w-6 h-6 text-cyanGlow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                </div>
                <h3 class="text-lg sm:text-xl font-bold text-slate-100 mb-2">Teachers & Staff</h3>
                <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                    Record 3-part CA scores, enter exam marks, mark daily class registers, and submit schemes of work.
                </p>
            </div>

            <div class="p-6 rounded-2xl glass-card glass-card-hover transition-all">
                <div class="w-12 h-12 rounded-xl bg-purpleGlow/10 border border-purpleGlow/30 text-purpleGlow flex items-center justify-center font-black text-xl mb-4 shadow-inner">
                    <svg class="w-6 h-6 text-purpleGlow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg sm:text-xl font-bold text-slate-100 mb-2">Students & Parents</h3>
                <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                    Access termly result cards, review fee bills, upload bank transfer receipts, and track syllabus coverage.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>