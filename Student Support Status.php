<?php
// Turn off raw HTML error rendering so it doesn't break our JSON interface
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

header('Content-Type: application/json');

// 1. Authenticate session credentials
if (!isset($_SESSION['studentID']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized access']));
}

$studentID = $_SESSION['studentID'];
$status = $_GET['status'] ?? 'Pending'; 

// 2. Safely verify that the database connection object ($conn) exists
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . ($conn->connect_error ?? 'Connection variable missing')]));
}

// 3. Query execution via prepared statement with a JOIN to the skills table
$sql = "SELECT sr.requestID, sr.requestDesc, sr.created_at, sr.requestStatus, sr.supportMode, sr.preferred_at, s.skillName AS preferredSkills 
        FROM supportrequest sr
        LEFT JOIN skills s ON sr.skillID = s.skillID
        WHERE sr.studentID = ? AND sr.requestStatus = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    die(json_encode(['error' => 'SQL preparation failed: ' . $conn->error]));
}

$stmt->bind_param("ss", $studentID, $status); 
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    http_response_code(500);
    die(json_encode(['error' => 'Failed to fetch query execution results: ' . $conn->error]));
}

$rows = $result->fetch_all(MYSQLI_ASSOC);

// Process rows to format Date, Time, and Volunteer placeholder for the frontend
foreach ($rows as &$row) {
    // Split preferred_at into date and time
    if (!empty($row['preferred_at'])) {
        $dt = new DateTime($row['preferred_at']);
        $row['preferredDate'] = $dt->format('Y-m-d');
        $row['preferredTime'] = $dt->format('H:i');
    } else {
        $row['preferredDate'] = 'Not Specified';
        $row['preferredTime'] = 'Not Specified';
    }

    // Fallback if no matching skill name is found
    if (empty($row['preferredSkills'])) {
        $row['preferredSkills'] = 'Not Specified';
    }

    // Placeholder for volunteer since it isn't stored in supportrequest yet
    $row['selectedVolunteer'] = 'Not Assigned Yet'; 
}

// 4. Render array conversion directly 
echo json_encode($rows);

$stmt->close();
$conn->close();
?>