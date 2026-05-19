<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth_helpers.php';

ensureAuthSchema($conn);

$message = '';
$error = '';
$showVerify = false;
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send_otp';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $emailValue = $email;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid registered Gmail address.';
    } elseif (!isGmailAddress($email)) {
        $error = 'Please use your Gmail account ending in @gmail.com.';
    } elseif ($action === 'send_otp') {
        $stmt = $conn->prepare("SELECT user_id, full_name, email FROM users WHERE LOWER(email) = ? AND status = 'Active' LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $message = 'If that Gmail is registered, an OTP has been sent.';

        if ($user) {
            $otp = (string) random_int(100000, 999999);
            $otpHash = hash('sha256', $otp);

            $clear = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
            $clear->bind_param("i", $user['user_id']);
            $clear->execute();
            $clear->close();

            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
            $stmt->bind_param("iss", $user['user_id'], $user['email'], $otpHash);
            $stmt->execute();
            $stmt->close();

            $showVerify = true;
            if (!sendPasswordOtpEmail($user['email'], $user['full_name'], $otp)) {
                $message = 'OTP created, but PHP mail is not configured. Configure XAMPP mail settings, then try again.';
                $host = $_SERVER['HTTP_HOST'] ?? '';
                if (str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1')) {
                    $message .= ' Local test OTP: ' . $otp;
                }
            }
        }
    } elseif ($action === 'verify_otp') {
        $showVerify = true;
        $otp = trim($_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!preg_match('/^\d{6}$/', $otp)) $error = 'Please enter the 6-digit OTP.';
        $policyErrors = passwordPolicyErrors($password);
        if (!empty($policyErrors)) $error = implode(' ', $policyErrors);
        if ($password !== $confirm) $error = 'Passwords do not match.';

        if ($error === '') {
            $otpHash = hash('sha256', $otp);
            $stmt = $conn->prepare("
                SELECT pr.reset_id, pr.user_id
                FROM password_resets pr
                JOIN users u ON u.user_id = pr.user_id
                WHERE LOWER(pr.email) = ? AND pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.status = 'Active'
                ORDER BY pr.reset_id DESC
                LIMIT 1
            ");
            $stmt->bind_param("ss", $email, $otpHash);
            $stmt->execute();
            $reset = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$reset) {
                $error = 'Invalid or expired OTP.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hash, $reset['user_id']);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?");
                $stmt->bind_param("i", $reset['reset_id']);
                $stmt->execute();
                $stmt->close();

                $showVerify = false;
                $emailValue = '';
                $message = 'Password updated successfully. You can now sign in.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Clinic Inventory System</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Exo 2',sans-serif;background:#020e1e;color:#e6edf3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{width:100%;max-width:450px;background:rgba(255,255,255,.03);border:1px solid rgba(0,245,255,.12);border-radius:22px;padding:30px 26px;box-shadow:0 24px 80px rgba(0,0,0,.5)}
    h1{font-family:'Syne',sans-serif;font-size:21px;color:#00f5ff;margin-bottom:6px}
    p{font-size:13px;color:rgba(255,255,255,.42);line-height:1.5;margin-bottom:20px}
    label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(0,245,255,.5);margin:14px 0 8px}
    input{width:100%;background:rgba(0,245,255,.04);border:1px solid rgba(0,245,255,.12);border-radius:10px;padding:12px 14px;font-size:14px;color:#e6edf3;font-family:'Exo 2',sans-serif}
    input:focus{outline:none;border-color:rgba(0,245,255,.4);box-shadow:0 0 0 3px rgba(0,245,255,.07)}
    .btn{width:100%;margin-top:16px;padding:14px;border:0;border-radius:12px;background:linear-gradient(135deg,#00b4d8,#00f5ff);color:#020e1e;font-family:'Syne',sans-serif;font-weight:700;cursor:pointer}
    .back{display:inline-flex;gap:7px;align-items:center;margin-top:18px;color:#00f5ff;text-decoration:none;font-size:12px;font-weight:700}
    .alert{padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;line-height:1.45}
    .ok{background:rgba(63,185,80,.1);border:1px solid rgba(63,185,80,.3);color:#3fb950}
    .bad{background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149}
    .rules{font-size:10px;color:rgba(255,255,255,.35);line-height:1.45;margin-top:7px}
  </style>
</head>
<body>
  <div class="card">
    <h1><i class="fas fa-key"></i> Forgot Password</h1>
    <p>Enter your registered Gmail account. The system will send a 6-digit OTP to reset your password.</p>
    <?php if ($message): ?><div class="alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (!$showVerify): ?>
      <form method="POST" action="forgot_password.php">
        <input type="hidden" name="action" value="send_otp">
        <label>Gmail Account</label>
        <input type="email" name="email" placeholder="yourname@gmail.com" pattern="^[^@\s]+@gmail\.com$" required>
        <button class="btn" type="submit"><i class="fas fa-envelope-circle-check"></i> Send OTP</button>
      </form>
    <?php else: ?>
      <form method="POST" action="forgot_password.php">
        <input type="hidden" name="action" value="verify_otp">
        <input type="hidden" name="email" value="<?= htmlspecialchars($emailValue) ?>">
        <label>Gmail Account</label>
        <input type="email" value="<?= htmlspecialchars($emailValue) ?>" disabled>
        <label>6-Digit OTP</label>
        <input type="text" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" required>
        <label>New Password</label>
        <input type="password" name="password" placeholder="Min. 10 characters" required>
        <div class="rules">Use uppercase, lowercase, number, and symbol.</div>
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Repeat password" required>
        <button class="btn" type="submit"><i class="fas fa-check"></i> Verify OTP & Update Password</button>
      </form>
    <?php endif; ?>

    <a href="login.php?role=student" class="back"><i class="fas fa-arrow-left"></i> Back to login</a>
  </div>
</body>
</html>
