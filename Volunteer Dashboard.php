<?php
session_start();
require_once 'db.php';

// Ensure user (volunteer) is logged in via student session
if (!isset($_SESSION['studentID'])) {
    header("Location: Login.html");
    exit;
}

$id = $_SESSION['studentID'];
$volunteerName = $_SESSION['name'] ?? 'Volunteer';
$searchTerm = '%' . $id . '%';

//Fetch Request Counts accurately for Pending and Accepted
$pendingCount = 0;
$acceptedCount = 0;

if ($stmt = $conn->prepare("SELECT COUNT(*) AS p FROM SUPPORTREQUEST WHERE requestDesc LIKE ? AND requestStatus = 'Pending'")) {
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $pendingCount = $counts['p'] ?? 0;
    $stmt->close();
}

if ($stmt = $conn->prepare("SELECT COUNT(*) AS a FROM SUPPORTREQUEST WHERE requestDesc LIKE ? AND (requestStatus = 'Accepted' OR requestStatus = 'Confirmed')")) {
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $acceptedCount = $counts['a'] ?? 0;
    $stmt->close();
}

//Fetch Recent Assigned Sessions for the Dashboard Table
$requestRows = '';
$sql = "SELECT sr.requestDesc, sr.requestStatus, sr.supportMode, sr.created_at, sk.skillName, st.Name AS studentName 
        FROM SUPPORTREQUEST sr 
        JOIN SKILLS sk ON sr.skillID = sk.skillID 
        JOIN STUDENT st ON sr.studentID = st.studentID 
        WHERE sr.requestDesc LIKE ? 
        ORDER BY sr.created_at DESC 
        LIMIT 5";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rawStatus = $row['requestStatus'];
            $status = htmlspecialchars($rawStatus, ENT_QUOTES);
            
            // Extract Assistance Description and Preferred Date/Time from requestDesc
            $assistanceRequired = 'Not Specified';
            $preferredDate = '';
            $preferredTime = '';

            if (!empty($row['requestDesc'])) {
                if (preg_match('/Assistance:\s*(.*?)\s*(?:\||$)/i', $row['requestDesc'], $matches)) {
                    $assistanceRequired = trim($matches[1]);
                } else {
                    $assistanceRequired = $row['requestDesc'];
                }

                if (preg_match('/Preferred Date:\s*([^\s,]+)/i', $row['requestDesc'], $matches)) {
                    $preferredDate = trim($matches[1]);
                }
                if (preg_match('/Time:\s*([^\s|]+)/i', $row['requestDesc'], $matches)) {
                    $preferredTime = trim($matches[1]);
                }
            }

            // Combine preferred date and time cleanly
            $prefDateTimeDisplay = ($preferredDate && $preferredTime) ? $preferredDate . ' ' . $preferredTime : ($preferredDate ?: 'Not Specified');
            
            // Dynamic color coding matching your session and status views
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
                . '<td style="padding: 10px; font-weight: bold; color: ' . $statusColor . ';">' . $status . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($prefDateTimeDisplay) . '</td>'
                . '<td style="padding: 10px;">' . htmlspecialchars($row['created_at']) . '</td>'
                . '</tr>';
        }
    } else {
        $requestRows = '<tr><td colspan="6" style="text-align: center; padding: 15px; color: #718096;">No support sessions found.</td></tr>';
    }
    $stmt->close();
}

$volunteerAlert = '';
if (!isset($_SESSION['volunteer_alert_shown'])) {
    if ($notifStmt = $conn->prepare("SELECT COUNT(*) AS total FROM SUPPORTREQUEST WHERE requestDesc LIKE ? AND requestStatus = 'Pending'")) {
        $notifStmt->bind_param("s", $searchTerm);
        $notifStmt->execute();
        $res = $notifStmt->get_result()->fetch_assoc();
        if (($res['total'] ?? 0) > 0) {
            $volunteerAlert = '
            <div id="loginAlertOverlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000;">
                <div style="background: white; padding: 30px; border-radius: 8px; text-align: center; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <i class="fa-solid fa-bell" style="font-size: 40px; color: #d69e2e; margin-bottom: 15px;"></i>
                    <h2>New Support Request!</h2>
                    <p style="margin: 15px 0; color: #4a5568;">You have new support requests waiting to be accepted or rejected.</p>
                    <button type="button" onclick="closeLoginAlert()" style="background: #3182ce; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">View Requests</button>
                </div>
            </div>
            <script>
                function closeLoginAlert() {
                    document.getElementById("loginAlertOverlay").style.display = "none";
                    window.location.href = "Volunteer Support Session Requests.php";
                }
            </script>';
            $_SESSION['volunteer_alert_shown'] = true;
        }
        $notifStmt->close();
    }
}

//Load Volunteer HTML template and inject variables
$htmlFile = 'Volunteer Dashboard.html';
if (file_exists($htmlFile)) {
    $html = file_get_contents($htmlFile);
    
    $placeholders = [
        '{{studentName}}'   => htmlspecialchars($volunteerName),
        '{{pendingCount}}'  => $pendingCount,
        '{{acceptedCount}}' => $acceptedCount,
        '{{requestRows}}'   => $requestRows,
        '{{volunteerAlert}}' => $volunteerAlert
    ];
    
    echo str_replace(array_keys($placeholders), array_values($placeholders), $html);
} else {
    echo "Error: Template file '$htmlFile' not found.";
}
exit;
?>