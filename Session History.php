<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if (!isset($_SESSION['studentID'])) {
    header("Location: Login Page.html");
    exit;
}

if (!isset($conn) || $conn->connect_error) {
    die("DB connection failed: " . ($conn->connect_error ?? 'unknown'));
}

$userID = $_SESSION['studentID'];
$role   = $_SESSION['role'] ?? 'student';

$historyHTML = '';

if ($role === 'volunteer') {
    // ---------- VOLUNTEER VIEW ----------
    // No FEEDBACK.volunteerID column — join on sessionID only.
    $searchTerm = '%AssignedVolunteer: ' . $userID . '%';
    $sql = "
        SELECT sr.requestID, sr.requestDesc, sr.requestStatus, sr.supportMode, sr.created_at,
               sk.skillName,
               st.Name AS studentName,
               s.sessionID, s.date AS sessionDate, s.status AS sessionStatus,
               f.rating, f.comment
        FROM SUPPORTREQUEST sr
        JOIN SKILLS sk ON sr.skillID = sk.skillID
        JOIN STUDENT st ON sr.studentID = st.studentID
        LEFT JOIN SESSION s ON s.requestID = sr.requestID
        LEFT JOIN FEEDBACK f ON f.sessionID = s.sessionID
        WHERE sr.requestDesc LIKE ?
        ORDER BY sr.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("SQL error (volunteer): " . $conn->error);
    $stmt->bind_param("s", $searchTerm);
    $backLink = 'Volunteer Dashboard.php';
} else {
    // ---------- STUDENT VIEW ----------
    $sql = "
        SELECT sr.requestID, sr.requestDesc, sr.requestStatus, sr.supportMode, sr.created_at,
               sk.skillName,
               vol.Name AS volunteerName,
               s.sessionID, s.date AS sessionDate, s.status AS sessionStatus,
               f.rating, f.comment
        FROM SUPPORTREQUEST sr
        JOIN SKILLS sk ON sr.skillID = sk.skillID
        LEFT JOIN SESSION s ON s.requestID = sr.requestID
        LEFT JOIN FEEDBACK f ON f.sessionID = s.sessionID
        LEFT JOIN STUDENT vol ON (
            vol.studentID = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sr.requestDesc, 'AssignedVolunteer:', -1), '|', 1))
        )
        WHERE sr.studentID = ?
        ORDER BY sr.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("SQL error (student): " . $conn->error);
    $stmt->bind_param("s", $userID);
    $backLink = 'Student Dashboard.php';
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Cleaner topic extraction (handles old rows without "Assistance:" prefix)
        $topic = 'Not Specified';
        if (!empty($row['requestDesc'])) {
            $clean = preg_replace('/\s*\|\s*AssignedVolunteer:.*$/i', '', $row['requestDesc']);
            if (preg_match('/Assistance:\s*(.*?)\s*(?:\||$)/i', $clean, $m)) {
                $topic = trim($m[1]);
            } else {
                $topic = trim($clean);
            }
        }

        // Column name is `comment` (singular)
        $feedbackDisplay = $row['rating']
            ? htmlspecialchars($row['rating']) . ' / 5' . (!empty($row['comment']) ? ' — ' . htmlspecialchars($row['comment']) : '')
            : 'No feedback yet';

        if ($role === 'volunteer') {
            $otherPersonRow = '<tr><th>Student Name</th><td>' . htmlspecialchars($row['studentName']) . '</td></tr>';
        } else {
            $otherPersonRow = '<tr><th>Volunteer</th><td>' . ($row['volunteerName'] ? htmlspecialchars($row['volunteerName']) : 'N/A') . '</td></tr>';
        }

        $historyHTML .= '
        <table class="support_status_table">
            <tbody>
                <tr><th>Request ID</th><td>' . htmlspecialchars($row['requestID']) . '</td></tr>
                ' . $otherPersonRow . '
                <tr><th>Skill / Topic</th><td>' . htmlspecialchars($row['skillName']) . '</td></tr>
                <tr><th>Assistance Required</th><td>' . htmlspecialchars($topic) . '</td></tr>
                <tr><th>Support Mode</th><td>' . htmlspecialchars($row['supportMode']) . '</td></tr>
                <tr><th>Request Date</th><td>' . htmlspecialchars($row['created_at']) . '</td></tr>
                <tr><th>Session Date</th><td>' . ($row['sessionDate'] ? htmlspecialchars($row['sessionDate']) : 'Not scheduled') . '</td></tr>
                <tr><th>Session Status</th><td>' . ($row['sessionStatus'] ? htmlspecialchars($row['sessionStatus']) : 'N/A') . '</td></tr>
                <tr><th>Feedback</th><td>' . $feedbackDisplay . '</td></tr>
            </tbody>
        </table>';
    }
} else {
    $historyHTML = '<p style="text-align:center; color:#718096; padding:20px;">No support history found.</p>';
}
$stmt->close();

$template = file_get_contents('Session History.html');
$template = str_replace('{{backLink}}', $backLink, $template);
$template = str_replace('{{historyHTML}}', $historyHTML, $template);

echo $template;
$conn->close();
?>