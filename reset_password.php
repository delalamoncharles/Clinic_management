<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth_helpers.php';

ensureAuthSchema($conn);

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$tokenHash = $token !== '' ? hash('sha256', $token) : '';
$errors = [];
$success = false;
$reset = null;

if ($tokenHash !== '') {
    $stmt = $conn->prepare("
        SELECT pr.reset_id, pr.user_id, u.full_name
        FROM password_resets pr
        JOIN users u ON u.user_id = pr.user_id
        WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$reset) {
    $errors[] = 'This reset link is invalid or expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = array_merge(passwordPolicyErrors($password), $errors);
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hash, $reset['user_id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?");
        $stmt->bind_param("i", $reset['reset_id']);
        $stmt->execute();
        $stmt->close();

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Clinic Inventory System</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Exo 2',sans-serif;background:#020e1e;color:#e6edf3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{width:100%;max-width:440px;background:rgba(255,255,255,.03);border:1px solid rgba(0,245,255,.12);border-radius:22px;padding:30px 26px;box-shadow:0 24px 80px rgba(0,0,0,.5)}
    h1{font-family:'Syne',sans-serif;font-size:21px;color:#00f5ff;margin-bottom:6px}
    p{font-size:13px;color:rgba(255,255,255,.42);line-height:1.5;margin-bottom:20px}
    label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(0,245,255,.5);margin:14px 0 8px}
    input{width:100%;background:rgba(0,245,255,.04);border:1px solid rgba(0,245,255,.12);border-radius:10px;padding:12px 44px 12px 14px;font-size:14px;color:#e6edf3;font-family:'Exo 2',sans-serif}
    input:focus{outline:none;border-color:rgba(0,245,255,.4);box-shadow:0 0 0 3px rgba(0,245,255,.07)}
    .password-wrap{position:relative}
    .eye-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:32px;height:32px;border:0;border-radius:8px;background:rgba(0,245,255,.06);color:#00f5ff;cursor:pointer}
    .btn{width:100%;margin-top:18px;padding:14px;border:0;border-radius:12px;background:linear-gradient(135deg,#00b4d8,#00f5ff);color:#020e1e;font-family:'Syne',sans-serif;font-weight:700;cursor:pointer}
    .back{display:inline-flex;gap:7px;align-items:center;margin-top:18px;color:#00f5ff;text-decoration:none;font-size:12px;font-weight:700}
    .alert{padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
    .ok{background:rgba(63,185,80,.1);border:1px solid rgba(63,185,80,.3);color:#3fb950}
    .bad{background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149}
    .rules{font-size:10px;color:rgba(255,255,255,.35);line-height:1.45;margin-top:7px}
  </style>
</head>
<body>
  <div class="card">
    <h1><i class="fas fa-lock"></i> Reset Password</h1>
    <?php if ($success): ?>
      <div class="alert ok">Password updated successfully. You can now sign in with your new password.</div>
      <a href="login.php?role=student" class="back"><i class="fas fa-right-to-bracket"></i> Go to login</a>
    <?php else: ?>
      <p>Create a new password with at least 10 characters, uppercase, lowercase, number, and symbol.</p>
      <?php if (!empty($errors)): ?><div class="alert bad"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
      <?php if ($reset): ?>
        <form method="POST" action="reset_password.php">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <label>New Password</label>
          <div class="password-wrap">
            <input type="password" name="password" id="newPassword" required>
            <button type="button" class="eye-btn" data-eye-target="newPassword" aria-label="Hold to show password"><i class="fas fa-eye"></i></button>
          </div>
          <div class="rules">Use uppercase, lowercase, number, and symbol.</div>
          <label>Confirm Password</label>
          <div class="password-wrap">
            <input type="password" name="confirm_password" id="confirmPassword" required>
            <button type="button" class="eye-btn" data-eye-target="confirmPassword" aria-label="Hold to show password"><i class="fas fa-eye"></i></button>
          </div>
          <button class="btn" type="submit"><i class="fas fa-check"></i> Update Password</button>
        </form>
      <?php endif; ?>
      <a href="forgot_password.php" class="back"><i class="fas fa-arrow-left"></i> Request another link</a>
    <?php endif; ?>
  </div>
  <script>
  document.querySelectorAll('.eye-btn').forEach(btn => {
    const input = document.getElementById(btn.dataset.eyeTarget);
    const show = () => { if (input) input.type = 'text'; };
    const hide = () => { if (input) input.type = 'password'; };
    btn.addEventListener('mousedown', show);
    btn.addEventListener('mouseup', hide);
    btn.addEventListener('mouseleave', hide);
    btn.addEventListener('touchstart', e => { e.preventDefault(); show(); });
    btn.addEventListener('touchend', hide);
    btn.addEventListener('blur', hide);
  });
  </script>
</body>
</html>
