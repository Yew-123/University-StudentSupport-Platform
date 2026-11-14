<?php
session_start();
require_once 'db.php';

$message = "";
$messageType = "";
$targetSessionID = $_GET['session_id'] ?? $_POST['sessionID'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rating = (int)($_POST['rating'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');

    if ($rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO FEEDBACK (sessionID, rating, comment) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sis", $targetSessionID, $rating, $comments);
            if ($stmt->execute()) {
                $message = "Thank you! Your feedback has been recorded.";
                $messageType = "success";
            } else {
                $message = "Failed to save feedback: " . $stmt->error;
                $messageType = "error";
            }
            $stmt->close();
        } else {
            $message = "SQL prepare error: " . $conn->error;
            $messageType = "error";
        }
    } else {
        $message = "Please provide a valid rating between 1 and 5.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Support Session Feedback</title>
    <link rel="stylesheet" href="style.css">
    <style>
        h2 { margin-bottom: 20px; }
        .question-text { font-size: 16px; margin-bottom: 10px; color: #333; }
    </style>
</head>
<body>

<div class="container">
    <h2>Support Session Feedback</h2>
    
    <?php if($message): ?>
        <p style="text-align:center; color: <?php echo strpos($message, 'Thank') !== false ? 'green' : 'red'; ?>; font-weight: bold; background: #f0f0f0; padding: 10px; border-radius: 5px;">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Hidden Input to pass the Session ID -->
        <input type="hidden" name="sessionID" value="<?php echo htmlspecialchars($targetSessionID); ?>">

        <p class="question-text">How was your experience?</p>

        <label for="rating">Rating:</label>
        <input type="number" id="rating" name="rating" min="1" max="5" placeholder="1-5" required>
        <span class="rating-hint">(1 = Poor, 5 = Excellent)</span>

        <label for="comments">Comments (Optional):</label>
        <textarea id="comments" name="comments" placeholder="Tell us more about your session..."></textarea>

        <button type="submit" class="btn-primary">Submit Feedback</button>
        <a href="schedule_session.php" class="btn-secondary">Back</a>
    </form>
</div>

</body>
</html>
