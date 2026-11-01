<?php
session_start();
require_once 'db.php'; 

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM STUDENT WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                // Verify the submitted password against the stored secure hash
                if (password_verify($password, $user['password'])) {
                    
                    // Store user data in session variables
                    $_SESSION['studentID'] = $user['studentID'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = 'student'; // Set role so dashboard check passes

                    // Checks for any pending feedback yet to submit
                    $_SESSION['pending_feedback_count'] = 0;
                    $_SESSION['pending_feedback_session'] = '';
                    $fbCheck = $conn->prepare("
                        SELECT s.sessionID
                        FROM SESSION s
                        JOIN SUPPORTREQUEST sr ON s.requestID = sr.requestID
                        LEFT JOIN FEEDBACK f ON f.sessionID = s.sessionID
                        WHERE s.studentID = ? AND s.status = 'Completed' AND f.feedbackID IS NULL
                        ORDER BY s.date DESC LIMIT 1
                    ");
                    // If found, flag it so the popout feedback form can show
                    if ($fbCheck) {
                        $fbCheck->bind_param("s", $user['studentID']);
                        $fbCheck->execute();
                        $fbRes = $fbCheck->get_result();
                        if ($fbRow = $fbRes->fetch_assoc()) {
                            $_SESSION['pending_feedback_session'] = $fbRow['sessionID'];
                            $_SESSION['pending_feedback_count'] = 1;
                        }
                        $fbCheck->close();
                    }
                    // Redirect to the role selection page
                    header("Location: Choose Role.html");
                    exit;
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Database query error: " . $conn->error;
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

// If login fails, alert the user and send them back to the login page
if (!empty($error)) {
    $safeError = addslashes($error);
    echo "<script>alert('$safeError'); window.location.href='Login Page.html';</script>";
    exit;
}
?>