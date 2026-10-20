<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['studentID'])) {
    header("Location: Login Page.html");
    exit;
}

// 1. Fetch skill names from the SKILLS table for the dropdown selection
$skillsQuery = "SELECT DISTINCT skillName FROM skills ORDER BY skillName ASC";
$skillsResult = $conn->query($skillsQuery);
$skillOptionsHTML = '<option value="">--Select Skill--</option>';
$selectedSkill = $_GET['skill'] ?? '';

while ($skill = $skillsResult->fetch_assoc()) {
    $isSel = ($selectedSkill == $skill['skillName']) ? 'selected' : '';
    $skillOptionsHTML .= '<option value="' . htmlspecialchars($skill['skillName']) . '" ' . $isSel . '>' . htmlspecialchars($skill['skillName']) . '</option>';
}

// 2. Capture filter parameters
$selectedAvailability = $_GET['availability'] ?? '';
$selectedSupportMode = $_GET['support_mode'] ?? '';

$volunteersHTML = '';

// 3. Check if the "Apply Filters" button was clicked (checking the URL trigger)
if (isset($_GET['skill']) || isset($_GET['availability']) || isset($_GET['support_mode'])) {
    
    // Ensure ALL THREE fields are selected before showing results
    if (empty($selectedSkill) || empty($selectedAvailability) || empty($selectedSupportMode)) {
        $volunteersHTML = '<p style="text-align: center; color: #e53e3e; padding: top 12px;">Please fill out all fields (Skill, Availability, and Support Mode) before applying filters.</p>';
    } else {
        $sql = "SELECT DISTINCT s.studentID, s.Name, s.Email, s.phoneNo, s.Availability, s.supportMode 
                FROM student s 
                LEFT JOIN VOLUNTEERSKILL vs ON s.studentID = vs.studentID
                LEFT JOIN skills sk ON vs.skillID = sk.skillID
                WHERE sk.skillName = ? AND s.Availability = ? AND (s.supportMode = ? OR s.supportMode = 'Both')";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("sss", $selectedSkill, $selectedAvailability, $selectedSupportMode);
        $stmt->execute();
        $result = $stmt->get_result();

        // 4. Generate Volunteers HTML Cards with separate View and Select buttons
        if ($result->num_rows > 0) {
            $volunteersHTML .= '<div class="volunteer-list">';
            while ($vol = $result->fetch_assoc()) {
                $vID = $vol['studentID'];

                // Fetch skills possessed by this specific volunteer
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

                $volunteersHTML .= '
                    <div class="volunteer-card">
                        <h3>' . $vName . '</h3>
                        <p><strong>Skill:</strong>' . $vSkills . '</p> 
                        <p><strong>Availability:</strong> ' . $vAvail . '</p>
                        <div style="margin-top: 12px;">
                            <button type="button" 
                            onclick="viewVolunteer(\'' . $vName . '\', \'' . $vEmail . '\', \'' . $vPhone . '\', \'' . $vAvail . '\', \'' . $vMode . '\', \'' . $vSkills . '\')" >View</button>
                            <button type="button" onclick="selectVolunteer(\'' . $vID . '\')">Select</button>
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
    $volunteersHTML = '<p style="text-align: center; color: #718096;">Please fill out all fields and click "Apply Filters" to find volunteers.</p>';
}

// 5. Handle Form Template Variables State
$availabilityDefaultSelected = empty($selectedAvailability) ? 'selected' : '';
$availabilityWeekdays = ($selectedAvailability === 'weekdays') ? 'selected' : '';
$availabilityWeekends = ($selectedAvailability === 'weekends') ? 'selected' : '';
$availabilityFlexible = ($selectedAvailability === 'flexible') ? 'selected' : '';

$onlineChecked = ($selectedSupportMode === 'Online') ? 'checked' : '';
$inPersonChecked = ($selectedSupportMode === 'In-person') ? 'checked' : '';
$bothChecked = ($selectedSupportMode === 'Both') ? 'checked' : '';

// 6. Load and render the HTML template
$htmlTemplate = file_get_contents('Find Volunteer.html');

$htmlTemplate = str_replace('{{skillOptions}}', $skillOptionsHTML, $htmlTemplate);
$htmlTemplate = str_replace('{{availabilityDefaultSelected}}', $availabilityDefaultSelected, $htmlTemplate);
$htmlTemplate = str_replace('{{availabilityWeekdays}}', $availabilityWeekdays, $htmlTemplate);
$htmlTemplate = str_replace('{{availabilityWeekends}}', $availabilityWeekends, $htmlTemplate);
$htmlTemplate = str_replace('{{availabilityFlexible}}', $availabilityFlexible, $htmlTemplate);
$htmlTemplate = str_replace('{{onlineChecked}}', $onlineChecked, $htmlTemplate);
$htmlTemplate = str_replace('{{inPersonChecked}}', $inPersonChecked, $htmlTemplate);
$htmlTemplate = str_replace('{{bothChecked}}', $bothChecked, $htmlTemplate);
$htmlTemplate = str_replace('{{volunteersHTML}}', $volunteersHTML, $htmlTemplate);

echo $htmlTemplate;
$conn->close();
?>