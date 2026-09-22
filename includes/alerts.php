<?php
/**
 * Global UI Feedback Alert Helper
 * File: includes/alerts.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
    $type = $_SESSION['flash_message']['type'] ?? 'info';
    $message = $_SESSION['flash_message']['message'] ?? '';

    // Color configurations mapping to Tailwind alert states
    $alertStyles = [
        'success' => 'bg-emerald-950/80 border-emerald-500/50 text-emerald-200',
        'danger'  => 'bg-rose-950/80 border-rose-500/50 text-rose-200',
        'warning' => 'bg-amber-950/80 border-amber-500/50 text-amber-200',
        'info'    => 'bg-cyan-950/80 border-cyan-500/50 text-cyan-200'
    ];

    $badgeStyles = [
        'success' => 'bg-emerald-500 text-slate-950',
        'danger'  => 'bg-rose-500 text-slate-950',
        'warning' => 'bg-amber-500 text-slate-950',
        'info'    => 'bg-cyan-400 text-slate-950'
    ];

    $styleClass = $alertStyles[$type] ?? $alertStyles['info'];
    $badgeClass = $badgeStyles[$type] ?? $badgeStyles['info'];

    if (!empty($message)) {
        ?>
        <div class="alert-banner max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="flex items-center justify-between p-4 rounded-xl border backdrop-blur-md shadow-lg transition-all duration-300 <?php echo $styleClass; ?>" role="alert">
                <div class="flex items-center space-x-3">
                    <span class="text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-md <?php echo $badgeClass; ?>">
                        <?php echo htmlspecialchars($type); ?>
                    </span>
                    <p class="text-sm font-medium">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                </div>
                <button type="button" class="alert-dismiss-btn p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 transition-colors focus:outline-none">
                    <span class="sr-only">Dismiss banner</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        <?php
    }

    // Clear session flash message after rendering
    unset($_SESSION['flash_message']);
}
?>