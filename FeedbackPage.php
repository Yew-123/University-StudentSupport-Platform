<?php
// feedback.php (NO DATABASE VERSION)

$message = "";
$targetSessionID = $_GET['session_id'] ?? 'S12345'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];

    // Basic Validation
    if (is_numeric($rating) && $rating >= 1 && $rating <= 5) {
        // Simulate success without DB
        $message = "Thank you! (Simulated) Your rating of $rating stars has been received.";
    } else {
        $message = "Please provide a valid rating between 1 and 5.";
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