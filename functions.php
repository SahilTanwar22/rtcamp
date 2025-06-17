<?php

/**
 * Generate a 6-digit numeric verification code.
 */
function generateVerificationCode(): string {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Send a verification code to an email.
 * @param string $email The recipient's email address.
 * @param string $code The verification code.
 * @param string $type The type of verification (e.g., 'registration', 'unsubscribe') to determine subject/body.
 * @return bool True on success, false on failure.
 */
function sendVerificationEmail(string $email, string $code, string $type = 'registration'): bool {
    $subject = "";
    $message = "";

    // Determine subject and message based on the type of verification
    switch ($type) {
        case 'registration':
            $subject = "Your Verification Code";
            $message = "<p>Your verification code is: <strong>$code</strong></p>";
            break;
        case 'unsubscribe':
            $subject = "Confirm Un-subscription";
            $message = "<p>To confirm un-subscription, use this code: <strong>$code</strong></p>";
            break;
        default:
            // Fallback for unknown types
            $subject = "Your Verification Code";
            $message = "<p>Your verification code is: <strong>$code</strong></p>";
            break;
    }

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@example.com" . "\r\n";

    return mail($email, $subject, $message, $headers);
}

/**
 * Verifies a given code against a stored pending verification.
 * @param string $email The email address to verify.
 * @param string $code The code provided by the user.
 * @return bool True if the code matches, false otherwise.
 */
function verifyCode(string $email, string $code): bool {
    $tempFile = __DIR__ . '/pending_verifications.json';

    if (!file_exists($tempFile)) {
        return false;
    }

    $data = json_decode(file_get_contents($tempFile), true);

    if (isset($data[$email]) && $data[$email] === $code) {
        unset($data[$email]); // remove the code after successful verification
        file_put_contents($tempFile, json_encode($data));
        return true;
    }

    return false;
}

/**
 * Register an email by storing it in a file.
 * @param string $email The email address to register.
 * @return bool True if registered successfully, false if already exists.
 */
function registerEmail(string $email): bool {
    $file = __DIR__ . '/registered_emails.txt';

    // Ensure the file exists
    if (!file_exists($file)) {
        file_put_contents($file, ""); // Create an empty file if it doesn't exist
    }

    $emails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    // Check for duplicate entries
    if (!in_array($email, $emails)) {
        file_put_contents($file, $email . PHP_EOL, FILE_APPEND);
        return true;
    }

    return false; // Email already registered
}

/**
 * Unsubscribe an email by removing it from the list.
 * @param string $email The email address to unsubscribe.
 * @return bool True if unsubscribed successfully, false if email not found or file error.
 */
function unsubscribeEmail(string $email): bool {
    $file = __DIR__ . '/registered_emails.txt';

    if (!file_exists($file)) {
        return false; // File does not exist, nothing to unsubscribe
    }

    $emails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $initialCount = count($emails);

    // Filter out the email to be unsubscribed
    $updatedEmails = array_filter($emails, fn($e) => trim($e) !== trim($email));

    // Only write back if there's a change (i.e., email was found and removed)
    if (count($updatedEmails) < $initialCount) {
        // Ensure no trailing empty line if the file becomes empty after unsubscription
        file_put_contents($file, implode(PHP_EOL, $updatedEmails));
        return true;
    }

    return false; // Email not found in the list
}

/**
 * Fetch random XKCD comic and format data as HTML.
 * @return string|false The HTML content of the comic, or false on failure.
 */
function fetchAndFormatXKCDData(): string|false {
    // A more robust way to get the latest comic ID, then pick a random one
    // This prevents requesting IDs that don't exist yet and provides a dynamic range.
    $latestComicUrl = "https://xkcd.com/info.0.json";
    $latestResponse = @file_get_contents($latestComicUrl);
    if ($latestResponse === false) {
        error_log("Failed to fetch latest XKCD comic info.");
        return false;
    }
    $latestComic = json_decode($latestResponse, true);
    if (!$latestComic || !isset($latestComic['num'])) {
        error_log("Failed to decode latest XKCD comic info or 'num' is missing.");
        return false;
    }
    $maxComicId = $latestComic['num'];

    // Random ID from 1 up to the latest comic ID
    $randomId = random_int(1, $maxComicId);
    $url = "https://xkcd.com/$randomId/info.0.json";

    $response = @file_get_contents($url);

    if ($response === false) {
        error_log("Failed to fetch XKCD comic ID $randomId.");
        return false;
    }

    $comic = json_decode($response, true);

    if (!$comic || !isset($comic['img'])) {
        error_log("Failed to decode XKCD comic ID $randomId or 'img' is missing.");
        return false;
    }

    $img = htmlspecialchars($comic['img']);
    $alt = htmlspecialchars($comic['alt'] ?? 'XKCD Comic');
    $title = htmlspecialchars($comic['title'] ?? 'Untitled Comic'); // Add title for better accessibility/info

    $html = "<h2>" . $title . "</h2>";
    $html .= "<img src=\"$img\" alt=\"$alt\">";
    // CORRECTED: Unsubscribe link points to unsubscribe.php
    $html .= "<p><a href=\"/unsubscribe.php\" id=\"unsubscribe-button\">Unsubscribe</a></p>";

    return $html;
}

/**
 * Send the formatted XKCD updates to registered emails.
 */
function sendXKCDUpdatesToSubscribers(): void {
    $file = __DIR__ . '/registered_emails.txt';

    if (!file_exists($file)) {
        return; // No registered emails file
    }

    $emails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($emails)) {
        return; // No emails to send to
    }

    $content = fetchAndFormatXKCDData();

    if ($content === false) { // Check for false explicitly from fetchAndFormatXKCDData
        error_log("Could not fetch or format XKCD data for sending updates.");
        return;
    }

    $subject = "Your XKCD Comic";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: no-reply@example.com\r\n";

    foreach ($emails as $email) {
        // Trim each email to ensure no whitespace issues
        $trimmedEmail = trim($email);
        if (!empty($trimmedEmail)) {
            mail($trimmedEmail, $subject, $content, $headers);
        }
    }
}