<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['studentID'])) {
    header("Location: Login Page.html");
    exit;
}

$studentID = $_SESSION['studentID'];
$sessionID = $_GET['session_id'] ?? $_POST['session_id'] ?? '';
$message = '';
$messageType = '';

$sessionInfo = null;

if (!empty($sessionID)) {
    // NOTE: no FEEDBACK join here — we derive volunteerID from requestDesc
    $stmt = $conn->prepare("
        SELECT s.sessionID, s.status AS sessionStatus, s.requestID,
               sr.requestDesc,
               st.Name AS studentName,
               vol.studentID AS volunteerID, vol.Name AS volunteerName
        FROM SESSION s
        JOIN SUPPORTREQUEST sr ON s.requestID = sr.requestID
        JOIN STUDENT st ON s.studentID = st.studentID
        LEFT JOIN STUDENT vol ON (
            vol.studentID = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sr.requestDesc, 'AssignedVolunteer:', -1), '|', 1))
        )
        WHERE s.sessionID = ? AND s.studentID = ?
    ");
    if (!$stmt) die("SQL error: " . $conn->error);
    $stmt->bind_param("ss", $sessionID, $studentID);
    $stmt->execute();
    $res = $stmt->get_result();
    $sessionInfo = $res->fetch_assoc();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rating = $_POST['rating'] ?? '';
    $comments = trim($_POST['comments'] ?? '');

    if (!$sessionInfo) {
        $message = "Invalid session.";
        $messageType = 'error';
    } elseif ($sessionInfo['sessionStatus'] !== 'Completed') {
        $message = "Feedback can only be submitted for completed sessions.";
        $messageType = 'error';
    } elseif (!is_numeric($rating) || $rating < 1 || $rating > 5) {
        $message = "Please provide a valid rating between 1 and 5.";
        $messageType = 'error';
    } else {
        // Duplicate check — FEEDBACK has no studentID column, so we rely on
        // the fact that only this student's session can produce this sessionID.
        // (sessionID FK on FEEDBACK already ties it to one row per session.)
        $dupCheck = $conn->prepare("SELECT feedbackID FROM FEEDBACK WHERE sessionID = ?");
        $dupCheck->bind_param("s", $sessionID);
        $dupCheck->execute();
        $dupCheck->store_result();
        $already = $dupCheck->num_rows > 0;
        $dupCheck->close();

        if ($already) {
            $message = "You have already submitted feedback for this session.";
            $messageType = 'error';
        } else {
            $feedbackID = 'FB-' . uniqid();
            // Only the columns that actually exist: feedbackID, sessionID, rating, comment
            $insertStmt = $conn->prepare("INSERT INTO FEEDBACK (feedbackID, sessionID, rating, comment) VALUES (?, ?, ?, ?)");
            if (!$insertStmt) {
                $message = "Insert prepare failed: " . $conn->error;
                $messageType = 'error';
            } else {
                $insertStmt->bind_param("ssis", $feedbackID, $sessionID, $rating, $comments);
                if ($insertStmt->execute()) {
                    $message = "Thank you! Your rating of $rating stars has been saved.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to save feedback. Please try again.";
                    $messageType = 'error';
                }
                $insertStmt->close();
            }
        }
    }
}
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Support Session Feedback</title>
    <link rel="stylesheet" href="style.css">
    <style>
        h2 { margin-bottom: 20px; }
        .question-text { font-size: 16px; margin-bottom: 10px; color: #333; }
    </style>
</head>
<body>
<div class="container">
    <h2>Support Session Feedback</h2>

    <?php if($message): ?>
        <p style="text-align:center; color: <?php echo $messageType === 'success' ? 'green' : 'red'; ?>; font-weight: bold; background: #f0f0f0; padding: 10px; border-radius: 5px;">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <?php if (!$sessionInfo): ?>
        <p style="text-align:center; color:#e53e3e;">Session not found or inaccessible.</p>
        <a href="Student Dashboard.php" class="btn-secondary">Back to Dashboard</a>
    <?php elseif ($sessionInfo['sessionStatus'] !== 'Completed'): ?>
        <p style="text-align:center; color:#d69e2e;">This session is not yet completed. Feedback will be available once the volunteer marks it as completed.</p>
        <a href="Student Dashboard.php" class="btn-secondary">Back to Dashboard</a>
    <?php else: ?>
        <p class="question-text" style="text-align:center;">
            Session: <strong><?php echo htmlspecialchars($sessionInfo['sessionID']); ?></strong><br>
            Volunteer: <strong><?php echo htmlspecialchars($sessionInfo['volunteerName'] ?? 'N/A'); ?></strong>
        </p>

        <form method="POST" action="">
            <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($sessionID); ?>">

            <label for="rating">Rating:</label>
            <input type="number" id="rating" name="rating" min="1" max="5" placeholder="1-5" required>
            <span class="rating-hint">(1 = Poor, 5 = Excellent)</span>

            <label for="comments">Comments (Optional):</label>
            <textarea id="comments" name="comments" placeholder="Tell us more about your session..."></textarea>

            <button type="submit" class="btn-primary">Submit Feedback</button>
            <a href="Student Dashboard.php" class="btn-secondary">Back</a>
        </form>
    <?php endif; ?>
</div>
</body>
</html>