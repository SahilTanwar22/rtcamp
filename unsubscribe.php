<?php
// Start the session at the very beginning
session_start();

require_once 'functions.php'; // Assuming functions.php is in the same directory

$unsubscribeSent = false;
$unsubSuccess = false;
$error = '';
$success_message = ''; // Renamed for clarity to avoid conflict with $unsubSuccess

// Variable to hold email for pre-filling, similar to index.php
$currentEmailForUnsubscribe = '';

// Handle unsubscribe request (send code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_unsubscribe_code') {
    $email = trim($_POST['unsubscribe_email'] ?? ''); // Use null coalescing operator
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $code = generateVerificationCode();

        // Store code in a dedicated file for unsubscribe requests
        $tempFile = __DIR__ . '/pending_unsubscribes.json';
        $pendingData = [];
        if (file_exists($tempFile)) {
            $pendingData = json_decode(file_get_contents($tempFile), true) ?? [];
        }
        $pendingData[$email] = $code;
        file_put_contents($tempFile, json_encode($pendingData));

        // Use the common function to send the email with the correct type
        if (sendVerificationEmail($email, $code, 'unsubscribe')) {
            $unsubscribeSent = true;
            $_SESSION['current_unsubscribe_email'] = $email; // Store email in session
        } else {
            $error = "Failed to send unsubscribe confirmation email. Please try again.";
        }
    } else {
        $error = "Invalid email address.";
    }
}

// Handle unsubscribe confirmation (verify code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_unsubscribe_code') {
    $email = trim($_POST['unsubscribe_email'] ?? ''); // Ensure this input is present
    $code = trim($_POST['verification_code'] ?? '');

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $tempFile = __DIR__ . '/pending_unsubscribes.json';

        if (file_exists($tempFile)) {
            $data = json_decode(file_get_contents($tempFile), true) ?? [];
            if (isset($data[$email]) && $data[$email] === $code) {
                unset($data[$email]); // Remove the code after successful verification
                file_put_contents($tempFile, json_encode($data));

                // Perform the actual unsubscription
                if (unsubscribeEmail($email)) {
                    $unsubSuccess = true;
                    $success_message = "You have been unsubscribed successfully.";
                } else {
                    $error = "Could not unsubscribe. Email not found or an error occurred.";
                }
                unset($_SESSION['current_unsubscribe_email']); // Clear session email after successful unsubscription
            } else {
                $error = "Invalid verification code or email mismatch.";
            }
        } else {
            $error = "No pending unsubscribe request found for this email.";
        }
    } else {
        $error = "Invalid email address provided for verification.";
    }
}

// Set current email for pre-filling
$currentEmailForUnsubscribe = $_SESSION['current_unsubscribe_email'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Unsubscribe - XKCD</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="email"], input[type="text"] {
            width: 300px;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            padding: 10px 15px;
            background-color: #dc3545; /* Red for unsubscribe */
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button#submit-verification {
            background-color: #007bff; /* Blue for verification */
        }
        button:hover {
            opacity: 0.9;
        }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <h2>Unsubscribe from Daily XKCD Comics</h2>

    <?php if (!empty($error)): ?>
        <p class="message error"><?= htmlspecialchars($error) ?></p>
    <?php elseif ($unsubSuccess): // Use $unsubSuccess directly as a boolean ?>
        <p class="message success"><?= htmlspecialchars($success_message) ?></p>
    <?php elseif ($unsubscribeSent): ?>
        <p class="message info">A confirmation code was sent to <strong><?= htmlspecialchars($currentEmailForUnsubscribe) ?></strong>. Please enter it below.</p>
    <?php endif; ?>

    <hr/>

    <h3>1. Enter your Email to get an Unsubscribe Code</h3>
    <form method="POST">
        <label for="unsubscribe_email_send">Email to unsubscribe:</label>
        <input type="email" id="unsubscribe_email_send" name="unsubscribe_email" value="<?= htmlspecialchars($currentEmailForUnsubscribe) ?>" required>
        <button id="submit-unsubscribe" name="action" value="send_unsubscribe_code">Unsubscribe</button>
    </form>

    <hr/>

    <h3>2. Enter Code to Confirm Unsubscription</h3>
    <form method="POST">
        <label for="unsubscribe_email_verify">Email:</label>
        <input type="email" id="unsubscribe_email_verify" name="unsubscribe_email" value="<?= htmlspecialchars($currentEmailForUnsubscribe) ?>" required>

        <label for="verification_code">Verification Code:</label>
        <input type="text" id="verification_code" name="verification_code" maxlength="6" required>
        <button id="submit-verification" name="action" value="verify_unsubscribe_code">Verify</button>
    </form>
</body>
</html>