<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['studentID'])) {
    header("Location: LoginPage.html");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestID = $_POST['requestID'] ?? '';
    $sessionDate = trim($_POST['session_date'] ?? '');
    $sessionTime = trim($_POST['session_time'] ?? '');
    $supportMode = trim($_POST['support_mode'] ?? '');

    if (empty($requestID) || empty($sessionDate) || empty($sessionTime) || empty($supportMode)) {
        $message = "Please fill in all fields.";
        $messageType = "error";
    } else {
        $checkStmt = $conn->prepare("SELECT requestID, requestStatus, requestDesc, studentID FROM SUPPORTREQUEST WHERE requestID = ?");
        $checkStmt->bind_param("s", $requestID);
        $checkStmt->execute();
        $reqResult = $checkStmt->get_result();
        $request = $reqResult->fetch_assoc();
        $checkStmt->close();

        if (!$request) {
            $message = "Request not found.";
            $messageType = "error";
        } elseif ($request['requestStatus'] !== 'Accepted' && $request['requestStatus'] !== 'Confirmed') {
            $message = "This request has not been accepted yet.";
            $messageType = "error";
        } elseif (strtotime($sessionDate . ' ' . $sessionTime) <= time()) {
            $message = "Please select a future date and time.";
            $messageType = "error";
        } else {
            $slotCheck = $conn->prepare("SELECT sessionID FROM SESSION WHERE requestID = ? AND date = ?");
            $dateTime = $sessionDate . ' ' . $sessionTime . ':00';
            $slotCheck->bind_param("ss", $requestID, $dateTime);
            $slotCheck->execute();
            $slotResult = $slotCheck->get_result();
            $slotTaken = $slotResult->num_rows > 0;
            $slotCheck->close();

            if ($slotTaken) {
                $message = "This slot is already taken. Please choose another time.";
                $messageType = "error";
            } else {
                $sessionID = 'SES-' . uniqid();
                $insertStmt = $conn->prepare("INSERT INTO SESSION (sessionID, requestID, date, status, studentID) VALUES (?, ?, ?, 'Scheduled', ?)");
                $insertStmt->bind_param("ssss", $sessionID, $requestID, $dateTime, $request['studentID']);
                
                if ($insertStmt->execute()) {
                    $insertStmt->close();
                    $updateStmt = $conn->prepare("UPDATE SUPPORTREQUEST SET requestStatus = 'Scheduled' WHERE requestID = ?");
                    $updateStmt->bind_param("s", $requestID);
                    $updateStmt->execute();
                    $updateStmt->close();

                    $message = "Session scheduled successfully for " . htmlspecialchars($sessionDate . ' ' . $sessionTime) . " (" . htmlspecialchars($supportMode) . ").";
                    $messageType = "success";
                } else {
                    $insertStmt->close();
                    $message = "Failed to schedule session. Please try again.";
                    $messageType = "error";
                }
            }
        }
    }
}
// Initialize variables
$requestInfo = null;
$volunteerInfo = null;

// Fetch request details if requestID is provided
if (!empty($requestID)) {
    $reqStmt = $conn->prepare("SELECT sr.requestID, sr.requestDesc, sr.requestStatus, sr.supportMode, s.Name AS studentName, s.Email AS studentEmail, sk.skillName FROM SUPPORTREQUEST sr JOIN STUDENT s ON sr.studentID = s.studentID JOIN SKILLS sk ON sr.skillID = sk.skillID WHERE sr.requestID = ?");
    $reqStmt->bind_param("s", $requestID);
    $reqStmt->execute();
    $reqResult = $reqStmt->get_result();
    $requestInfo = $reqResult->fetch_assoc();
    $reqStmt->close();

    // Extract volunteer ID from requestDesc and fetch volunteer info
    if ($requestInfo && preg_match('/AssignedVolunteer:\s*([^\s|]+)/i', $requestInfo['requestDesc'], $m)) {
        $volID = trim($m[1]);
        $volStmt = $conn->prepare("SELECT Name, Email, phoneNo, Availability FROM STUDENT WHERE studentID = ?");
        $volStmt->bind_param("s", $volID);
        $volStmt->execute();
        $volResult = $volStmt->get_result();
        $volunteerInfo = $volResult->fetch_assoc();
        $volStmt->close();
    }
}

