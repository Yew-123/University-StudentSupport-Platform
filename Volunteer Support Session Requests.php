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
    
    } elseif ($action === 'complete') {
        // Session will will be makred as completed, same for the request status
        $updateSessionStmt = $conn->prepare("UPDATE SESSION SET status = 'Completed' WHERE requestID = ?");
        $updateSessionStmt->bind_param("s", $requestID);
        $updateSessionStmt->execute();
        $updateSessionStmt->close();

        $updateReqStmt = $conn->prepare("UPDATE SUPPORTREQUEST SET requestStatus = 'Completed' WHERE requestID = ?");
        $updateReqStmt->bind_param("s", $requestID);
        $updateReqStmt->execute();
        $updateReqStmt->close();
    }

    header("Location: Volunteer Support Session Requests.php");
    exit;
}

// Fetch sessions assigned to this volunteer securely using wildcard matching
$sql = "SELECT sr.requestID, sr.requestDesc, sr.created_at, sr.requestStatus, sr.supportMode, 
               s.skillName, st.Name AS studentName, st.Email AS studentEmail, st.phoneNo AS studentPhone, ses.date AS scheduledDate
        FROM SUPPORTREQUEST sr
        JOIN SKILLS s ON sr.skillID = s.skillID
        JOIN STUDENT st ON sr.studentID = st.studentID
        LEFT JOIN SESSION ses ON sr.requestID = ses.requestID
        WHERE sr.requestDesc LIKE ? AND sr.requestStatus != 'Completed'
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
        $scheduledDateRaw = $req['scheduledDate'] ?? '';
        $scheduledDate = $scheduledDateRaw ? date('Y-m-d H:i', strtotime($scheduledDateRaw)) : 'Not scheduled yet';

        // Extract Assistance Required text from requestDesc safely
        $assistanceRequired = 'Not Specified';
        if (!empty($req['requestDesc'])) {
            if (preg_match('/Assistance:\s*(.*?)\s*\|\s*Preferred Date:/i', $req['requestDesc'], $matches)) {
                $assistanceRequired = trim($matches[1]);
            } else {
                $assistanceRequired = $req['requestDesc'];
            }
        }
        $assistanceDisplay = htmlspecialchars($assistanceRequired, ENT_QUOTES);

        $rawStatus = $req['requestStatus'];
        $displayStatus = htmlspecialchars($rawStatus, ENT_QUOTES);

        // Determine dynamic status colors and background badge styling matching the student view
        $statusColor = '#4a5568'; 
        $statusBg = '#edf2f7';

        if ($rawStatus === 'Accepted' || $rawStatus === 'Confirmed') {
            $statusColor = '#38a169'; // Green
            $statusBg = '#f0fff4';
        } elseif ($rawStatus === 'Scheduled') {
            $statusColor = '#805ad5'; // Purple for Scheduled
            $statusBg = '#f3e8ff';
        } elseif ($rawStatus === 'Rejected') {
            $statusColor = '#e53e3e'; // Red
            $statusBg = '#fff5f5';
         } elseif ($rawStatus === 'Completed') {
            $statusColor = '#0bcce6'; // Cyan for completed, to avoid using same colour
            $statusBg = '#ebf8ff';
        } else {
            $statusColor = '#2b6cb0'; // Default Blue / Pending
            $statusBg = '#ebf8ff';
        }
        // Conditionally show buttons based on status
        $actionButtonsHTML = '';
        if ($rawStatus === 'Pending') {
            $actionButtonsHTML = '
                <td class="accept_or_reject">
                    <a class="accept_btn" href="Volunteer Support Session Requests.php?action=accept&requestID=' . $reqID . '" onclick="return confirm(\'Are you sure you want to accept this session?\');">Accept</a>
                    <a class="reject_btn" href="Volunteer Support Session Requests.php?action=reject&requestID=' . $reqID . '" onclick="return confirm(\'Are you sure you want to reject this session?\');">Reject</a>
                </td>';
        } elseif ($rawStatus === 'Accepted' || $rawStatus === 'Confirmed') {
            $actionButtonsHTML = '
                <td style="text-align: center;">
                    <a href="Schedule Session.php?requestID=' . $reqID . '&from=volunteer" 
                       style="background: #3182ce; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                        <i class="fa-solid fa-calendar-days"></i> Schedule Session
                    </a>
                </td>';
        } elseif ($rawStatus === 'Scheduled') {
            $actionButtonsHTML = '
                <td style="text-align: center;">
                    <a href="Volunteer Support Session Requests.php?action=complete&requestID=' . $reqID . '" 
                       onclick="return confirm(\'Confirmation to mark this session as completed? The student will be notified and able to leave a review.\');"
                       style="background: #38a169; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                        <i class="fa-solid fa-circle-check"></i> Mark Session as Completed
                    </a>
                </td>';
        } else {
            $actionButtonsHTML = '
                <td style="text-align: center; font-style: italic; color: #718096; font-size: 13px;">
                    Session status finalized
                </td>';
        }

        $assignedRequestsHTML .= '          
             <table class="support_session_table">
                <tbody>
                    <tr><th>Support Topic</th><td>' . $skillName . '</td></tr>
                    <tr><th>Assistance Required</th><td>' . $assistanceDisplay . '</td></tr>
                    <tr><th>Student Name</th><td>' . $studentName . '</td></tr>
                    <tr><th>Student Email</th><td>' . $studentEmail . '</td></tr>
                    <tr><th>Phone No</th><td>' . $studentPhone . '</td></tr>
                    <tr><th>Support Mode</th><td>' . $supportMode . '</td></tr>
                    <tr><th>Date Requested</th><td>' . $createdAt . '</td></tr>
                    <tr>
                        <th>Scheduled Date & Time</th>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #edf2f7; color: #2F6FB2; font-weight: bold;">' . $scheduledDate . '</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="status-badge" style="color: ' . $statusColor . '; background-color: ' . $statusBg . '; padding: 4px 8px; border-radius: 4px; font-weight: bold; display: inline-block;">
                                <i class="fa-solid fa-circle-info"></i> ' . $displayStatus . '
                            </span>
                        </td>
                    </tr>
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