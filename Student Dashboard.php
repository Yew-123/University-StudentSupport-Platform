<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['studentID'])) {
    header("Location: Login.html");
    exit;
}

$id = $_SESSION['studentID'];
$studentName = $_SESSION['name'] ?? 'Student';

// 1. Fetch Request Counts
$pendingCount = $acceptedCount = 0;

if ($stmt = $conn->prepare("SELECT COUNT(*) AS p FROM SUPPORTREQUEST WHERE studentID = ? AND requestStatus = 'Pending'")) {
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $pendingCount = $counts['p'] ?? 0;
    $stmt->close();
}

if ($stmt = $conn->prepare("SELECT COUNT(*) AS a FROM SUPPORTREQUEST WHERE studentID = ? AND requestStatus IN ('Accepted', 'Confirmed')")) {
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
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
            $rawStatus = $row['requestStatus'];
            $displayStatus = htmlspecialchars($rawStatus);
            $cleanDesc = trim(explode(' | AssignedVolunteer:', $row['requestDesc'])[0]);

            // Dynamic color coding based on status
            $statusColor = '#2b6cb0'; // Default Blue for Pending
            if ($rawStatus === 'Accepted' || $rawStatus === 'Confirmed') {
                $statusColor = '#38a169'; // Green for Accepted/Confirmed
            } elseif ($rawStatus === 'Rejected') {
                $statusColor = '#e53e3e'; // Red for Rejected
            }

            $requestRows .= '<tr>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['skillName']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($cleanDesc) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['supportMode']) . '</td>'
                . '<td style="padding: 10px; font-weight: bold; color: ' . $statusColor . ';">' . $displayStatus . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['created_at']) . '</td>'
                . '</tr>';
        }
    } else {
        $requestRows = '<tr><td colspan="5" style="text-align: center; padding: 15px;">No support requests found.</td></tr>';
    }
    $stmt->close();
}

// 3. Load HTML template and inject variables
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