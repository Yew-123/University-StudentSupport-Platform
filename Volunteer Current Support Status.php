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

// Fetch requests where this volunteer's ID is stored in the requestDesc
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
        $skillName = htmlspecialchars($req['skillName'], ENT_QUOTES);
        $studentName = htmlspecialchars($req['studentName'], ENT_QUOTES);
        $studentEmail = htmlspecialchars($req['studentEmail'], ENT_QUOTES);
        $studentPhone = htmlspecialchars($req['studentPhone'], ENT_QUOTES);
        $supportMode = htmlspecialchars($req['supportMode'], ENT_QUOTES);
        $createdAt = htmlspecialchars($req['created_at'], ENT_QUOTES);
        $status = htmlspecialchars($req['requestStatus'], ENT_QUOTES);

        // Render each session as a vertical table with headers on the left and data on the right
        $assignedRequestsHTML .= '
            <table class="support_status_table">
                <tbody>
                    <tr>
                        <th>Support Topic</th>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #edf2f7; color: #2d3748; font-weight: 500;">' . $skillName . '</td>
                    </tr>
                    <tr>
                        <th>Student Name</th>
                        <td>' . $studentName . '</td>
                    </tr>
                    <tr>
                        <th>Student Email</th>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #edf2f7; color: #2d3748;">' . $studentEmail . '</td>
                    </tr>
                    <tr>
                        <th>Phone No</th>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #edf2f7; color: #2d3748;">' . $studentPhone . '</td>
                    </tr>
                    <tr>
                        <th>Support Mode</th>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #edf2f7; color: #2d3748;">' . $supportMode . '</td>
                    </tr>
                    <tr>
                        <th>Date Requested</th>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #edf2f7; color: #2d3748;">' . $createdAt . '</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td class="support_status_Status">' . $status . '</td>
                    </tr>
                </tbody>
            </table>';
    }
} else {
    $assignedRequestsHTML = '<p style="text-align: center; color: #718096; padding-top: 20px;">You have not accepted any support requests yet.</p>';
}

$stmt->close();

// Load and render template safely
$htmlTemplate = file_get_contents('Volunteer Current Support Status.html');
$htmlTemplate = str_replace('{{assignedRequestsHTML}}', $assignedRequestsHTML, $htmlTemplate);

echo $htmlTemplate;
$conn->close();
?>