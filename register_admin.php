<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth_helpers.php';

// ── SECURITY KEY ──
// Change this to any secret word only you know
define('ADMIN_SECRET_KEY', 'icas_clinic_2026');

$secretKey = $_GET['key'] ?? '';
if ($secretKey !== ADMIN_SECRET_KEY) {
    http_response_code(404);
    die('404 Not Found');
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($username))          $errors[] = 'Gmail is required.';
    elseif (!isGmailAddress($username)) $errors[] = 'Must be a valid Gmail address.';
    if (empty($password))          $errors[] = 'Password is required.';
    if (strlen($password) < 6)     $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)    $errors[] = 'Passwords do not match.';

    // Check if already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM admin WHERE LOWER(username) = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $errors[] = 'This Gmail is already registered as admin.';
        $stmt->close();
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Registration</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'Exo 2', sans-serif;
      background: #020e1e;
      background-image:
        radial-gradient(ellipse at 20% 20%, rgba(0,245,255,0.08) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(0,150,255,0.06) 0%, transparent 50%);
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      color: #e6edf3; padding: 20px;
    }
    .wrapper { width: 100%; max-width: 440px; position: relative; z-index: 1; }

    .logo-section { text-align: center; margin-bottom: 28px; }
    .logo-icon {
      width: 70px; height: 70px;
      background: linear-gradient(135deg, #00b4d8, #00f5ff);
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 14px;
      box-shadow: 0 0 40px rgba(0,245,255,0.3);
      font-size: 28px; color: #020e1e;
    }
    .logo-title {
      font-family: 'Syne', sans-serif;
      font-size: 18px; font-weight: 800;
      letter-spacing: 3px; color: #00f5ff;
      text-shadow: 0 0 20px rgba(0,245,255,0.5);
    }
    .logo-sub {
      font-size: 10px; letter-spacing: 3px;
      color: rgba(0,245,255,0.4);
      text-transform: uppercase; margin-top: 4px;
    }

    /* Secret badge */
    .secret-badge {
      display: flex; align-items: center; gap: 8px;
      background: rgba(227,179,65,0.08);
      border: 1px solid rgba(227,179,65,0.2);
      border-radius: 10px; padding: 10px 14px;
      margin-bottom: 20px; font-size: 12px;
      color: rgba(227,179,65,0.8);
    }

    .card {
      background: rgba(255,255,255,0.03);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(0,245,255,0.12);
      border-radius: 24px; padding: 32px 28px;
      box-shadow: 0 24px 80px rgba(0,0,0,0.5);
    }
    .card-title {
      font-family: 'Syne', sans-serif;
      font-size: 20px; font-weight: 700;
      margin-bottom: 4px;
    }
    .card-sub { font-size: 12px; color: rgba(255,255,255,0.35); margin-bottom: 24px; }

    .alert-success {
      background: rgba(63,185,80,0.1); border: 1px solid rgba(63,185,80,0.3);
      border-radius: 10px; padding: 12px 14px; font-size: 13px; color: #3fb950;
      margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
    }
    .alert-error {
      background: rgba(248,81,73,0.1); border: 1px solid rgba(248,81,73,0.3);
      border-radius: 10px; padding: 12px 14px; font-size: 13px; color: #f85149;
      margin-bottom: 16px;
    }

    .field { margin-bottom: 16px; }
    .field label {
      display: block; font-size: 10px; font-weight: 700;
      text-transform: uppercase; letter-spacing: .1em;
      color: rgba(0,245,255,0.5); margin-bottom: 7px;
    }
    .field input {
      width: 100%;
      background: rgba(0,245,255,0.04);
      border: 1px solid rgba(0,245,255,0.12);
      border-radius: 10px; padding: 12px 14px;
      font-size: 14px; color: #e6edf3;
      transition: all 0.2s; font-family: 'Exo 2', sans-serif;
    }
    .field input:focus {
      outline: none;
      border-color: rgba(0,245,255,0.4);
      background: rgba(0,245,255,0.06);
    }
    .field input::placeholder { color: rgba(255,255,255,0.2); }
    .field .hint { font-size: 11px; color: rgba(255,255,255,0.2); margin-top: 5px; }

    .btn-register {
      width: 100%; padding: 14px;
      background: linear-gradient(135deg, #00b4d8, #00f5ff);
      border: none; border-radius: 12px;
      color: #020e1e; font-family: 'Syne', sans-serif;
      font-size: 14px; font-weight: 700; letter-spacing: 1px;
      cursor: pointer; transition: all 0.2s;
      box-shadow: 0 4px 24px rgba(0,245,255,0.25);
      margin-top: 8px;
    }
    .btn-register:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 32px rgba(0,245,255,0.4);
    }

    .divider { border: none; border-top: 1px solid rgba(0,245,255,0.08); margin: 20px 0; }

    .back-link {
      display: block; text-align: center;
      margin-top: 20px; font-size: 12px;
      color: rgba(255,255,255,0.3);
    }
    .back-link a { color: #00f5ff; text-decoration: none; font-weight: 600; }

    /* Password strength */
    #pwBar { height: 3px; border-radius: 2px; background: rgba(0,245,255,0.1); margin-top: 6px; overflow: hidden; }
    #pwFill { height: 100%; width: 0; border-radius: 2px; transition: width .3s, background .3s; }
    #pwLabel { font-size: 10px; color: rgba(0,245,255,0.4); margin-top: 4px; }
  </style>
</head>
<body>

<div class="wrapper">

  <div class="logo-section">
    <div class="logo-icon">
      <i class="fas fa-user-shield"></i>
    </div>
    <div class="logo-title">ADMIN REGISTRATION</div>
    <div class="logo-sub">School Clinic Inventory System</div>
  </div>

  <div class="card">

    <!-- Secret page notice -->
    <div class="secret-badge">
      <i class="fas fa-lock"></i>
      This is a restricted page. Not visible to regular users.
    </div>

    <div class="card-title">Create Admin Account</div>
    <div class="card-sub">Register a new Nurse / Admin account</div>

    <?php if ($success): ?>
      <div class="alert-success">
        <i class="fas fa-circle-check"></i>
        Admin account created successfully!
        <a href="login.php" style="color:#3fb950;font-weight:700;margin-left:4px;">Go to Login →</a>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert-error">
        <i class="fas fa-circle-exclamation"></i>
        <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
      </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="register_admin.php?key=<?= htmlspecialchars($secretKey) ?>">

      <div class="field">
        <label>Admin Gmail *</label>
        <input type="email" name="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               placeholder="admin@gmail.com"
               pattern="^[^@\s]+@gmail\.com$"
               required autofocus>
        <div class="hint">Must be a Gmail address (@gmail.com)</div>
      </div>

      <hr class="divider">

      <div class="field">
        <label>Password *</label>
        <input type="password" name="password"
               placeholder="Min. 6 characters"
               required id="pwInput"
               oninput="checkPw(this.value)">
        <div id="pwBar"><div id="pwFill"></div></div>
        <div id="pwLabel"></div>
      </div>

      <div class="field">
        <label>Confirm Password *</label>
        <input type="password" name="confirm_password"
               placeholder="Repeat password" required>
      </div>

      <button type="submit" class="btn-register">
        <i class="fas fa-user-plus"></i> &nbsp;Create Admin Account
      </button>

    </form>
    <?php endif; ?>

    <div class="back-link">
      <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>

  </div>

</div>

<script>
function checkPw(pw) {
  let score = 0;
  if (pw.length >= 6)  score++;
  if (pw.length >= 10) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const pct    = (score / 5) * 100;
  const colors = ['#f85149','#f85149','#e3b341','#e3b341','#00f5ff'];
  const labels = ['','Very Weak','Weak','Fair','Strong','Very Strong'];
  document.getElementById('pwFill').style.width      = pct + '%';
  document.getElementById('pwFill').style.background = colors[score-1] || 'rgba(0,245,255,0.1)';
  document.getElementById('pwLabel').textContent     = pw.length ? labels[score] : '';
  document.getElementById('pwLabel').style.color     = colors[score-1] || 'rgba(0,245,255,0.4)';
}
</script>

</body>
</html>