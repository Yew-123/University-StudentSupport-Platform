<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['studentID'])) {
    header("Location: Login.html");
    exit;
}

$id = $_SESSION['studentID'];
$studentName = $_SESSION['name'] ?? 'Student';

//Fetch Request Counts
$pendingCount = $acceptedCount = 0;

if ($stmt = $conn->prepare("SELECT COUNT(*) AS p FROM SUPPORTREQUEST WHERE studentID = ? AND requestStatus = 'Pending'")) {
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $pendingCount = $counts['p'] ?? 0;
    $stmt->close();
}

if ($stmt = $conn->prepare("SELECT COUNT(*) AS a FROM SUPPORTREQUEST WHERE studentID = ? AND requestStatus IN ('Accepted', 'Confirmed')")) {
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $acceptedCount = $counts['a'] ?? 0;
    $stmt->close();
}

//Fetch Recent Requests
$requestRows = '';
$requestSql = "SELECT sr.requestDesc, sr.requestStatus, sr.supportMode, sr.created_at, sk.skillName 
               FROM SUPPORTREQUEST sr 
               JOIN SKILLS sk ON sr.skillID = sk.skillID 
               WHERE sr.studentID = ? 
               ORDER BY sr.created_at DESC 
               LIMIT 5";

if ($stmt = $conn->prepare($requestSql)) {
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rawStatus = $row['requestStatus'];
            $displayStatus = htmlspecialchars($rawStatus, ENT_QUOTES);

            // Extract Assistance Description and Preferred Date/Time cleanly
            $assistanceRequired = 'Not Specified';
            $preferredDate = '';
            $preferredTime = '';

            if (!empty($row['requestDesc'])) {
                // Strip off trailing volunteer tag if present first
                $baseDesc = explode(' | AssignedVolunteer:', $row['requestDesc'])[0];

                if (preg_match('/Assistance:\s*(.*?)\s*(?:\||$)/i', $baseDesc, $matches)) {
                    $assistanceRequired = trim($matches[1]);
                } else {
                    $assistanceRequired = $baseDesc;
                }

                if (preg_match('/Preferred Date:\s*([^\s,]+)/i', $baseDesc, $matches)) {
                    $preferredDate = trim($matches[1]);
                }
                if (preg_match('/Time:\s*([^\s|]+)/i', $baseDesc, $matches)) {
                    $preferredTime = trim($matches[1]);
                }
            }

            $prefDateTimeDisplay = ($preferredDate && $preferredTime)
                ? $preferredDate . ' ' . $preferredTime
                : ($preferredDate ?: 'Not Specified');

            // Dynamic color coding based on status
            $statusColor = '#d69e2e'; // Warm Amber for Pending
            if ($rawStatus === 'Accepted' || $rawStatus === 'Confirmed') {
                $statusColor = '#38a169'; // Green for Accepted
            } elseif ($rawStatus === 'Scheduled') {
                $statusColor = '#805ad5'; // Purple for Scheduled
            } elseif ($rawStatus === 'Rejected') {
                $statusColor = '#e53e3e'; // Red for Rejected
            } elseif ($rawStatus === 'Completed') {
                $statusColor = '#0bcce6'; // Cyan for Completed
            }

            $requestRows .= '<tr>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['skillName']) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($assistanceRequired) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['supportMode']) . '</td>'
                . '<td style="padding: 10px; font-weight: bold; color: ' . $statusColor . ';">' . $displayStatus . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($prefDateTimeDisplay) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['created_at']) . '</td>'
                . '</tr>';
        }
    } else {
        $requestRows = '<tr><td colspan="6" style="text-align: center; padding: 15px; color: #718096;">No support requests found.</td></tr>';
    }
    $stmt->close();
}

