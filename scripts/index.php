<?php
session_start();

// ✅ MySQL database connection
$host = 'localhost';
$db = 'heyteacher_db';
$user = 'root';
$pass = 'mysql';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}

function user_exists($conn, $username) {
  $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();
  $exists = $stmt->num_rows > 0;
  $stmt->close();
  return $exists;
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

  $action = $_POST['action'] ?? '';

  if ($action === 'login') {
    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $stmt->bind_result($stored_password);
      $stmt->fetch();
      if ($password === $stored_password) {
        $_SESSION['username'] = $username;
        header("Location: selection.php");
        exit();
      } else {
        $message = "❌ Incorrect password.";
      }
    } else {
      $message = "❌ Username not found.";
    }
    $stmt->close();
  } elseif ($action === 'register') {
    if (user_exists($conn, $username)) {
      $message = "❌ Username already exists.";
    } else {
      $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
      $stmt->bind_param("ss", $username, $password);
      if ($stmt->execute()) {
        $_SESSION['username'] = $username;
        header("Location: subject_selection.php");
        exit();
      } else {
        $message = "❌ Error registering user.";
      }
      $stmt->close();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HeyTeacher – Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      padding-top: 70px; /* space for navbar */
    }
    .form-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: calc(100vh - 70px);
    }
    .w-48 {
      width: 48%;
    }
  </style>
</head>
<body>

  <?php include 'header.php'; ?>

  <div class="form-container">
    <form method="post" action="index.php" class="p-4 bg-white rounded shadow-sm w-100" style="max-width: 400px;">
      <h3 class="text-center mb-4">Login / Register</h3>

      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>

      <div class="d-flex justify-content-between">
        <button type="submit" name="action" value="login" class="btn btn-success w-48">Login</button>
        <button type="submit" name="action" value="register" class="btn btn-primary w-48">Register</button>
      </div>

      <?php if (!empty($message)): ?>
        <div class="alert alert-danger mt-3 text-center mb-0" role="alert">
          <?php echo $message; ?>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
