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
$status = $_GET['status'] ?? 'Pending'; 

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$sql = "SELECT sr.requestID, sr.requestDesc, sr.created_at, sr.requestStatus, sr.supportMode, 
               s.skillName AS preferredSkills 
        FROM SUPPORTREQUEST sr
        LEFT JOIN SKILLS s ON sr.skillID = s.skillID
        WHERE sr.studentID = ? AND sr.requestStatus = ?
        ORDER BY sr.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'SQL preparation failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ss", $studentID, $status); 
$stmt->execute();
$result = $stmt->get_result();

$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

foreach ($rows as &$row) {
    $row['preferredDate'] = !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : 'Not Specified';
    $row['preferredTime'] = !empty($row['created_at']) ? date('H:i', strtotime($row['created_at'])) : 'Not Specified';
    $row['preferredSkills'] = $row['preferredSkills'] ?? 'Not Specified';

    $volunteerName = '';
    
    // Check if requestDesc has our assigned volunteer tag
    if (!empty($row['requestDesc']) && strpos($row['requestDesc'], 'AssignedVolunteer:') !== false) {
        $parts = explode('AssignedVolunteer:', $row['requestDesc']);
        if (isset($parts[1])) {
            $assignedVolID = trim(explode('|', $parts[1])[0]); 

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
}

echo json_encode($rows);

if ($stmt) $stmt->close();
if (isset($conn)) $conn->close();
?>