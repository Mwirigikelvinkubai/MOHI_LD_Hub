<?php
/**
 * export.php — CSV Export Handler
 * Handles all CSV exports. Called via GET with ?type=staff|results|report
 * For report exports, accepts the same GET params as reports.php
 */

require_once 'config.php';

$pdo  = getDB();
$type = $_GET['type'] ?? 'staff';

// Helper: output CSV headers and start streaming
function startCSV(string $filename): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    // BOM for Excel UTF-8 compatibility
    echo "\xEF\xBB\xBF";
}

// Helper: write one row to CSV output
function writeRow(array $row): void {
    $line = implode(',', array_map(function($v) {
        // Wrap in quotes if contains comma, quote, or newline
        $v = str_replace('"', '""', $v ?? '');
        return '"' . $v . '"';
    }, $row));
    echo $line . "\r\n";
}

// ===================== STAFF EXPORT =====================
if ($type === 'staff') {
    startCSV('staff_' . date('Ymd') . '.csv');

    writeRow(['Staff No', 'Full Name', 'Job Title', 'Work Station', 'Department', 'Email', 'Created At']);

    $rows = $pdo->query("SELECT staff_no, full_name, job_title, workstation, department, email, created_at FROM staff ORDER BY full_name")->fetchAll();
    foreach ($rows as $r) {
        writeRow([$r['staff_no'], $r['full_name'], $r['job_title'], $r['workstation'], $r['department'], $r['email'], $r['created_at']]);
    }
    exit;
}

