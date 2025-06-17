<?php
// Start the session at the very beginning
session_start();

require_once 'functions.php'; // Assuming functions.php is in the same directory

$verificationSent = false;
$registrationSuccess = false;
$error = '';
$success = '';

// Handle all POST logic first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action to send verification code
    if ($action === 'send_code') {
        $email = trim($_POST['email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $code = generateVerificationCode();
            // Store the code in pending_verifications.json for the verifyCode function to check
            $tempFile = __DIR__ . '/pending_verifications.json';
            $pendingData = [];
            if (file_exists($tempFile)) {
                $pendingData = json_decode(file_get_contents($tempFile), true) ?? [];
            }
            $pendingData[$email] = $code;
            file_put_contents($tempFile, json_encode($pendingData));

            // Send the email with the generated code
            if (sendVerificationEmail($email, $code, 'registration')) { // Pass 'registration' type
                $verificationSent = true;
                // Store email in session to pre-fill the verification form if needed
                $_SESSION['current_verification_email'] = $email;
            } else {
                $error = "Failed to send verification email. Please try again.";
            }
        } else {
            $error = "Invalid email address.";
        }
    }

    // Action to verify the code
    if ($action === 'verify_code') {
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['verification_code'] ?? '');

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Use the verifyCode function which checks against pending_verifications.json
            if (verifyCode($email, $code)) {
                if (registerEmail($email)) {
                    $registrationSuccess = true;
                    $success = "Email verified and subscribed successfully!";
                } else {
                    $success = "Email was already registered."; // Email was verified but already in registered_emails.txt
                }
                unset($_SESSION['current_verification_email']); // Clear session email after successful verification
            } else {
                $error = "Verification failed. Please check your email and code.";
            }
        } else {
            $error = "Invalid email address provided for verification.";
        }
    }
}

// Get the email for pre-filling the verification form if it was just sent
$currentEmailForVerification = $_SESSION['current_verification_email'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Verification - XKCD</title>
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
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <h2>Subscribe to Daily XKCD Comic</h2>

    <?php if (!empty($error)): ?>
        <p class="message error"><?= htmlspecialchars($error) ?></p>
    <?php elseif (!empty($success)): ?>
        <p class="message success"><?= htmlspecialchars($success) ?></p>
    <?php elseif ($verificationSent): ?>
        <p class="message info">Verification code sent to <strong><?= htmlspecialchars($currentEmailForVerification) ?></strong>. Enter it below.</p>
    <?php endif; ?>

    <hr/>

    <h3>1. Enter your Email to get a Verification Code</h3>
    <form method="POST">
        <label for="email_send_code">Email:</label>
        <input type="email" id="email_send_code" name="email" value="<?= htmlspecialchars($currentEmailForVerification) ?>" required>
        <button id="submit-email" name="action" value="send_code">Submit</button>
    </form>

    <hr/>

    <h3>2. Verify Your Code to Subscribe</h3>
    <form method="POST">
        <label for="email_verify_code">Email:</label>
        <input type="email" id="email_verify_code" name="email" value="<?= htmlspecialchars($currentEmailForVerification) ?>" required>

        <label for="verification_code">Verification Code:</label>
        <input type="text" id="verification_code" name="verification_code" maxlength="6" required>
        <button id="submit-verification" name="action" value="verify_code">Verify</button>
    </form>
</body>
</html>