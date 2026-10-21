<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if (!isset($_SESSION['studentID'])) {
    header("Location: Login Page.html");
    exit;
}

$volunteerID = $_SESSION['studentID'];

// Handle Accept/Reject Actions
if (isset($_GET['action']) && isset($_GET['requestID'])) {
    $action = $_GET['action'];
    $requestID = $_GET['requestID'];

    if ($action === 'accept') {
        $updateStmt = $conn->prepare("UPDATE SUPPORTREQUEST SET requestStatus = 'Accepted' WHERE requestID = ?");
        $updateStmt->bind_param("s", $requestID);
        $updateStmt->execute();
        $updateStmt->close();
    } elseif ($action === 'reject') {
        // Update status to Rejected while keeping the volunteer tag so they can still see the record
        $updateStmt = $conn->prepare("UPDATE SUPPORTREQUEST SET requestStatus = 'Rejected' WHERE requestID = ?");
        $updateStmt->bind_param("s", $requestID);
        $updateStmt->execute();
        $updateStmt->close();
    }

    header("Location: Volunteer Support Session Requests.php");
    exit;
}

// Fetch sessions assigned to this volunteer securely using wildcard matching
$sql = "SELECT sr.requestID, sr.requestDesc, sr.created_at, sr.requestStatus, sr.supportMode, 
               s.skillName, st.Name AS studentName, st.Email AS studentEmail, st.phoneNo AS studentPhone
        FROM SUPPORTREQUEST sr
        JOIN SKILLS s ON sr.skillID = s.skillID
        JOIN STUDENT st ON sr.studentID = st.studentID
        WHERE sr.requestDesc LIKE ?
        ORDER BY sr.created_at DESC";

$stmt = $conn->prepare($sql);
$searchTerm = '%' . $volunteerID . '%';
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$assignedRequestsHTML = '';

if ($result->num_rows > 0) {
    while ($req = $result->fetch_assoc()) {
        $reqID = htmlspecialchars($req['requestID'], ENT_QUOTES);
        $skillName = htmlspecialchars($req['skillName'], ENT_QUOTES);
        $studentName = htmlspecialchars($req['studentName'], ENT_QUOTES);
        $studentEmail = htmlspecialchars($req['studentEmail'], ENT_QUOTES);
        $studentPhone = htmlspecialchars($req['studentPhone'], ENT_QUOTES);
        $supportMode = htmlspecialchars($req['supportMode'], ENT_QUOTES);
        $createdAt = htmlspecialchars($req['created_at'], ENT_QUOTES);
        
        $rawStatus = $req['requestStatus'];
        $displayStatus = htmlspecialchars($rawStatus, ENT_QUOTES);

        // Determine dynamic status colors (Green for Accepted, Red for Rejected, Blue for Pending)
        $statusColor = '#2b6cb0'; // Default Blue
        if ($rawStatus === 'Accepted') {
            $statusColor = '#38a169'; // Green
        } elseif ($rawStatus === 'Rejected') {
            $statusColor = '#e53e3e'; // Red
        }

        // Conditionally show buttons only if status is Pending
        $actionButtonsHTML = '';
        if ($rawStatus === 'Pending') {
            $actionButtonsHTML = '
                <td class="accept_or_reject">
                    <a class="accept_btn" href="Volunteer Support Session Requests.php?action=accept&requestID=' . $reqID . '" onclick="return confirm(\'Are you sure you want to accept this session?\');">Accept</a>
                    <a class="reject_btn" href="Volunteer Support Session Requests.php?action=reject&requestID=' . $reqID . '" onclick="return confirm(\'Are you sure you want to reject this session?\');">Reject</a>
                </td>';
        } else {
            $actionButtonsHTML = '
                <td style="text-align: center; font-style: italic; color: ' . $statusColor . '; font-weight: bold;">
                    Response Recorded: ' . $displayStatus . '
                </td>';
        }

        $assignedRequestsHTML .= '          
             <table class="support_session_table">
                <tbody>
                    <tr><th>Support Topic</th><td>' . $skillName . '</td></tr>
                    <tr><th>Student Name</th><td>' . $studentName . '</td></tr>
                    <tr><th>Student Email</th><td>' . $studentEmail . '</td></tr>
                    <tr><th>Phone No</th><td>' . $studentPhone . '</td></tr>
                    <tr><th>Support Mode</th><td>' . $supportMode . '</td></tr>
                    <tr><th>Date Requested</th><td>' . $createdAt . '</td></tr>
                    <tr><th>Status</th><td class="support_session_Status" style="color: ' . $statusColor . '; font-weight: bold;">' . $displayStatus . '</td></tr>
                    <tr>
                        ' . $actionButtonsHTML . '
                    </tr>
                </tbody>
            </table>';
    }
} else {
    $assignedRequestsHTML = '<p style="text-align: center; color: #718096; padding-top: 20px;">No current support sessions assigned.</p>';
}

$stmt->close();

$htmlTemplate = file_get_contents('Volunteer Support Session Requests.html');
$htmlTemplate = str_replace('{{assignedRequestsHTML}}', $assignedRequestsHTML, $htmlTemplate);

echo $htmlTemplate;
$conn->close();
?>