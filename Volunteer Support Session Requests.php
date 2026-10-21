<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// Ensure volunteer is logged in
if (!isset($_SESSION['studentID'])) {
    header("Location: Login Page.html");
    exit;
}

$volunteerID = $_SESSION['studentID'];

// Handle Reject or Accept actions
if (isset($_GET['action']) && isset($_GET['requestID'])) {
    $action = $_GET['action'];
    $requestID = $_GET['requestID'];

    if ($action === 'accept') {
        // Confirm the session
        $updateStmt = $conn->prepare("UPDATE SUPPORTREQUEST SET requestStatus = 'Confirmed' WHERE requestID = ?");
        $updateStmt->bind_param("s", $requestID);
        $updateStmt->execute();
        $updateStmt->close();
    } elseif ($action === 'reject') {
        // INSTANT REMOVAL: Strip out the volunteer tag and reset status to Pending
        $updateStmt = $conn->prepare("UPDATE SUPPORTREQUEST SET requestDesc = REPLACE(requestDesc, CONCAT(' | AssignedVolunteer: ', ?), ''), requestStatus = 'Pending' WHERE requestID = ?");
        $updateStmt->bind_param("ss", $volunteerID, $requestID);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Redirect to refresh and instantly clear from list
    header("Location: volunteer current support status.php");
    exit;
}

// Fetch sessions assigned to this volunteer
$sql = "SELECT sr.requestID, sr.requestDesc, sr.created_at, sr.requestStatus, sr.supportMode, 
               s.skillName, st.Name AS studentName, st.Email AS studentEmail, st.phoneNo AS studentPhone
        FROM SUPPORTREQUEST sr
        JOIN SKILLS s ON sr.skillID = s.skillID
        JOIN STUDENT st ON sr.studentID = st.studentID
        WHERE sr.requestDesc LIKE ?
        ORDER BY sr.created_at DESC";

$stmt = $conn->prepare($sql);
$searchTerm = '%AssignedVolunteer: ' . $volunteerID . '%';
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
        $status = htmlspecialchars($req['requestStatus'], ENT_QUOTES);

        $assignedRequestsHTML .= '          
             <table class="support_session_table">
                <tbody>
                    <tr>
                        <th>Support Topic</th>
                        <td>' . $skillName . '</td>
                    </tr>
                    <tr>
                        <th>Student Name</th>
                        <td>' . $studentName . '</td>
                    </tr>
                    <tr>
                        <th>Student Email</th>
                        <td>' . $studentEmail . '</td>
                    </tr>
                    <tr>
                        <th>Phone No</th>
                        <td>' . $studentPhone . '</td>
                    </tr>
                    <tr>
                        <th>Support Mode</th>
                        <td>' . $supportMode . '</td>
                    </tr>
                    <tr>
                        <th>Date Requested</th>
                        <td>' . $createdAt . '</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td class="support_session_Status">' . $status . '</td>
                    </tr>
                    <tr>
                        <td class="accept_or_reject">
                        <a class="accept_btn" href="volunteer current support status.php?action=accept&requestID=' . $reqID . '">
                        Accept
                        </a>

                        <a class="reject_btn" href="volunteer current support status.php?action=reject&requestID=' . $reqID . '" 
                        onclick="return confirm(\'Are you sure you want to reject this session?\');">
                        Reject
                        </a>
                        </td>
                    </tr>
                </tbody>
            </table>';
    }
} else {
    $assignedRequestsHTML = '<p style="text-align: center; color: #718096; padding-top: 20px;">No current support sessions assigned.</p>';
}

$stmt->close();

// Fixed to load the exact template file name matching your naming convention
$htmlTemplate = file_get_contents('volunteer Support Session Requests.html');
$htmlTemplate = str_replace('{{assignedRequestsHTML}}', $assignedRequestsHTML, $htmlTemplate);

echo $htmlTemplate;
$conn->close();
?>