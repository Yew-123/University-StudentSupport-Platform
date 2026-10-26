<?php
session_start();
require_once 'db.php'; // This brings in your $conn variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and trim form inputs
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $studentID = trim($_POST['studentID'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Basic validation
    if (empty($name) || empty($email) || empty($studentID) || empty($phone) || empty($password)) {
        echo "<script>alert('Please fill in all required fields.'); window.history.back();</script>";
        exit;
    }

    // Check if studentID or email already exists
    $checkStmt = $conn->prepare("SELECT studentID FROM STUDENT WHERE studentID = ? OR Email = ?");
    if ($checkStmt) {
        $checkStmt->bind_param("ss", $studentID, $email);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $checkStmt->close();
            echo "<script>alert('Student ID or Email already registered.'); window.history.back();</script>";
            exit;
        }
        $checkStmt->close();
    }

    // Hash the password securely before storing it in the database
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Set default values for columns required by the database table dictionary
    $availability = 'Not Specified';
    $supportMode = 'Online';

    // Insert into STUDENT table using MySQLi prepared statements (using $hashedPassword)
    $stmt = $conn->prepare("
        INSERT INTO STUDENT (studentID, name, email, password, phoneNo, availability, supportMode) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt) {
        $stmt->bind_param("sssssss", $studentID, $name, $email, $hashedPassword, $phone, $availability, $supportMode);
        
        if ($stmt->execute()) {
            // Automatically log them in by setting session variables
            $_SESSION['studentID'] = $studentID;
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;

            // Redirect to Choose Role page
            header("Location: Choose Role.html");
            exit;
        } else {
            $errorMsg = addslashes($stmt->error);
            echo "<script>alert('Database Error: $errorMsg'); window.history.back();</script>";
            exit;
        }
        $stmt->close();
    } else {
        echo "<script>alert('Database preparation error.'); window.history.back();</script>";
        exit;
    }
} else {
    header("Location: Sign Up Page.html");
    exit;
}
?>