<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['studentID'])) {
    header("Location: Login.html");
    exit;
}

$id = $_SESSION['studentID'];
$studentName = $_SESSION['name'] ?? 'Student';

// 1. Fetch Request Counts
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

// 2. Fetch Recent Requests
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

            $prefDateTimeDisplay = ($preferredDate && $preferredTime) ? $preferredDate . ' ' . $preferredTime : ($preferredDate ?: 'Not Specified');

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

// 3. Load HTML template and inject variables
$htmlFile = 'Student Dashboard.html';
if (file_exists($htmlFile)) {
    $html = file_get_contents($htmlFile);

    // Feedback form popup
    $feedbackPopup = '';
    if (!empty($_SESSION['pending_feedback_session'])) {
        $fbSession = htmlspecialchars($_SESSION['pending_feedback_session']);
        $feedbackPopup = '
        <div id="autoFeedbackModal" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); display:flex; justify-content:center; align-items:center; z-index:9999;">
            <div style="background:#fff; padding:30px; border-radius:12px; max-width:400px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.2); text-align:center;">
                <i class="fa-solid fa-star" style="color:#f6c343; font-size:40px; margin-bottom:15px;"></i>
                <h2 style="margin-bottom:10px;">Session Completed!</h2>
                <p style="color:#555; margin-bottom:20px;">Your volunteer has marked the session as completed. Please take a moment to rate your experience.</p>
                <a href="FeedbackPage.php?session_id=' . urlencode($fbSession) . '" style="display:block; background:#2F6FB2; color:#fff; padding:12px; border-radius:8px; text-decoration:none; margin-bottom:10px;">Leave Feedback Now</a>
                <button type="button" onclick="document.getElementById(\'autoFeedbackModal\').style.display=\'none\';" style="width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; background:#fff; cursor:pointer;">Later</button>
            </div>
        </div>';
        // Shows once per login
        unset($_SESSION['pending_feedback_session']);
        unset($_SESSION['pending_feedback_count']);
    }

    $placeholders = [
        '{{studentName}}'   => htmlspecialchars($studentName),
        '{{pendingCount}}'  => $pendingCount,
        '{{acceptedCount}}' => $acceptedCount,
        '{{requestRows}}'   => $requestRows,
        '{{feedbackPopup}}' => $feedbackPopup
    ];

    echo str_replace(array_keys($placeholders), array_values($placeholders), $html);
} else {
    echo "Error: Template file '$htmlFile' not found.";
}
exit;
?>