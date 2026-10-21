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
$searchTerm = '%' . $id . '%';

// 1. Fetch Request Counts accurately for Pending and Accepted
$pendingCount = 0;
$acceptedCount = 0;

if ($stmt = $conn->prepare("SELECT COUNT(*) AS p FROM SUPPORTREQUEST WHERE requestDesc LIKE ? AND requestStatus = 'Pending'")) {
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $pendingCount = $counts['p'] ?? 0;
    $stmt->close();
}

if ($stmt = $conn->prepare("SELECT COUNT(*) AS a FROM SUPPORTREQUEST WHERE requestDesc LIKE ? AND requestStatus = 'Accepted'")) {
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $acceptedCount = $counts['a'] ?? 0;
    $stmt->close();
}

// 2. Fetch Recent Assigned Sessions for the Dashboard Table
$requestRows = '';
$sql = "SELECT sr.requestDesc, sr.requestStatus, sr.supportMode, sr.created_at, sk.skillName, st.Name AS studentName 
        FROM SUPPORTREQUEST sr 
        JOIN SKILLS sk ON sr.skillID = sk.skillID 
        JOIN STUDENT st ON sr.studentID = st.studentID 
        WHERE sr.requestDesc LIKE ? 
        ORDER BY sr.created_at DESC 
        LIMIT 5";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rawStatus = $row['requestStatus'];
            $status = htmlspecialchars($rawStatus, ENT_QUOTES);
            
            // Dynamic color coding based on the status text
            $statusColor = '#2b6cb0'; // Default Blue for Pending
            if ($rawStatus === 'Accepted') {
                $statusColor = '#38a169'; // Green for Accepted
            } elseif ($rawStatus === 'Rejected') {
                $statusColor = '#e53e3e'; // Red for Rejected
            }
            
            $requestRows .= '<tr>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['skillName']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['studentName']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['supportMode']) . '</td>'
                . '<td style="padding: 10px; font-weight: bold; color: ' . $statusColor . ';">' . $status . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['created_at']) . '</td>'
                . '</tr>';
        }
    } else {
        $requestRows = '<tr><td colspan="5" style="text-align: center; padding: 15px; color: #718096;">No support sessions found.</td></tr>';
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