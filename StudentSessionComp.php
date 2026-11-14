<?php
// session_completion.php (NO DATABASE VERSION)

// MOCK DATA
$sessionDetails = [
    'sessionID' => 'S-10293',
    'volunteerName' => 'Jane Doe',
    'date' => '2023-10-24',
    'time' => '14:00',
    'topic' => 'Resume Review & Interview Prep'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Session Completed</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Override to make readonly inputs look like the schedule page boxes */
        input[readonly] {
            background-color: #fff; /* Keep it white */
            color: #333;
            cursor: default;
        }
        
        /* Style for the green success message */
        .success-message {
            text-align: center;
            color: #34a853;
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 16px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Session Completed</h2>
    
    <p class="success-message">✔ Thank you for participating!</p>

    <!-- We use a form layout, but inputs are readonly, just to match the Schedule page look -->
    <form>
        <label for="sessionID">Session ID:</label>
        <input type="text" id="sessionID" value="<?php echo $sessionDetails['sessionID']; ?>" readonly>

        <label for="volunteer">Volunteer:</label>
        <input type="text" id="volunteer" value="<?php echo $sessionDetails['volunteerName']; ?>" readonly>

        <label for="date">Date:</label>
        <input type="date" id="date" value="<?php echo $sessionDetails['date']; ?>" readonly>

        <label for="time">Time:</label>
        <input type="time" id="time" value="<?php echo $sessionDetails['time']; ?>" readonly>

        <label for="topic">Topic:</label>
        <input type="text" id="topic" value="<?php echo $sessionDetails['topic']; ?>" readonly>

        <!-- Match the exact button layout from the Schedule page -->
        <a href="feedback.php?session_id=<?php echo $sessionDetails['sessionID']; ?>" class="btn-primary" style="text-decoration: none; display: block; text-align: center; box-sizing: border-box;">
            Leave Feedback
        </a>
        
        <a href="schedule_session.php" class="btn-secondary">Return to Home</a>
    </form>
</div>

</body>
</html>