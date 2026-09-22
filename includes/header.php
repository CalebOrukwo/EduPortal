<?php
/**
 * Shared Header Component (Mobile-First / Responsive - Emerald Theme)
 * File: includes/header.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure functions.php is included to access BASE_URL
require_once __DIR__ . '/../config/functions.php';

$isLoggedIn   = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userFullName = $_SESSION['full_name'] ?? 'User';
$userRole     = $_SESSION['role'] ?? '';

// Determine current script name for active menu link styling
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>School Portal Management System</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        canvas: '#061110',
                        sidebarBg: '#0b1a18',
                        cardBg: '#0d221f',
                        cardHover: '#122d29',
                        inputBg: '#081715',
                        emeraldGlow: '#10b981',
                        mintGlow: '#34d399',
                        cyanGlow: '#06b6d4',
                        blueGlow: '#3b82f6',
                        purpleGlow: '#8b5cf6',
                        amberGlow: '#f59e0b',
                        roseGlow: '#f43f5e'
                    }
                }
            }
        }
    </script>
    
    <!-- Extended CSS Variables & UI Custom Styles -->
    <style>
        :root {
            /* Background System Palette */
            --bg-canvas: #061110;
            --bg-sidebar: #0b1a18;
            --bg-card: #0d221f;
            --bg-card-hover: #122d29;
            --bg-input: #081715;

            /* Accent & Glow Variables */
            --primary-emerald: #10b981;
            --primary-glow: rgba(16, 185, 129, 0.25);
            --emerald-mint: #34d399;
            --emerald-dark: #047857;

            /* Multi-Color Accent System */
            --accent-cyan: #06b6d4;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-amber: #f59e0b;
            --accent-rose: #f43f5e;

            /* Typography */
            --text-primary: #f0fdf4;
            --text-secondary: #94a3b8;
            --text-muted: #475569;

            /* Glassmorphism & Effects */
            --border-subtle: rgba(20, 184, 166, 0.12);
            --border-focus: rgba(16, 185, 129, 0.4);
            --card-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            --glow-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
        }

        body {
            background-color: var(--bg-canvas);
            color: var(--text-primary);
        }

        /* Glass Cards & UI Utilities */
        .glass-card {
            background: rgba(13, 34, 31, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-subtle);
            box-shadow: var(--card-shadow);
            border-radius: 16px;
        }

        .glass-card-hover:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-focus);
            box-shadow: var(--glow-shadow);
        }

        .glow-button {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.3);
        }

        .glow-button:hover {
            box-shadow: 0 0 20px rgba(52, 211, 153, 0.5);
        }

        /* ==========================================================================
           GLOBAL FORM CONTROLS & MODAL OVERRIDES (Theme Enforcement)
           ========================================================================== */
        
        /* Global Inputs, Selects, and Textareas */
        input:not([type="submit"]):not([type="button"]):not([type="checkbox"]):not([type="radio"]),
        select,
        textarea {
            background-color: var(--bg-input) !important;
            color: var(--text-primary) !important;
            border-color: rgba(16, 185, 129, 0.2) !important;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none !important;
            border-color: var(--primary-emerald) !important;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25) !important;
        }

        /* Placeholder Text Styles */
        ::placeholder {
            color: var(--text-muted) !important;
            opacity: 1;
        }
        :-ms-input-placeholder {
            color: var(--text-muted) !important;
        }
        ::-ms-input-placeholder {
            color: var(--text-muted) !important;
        }

        /* Native Dropdown Option List Styling */
        option {
            background-color: var(--bg-card) !important;
            color: var(--text-primary) !important;
        }

        option:disabled {
            color: var(--text-muted) !important;
            background-color: var(--bg-canvas) !important;
        }

        /* Modal Overlay Theme Rules */
        .modal-backdrop,
        [id*="modal"],
        [id*="Modal"] {
            color: var(--text-primary);
        }

        /* Hide scrollbars for navigation ribbons */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased pb-16 sm:pb-0 selection:bg-emerald-500 selection:text-white">

    <!-- Top Navigation Bar -->
    <nav class="bg-sidebarBg/95 border-b border-emeraldGlow/10 sticky top-0 z-50 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14 sm:h-16">
                
                <!-- Brand Logo & Title -->
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <a href="<?php echo BASE_URL; ?>index.php" class="flex items-center space-x-2">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl glow-button flex items-center justify-center text-white font-black text-lg sm:text-xl">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/>
                            </svg>
                        </div>
                        <span class="text-lg sm:text-xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 via-mintGlow to-cyan-400 bg-clip-text text-transparent">
                            EduPortal
                        </span>
                    </a>
                </div>

                <!-- Desktop Search Bar -->
                <?php if ($isLoggedIn): ?>
                <div class="hidden md:flex flex-1 max-w-md mx-8">
                    <div class="relative w-full">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </span>
                        <input type="text" placeholder="Search portal..." class="w-full bg-inputBg border border-emeraldGlow/15 text-slate-200 placeholder-slate-500 text-xs rounded-xl pl-9 pr-4 py-2 focus:outline-none focus:border-emeraldGlow/40 focus:ring-1 focus:ring-emeraldGlow/40 transition-all">
                    </div>
                </div>
                <?php endif; ?>

                <!-- Right Navigation Section -->
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <?php if ($isLoggedIn): ?>
                        
                        <!-- Notification Button -->
                        <div class="relative">
                            <button type="button" class="p-2 text-slate-400 hover:text-emeraldGlow transition-colors focus:outline-none rounded-xl hover:bg-cardBg">
                                <span class="sr-only">View notifications</span>
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-emeraldGlow rounded-full animate-pulse"></span>
                            </button>
                        </div>

                        <!-- User Info & Mobile Actions -->
                        <div class="flex items-center space-x-2 pl-2 border-l border-emeraldGlow/15">
                            <div class="text-right hidden sm:block">
                                <div class="text-xs sm:text-sm font-semibold text-slate-100"><?php echo htmlspecialchars($userFullName); ?></div>
                                <div class="text-[10px] text-emeraldGlow uppercase font-bold tracking-wider"><?php echo htmlspecialchars($userRole); ?></div>
                            </div>

                            <!-- Avatar Profile Circle -->
                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-cardBg border border-emeraldGlow/30 flex items-center justify-center text-emeraldGlow font-bold text-xs sm:text-sm shadow-inner">
                                <?php echo strtoupper(substr($userFullName, 0, 1)); ?>
                            </div>

                            <!-- Mobile Menu Drawer Toggle -->
                            <button type="button" onclick="toggleMobileMenu()" class="sm:hidden p-1.5 text-slate-300 hover:text-white rounded-lg focus:outline-none">
                                <svg id="menuIconOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                                <svg id="menuIconClose" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>

                            <!-- Desktop Logout Button -->
                            <a href="<?php echo BASE_URL; ?>logout.php" class="hidden sm:inline-flex items-center text-xs text-roseGlow hover:text-rose-300 px-2.5 py-1.5 rounded-lg border border-roseGlow/30 hover:border-roseGlow/60 hover:bg-roseGlow/10 transition-all font-medium">
                                Logout
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Guest Portal Links -->
                        <a href="<?php echo BASE_URL; ?>login.php" class="text-slate-300 hover:text-emeraldGlow text-xs sm:text-sm font-medium transition-colors px-2 py-1.5">
                            Sign In
                        </a>
                        <a href="<?php echo BASE_URL; ?>signup.php" class="glow-button text-white text-xs sm:text-sm font-semibold px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl transition-all">
                            Get Started
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($isLoggedIn): ?>
            <!-- Desktop / Tablet Navigation Ribbon -->
            <div class="hidden sm:block bg-canvas/80 border-t border-emeraldGlow/10">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center space-x-1 overflow-x-auto py-2 no-scrollbar">
                        <?php
                        $navLinks = [];
                        if ($userRole === 'admin') {
                            $navLinks = [
                                'index.php'          => 'Dashboard',
                                'sessions-terms.php' => 'Sessions & Terms',
                                'classes.php'        => 'Classes',
                                'subjects.php'       => 'Subjects',
                                'staff.php'          => 'Staff',
                                'students.php'       => 'Students',
                                'fees.php'           => 'Fees',
                                'payments.php'       => 'Payments',
                                'promotions.php'     => 'Promotions',
                                'curriculum.php'     => 'Curriculum',
                            ];
                        } elseif ($userRole === 'staff') {
                            $navLinks = [
                                'index.php'        => 'Dashboard',
                                'assessments.php'  => 'Assessments',
                                'attendance.php'   => 'Attendance',
                                'curriculum.php'   => 'Curriculum',
                                'promotions.php'   => 'Promotions',
                            ];
                        } elseif ($userRole === 'student') {
                            $navLinks = [
                                'index.php'       => 'Dashboard',
                                'report-card.php' => 'Report Card',
                                'payments.php'    => 'Payments',
                                'curriculum.php'  => 'Curriculum',
                            ];
                        }

                        $roleFolder = strtolower($userRole);
                        foreach ($navLinks as $file => $label):
                            $isActive = ($currentScript === $file);
                            $activeClass = $isActive 
                                ? 'bg-emeraldGlow/15 text-emeraldGlow border border-emeraldGlow/30 font-semibold' 
                                : 'text-slate-400 hover:text-slate-200 hover:bg-cardBg/60';
                        ?>
                            <a href="<?php echo BASE_URL . $roleFolder . '/' . $file; ?>" 
                               class="px-3 py-1.5 rounded-xl text-xs transition-all whitespace-nowrap <?php echo $activeClass; ?>">
                                <?php echo $label; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Mobile Drawer Navigation Overlay -->
            <div id="mobileMenu" class="hidden sm:hidden bg-sidebarBg/98 border-b border-emeraldGlow/20 px-4 pt-3 pb-4 space-y-2">
                <div class="flex items-center justify-between pb-3 mb-2 border-b border-emeraldGlow/10">
                    <div>
                        <div class="text-xs font-bold text-white"><?php echo htmlspecialchars($userFullName); ?></div>
                        <div class="text-[10px] text-emeraldGlow font-semibold uppercase tracking-wide"><?php echo htmlspecialchars($userRole); ?></div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="text-xs text-roseGlow hover:text-rose-300 px-2.5 py-1 rounded-md border border-roseGlow/30 bg-roseGlow/10 font-medium">
                        Logout
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-1.5">
                    <?php foreach ($navLinks as $file => $label): 
                        $isActive = ($currentScript === $file);
                        $activeClass = $isActive 
                            ? 'bg-emeraldGlow/20 text-emeraldGlow border border-emeraldGlow/40 font-bold' 
                            : 'bg-cardBg text-slate-300 hover:bg-cardHover border border-emeraldGlow/10';
                    ?>
                        <a href="<?php echo BASE_URL . $roleFolder . '/' . $file; ?>" 
                           class="px-2.5 py-2 rounded-xl text-xs transition-all text-center truncate <?php echo $activeClass; ?>">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </nav>

    <!-- Fixed Mobile Bottom Navigation Bar -->
    <?php if ($isLoggedIn): ?>
    <div class="sm:hidden fixed bottom-0 left-0 right-0 z-50 bg-sidebarBg/95 backdrop-blur-md border-t border-emeraldGlow/15 px-4 py-2">
        <div class="flex items-center justify-around">
            <a href="<?php echo BASE_URL . $roleFolder . '/index.php'; ?>" class="flex flex-col items-center text-xs <?php echo ($currentScript === 'index.php') ? 'text-emeraldGlow' : 'text-slate-400'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span class="text-[10px] mt-0.5">Home</span>
            </a>

            <?php if ($userRole === 'admin'): ?>
                <a href="<?php echo BASE_URL . $roleFolder . '/students.php'; ?>" class="flex flex-col items-center text-xs <?php echo ($currentScript === 'students.php') ? 'text-emeraldGlow' : 'text-slate-400'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span class="text-[10px] mt-0.5">Students</span>
                </a>
            <?php elseif ($userRole === 'staff'): ?>
                <a href="<?php echo BASE_URL . $roleFolder . '/attendance.php'; ?>" class="flex flex-col items-center text-xs <?php echo ($currentScript === 'attendance.php') ? 'text-emeraldGlow' : 'text-slate-400'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-[10px] mt-0.5">Attendance</span>
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL . $roleFolder . '/report-card.php'; ?>" class="flex flex-col items-center text-xs <?php echo ($currentScript === 'report-card.php') ? 'text-emeraldGlow' : 'text-slate-400'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="text-[10px] mt-0.5">Reports</span>
                </a>
            <?php endif; ?>

            <a href="<?php echo BASE_URL . $roleFolder . '/curriculum.php'; ?>" class="flex flex-col items-center text-xs <?php echo ($currentScript === 'curriculum.php') ? 'text-emeraldGlow' : 'text-slate-400'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <span class="text-[10px] mt-0.5">Curriculum</span>
            </a>

            <button type="button" onclick="toggleMobileMenu()" class="flex flex-col items-center text-xs text-slate-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <span class="text-[10px] mt-0.5">Menu</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const openIcon = document.getElementById('menuIconOpen');
            const closeIcon = document.getElementById('menuIconClose');
            
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
                openIcon.classList.add('hidden');
                closeIcon.classList.remove('hidden');
            } else {
                menu.classList.add('hidden');
                openIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            }
        }
    </script>

    <main class="flex-grow px-2 sm:px-6 lg:px-8 py-4 max-w-7xl mx-auto w-full">