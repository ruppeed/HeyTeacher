<!-- header.php -->
<nav class="navbar navbar-expand-lg bg-primary navbar-dark fixed-top" data-bs-theme="dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="/">HeyTeacher</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
      aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="reporting.php">PDF Analytics</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="quiz.php">Quiz Generator</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="mockexam.php">Mock Exam Generator</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="manage_subjects.php">Manage Subjects</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="about.php">About</a>
        </li>                
      </ul>

      <form class="d-flex" method="post" action="/">
        <button class="btn btn-outline-light" type="submit" name="authAction">
          <?php echo isset($_SESSION["username"]) ? "Log Out" : "Log In"; ?>
        </button>
      </form>
    </div>
  </div>
</nav>
