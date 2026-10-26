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

</div>
</body>
</html>