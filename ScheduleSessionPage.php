<?php
// schedule_session.php (NO DATABASE VERSION)

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Just grab the data to show it works
    $date = $_POST['date'];
    $time = $_POST['time'];
    
    // Simulate success without DB
    $message = "Success! (Simulated) Session scheduled for $date at $time.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Schedule Session</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <h2>Schedule Session</h2>
    
    <?php if($message): ?>
        <p style="text-align:center; color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border-radius: 5px;">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="date">Date:</label>
        <input type="date" id="date" name="date" required>

        <label for="time">Time:</label>
        <input type="time" id="time" name="time" required>

        <button type="submit" class="btn-primary">Schedule</button>
        <a href="feedback.php" class="btn-secondary">Go to Feedback</a>
    </form>
</div>

</body>
</html>