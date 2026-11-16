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

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skillID = trim($_POST['skill'] ?? '');
    $assistance = trim($_POST['assistance'] ?? '');
    $prefDate = trim($_POST['preferred_date'] ?? '');
    $prefTime = trim($_POST['preferred_time'] ?? '');
    $supportMode = trim($_POST['support_mode'] ?? 'Online');
    
    $requestID = 'REQ' . strtoupper(bin2hex(random_bytes(5)));
    // Safely combine description fields while preserving regex compatibility for date/time parsing
    $requestDesc = "Assistance: $assistance | Preferred Date: $prefDate, Time: $prefTime";
    $requestStatus = 'Pending';
    $createdAt = date('Y-m-d H:i:s');

    if (!empty($skillID) && !empty($assistance) && !empty($prefDate) && !empty($prefTime)) {
        $stmt = $conn->prepare("INSERT INTO SUPPORTREQUEST (requestID, studentID, skillID, requestDesc, requestStatus, supportMode, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("ssissss", $requestID, $id, $skillID, $requestDesc, $requestStatus, $supportMode, $createdAt);
            
            if ($stmt->execute()) {
                $stmt->close();
                $showPopup = 'show'; 
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

// Fetch skills for dropdown
$skillOptions = '';
$skillResult = $conn->query("SELECT skillID, skillName FROM SKILLS");
if ($skillResult && $skillResult->num_rows > 0) {
    while ($skill = $skillResult->fetch_assoc()) {
        $skillOptions .= '<option value="' . htmlspecialchars($skill['skillID']) . '">' . htmlspecialchars($skill['skillName']) . '</option>';
    }
} else {
    $skillOptions = '<option value="" disabled>No skills available</option>';
}

// Load HTML template
$htmlFile = 'Support Request.html';
if (file_exists($htmlFile)) {
    $html = file_get_contents($htmlFile);
    
    $placeholders = [
        '{{skillOptions}}' => $skillOptions,
        '{{popupClass}}'   => $showPopup
    ];

    echo str_replace(array_keys($placeholders), array_values($placeholders), $html);
} else {
    echo "Error: Template file '$htmlFile' not found.";
}
exit;
?>