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

$studentID =$_SESSION['studentID'];
$successMessage = '';$errorMessage = '';

// 1. Handle Form Submission (Update Profile & Skills)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $availability = trim($_POST['availability'] ?? '');
    $supportMode = trim($_POST['support_mode'] ?? '');
    $selectedSkills =$_POST['skills'] ?? []; // Array of skillIDs

    if (!empty($availability) && !empty($supportMode)) {
        // Update STUDENT table availability and supportMode
        $updateStmt =$conn->prepare("UPDATE STUDENT SET Availability = ?, supportMode = ? WHERE studentID = ?");
        $updateStmt->bind_param("sss", $availability, $supportMode,$studentID);
        
        if ($updateStmt->execute()) {$updateStmt->close();

            // Refresh user skills in VOLUNTEERSKILL table:
            $delStmt =$conn->prepare("DELETE FROM VOLUNTEERSKILL WHERE studentID = ?");
            $delStmt->bind_param("s", $studentID);
            $delStmt->execute();$delStmt->close();

            if (!empty($selectedSkills)) {
                $insStmt =$conn->prepare("INSERT INTO VOLUNTEERSKILL (studentID, skillID) VALUES (?, ?)");
                foreach ($selectedSkills as$skillID) {
                    $insStmt->bind_param("ss", $studentID, $skillID);$insStmt->execute();
                }
                $insStmt->close();
            }

            $successMessage = "Your skill and availability preferences have been updated successfully!";
        } else {
            $errorMessage = "Failed to update profile. Please try again.";
        }
    } else {
        $errorMessage = "Availability and Support Mode cannot be empty.";
    }
}

// 2. Fetch current student details
$studentQuery =$conn->prepare("SELECT Availability, supportMode FROM STUDENT WHERE studentID = ?");
$studentQuery->bind_param("s", $studentID);
$studentQuery->execute();$studentResult = $studentQuery->get_result();$studentData = $studentResult->fetch_assoc();$studentQuery->close();

$currentAvailability =$studentData['Availability'] ?? '';
$currentSupportMode =$studentData['supportMode'] ?? '';

// 3. Fetch skills currently possessed by this user from VOLUNTEERSKILL
$userSkillsQuery =$conn->prepare("SELECT skillID FROM VOLUNTEERSKILL WHERE studentID = ?");
$userSkillsQuery->bind_param("s", $studentID);
$userSkillsQuery->execute();$userSkillsResult = $userSkillsQuery->get_result();$userSkills = [];
while ($row =$userSkillsResult->fetch_assoc()) {
    $userSkills[] =$row['skillID'];
}
$userSkillsQuery->close();

// 4. Fetch all available skills from SKILLS table and build checkboxes HTML
$allSkillsQuery = "SELECT skillID, skillName FROM SKILLS ORDER BY skillName ASC";
$allSkillsResult =$conn->query($allSkillsQuery);$skillCheckboxesHTML = '';

if ($allSkillsResult->num_rows > 0) {
    while ($skill =$allSkillsResult->fetch_assoc()) {
        $isChecked = in_array($skill['skillID'], $userSkills) ? 'checked' : '';$skillCheckboxesHTML .= '
            <div style="margin-bottom: 8px;">
                <input type="checkbox" id="skill_' . $skill['skillID'] . '" name="skills[]" value="' . htmlspecialchars($skill['skillID']) . '" ' . $isChecked . '>
                <label for="skill_' . $skill['skillID'] . '">' . htmlspecialchars($skill['skillName']) . '</label>
            </div>';
    }
} else {
    $skillCheckboxesHTML = '<p style="color: #718096;">No skills available in the database.</p>';
}

// 5. Build message block HTML
$messageBlockHTML = '';
if (!empty($successMessage)) {$messageBlockHTML = '<p style="color: green; background: #e6f4ea; padding: 10px; border-radius: 5px;">' . $successMessage . '</p>';
} elseif (!empty($errorMessage)) {$messageBlockHTML = '<p style="color: red; background: #fdeede; padding: 10px; border-radius: 5px;">' . $errorMessage . '</p>';
}

// 6. Template State Variables
$availabilityDefaultSelected = empty($currentAvailability) ? 'selected' : '';
$availabilityWeekdays = ($currentAvailability === 'weekdays') ? 'selected' : '';
$availabilityWeekends = ($currentAvailability === 'weekends') ? 'selected' : '';
$availabilityFlexible = ($currentAvailability === 'flexible') ? 'selected' : '';

$onlineChecked = ($currentSupportMode === 'Online') ? 'checked' : '';
$inPersonChecked = ($currentSupportMode === 'In-person') ? 'checked' : '';
$bothChecked = ($currentSupportMode === 'Both') ? 'checked' : '';

// 7. Load and render the HTML template
$htmlTemplate = file_get_contents('Volunteer Skill & Availability.html');

$htmlTemplate = str_replace('{{messageBlock}}', $messageBlockHTML,$htmlTemplate);
$htmlTemplate = str_replace('{{availabilityDefaultSelected}}',$availabilityDefaultSelected, $htmlTemplate);$htmlTemplate = str_replace('{{availabilityWeekdays}}', $availabilityWeekdays,$htmlTemplate);
$htmlTemplate = str_replace('{{availabilityWeekends}}',$availabilityWeekends, $htmlTemplate);$htmlTemplate = str_replace('{{availabilityFlexible}}', $availabilityFlexible,$htmlTemplate);
$htmlTemplate = str_replace('{{onlineChecked}}',$onlineChecked, $htmlTemplate);$htmlTemplate = str_replace('{{inPersonChecked}}', $inPersonChecked,$htmlTemplate);
$htmlTemplate = str_replace('{{bothChecked}}',$bothChecked, $htmlTemplate);$htmlTemplate = str_replace('{{skillCheckboxes}}', $skillCheckboxesHTML,$htmlTemplate);

echo $htmlTemplate;
$conn->close();
?>