<?php
session_start(); // Required if using $_SESSION for login state
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HeyTeacher</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/css/heyteacher.css">
</head>
<body>

  <?php include 'header.php'; ?>

  <div class="container mt-5">
    <h1>Welcome to HeyTeacher</h1>
    <p>This is the homepage content.</p>
  </div>

  <!-- Bootstrap JS Bundle (includes Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
