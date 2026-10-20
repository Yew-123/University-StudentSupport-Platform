<?php
session_start();
require_once 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['studentID'])) {
    header("Location: Login.html");
    exit;
}

$id = $_SESSION['studentID'];
$studentName = $_SESSION['name'] ?? 'Student';

// 1. Fetch Request Counts (Included 'Confirmed' status and AssignedVolunteer checks)
$pendingCount = $acceptedCount = 0;

$countSql = "SELECT 
    SUM(requestStatus = 'Pending' AND (requestDesc NOT LIKE '%AssignedVolunteer:%' OR requestDesc IS NULL)) AS p,
    SUM(requestStatus IN ('Accepted','Approved','Confirmed') OR requestDesc LIKE '%AssignedVolunteer:%') AS a 
    FROM SUPPORTREQUEST 
    WHERE studentID = ?";

if ($stmt = $conn->prepare($countSql)) {
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $pendingCount = $counts['p'] ?? 0;
    $acceptedCount = $counts['a'] ?? 0;
    $stmt->close();
}

// 2. Fetch Recent Requests
$requestRows = '';
$requestSql = "SELECT sr.requestDesc, sr.requestStatus, sr.supportMode, sr.created_at, sk.skillName 
               FROM SUPPORTREQUEST sr 
               JOIN SKILLS sk ON sr.skillID = sk.skillID 
               WHERE sr.studentID = ? 
               ORDER BY sr.created_at DESC 
               LIMIT 5";

if ($stmt = $conn->prepare($requestSql)) {
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Determine display status dynamically if volunteer is assigned
            $displayStatus = htmlspecialchars($row['requestStatus']);
            if (strpos($row['requestDesc'], 'AssignedVolunteer:') !== false && $displayStatus === 'Pending') {
                $displayStatus = 'Accepted';
            }

            // Clean requestDesc for output (hide raw AssignedVolunteer tag from UI)
            $cleanDesc = trim(explode(' | AssignedVolunteer:', $row['requestDesc'])[0]);

            $requestRows .= '<tr>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['skillName']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($cleanDesc) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['supportMode']) . '</td>'
                . '<td style="padding: 10px; font-weight: bold;">' . $displayStatus . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['created_at']) . '</td>'
                . '</tr>';
        }
    } else {
        $requestRows = '<tr><td colspan="5" style="text-align: center; padding: 15px;">No support requests found.</td></tr>';
    }
    $stmt->close();
}

// 3. Load HTML template and inject variables efficiently
$htmlFile = 'Student Dashboard.html';
if (file_exists($htmlFile)) {
    $html = file_get_contents($htmlFile);
    
    $placeholders = [
        '{{studentName}}'  => htmlspecialchars($studentName),
        '{{pendingCount}}' => $pendingCount,
        '{{acceptedCount}}'=> $acceptedCount,
        '{{requestRows}}'  => $requestRows
    ];
    
    echo str_replace(array_keys($placeholders), array_values($placeholders), $html);
} else {
    echo "Error: Template file '$htmlFile' not found.";
}
exit;
?>