<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if (!isset($_SESSION['studentID'])) {
    header("Location: Login Page.html");
    exit;
}

// 1. HANDLE DIRECT ASSIGNMENT (If 'assignID' and 'requestID' are passed)
if (isset($_GET['assignID']) && isset($_GET['requestID'])) {
    $volunteerID = $_GET['assignID'];
    $requestID = $_GET['requestID'];

    $fetchStmt = $conn->prepare("SELECT requestDesc FROM SUPPORTREQUEST WHERE requestID = ?");
    $fetchStmt->bind_param("s", $requestID);
    $fetchStmt->execute();
    $res = $fetchStmt->get_result();
    $currentDesc = '';
    if ($row = $res->fetch_assoc()) {
        $currentDesc = $row['requestDesc'] ?? '';
    }
    $fetchStmt->close();

    // Remove any existing AssignedVolunteer tag to prevent duplicates
    if (strpos($currentDesc, 'AssignedVolunteer:') !== false) {
        $currentDesc = preg_replace('/\|\s*AssignedVolunteer:\s*[^\s|]+/', '', $currentDesc);
        $currentDesc = trim($currentDesc, ' |');
    }

    // Append new volunteer tag cleanly
    $updatedDesc = ($currentDesc !== '') ? $currentDesc . ' | AssignedVolunteer: ' . $volunteerID : 'AssignedVolunteer: ' . $volunteerID;

    // Save back to database and keep status as Pending
    $updateStmt = $conn->prepare("UPDATE SUPPORTREQUEST SET requestDesc = ?, requestStatus = 'Pending' WHERE requestID = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("ss", $updatedDesc, $requestID);
        $updateStmt->execute();
        $updateStmt->close();
    }

    header("Location: Student Support Status.html?status=Pending");
    exit;
}

// 2. Capture target requestID from GET or POST
$targetRequestID = $_GET['requestID'] ?? $_POST['requestID'] ?? '';

$skillsQuery = "SELECT DISTINCT skillName FROM skills ORDER BY skillName ASC";
$skillsResult = $conn->query($skillsQuery);
$skillOptionsHTML = '<option value="">--Select Skill--</option>';
$selectedSkill = $_GET['skill'] ?? $_POST['skill'] ?? '';

while ($skill = $skillsResult->fetch_assoc()) {
    $isSel = ($selectedSkill == $skill['skillName']) ? 'selected' : '';
    $skillOptionsHTML .= '<option value="' . htmlspecialchars($skill['skillName']) . '" ' . $isSel . '>' . htmlspecialchars($skill['skillName']) . '</option>';
}

$selectedAvailability = $_GET['availability'] ?? $_POST['availability'] ?? '';
$selectedSupportMode = $_GET['support_mode'] ?? $_POST['support_mode'] ?? '';
$volunteersHTML = '';

