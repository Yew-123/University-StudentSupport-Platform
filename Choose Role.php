<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['studentID'])) {
    header("Location: Login Page.html");
    exit;
}

// Check if a role parameter was passed in the URL
if (isset($_GET['role'])) {
    $role = trim($_GET['role']);

    // Validate the role to ensure it's either student or volunteer
    if ($role === 'student' || $role === 'volunteer') {
        $_SESSION['role'] = $role;
        // Redirect based on the chosen role
        if ($role === 'student') {
            header("Location: Student Dashboard.php"); // to student page
            exit;
        } elseif ($role === 'volunteer') {
            header("Location: Volunteer Dashboard.php"); // to volunteer page
            exit;
        }
    }
}

// If no valid role was provided, redirect back to the choice page
header("Location: Choose Role.html");
exit;
?>