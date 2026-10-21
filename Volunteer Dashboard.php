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

// 1. Fetch Request Counts (Count sessions assigned/accepted by this volunteer)
$acceptedCount = 0;
$searchTerm = '%AssignedVolunteer: ' . $id . '%';

if ($stmt = $conn->prepare("SELECT COUNT(*) AS a FROM SUPPORTREQUEST WHERE requestDesc LIKE ?")) {
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $acceptedCount = $counts['a'] ?? 0;
    $stmt->close();
}

// Pending count for volunteers can represent available unassigned requests matching their skills, or 0 if not tracked on dashboard
$pendingCount = 0; 

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
            $status = htmlspecialchars($row['requestStatus']);
            
            $requestRows .= '<tr>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['skillName']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['studentName']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['supportMode']) . '</td>'
                . '<td style="padding: 10px; font-weight: bold; color: #2b6cb0;">' . $status . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['created_at']) . '</td>'
                . '</tr>';
        }
    } else {
        $requestRows = '<tr><td colspan="5" style="text-align: center; padding: 15px; color: #718096;">No accepted support sessions found.</td></tr>';
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