if (isset($_GET['skill']) || isset($_POST['skill']) || isset($_GET['availability']) || isset($_POST['availability'])) {
    
    if (empty($selectedSkill) || empty($selectedAvailability) || empty($selectedSupportMode)) {
        $volunteersHTML = '<p style="text-align: center; color: #e53e3e; padding-top: 12px;">Please fill out all fields before applying filters.</p>';
    } else {
        $sql = "SELECT DISTINCT s.studentID, s.Name, s.Email, s.phoneNo, s.Availability, s.supportMode 
                FROM student s 
                LEFT JOIN VOLUNTEERSKILL vs ON s.studentID = vs.studentID
                LEFT JOIN skills sk ON vs.skillID = sk.skillID
                WHERE sk.skillName = ? AND s.Availability = ? AND (s.supportMode = ? OR s.supportMode = 'Both')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $selectedSkill, $selectedAvailability, $selectedSupportMode);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $volunteersHTML .= '<div class="volunteer-list">';
            while ($vol = $result->fetch_assoc()) {
                $vID = $vol['studentID'];

                $volSkillsStmt = $conn->prepare("SELECT sk.skillName FROM VOLUNTEERSKILL vs JOIN SKILLS sk ON vs.skillID = sk.skillID WHERE vs.studentID = ?");
                $volSkillsStmt->bind_param("s", $vID);
                $volSkillsStmt->execute();
                $volSkillsRes = $volSkillsStmt->get_result();
                $skillNamesArray = [];
                while ($sRow = $volSkillsRes->fetch_assoc()) {
                    $skillNamesArray[] = $sRow['skillName'];
                }
                $volSkillsStmt->close();

                $skillsString = implode(', ', $skillNamesArray);
                $vName = htmlspecialchars($vol['Name'], ENT_QUOTES);
                $vEmail = htmlspecialchars($vol['Email'], ENT_QUOTES);
                $vPhone = htmlspecialchars($vol['phoneNo'], ENT_QUOTES);
                $vAvail = htmlspecialchars($vol['Availability'], ENT_QUOTES);
                $vMode = htmlspecialchars($vol['supportMode'], ENT_QUOTES);
                $vSkills = htmlspecialchars($skillsString, ENT_QUOTES);

                // Pass requestID along with assignID when clicking select
                $reqIdParam = !empty($targetRequestID) ? '&requestID=' . urlencode($targetRequestID) : '';
                $selectAction = "window.location.href='Find Volunteer.php?assignID=" . $vID . $reqIdParam . "'";

                $volunteersHTML .= '
                    <div class="volunteer-card">
                        <h3>' . $vName . '</h3>
                        <p><strong>Skill:</strong> ' . $vSkills . '</p> 
                        <p><strong>Availability:</strong> ' . $vAvail . '</p>
                        <div style="margin-top: 12px;">
                            <button type="button" onclick="viewVolunteer(\'' . $vName . '\', \'' . $vEmail . '\', \'' . $vPhone . '\', \'' . $vAvail . '\', \'' . $vMode . '\', \'' . $vSkills . '\')">View</button>
                            <button type="button" onclick="' . $selectAction . '">Select</button>
                        </div>
                    </div>';
            }
            $volunteersHTML .= '</div>';
        } else {
            $volunteersHTML = '<p style="text-align: center; color: #718096; padding-top: 12px">No matching volunteers found.</p>';
        }
        $stmt->close();
    }
} else {
    $volunteersHTML = '<p style="text-align: center; color: #718096; padding-top: 12px">Please fill out all fields and click "Apply Filters" to find volunteers.</p>';
}

$htmlTemplate = file_get_contents('Find Volunteer.html');
$htmlTemplate = str_replace([
    '{{skillOptions}}', '{{availabilityDefaultSelected}}', '{{availabilityWeekdays}}', 
    '{{availabilityWeekends}}', '{{availabilityFlexible}}', '{{onlineChecked}}', 
    '{{inPersonChecked}}', '{{bothChecked}}', '{{volunteersHTML}}'
], [
    $skillOptionsHTML, empty($selectedAvailability) ? 'selected' : '', 
    ($selectedAvailability === 'weekdays') ? 'selected' : '', 
    ($selectedAvailability === 'weekends') ? 'selected' : '', 
    ($selectedAvailability === 'flexible') ? 'selected' : '', 
    ($selectedSupportMode === 'Online') ? 'checked' : '', 
    ($selectedSupportMode === 'In-person') ? 'checked' : '', 
    ($selectedSupportMode === 'Both') ? 'checked' : '', 
    $volunteersHTML
], $htmlTemplate);

// Permanently bind the requestID into the form action URL so it never gets lost during filtering
$formAction = !empty($targetRequestID) ? 'Find Volunteer.php?requestID=' . urlencode($targetRequestID) : 'Find Volunteer.php';
$htmlTemplate = str_replace('action=""', 'action="' . $formAction . '"', $htmlTemplate);

echo $htmlTemplate;
$conn->close();
?>