<?php
session_start();
require_once 'db.php';

// Ensure student is logged in
if (!isset($_SESSION['studentID']) || $_SESSION['role'] !== 'student') {
    header("Location: Login.html");
    exit;
}

$id = $_SESSION['studentID'];
$showPopup = '';
$findVolunteerLink = 'Find Volunteer.php'; // Default fallback

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skillID = trim($_POST['skill'] ?? '');
    $prefDate = trim($_POST['preferred_date'] ?? '');
    $prefTime = trim($_POST['preferred_time'] ?? '');
    $supportMode = trim($_POST['support_mode'] ?? 'Online');
    
    // Generate unique ID for your VARCHAR primary key
    $requestID = 'REQ-' . uniqid(); 
    $requestDesc = "Preferred Date: $prefDate, Time: $prefTime";
    $requestStatus = 'Pending';
    $createdAt = date('Y-m-d H:i:s');

    if (!empty($skillID) && !empty($prefDate) && !empty($prefTime)) {
        // Insert support request (leave volunteerID NULL initially)
        $stmt = $conn->prepare("INSERT INTO SUPPORTREQUEST (requestID, studentID, skillID, requestDesc, requestStatus, supportMode, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("ssissss", $requestID, $id, $skillID, $requestDesc, $requestStatus, $supportMode, $createdAt);
            
            if ($stmt->execute()) {
                $stmt->close();
                // Fetch the corresponding skillName for this skillID so Find Volunteer can read it
                $skillNameQuery = $conn->prepare("SELECT skillName FROM SKILLS WHERE skillID = ?");
                $skillNameQuery->bind_param("i", $skillID);
                $skillNameQuery->execute();
                $skillNameRes = $skillNameQuery->get_result();
                $skillNameRow = $skillNameRes->fetch_assoc();
                $skillNameQuery->close();

                $selectedSkillName = $skillNameRow['skillName'] ?? '';

                $showPopup = 'show'; // Triggers popup CSS class in template
                
                // Pass BOTH the skill name and requestID to the Find Volunteer page
                $findVolunteerLink = 'Find Volunteer.php?skill=' . urlencode($selectedSkillName) . '&requestID=' . urlencode($requestID);
            } else {
                echo "Database Insert Error: " . htmlspecialchars($stmt->error);
                exit;
            }
        } else {
            echo "Prepare Error: " . htmlspecialchars($conn->error);
            exit;
        }
    } else {
        echo "Validation Error: Please fill in all fields.";
        exit;
    }
}

// Fetch skills from database for dropdown options
$skillOptions = '';
$skillResult = $conn->query("SELECT skillID, skillName FROM SKILLS");
if ($skillResult && $skillResult->num_rows > 0) {
    while ($skill = $skillResult->fetch_assoc()) {
        $skillOptions .= '<option value="' . htmlspecialchars($skill['skillID']) . '">' . htmlspecialchars($skill['skillName']) . '</option>';
    }
} else {
    $skillOptions = '<option value="" disabled>No skills available</option>';
}

// Load HTML template and inject dynamic data
$htmlFile = 'Support Request.html';
if (file_exists($htmlFile)) {
    $html = file_get_contents($htmlFile);
    
    $placeholders = [
        '{{skillOptions}}'      => $skillOptions,
        '{{popupClass}}'        => $showPopup,
        '{{findVolunteerLink}}' => $findVolunteerLink
    ];

    echo str_replace(array_keys($placeholders), array_values($placeholders), $html);
} else {
    echo "Error: Template file '$htmlFile' not found.";
}
exit;
?>