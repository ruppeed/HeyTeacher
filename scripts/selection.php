<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "mysql", "heyteacher_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($user_id);
$stmt->fetch();
$stmt->close();

if (!$user_id) {
    die("User not found.");
}

$message = "";
$alertClass = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['subjects'])) {
    $subjects = $_POST['subjects'];

    if (!empty($subjects)) {
        $stmt = $conn->prepare("INSERT INTO user_subjects (user_id, exam_code_id) VALUES (?, ?)");

        foreach ($subjects as $exam_code_id) {
            $exam_code_id = (int)$exam_code_id;

            $check = $conn->prepare("SELECT id FROM user_subjects WHERE user_id = ? AND exam_code_id = ?");
            $check->bind_param("ii", $user_id, $exam_code_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows == 0) {
                $stmt->bind_param("ii", $user_id, $exam_code_id);
                $stmt->execute();
            }

            $check->close();
        }

        $stmt->close();
        $message = "✅ Subjects saved successfully.";
        $alertClass = "alert-success";
    } else {
        $message = "❌ No subjects selected.";
        $alertClass = "alert-danger";
    }
}

$sql = "SELECT DISTINCT exam_code_id, subject FROM exam_codes ORDER BY subject ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Subjects</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="/css/heyteacher.css">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="card-title text-center mb-3">Select Subjects</h3>
                    <p class="text-muted text-center">Hold Ctrl (Windows) or Cmd (Mac) to select multiple subjects</p>

                    <form method="post" action="selection.php">
                        <div class="mb-3">
                            <label for="subjects" class="form-label">Subjects</label>
                            <select name="subjects[]" id="subjects" class="form-select" multiple required style="height: 200px;">
                                <?php
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $subject = htmlspecialchars($row['subject']);
                                        $id = (int)$row['exam_code_id'];
                                        echo "<option value=\"$id\">$subject</option>";
                                    }
                                } else {
                                    echo "<option disabled>No subjects found</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Submit</button>

                        <?php if (!empty($message)): ?>
                            <div class="alert <?php echo $alertClass; ?> mt-3 text-center" role="alert">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
