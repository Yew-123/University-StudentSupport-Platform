<?php
// StudentSessionComp.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['studentID'])) {
    header("Location: Login Page.html");
    exit;
}

$studentID = $_SESSION['studentID'];
$sessionID = $_GET['session_id'] ?? $_POST['session_id'] ?? '';

$sessionDetails = null;
$volunteerName = '';
$feedbackExists = false;
$message = '';

if (!empty($sessionID)) {
    // Fetch session, related request and volunteer info
    $stmt = $conn->prepare("
        SELECT s.sessionID, s.date, s.status AS sessionStatus, s.requestID,
               sr.requestDesc, sr.supportMode, sr.requestStatus,
               sk.skillName,
               st.Name AS studentName,
               vol.studentID AS volunteerID, vol.Name AS volunteerName
        FROM SESSION s
        JOIN SUPPORTREQUEST sr ON s.requestID = sr.requestID
        JOIN SKILLS sk ON sr.skillID = sk.skillID
        JOIN STUDENT st ON s.studentID = st.studentID
        LEFT JOIN STUDENT vol ON (
            vol.studentID = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sr.requestDesc, 'AssignedVolunteer:', -1), '|', 1))
        )
        WHERE s.sessionID = ? AND s.studentID = ?
    ");
    $stmt->bind_param("ss", $sessionID, $studentID);
    $stmt->execute();
    $res = $stmt->get_result();
    $sessionDetails = $res->fetch_assoc();
    $stmt->close();

    if ($sessionDetails) {
        $volunteerName = $sessionDetails['volunteerName'] ?? 'N/A';

        // Extract topic from requestDesc (Assistance portion)
        $topic = 'Not Specified';
        if (!empty($sessionDetails['requestDesc'])) {
            if (preg_match('/Assistance:\s*(.*?)\s*\|\s*Preferred Date:/i', $sessionDetails['requestDesc'], $m)) {
                $topic = trim($m[1]);
            } else {
                $topic = $sessionDetails['requestDesc'];
            }
        }
        $sessionDetails['topic'] = $topic;

        // Check if feedback already exists
        $fbStmt = $conn->prepare("SELECT feedbackID FROM FEEDBACK WHERE sessionID = ?");
        $fbStmt->bind_param("s", $sessionID);
        $fbStmt->execute();
        $fbStmt->store_result();
        $feedbackExists = $fbStmt->num_rows > 0;
        $fbStmt->close();
    }
}

// Set back link
$backLink = 'Student Dashboard.php';

// Parse date/time
$displayDate = '';
$displayTime = '';
if ($sessionDetails && !empty($sessionDetails['date'])) {
    $dt = strtotime($sessionDetails['date']);
    $displayDate = date('Y-m-d', $dt);
    $displayTime = date('H:i', $dt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Session Completed</title>
    <link rel="stylesheet" href="style.css">
    <style>
        input[readonly] { background-color:#fff; color:#333; cursor:default; }
        .success-message {
            text-align:center; color:#34a853; font-weight:600;
            margin-bottom:20px; font-size:16px;
        }
        .error-message {
            text-align:center; color:#e53e3e; font-weight:600;
            margin-bottom:20px; font-size:16px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Session Completed</h2>

    <?php if (!$sessionDetails): ?>
        <p class="error-message">✖ Session not found or you do not have access.</p>
        <a href="<?php echo htmlspecialchars($backLink); ?>" class="btn-secondary">Back to Dashboard</a>
    <?php else: ?>
        <p class="success-message">✔ Thank you for participating!</p>

        <form>
            <label for="sessionID">Session ID:</label>
            <input type="text" id="sessionID" value="<?php echo htmlspecialchars($sessionDetails['sessionID']); ?>" readonly>

            <label for="volunteer">Volunteer:</label>
            <input type="text" id="volunteer" value="<?php echo htmlspecialchars($volunteerName); ?>" readonly>

            <label for="date">Date:</label>
            <input type="date" id="date" value="<?php echo htmlspecialchars($displayDate); ?>" readonly>

            <label for="time">Time:</label>
            <input type="time" id="time" value="<?php echo htmlspecialchars($displayTime); ?>" readonly>

            <label for="topic">Topic:</label>
            <input type="text" id="topic" value="<?php echo htmlspecialchars($sessionDetails['topic']); ?>" readonly>

            <label for="status">Status:</label>
            <input type="text" id="status" value="<?php echo htmlspecialchars($sessionDetails['sessionStatus']); ?>" readonly>

            <?php if ($feedbackExists): ?>
                <p style="text-align:center; color:#38a169; font-weight:600; margin:16px 0;">
                    ✔ You have already submitted feedback for this session.
                </p>
                <a href="<?php echo htmlspecialchars($backLink); ?>" class="btn-secondary">Back to Dashboard</a>
            <?php elseif ($sessionDetails['sessionStatus'] === 'Completed'): ?>
                <a href="FeedbackPage.php?session_id=<?php echo urlencode($sessionDetails['sessionID']); ?>" class="btn-primary" style="text-decoration:none; display:block; text-align:center; box-sizing:border-box;">
                    Leave Feedback
                </a>
                <a href="<?php echo htmlspecialchars($backLink); ?>" class="btn-secondary">Return to Home</a>
            <?php else: ?>
                <p style="text-align:center; color:#d69e2e; font-weight:600; margin:16px 0;">
                    ⏳ This session is not yet marked as completed by the volunteer.
                </p>
                <a href="<?php echo htmlspecialchars($backLink); ?>" class="btn-secondary">Back to Dashboard</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
</body>
</html>