<?php
/**
 * Shared Sidebar Component
 * File: includes/sidebar.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userRole = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Sidebar Toggle Overlay -->
<div id="sidebar-backdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden"></div>

<!-- Sidebar Container -->
<aside id="main-sidebar" class="fixed lg:static inset-y-0 left-0 w-64 bg-navy-900 border-r border-gray-800 transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out z-40 flex flex-col min-h-screen">
    
    <!-- Sidebar Header / Mobile Close Button -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800 lg:hidden">
        <span class="text-xs font-bold uppercase tracking-wider text-cyan-400">Navigation Menu</span>
        <button onclick="toggleSidebar()" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Dynamic Role Navigation -->
    <nav class="flex-grow px-4 py-6 space-y-1 overflow-y-auto">

        <?php if ($userRole === 'admin'): ?>
            <!-- ADMIN NAVIGATION -->
            <div class="px-3 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Admin Portal</div>
            
            <a href="/admin/index.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'index.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Dashboard
            </a>
            <a href="/admin/sessions-terms.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'sessions-terms.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Sessions & Terms
            </a>
            <a href="/admin/classes.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'classes.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Classes
            </a>
            <a href="/admin/subjects.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'subjects.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Subjects
            </a>
            <a href="/admin/staff.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'staff.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Staff Management
            </a>
            <a href="/admin/students.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'students.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Student Management
            </a>
            <a href="/admin/fees.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'fees.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Fee Structure
            </a>
            <a href="/admin/payments.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'payments.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Payment Verification
            </a>
            <a href="/admin/promotions.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'promotions.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Promotions
            </a>
            <a href="/admin/curriculum.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'curriculum.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Curriculum Approvals
            </a>

        <?php elseif ($userRole === 'staff'): ?>
            <!-- STAFF NAVIGATION -->
            <div class="px-3 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Staff Portal</div>
            
            <a href="/staff/index.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'index.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Dashboard
            </a>
            <a href="/staff/assessments.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'assessments.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Gradebook / CA Entry
            </a>
            <a href="/staff/attendance.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'attendance.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Mark Attendance
            </a>
            <a href="/staff/curriculum.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'curriculum.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Scheme of Work
            </a>
            <a href="/staff/promotions.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'promotions.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Promotion Requests
            </a>

        <?php elseif ($userRole === 'student'): ?>
            <!-- STUDENT NAVIGATION -->
            <div class="px-3 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Student Portal</div>
            
            <a href="/student/index.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'index.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Dashboard
            </a>
            <a href="/student/report-card.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'report-card.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Terminal Report Card
            </a>
            <a href="/student/payments.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'payments.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                School Fees & Uploads
            </a>
            <a href="/student/curriculum.php" class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium <?php echo ($currentPage == 'curriculum.php') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 'text-gray-300 hover:bg-navy-800 hover:text-white'; ?> transition-all">
                Class Scheme / Syllabus
            </a>
        <?php endif; ?>

    </nav>
</aside>