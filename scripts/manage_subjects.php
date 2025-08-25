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
        // Convert subjects array to JSON and store in users table
        $subjectsJson = json_encode($subjects);
        $stmt = $conn->prepare("UPDATE users SET subjects = ? WHERE user_id = ?");
        $stmt->bind_param("si", $subjectsJson, $user_id);
        
        if ($stmt->execute()) {
            $message = "✅ Subjects updated successfully!";
            $alertClass = "alert-success";
        } else {
            $message = "❌ Error updating subjects: " . $conn->error;
            $alertClass = "alert-danger";
        }
        $stmt->close();
    } else {
        $message = "❌ Please select at least one subject.";
        $alertClass = "alert-danger";
    }
}

// Get current user subjects
$stmt = $conn->prepare("SELECT subjects FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($subjectsJson);
$stmt->fetch();
$stmt->close();

$currentSubjects = [];
if (!empty($subjectsJson)) {
    $currentSubjects = json_decode($subjectsJson, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Subjects - HeyTeacher</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        .subject-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
        }
        .subject-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .subject-card.selected {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        .subject-card input[type="checkbox"] {
            display: none;
        }
        .subject-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="mb-3">Manage Your Subjects</h2>
                        <p class="text-muted">Select which subjects you want to analyze and work with.</p>
                        <p class="text-muted small">You can select multiple subjects by clicking on them.</p>
                    </div>

                    <form method="post" action="manage_subjects.php" id="subjectForm">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="subject-card card h-100 text-center p-3 <?= in_array('Physics', $currentSubjects) ? 'selected' : '' ?>" data-subject="Physics">
                                    <input type="checkbox" name="subjects[]" value="Physics" <?= in_array('Physics', $currentSubjects) ? 'checked' : '' ?>>
                                    <div class="subject-icon">⚛️</div>
                                    <h5>Physics</h5>
                                    <p class="small text-muted">Mechanics, Electricity, Waves, Energy</p>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="subject-card card h-100 text-center p-3 <?= in_array('Chemistry', $currentSubjects) ? 'selected' : '' ?>" data-subject="Chemistry">
                                    <input type="checkbox" name="subjects[]" value="Chemistry" <?= in_array('Chemistry', $currentSubjects) ? 'checked' : '' ?>>
                                    <div class="subject-icon">🧪</div>
                                    <h5>Chemistry</h5>
                                    <p class="small text-muted">Atomic Structure, Bonding, Reactions</p>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="subject-card card h-100 text-center p-3 <?= in_array('Biology', $currentSubjects) ? 'selected' : '' ?>" data-subject="Biology">
                                    <input type="checkbox" name="subjects[]" value="Biology" <?= in_array('Biology', $currentSubjects) ? 'checked' : '' ?>>
                                    <div class="subject-icon">🧬</div>
                                    <h5>Biology</h5>
                                    <p class="small text-muted">Cells, Genetics, Ecology, Evolution</p>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
                                Update Subjects
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-lg px-5 ms-2">
                                Back to Dashboard
                            </a>
                        </div>

                        <?php if (!empty($message)): ?>
                            <div class="alert <?php echo $alertClass; ?> mt-4 text-center" role="alert">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const subjectCards = document.querySelectorAll('.subject-card');
    const submitBtn = document.getElementById('submitBtn');
    
    // Handle subject card clicks
    subjectCards.forEach(card => {
        card.addEventListener('click', function() {
            const checkbox = this.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                this.classList.add('selected');
            } else {
                this.classList.remove('selected');
            }
        });
    });
});
</script>

</body>
</html>

<?php $conn->close(); ?>