//Login notification popup (once per login session) if status changed
$studentAlert = '';
if (!isset($_SESSION['student_alert_shown'])) {
    if ($notifStmt = $conn->prepare("SELECT COUNT(*) AS total FROM SUPPORTREQUEST WHERE studentID = ? AND requestStatus IN ('Accepted', 'Rejected', 'Scheduled')")) {
        $notifStmt->bind_param("s", $id);
        $notifStmt->execute();
        $res = $notifStmt->get_result()->fetch_assoc();
        if (($res['total'] ?? 0) > 0) {
            $studentAlert = '
            <div id="studentAlertOverlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000;">
                <div style="background: white; padding: 30px; border-radius: 8px; text-align: center; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <i class="fa-solid fa-circle-check" style="font-size: 40px; color: #38a169; margin-bottom: 15px;"></i>
                    <h2>Request Status Update</h2>
                    <p style="margin: 15px 0; color: #4a5568;">Some of your support requests have been updated (Accepted, Rejected, or Scheduled)!</p>
                    <button type="button" onclick="closeStudentAlert()" style="background: #38a169; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">View Status</button>
                </div>
            </div>
            <script>
                function closeStudentAlert() {
                    document.getElementById("studentAlertOverlay").style.display = "none";
                    window.location.href = "Student Support Status.html";
                }
            </script>';
            $_SESSION['student_alert_shown'] = true;
        }
        $notifStmt->close();
    }
}

//Feedback popup — show once per completed session with no feedback
$feedbackPopup = '';

if (!isset($_SESSION['feedback_prompted'])) {
    $_SESSION['feedback_prompted'] = [];
}

$fbSql = "
    SELECT s.sessionID, s.date
    FROM SESSION s
    JOIN SUPPORTREQUEST sr ON s.requestID = sr.requestID
    LEFT JOIN FEEDBACK f  ON f.sessionID = s.sessionID
    WHERE sr.studentID = ?
      AND s.status = 'Completed'
      AND f.feedbackID IS NULL
    ORDER BY s.date DESC
    LIMIT 1
";
if ($fbStmt = $conn->prepare($fbSql)) {
    $fbStmt->bind_param("s", $id);
    $fbStmt->execute();
    $fbRow = $fbStmt->get_result()->fetch_assoc();
    $fbStmt->close();

    if ($fbRow && !in_array($fbRow['sessionID'], $_SESSION['feedback_prompted'], true)) {
        $feedbackPopup = '
        <div id="autoFeedbackModal" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); display:flex; justify-content:center; align-items:center; z-index:9999;">
            <div style="background:#fff; padding:30px; border-radius:12px; max-width:400px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.2); text-align:center;">
                <i class="fa-solid fa-star" style="color:#f6c343; font-size:40px; margin-bottom:15px;"></i>
                <h2 style="margin-bottom:10px;">Session Completed!</h2>
                <p style="color:#555; margin-bottom:20px;">Your volunteer has marked the session as completed. Please take a moment to rate your experience.</p>
                <a href="FeedbackPage.php?session_id=' . urlencode($fbRow['sessionID']) . '" style="display:block; background:#2F6FB2; color:#fff; padding:12px; border-radius:8px; text-decoration:none; margin-bottom:10px;">Leave Feedback Now</a>
                <button type="button" onclick="document.getElementById(\'autoFeedbackModal\').style.display=\'none\';" style="width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; background:#fff; cursor:pointer;">Later</button>
            </div>
        </div>';
        $_SESSION['feedback_prompted'][] = $fbRow['sessionID'];
    }
}

$htmlFile = 'Student Dashboard.html';
if (file_exists($htmlFile)) {
    $html = file_get_contents($htmlFile);

    $placeholders = [
        '{{studentName}}'    => htmlspecialchars($studentName),
        '{{pendingCount}}'   => $pendingCount,
        '{{acceptedCount}}'  => $acceptedCount,
        '{{requestRows}}'    => $requestRows,
        '{{studentAlert}}'   => $studentAlert,
        '{{feedbackPopup}}'  => $feedbackPopup
    ];

    echo str_replace(array_keys($placeholders), array_values($placeholders), $html);
} else {
    echo "Error: Template file '$htmlFile' not found.";
}
exit;
?>