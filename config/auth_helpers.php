<?php

function ensureAuthSchema(mysqli $conn): void {
    @$conn->query("ALTER TABLE users MODIFY student_id VARCHAR(20) NULL");
    @$conn->query("ALTER TABLE users MODIFY email VARCHAR(100) NOT NULL");
    @$conn->query("
        CREATE TABLE IF NOT EXISTS password_resets (
            reset_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(100) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY token_hash (token_hash),
            KEY user_id (user_id),
            KEY email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function passwordPolicyErrors(string $password): array {
    $errors = [];
    if (strlen($password) < 10) $errors[] = 'Password must be at least 10 characters.';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'Password needs at least one lowercase letter.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password needs at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password needs at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Password needs at least one symbol.';
    return $errors;
}

function isGmailAddress(string $email): bool {
    return (bool) preg_match('/^[^@\s]+@gmail\.com$/i', $email);
}

function resetBaseUrl(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . ($dir ? $dir : '');
}

function sendPasswordResetEmail(string $to, string $name, string $link): bool {
    $subject = 'School Clinic password reset';
    $safeName = $name !== '' ? $name : 'there';
    $message = "Hi {$safeName},\n\nUse this link to reset your School Clinic account password:\n{$link}\n\nThis link expires in 30 minutes. If you did not request it, you can ignore this email.";
    $headers = "From: School Clinic <no-reply@localhost>\r\n";
    return @mail($to, $subject, $message, $headers);
}

function sendPasswordOtpEmail(string $to, string $name, string $otp): bool {
    $subject = 'School Clinic password reset OTP';
    $safeName = $name !== '' ? $name : 'there';
    $message = "Hi {$safeName},\n\nYour School Clinic password reset OTP is: {$otp}\n\nThis code expires in 10 minutes. If you did not request it, you can ignore this email.";
    $headers = "From: School Clinic <no-reply@localhost>\r\n";
    return @mail($to, $subject, $message, $headers);
}

?>