// Check if a session already exists for this request
$existingSession = null;
if (!empty($requestID)) {
    $sesStmt = $conn->prepare("SELECT sessionID, date, status FROM SESSION WHERE requestID = ? ORDER BY date DESC LIMIT 1");
    $sesStmt->bind_param("s", $requestID);
    $sesStmt->execute();
    $sesResult = $sesStmt->get_result();
    $existingSession = $sesResult->fetch_assoc();
    $sesStmt->close();
}

// Set back link based on user role
$backLink = 'Student Dashboard.php';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'volunteer') {
    $backLink = 'Volunteer Support Session Requests.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Schedule Session</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Schedule Session</h2>

    <!-- Show success/error message -->
    <?php if ($message): ?>
        <p style="text-align:center; font-weight:bold; padding:10px; border-radius:5px; background:<?php echo $messageType === 'success' ? '#e8f5e9' : '#fdeede'; ?>; color:<?php echo $messageType === 'success' ? 'green' : 'red'; ?>;">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <!-- Show error if no valid request -->
    <?php if (!$requestInfo): ?>
        <p style="text-align:center; color:#e53e3e;">No valid request specified. Please select a request first.</p>
        <a href="<?php echo htmlspecialchars($backLink); ?>" class="btn-secondary">Back</a>
    <?php else: ?>

        <!-- Request details (read-only) -->
        <p style="font-weight:bold; margin-bottom:8px;">Request Details</p>
        <label>Student:</label>
        <input type="text" value="<?php echo htmlspecialchars($requestInfo['studentName']); ?>" readonly>

        <label>Volunteer:</label>
        <input type="text" value="<?php echo $volunteerInfo ? htmlspecialchars($volunteerInfo['Name']) : 'Not Assigned'; ?>" readonly>

        <label>Skill / Topic:</label>
        <input type="text" value="<?php echo htmlspecialchars($requestInfo['skillName']); ?>" readonly>

        <label>Volunteer Availability:</label>
        <input type="text" value="<?php echo $volunteerInfo ? htmlspecialchars($volunteerInfo['Availability']) : 'N/A'; ?>" readonly>

        <label>Current Status:</label>
        <input type="text" value="<?php echo htmlspecialchars($requestInfo['requestStatus']); ?>" readonly>

        <!-- If session already scheduled, show confirmed details -->
        <?php if ($existingSession && $requestInfo['requestStatus'] === 'Scheduled'): ?>
            <p style="font-weight:bold; margin-top:16px;">Session Confirmed</p>
            <label>Session ID:</label>
            <input type="text" value="<?php echo htmlspecialchars($existingSession['sessionID']); ?>" readonly>
            <label>Date & Time:</label>
            <input type="text" value="<?php echo htmlspecialchars($existingSession['date']); ?>" readonly>
            <label>Support Mode:</label>
            <input type="text" value="<?php echo htmlspecialchars($requestInfo['supportMode']); ?>" readonly>
            <a href="<?php echo htmlspecialchars($backLink); ?>" class="btn-secondary">Back to Dashboard</a>
        <?php else: ?>
            <!-- Otherwise show scheduling form -->
            <form method="POST" action="">
                <input type="hidden" name="requestID" value="<?php echo htmlspecialchars($requestID); ?>">

                <label for="session_date">Session Date:</label>
                <input type="date" id="session_date" name="session_date" required>

                <label for="session_time">Session Time:</label>
                <input type="time" id="session_time" name="session_time" required>

                <!-- Support mode buttons -->
                <label>Support Mode:</label>
                <div style="display:flex; gap:16px; margin-bottom:20px;">
                    <label><input type="radio" name="support_mode" value="Online" required> Online</label>
                    <label><input type="radio" name="support_mode" value="In-person"> In-person</label>
                    <label><input type="radio" name="support_mode" value="Both"> Both</label>
                </div>

                <button type="submit" class="btn-primary">Confirm Session</button>
                <a href="<?php echo htmlspecialchars($backLink); ?>" class="btn-secondary">Back</a>
            </form>
        <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>