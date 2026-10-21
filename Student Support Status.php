<?php
// Turn off raw HTML error display so errors don't corrupt JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['studentID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$studentID = $_SESSION['studentID'];
$status = $_GET['status'] ?? 'All'; 

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Build query dynamically depending on whether a specific status filter is applied
if ($status === 'All' || empty($status)) {
    $sql = "SELECT sr.requestID, sr.requestDesc, sr.created_at, sr.requestStatus, sr.supportMode, 
                   s.skillName AS preferredSkills 
            FROM SUPPORTREQUEST sr
            LEFT JOIN SKILLS s ON sr.skillID = s.skillID
            WHERE sr.studentID = ?
            ORDER BY sr.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $studentID);
    }
} else {
    $sql = "SELECT sr.requestID, sr.requestDesc, sr.created_at, sr.requestStatus, sr.supportMode, 
                   s.skillName AS preferredSkills 
            FROM SUPPORTREQUEST sr
            LEFT JOIN SKILLS s ON sr.skillID = s.skillID
            WHERE sr.studentID = ? AND sr.requestStatus = ?
            ORDER BY sr.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $studentID, $status);
    }
}

if (!isset($stmt) || !$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'SQL preparation failed: ' . $conn->error]);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

foreach ($rows as &$row) {
    $preferredDate = 'Not Specified';
    $preferredTime = 'Not Specified';
    $row['preferredSkills'] = $row['preferredSkills'] ?? 'Not Specified';

    if (!empty($row['requestDesc'])) {
        if (preg_match('/Preferred Date:\s*([^\s,]+)/i', $row['requestDesc'], $matches)) {
            $preferredDate = trim($matches[1]);
        }
        if (preg_match('/Time:\s*([^\s|]+)/i', $row['requestDesc'], $matches)) {
            $preferredTime = trim($matches[1]);
        }
    }

    $row['preferredDate'] = $preferredDate;
    $row['preferredTime'] = $preferredTime;

    $volunteerName = '';
    
    // Robustly extract the assigned volunteer ID
    if (!empty($row['requestDesc']) && preg_match('/AssignedVolunteer:\s*([^\s|]+)/i', $row['requestDesc'], $match)) {
        $assignedVolID = trim($match[1]);

        if (!empty($assignedVolID)) {
            $volQuery = $conn->prepare("SELECT Name FROM STUDENT WHERE studentID = ?");
            if ($volQuery) {
                $volQuery->bind_param("s", $assignedVolID);
                $volQuery->execute();
                $volRes = $volQuery->get_result();
                if ($volRow = $volRes->fetch_assoc()) {
                    $volunteerName = $volRow['Name'] ?? '';
                }
                $volQuery->close();
            }
        }
    }

    $row['volunteerName'] = $volunteerName;
 
    // Add scheduling link info if request is Accepted or Confirmed
    if ($row['requestStatus'] === 'Accepted' || $row['requestStatus'] === 'Confirmed') {
        $row['scheduleLink'] = 'Schedule Session.php?requestID=' . urlencode($row['requestID']);
    } else {
        $row['scheduleLink'] = '';
    }
}

echo json_encode($rows);

if ($stmt) $stmt->close();
if (isset($conn)) $conn->close();
?>