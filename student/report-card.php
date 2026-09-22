<?php
/**
 * Academic Terminal Result Sheet Viewer
 * File: student/report-card.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Access Control: Valid roles are 'student', 'admin', or 'staff'
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['student', 'admin', 'staff'])) {
    setFlashMessage('error', 'Access denied. Please log in.');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? 'student';

// Resolve Student ID
$studentId = 0;

if ($userRole === 'student') {
    $studentId = (int)($_SESSION['student_id'] ?? 0);
    if ($studentId === 0) {
        $stLookup = $pdo->prepare("SELECT id FROM sch_students WHERE id = :user_id LIMIT 1");
        $stLookup->execute([':user_id' => $userId]);
        $studentId = (int)$stLookup->fetchColumn();
    }
} else {
    // Admin or Staff viewing student result sheet
    $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
}

// Fetch Student Profile
$student = null;
if ($studentId > 0) {
    $stStmt = $pdo->prepare("
        SELECT 
            s.id, s.student_code, s.first_name, s.last_name, s.gender, s.dob,
            s.guardian_name, s.guardian_phone, s.current_class_id,
            c.name AS class_name
        FROM sch_students s
        INNER JOIN sch_classes c ON s.current_class_id = c.id
        WHERE s.id = :student_id LIMIT 1
    ");
    $stStmt->execute([':student_id' => $studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);
}

if (!$student) {
    setFlashMessage('error', 'Student record not found.');
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

// -------------------------------------------------------------------------
// 1. FETCH ALL AVAILABLE TERMS FOR SELECTION FILTER
// -------------------------------------------------------------------------
$allTermsStmt = $pdo->query("
    SELECT t.id, t.term_name, t.is_current, s.name AS session_name
    FROM sch_terms t
    INNER JOIN sch_sessions s ON t.session_id = s.id
    ORDER BY s.id DESC, t.id DESC
");
$allTerms = $allTermsStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedTermId = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;

// Default to current active term if not specified
if ($selectedTermId === 0 && !empty($allTerms)) {
    foreach ($allTerms as $t) {
        if ((int)$t['is_current'] === 1) {
            $selectedTermId = (int)$t['id'];
            break;
        }
    }
    if ($selectedTermId === 0) {
        $selectedTermId = (int)$allTerms[0]['id'];
    }
}

// Active Selected Term Details
$currentTermInfo = null;
foreach ($allTerms as $t) {
    if ((int)$t['id'] === $selectedTermId) {
        $currentTermInfo = $t;
        break;
    }
}

// -------------------------------------------------------------------------
// 2. FETCH ACADEMIC ASSESSMENTS
// -------------------------------------------------------------------------
$assessments = [];
$totalObtained = 0;
$totalMaxPossible = 0;
$averageScore = 0;

if ($selectedTermId > 0) {
    $assStmt = $pdo->prepare("
        SELECT 
            sub.name AS subject_name,
            sub.code AS subject_code,
            a.ca1_score,
            a.ca2_score,
            a.ca3_score,
            a.exam_score,
            a.total_score
        FROM sch_assessments a
        INNER JOIN sch_subjects sub ON a.subject_id = sub.id
        WHERE a.student_id = :student_id AND a.term_id = :term_id
        ORDER BY sub.name ASC
    ");
    $assStmt->execute([
        ':student_id' => $studentId,
        ':term_id'    => $selectedTermId
    ]);
    $assessments = $assStmt->fetchAll(PDO::FETCH_ASSOC);

    $subjectCount = count($assessments);
    if ($subjectCount > 0) {
        foreach ($assessments as $ass) {
            $totalObtained += (float)$ass['total_score'];
        }
        $totalMaxPossible = $subjectCount * 100;
        $averageScore = $totalObtained / $subjectCount;
    }
}

// -------------------------------------------------------------------------
// 3. FETCH ATTENDANCE SUMMARY FOR THE TERM
// -------------------------------------------------------------------------
$attendanceSummary = ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
if ($selectedTermId > 0) {
    $attStmt = $pdo->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM sch_attendance
        WHERE student_id = :student_id AND term_id = :term_id
        GROUP BY status
    ");
    $attStmt->execute([
        ':student_id' => $studentId,
        ':term_id'    => $selectedTermId
    ]);
    $attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($attRows as $r) {
        $st = strtolower($r['status']);
        if (isset($attendanceSummary[$st])) {
            $attendanceSummary[$st] = (int)$r['cnt'];
        }
    }
    $attendanceSummary['total'] = array_sum([
        $attendanceSummary['present'],
        $attendanceSummary['absent'],
        $attendanceSummary['late']
    ]);
}

// Grading Helper Function
function calculateGrade($score) {
    if ($score >= 70) return ['grade' => 'A', 'remark' => 'Excellent', 'color' => 'var(--text-success, #10b981)'];
    if ($score >= 60) return ['grade' => 'B', 'remark' => 'Very Good', 'color' => 'var(--text-accent, #06b6d4)'];
    if ($score >= 50) return ['grade' => 'C', 'remark' => 'Credit', 'color' => 'var(--text-warning, #f59e0b)'];
    if ($score >= 45) return ['grade' => 'D', 'remark' => 'Pass', 'color' => 'var(--text-warning-alt, #f97316)'];
    if ($score >= 40) return ['grade' => 'E', 'remark' => 'Fair Pass', 'color' => 'var(--text-warning-alt, #f97316)'];
    return ['grade' => 'F', 'remark' => 'Fail', 'color' => 'var(--text-danger, #ef4444)'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>

<!-- Print Stylesheet: Ensures ONLY the report card container prints -->
<style>
@media print {
    /* Hide every element on the page by default */
    body * {
        visibility: hidden !important;
    }
    
    /* Reveal only the report card printing area and its children */
    #report-card-print-area, #report-card-print-area * {
        visibility: visible !important;
    }

    /* Position the report card area to cover the full printable page */
    #report-card-print-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 20px !important;
        background: #ffffff !important;
        color: #000000 !important;
        box-shadow: none !important;
        border: 1px solid #d1d5db !important;
        border-radius: 0 !important;
    }

    /* Target specific dark components and force high-contrast print styles */
    .print-card-bg {
        background: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        color: #111827 !important;
    }

    .print-text-dark {
        color: #111827 !important;
    }

    .print-text-muted {
        color: #4b5563 !important;
    }

    .print-table th {
        background-color: #f3f4f6 !important;
        color: #111827 !important;
        border-bottom: 2px solid #374151 !important;
    }

    .print-table td {
        border-bottom: 1px solid #e5e7eb !important;
        color: #111827 !important;
    }

    .no-print {
        display: none !important;
    }
}
</style>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Action Bar & Term Switcher (Hidden in Print) -->
    <div class="no-print glass-card p-5 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div>
            <h1 class="text-xl font-bold" style="color: var(--text-primary);">Academic Report Card</h1>
            <p class="text-xs mt-0.5" style="color: var(--text-secondary);">Select session term to view historical or current performance evaluation.</p>
        </div>

        <div class="flex items-center space-x-3 w-full md:w-auto">
            <form method="GET" action="" class="flex items-center space-x-2">
                <?php if ($userRole !== 'student'): ?>
                    <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                <?php endif; ?>
                <select name="term_id" onchange="this.form.submit()" class="rounded-xl px-4 py-2 text-xs font-medium focus:outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <?php foreach ($allTerms as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $selectedTermId === (int)$t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['session_name'] . ' - ' . $t['term_name']); ?>
                            <?php echo (int)$t['is_current'] === 1 ? ' (Active)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <button onclick="window.print()" class="px-4 py-2 text-xs font-bold rounded-xl flex items-center space-x-2 shrink-0 cursor-pointer transition" style="background: var(--accent-gradient, #06b6d4); color: var(--text-on-accent, #ffffff);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Print Sheet</span>
            </button>
        </div>
    </div>

    <!-- Main Report Card Container (Targeted Print Region) -->
    <div id="report-card-print-area" class="glass-card print-card-bg p-8 rounded-2xl space-y-8" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">

        <!-- School & Terminal Header -->
        <div class="pb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4" style="border-bottom: 1px solid var(--border-subtle);">
            <div>
                <span class="text-xs font-mono uppercase tracking-widest font-semibold print-text-dark" style="color: var(--text-accent);">Official Terminal Evaluation</span>
                <h2 class="text-2xl font-black mt-1 print-text-dark" style="color: var(--text-primary);">ACADEMIC RESULT SHEET</h2>
                <p class="text-xs mt-1 print-text-muted" style="color: var(--text-secondary);">
                    Academic Session: <strong class="print-text-dark" style="color: var(--text-primary);"><?php echo htmlspecialchars($currentTermInfo['session_name'] ?? 'N/A'); ?></strong> | 
                    Term: <strong class="print-text-dark" style="color: var(--text-primary);"><?php echo htmlspecialchars($currentTermInfo['term_name'] ?? 'N/A'); ?></strong>
                </p>
            </div>

            <div class="text-left md:text-right text-xs print-text-muted" style="color: var(--text-secondary);">
                <p>Date Issued: <strong class="print-text-dark" style="color: var(--text-primary);"><?php echo date('F d, Y'); ?></strong></p>
                <p class="mt-0.5">Status: <span class="font-semibold" style="color: var(--text-success, #10b981);">Verified</span></p>
            </div>
        </div>

        <!-- Student Meta Information Block -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 rounded-xl text-xs print-card-bg" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
            <div>
                <span class="block print-text-muted" style="color: var(--text-secondary);">Student Name</span>
                <strong class="text-sm block mt-0.5 print-text-dark" style="color: var(--text-primary);">
                    <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                </strong>
            </div>

            <div>
                <span class="block print-text-muted" style="color: var(--text-secondary);">Student ID / Code</span>
                <strong class="font-mono text-sm block mt-0.5 print-text-dark" style="color: var(--text-accent);">
                    <?php echo htmlspecialchars($student['student_code']); ?>
                </strong>
            </div>

            <div>
                <span class="block print-text-muted" style="color: var(--text-secondary);">Class</span>
                <strong class="text-sm block mt-0.5 print-text-dark" style="color: var(--text-primary);">
                    <?php echo htmlspecialchars($student['class_name']); ?>
                </strong>
            </div>

            <div>
                <span class="block print-text-muted" style="color: var(--text-secondary);">Gender</span>
                <strong class="text-sm block mt-0.5 print-text-dark" style="color: var(--text-primary);">
                    <?php echo htmlspecialchars($student['gender']); ?>
                </strong>
            </div>
        </div>

        <!-- Assessment Results Table -->
        <div class="space-y-4">
            <h3 class="text-sm font-bold uppercase tracking-wider print-text-dark" style="color: var(--text-primary);">Subject Performance Breakdown</h3>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs print-table" style="color: var(--text-secondary);">
                    <thead class="uppercase tracking-wider" style="background: var(--bg-input); color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle);">
                        <tr>
                            <th class="py-3 px-3">Subject Name</th>
                            <th class="py-3 px-3 text-center">CA 1 (10)</th>
                            <th class="py-3 px-3 text-center">CA 2 (10)</th>
                            <th class="py-3 px-3 text-center">CA 3 (10)</th>
                            <th class="py-3 px-3 text-center">Exam (70)</th>
                            <th class="py-3 px-3 text-center">Total (100)</th>
                            <th class="py-3 px-3 text-center">Grade</th>
                            <th class="py-3 px-3 text-center">Remark</th>
                        </tr>
                    </thead>
                    <tbody style="border-top: 1px solid var(--border-subtle);">
                        <?php if (!empty($assessments)): ?>
                            <?php foreach ($assessments as $ass): ?>
                                <?php 
                                    $score = (float)$ass['total_score'];
                                    $eval  = calculateGrade($score);
                                ?>
                                <tr class="transition" style="border-bottom: 1px solid var(--border-subtle);">
                                    <td class="py-3 px-3 font-bold print-text-dark" style="color: var(--text-primary);">
                                        <?php echo htmlspecialchars($ass['subject_name']); ?>
                                        <span class="text-[10px] font-mono block print-text-muted" style="color: var(--text-muted);"><?php echo htmlspecialchars($ass['subject_code']); ?></span>
                                    </td>
                                    <td class="py-3 px-3 text-center font-mono"><?php echo number_format((float)$ass['ca1_score'], 1); ?></td>
                                    <td class="py-3 px-3 text-center font-mono"><?php echo number_format((float)$ass['ca2_score'], 1); ?></td>
                                    <td class="py-3 px-3 text-center font-mono"><?php echo number_format((float)$ass['ca3_score'], 1); ?></td>
                                    <td class="py-3 px-3 text-center font-mono"><?php echo number_format((float)$ass['exam_score'], 1); ?></td>
                                    <td class="py-3 px-3 text-center font-mono font-bold print-text-dark" style="color: var(--text-primary);"><?php echo number_format($score, 1); ?></td>
                                    <td class="py-3 px-3 text-center font-bold font-mono print-text-dark" style="color: <?php echo $eval['color']; ?>;"><?php echo $eval['grade']; ?></td>
                                    <td class="py-3 px-3 text-center print-text-muted" style="color: var(--text-secondary);"><?php echo $eval['remark']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="py-8 text-center text-xs print-text-muted" style="color: var(--text-muted);">
                                    No academic assessment records uploaded for this term.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary & Attendance Section Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4" style="border-top: 1px solid var(--border-subtle);">
            
            <!-- Overall Academic Summary Card -->
            <div class="p-4 rounded-xl space-y-3 text-xs print-card-bg" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                <h4 class="font-bold uppercase tracking-wider text-[11px] print-text-dark" style="color: var(--text-primary);">Academic Summary</h4>
                
                <div class="flex justify-between items-center py-1" style="border-bottom: 1px solid var(--border-subtle);">
                    <span class="print-text-muted" style="color: var(--text-secondary);">Total Subjects Evaluated:</span>
                    <strong class="font-mono print-text-dark" style="color: var(--text-primary);"><?php echo count($assessments); ?></strong>
                </div>

                <div class="flex justify-between items-center py-1" style="border-bottom: 1px solid var(--border-subtle);">
                    <span class="print-text-muted" style="color: var(--text-secondary);">Total Marks Obtained:</span>
                    <strong class="font-mono print-text-dark" style="color: var(--text-primary);"><?php echo number_format($totalObtained, 1); ?> / <?php echo $totalMaxPossible; ?></strong>
                </div>

                <div class="flex justify-between items-center py-1">
                    <span class="print-text-muted" style="color: var(--text-secondary);">Cumulative Average Score:</span>
                    <strong class="font-mono text-sm print-text-dark" style="color: var(--text-accent);"><?php echo number_format($averageScore, 2); ?>%</strong>
                </div>
            </div>

            <!-- Term Attendance Breakdown Card -->
            <div class="p-4 rounded-xl space-y-3 text-xs print-card-bg" style="background: var(--bg-input); border: 1px solid var(--border-subtle);">
                <h4 class="font-bold uppercase tracking-wider text-[11px] print-text-dark" style="color: var(--text-primary);">Attendance Record</h4>
                
                <div class="grid grid-cols-3 gap-2 text-center pt-1">
                    <div class="p-2 rounded-lg print-card-bg" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                        <span class="block text-[10px] print-text-muted" style="color: var(--text-secondary);">Present</span>
                        <strong class="font-mono text-sm block mt-0.5 print-text-dark" style="color: var(--text-success, #10b981);"><?php echo $attendanceSummary['present']; ?></strong>
                    </div>

                    <div class="p-2 rounded-lg print-card-bg" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                        <span class="block text-[10px] print-text-muted" style="color: var(--text-secondary);">Absent</span>
                        <strong class="font-mono text-sm block mt-0.5 print-text-dark" style="color: var(--text-danger, #ef4444);"><?php echo $attendanceSummary['absent']; ?></strong>
                    </div>

                    <div class="p-2 rounded-lg print-card-bg" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                        <span class="block text-[10px] print-text-muted" style="color: var(--text-secondary);">Late</span>
                        <strong class="font-mono text-sm block mt-0.5 print-text-dark" style="color: var(--text-warning, #f59e0b);"><?php echo $attendanceSummary['late']; ?></strong>
                    </div>
                </div>

                <div class="text-right text-[11px] pt-1 print-text-muted" style="color: var(--text-secondary);">
                    Total School Days Recorded: <strong class="font-mono print-text-dark" style="color: var(--text-primary);"><?php echo $attendanceSummary['total']; ?></strong>
                </div>
            </div>

        </div>

        <!-- Grade Interpretation Guide & Signatures -->
        <div class="pt-6 space-y-6" style="border-top: 1px solid var(--border-subtle);">
            <div class="text-[11px] space-y-1 print-text-muted" style="color: var(--text-secondary);">
                <strong class="block print-text-dark" style="color: var(--text-primary);">Grading Scale:</strong>
                <p>A (70-100%): Excellent | B (60-69%): Very Good | C (50-59%): Credit | D (45-49%): Pass | E (40-44%): Fair Pass | F (0-39%): Fail</p>
            </div>

            <div class="grid grid-cols-2 gap-8 pt-8 text-xs">
                <div class="pt-2 text-center" style="border-top: 1px solid var(--border-subtle);">
                    <p class="font-bold print-text-dark" style="color: var(--text-primary);">Form Teacher Signature</p>
                </div>
                <div class="pt-2 text-center" style="border-top: 1px solid var(--border-subtle);">
                    <p class="font-bold print-text-dark" style="color: var(--text-primary);">Principal / Administrator Stamp</p>
                </div>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>