// ===================== RESULTS EXPORT =====================
if ($type === 'results') {
    startCSV('results_' . date('Ymd') . '.csv');

    writeRow(['Date Taken', 'Staff No', 'Full Name', 'Department', 'Work Station', 'Training', 'Sub-Course', 'Score (%)', 'Pass Mark (%)', 'Status']);

    $rows = $pdo->query("
        SELECT r.date_taken, s.staff_no, s.full_name, s.department, s.workstation,
               t.name as training, sc.name as sub_course, r.score, sc.pass_mark
        FROM results r
        JOIN staff s ON s.id = r.staff_id
        JOIN trainings t ON t.id = r.training_id
        JOIN sub_courses sc ON sc.id = r.sub_course_id
        ORDER BY r.created_at DESC
    ")->fetchAll();

    foreach ($rows as $r) {
        $status = $r['score'] >= $r['pass_mark'] ? 'Pass' : 'Fail';
        writeRow([$r['date_taken'] ?? '', $r['staff_no'], $r['full_name'], $r['department'], $r['workstation'], $r['training'], $r['sub_course'], $r['score'], $r['pass_mark'], $status]);
    }
    exit;
}

// ===================== REPORT EXPORT =====================
if ($type === 'report') {
    $reportType  = $_GET['report']       ?? 'completion';
    $trainingId  = (int)($_GET['training_id'] ?? 0);
    $staffEmail  = clean($_GET['staff_email'] ?? '');   // email — unique identifier
    $department  = clean($_GET['dept']        ?? '');
    $workstation = clean($_GET['workstation'] ?? '');

    startCSV('report_' . $reportType . '_' . date('Ymd') . '.csv');

    if ($reportType === 'completion') {
        writeRow(['Staff No', 'Full Name', 'Department', 'Work Station', 'Training', 'Sub-Courses Done', 'Sub-Courses Total', 'Avg Score (%)', 'Passed', 'Failed']);

        $where = "WHERE 1=1"; $params = [];
        if ($department)  { $where .= " AND s.department =?"; $params[] = $department; }
        if ($workstation) { $where .= " AND s.workstation=?"; $params[] = $workstation; }
        if ($trainingId)  { $where .= " AND t.id=?";          $params[] = $trainingId; }

        $stmt = $pdo->prepare("
            SELECT s.staff_no, s.full_name, s.department, s.workstation,
                   t.name as training_name,
                   COUNT(r.id) as done,
                   (SELECT COUNT(*) FROM sub_courses WHERE training_id = t.id) as total,
                   ROUND(AVG(r.score),1) as avg_score,
                   SUM(CASE WHEN r.score >= sc.pass_mark THEN 1 ELSE 0 END) as passed,
                   SUM(CASE WHEN r.score < sc.pass_mark  THEN 1 ELSE 0 END) as failed
            FROM staff s CROSS JOIN trainings t
            LEFT JOIN results r ON r.staff_id=s.id AND r.training_id=t.id
            LEFT JOIN sub_courses sc ON sc.id=r.sub_course_id
            $where GROUP BY s.id, t.id ORDER BY s.full_name, t.name
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            writeRow([$r['staff_no'], $r['full_name'], $r['department'], $r['workstation'], $r['training_name'], $r['done'], $r['total'], $r['avg_score'] ?? '', (int)$r['passed'], (int)$r['failed']]);
        }
    }

    elseif ($reportType === 'training_detail' && $trainingId) {
        writeRow(['Staff No', 'Full Name', 'Department', 'Work Station', 'Sub-Course', 'Score (%)', 'Pass Mark (%)', 'Status', 'Date']);
        $where = "WHERE r.training_id=?"; $params = [$trainingId];
        if ($department) { $where .= " AND s.department=?"; $params[] = $department; }
        $stmt = $pdo->prepare("SELECT s.staff_no, s.full_name, s.department, s.workstation, sc.name as sub_course, sc.pass_mark, r.score, r.date_taken FROM results r JOIN staff s ON s.id=r.staff_id JOIN sub_courses sc ON sc.id=r.sub_course_id $where ORDER BY s.full_name");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            writeRow([$r['staff_no'], $r['full_name'], $r['department'], $r['workstation'], $r['sub_course'], $r['score'], $r['pass_mark'], $r['score']>=$r['pass_mark']?'Pass':'Fail', $r['date_taken']??'']);
        }
    }

    elseif ($reportType === 'transcript' && $staffEmail) {
        // Fetch staff info for header rows
        $sInfo = $pdo->prepare("SELECT staff_no, full_name, job_title, department, workstation FROM staff WHERE LOWER(email)=LOWER(?)");
        $sInfo->execute([$staffEmail]);
        $sRow = $sInfo->fetch();

        // Write staff info header block
        writeRow(['STAFF TRAINING TRANSCRIPT', '']);
        writeRow(['Full Name',   $sRow['full_name']   ?? '']);
        writeRow(['Email',       $staffEmail]);
        writeRow(['Staff No',    $sRow['staff_no']    ?? '']);
        writeRow(['Job Title',   $sRow['job_title']   ?? '']);
        writeRow(['Department',  $sRow['department']  ?? '']);
        writeRow(['Work Station',$sRow['workstation'] ?? '']);
        writeRow(['Generated',   date('d M Y H:i')]);
        writeRow(['', '']); // blank spacer

        writeRow(['Training', 'Sub-Course', 'Score (%)', 'Pass Mark (%)', 'Status', 'Date Taken']);
        $stmt = $pdo->prepare("
            SELECT t.name as training, sc.name as sub_course, sc.pass_mark, r.score, r.date_taken
            FROM results r
            JOIN trainings t    ON t.id  = r.training_id
            JOIN sub_courses sc ON sc.id = r.sub_course_id
            WHERE r.staff_id = (SELECT id FROM staff WHERE LOWER(email) = LOWER(?))
            ORDER BY t.name, sc.name
        ");
        $stmt->execute([$staffEmail]);
        foreach ($stmt->fetchAll() as $r) {
            writeRow([$r['training'], $r['sub_course'], $r['score'], $r['pass_mark'], $r['score']>=$r['pass_mark']?'Pass':'Fail', $r['date_taken']??'']);
        }
    }

    elseif ($reportType === 'dept_summary') {
        writeRow(['Department', 'Staff Count', 'Total Results', 'Passed', 'Avg Score (%)', 'Pass Rate (%)']);
        $where = "WHERE 1=1"; $params = [];
        if ($trainingId) { $where .= " AND r.training_id=?"; $params[] = $trainingId; }
        $stmt = $pdo->prepare("SELECT s.department, COUNT(DISTINCT s.id) as staff_count, COUNT(r.id) as total, SUM(CASE WHEN r.score>=sc.pass_mark THEN 1 ELSE 0 END) as passed, ROUND(AVG(r.score),1) as avg_score, ROUND(SUM(CASE WHEN r.score>=sc.pass_mark THEN 1.0 ELSE 0 END)/NULLIF(COUNT(r.id),0)*100,1) as pass_rate FROM staff s LEFT JOIN results r ON r.staff_id=s.id LEFT JOIN sub_courses sc ON sc.id=r.sub_course_id $where GROUP BY s.department ORDER BY pass_rate DESC");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            writeRow([$r['department']??'Unassigned', $r['staff_count'], $r['total'], (int)$r['passed'], $r['avg_score']??'', $r['pass_rate']??'']);
        }
    }

    elseif ($reportType === 'non_participants' && $trainingId) {
        writeRow(['Staff No', 'Full Name', 'Job Title', 'Department', 'Work Station', 'Email']);
        $where = "WHERE 1=1"; $params = [];
        if ($department) { $where .= " AND s.department=?"; $params[] = $department; }
        $params[] = $trainingId;
        $stmt = $pdo->prepare("SELECT s.staff_no, s.full_name, s.job_title, s.department, s.workstation, s.email FROM staff s $where AND s.id NOT IN (SELECT DISTINCT staff_id FROM results WHERE training_id=?) ORDER BY s.department, s.full_name");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            writeRow([$r['staff_no'], $r['full_name'], $r['job_title'], $r['department'], $r['workstation'], $r['email']]);
        }
    }

    exit;
}

// Fallback
redirect('train_dashboard.php');