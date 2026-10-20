<?php
session_start();
require_once 'db.php';

// Ensure user (volunteer) is logged in via student session
if (!isset($_SESSION['studentID'])) {
    header("Location: Login.html");
    exit;
}

$id = $_SESSION['studentID'];
$volunteerName = $_SESSION['name'] ?? 'Volunteer';

// 1. Fetch Request Counts (Filtered for the volunteer student)
$pendingCount = $acceptedCount = 0;
if ($stmt = $conn->prepare("SELECT SUM(requestStatus='Pending') AS p, SUM(requestStatus IN ('Accepted','Approved')) AS a FROM SUPPORTREQUEST WHERE studentID = ?")) {
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $pendingCount = $counts['p'] ?? 0;
    $acceptedCount = $counts['a'] ?? 0;
    $stmt->close();
}

// 2. Fetch Recent Requests
$requestRows = '';
if ($stmt = $conn->prepare("SELECT sr.requestDesc, sr.requestStatus, sr.supportMode, sr.created_at, sk.skillName FROM SUPPORTREQUEST sr JOIN SKILLS sk ON sr.skillID = sk.skillID WHERE sr.studentID = ? ORDER BY sr.created_at DESC LIMIT 5")) {
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $requestRows .= '<tr>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['skillName']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['requestDesc']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['supportMode']) . '</td>'
                . '<td style="padding: 10px; font-weight: bold;">' . htmlspecialchars($row['requestStatus']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['created_at']) . '</td>'
                . '</tr>';
        }
    } else {
        $requestRows = '<tr><td colspan="5" style="text-align: center; padding: 15px;">No support requests found.</td></tr>';
    }
    $stmt->close();
}

// 3. Load Volunteer HTML template and inject variables
$htmlFile = 'Volunteer Dashboard.html';
if (file_exists($htmlFile)) {
    $html = file_get_contents($htmlFile);
    
    $placeholders = [
        '{{studentName}}'   => htmlspecialchars($volunteerName),
        '{{pendingCount}}'  => $pendingCount,
        '{{acceptedCount}}' => $acceptedCount,
        '{{requestRows}}'   => $requestRows
    ];
    
    echo str_replace(array_keys($placeholders), array_values($placeholders), $html);
} else {
    echo "Error: Template file '$htmlFile' not found.";
}
exit;
?>