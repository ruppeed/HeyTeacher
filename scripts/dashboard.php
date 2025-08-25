<?php
session_start();
require 'config.php';

if (!isset($_SESSION['username'])) {
  header("Location: index.php");
  exit();
}

// Get user's allowed subjects
$userSubjects = [];
$conn = new mysqli("localhost", "root", "mysql", "heyteacher_db");
if (!$conn->connect_error) {
    $stmt = $conn->prepare("SELECT subjects FROM users WHERE username = ?");
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $stmt->bind_result($subjectsJson);
    $stmt->fetch();
    $stmt->close();
    
    if (!empty($subjectsJson)) {
        $userSubjects = json_decode($subjectsJson, true);
    }
    $conn->close();
}

// If no subjects found, default to all subjects
if (empty($userSubjects)) {
    $userSubjects = ['Physics', 'Biology', 'Chemistry'];
}

$responseText = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question'])) {
  $question = trim($_POST['question']);
  
  if (!empty($question)) {
    // Call OpenAI Assistants API using your specific ChatGPT assistant
    $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $OPENAI_API_KEY,
      'OpenAI-Beta: assistants=v2'
    ];

    // First, create a thread
    $threadUrl = 'https://api.openai.com/v1/threads';
    $threadData = [];

    $ch = curl_init($threadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($threadData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $threadResponse = curl_exec($ch);
    $threadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($threadHttpCode !== 200) {
      throw new Exception('Failed to create thread: ' . $threadResponse);
    }

    $threadData = json_decode($threadResponse, true);
    $threadId = $threadData['id'];

    // Add the user's message to the thread
    $messageUrl = 'https://api.openai.com/v1/threads/' . $threadId . '/messages';
    $messageData = [
      'role' => 'user',
      'content' => $question
    ];

    $ch = curl_init($messageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $messageResponse = curl_exec($ch);
    $messageHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($messageHttpCode !== 200) {
      throw new Exception('Failed to add message to thread: ' . $messageResponse);
    }

    // Now run the assistant on the thread
    $runUrl = 'https://api.openai.com/v1/threads/' . $threadId . '/runs';
    $runData = [
      'assistant_id' => $GPT_ASSISTANT_ID
    ];

    $ch = curl_init($runUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($runData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $runResponse = curl_exec($ch);
    $runHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($runHttpCode !== 200) {
      throw new Exception('Failed to run assistant: ' . $runResponse);
    }

    $runData = json_decode($runResponse, true);
    $runId = $runData['id'];

    // Wait for the run to complete and get the response
    $status = 'queued';
    $maxWaitTime = 60; // Maximum wait time in seconds
    $startTime = time();
    
    while ($status !== 'completed' && $status !== 'failed' && (time() - $startTime) < $maxWaitTime) {
      sleep(2); // Wait 2 seconds before checking again
      
      $statusUrl = 'https://api.openai.com/v1/threads/' . $threadId . '/runs/' . $runId;
      $ch = curl_init($statusUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      
      $statusResponse = curl_exec($ch);
      $statusHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      
      if ($statusHttpCode === 200) {
        $statusData = json_decode($statusResponse, true);
        $status = $statusData['status'];
      }
    }

    if ($status !== 'completed') {
      throw new Exception('Assistant run did not complete in time or failed');
    }

    // Get the messages from the thread
    $messagesUrl = 'https://api.openai.com/v1/threads/' . $threadId . '/messages';
    $ch = curl_init($messagesUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $messagesResponse = curl_exec($ch);
    $messagesHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($messagesHttpCode === 200 && $messagesResponse) {
      $messagesData = json_decode($messagesResponse, true);
      
      if (isset($messagesData['data'][0]['content'][0]['text']['value'])) {
        $responseText = $messagesData['data'][0]['content'][0]['text']['value'];
      } else {
        $errorMessage = "Failed to get response from assistant.";
      }
    } else {
      $errorMessage = "Failed to get response. HTTP Error: $messagesHttpCode";
      if ($messagesResponse) {
        $errorData = json_decode($messagesResponse, true);
        if (isset($errorData['error']['message'])) {
          $errorMessage .= " - " . $errorData['error']['message'];
        }
      }
    }
  }
}

// Handle logout
if (isset($_POST['logout'])) {
  session_destroy();
  header("Location: index.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>AI Dashboard - HeyTeacher</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/css/heyteacher.css">
  
  <style>
    .dashboard-container {
      background: white;
      border-radius: 15px;
      padding: 30px;
      margin-top: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .response-box {
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 10px;
      padding: 20px;
      margin-top: 20px;
      white-space: pre-wrap;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
    }
    .welcome-section {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 25px;
      border-radius: 15px;
      margin-bottom: 25px;
      text-align: center;
    }
    .feature-cards {
      margin-bottom: 30px;
    }
    .feature-card {
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .feature-icon {
      font-size: 2.5rem;
      margin-bottom: 15px;
    }
  </style>
</head>
<body class="bg-light">

  <?php include 'header.php'; ?>

  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="dashboard-container">
          
          <!-- Welcome Section -->
          <div class="welcome-section">
            <h2>🎓 Welcome to HeyTeacher, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <p class="mb-0">Your AI-powered educational assistant is ready to help you learn.</p>
            
            <!-- User's Allowed Subjects -->
            <div class="mt-3">
              <p class="mb-2"><strong>Your Subjects:</strong></p>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($userSubjects as $subject): ?>
                  <span class="badge bg-primary fs-6"><?= htmlspecialchars($subject) ?></span>
                <?php endforeach; ?>
              </div>
              <div class="mt-2">
                <a href="manage_subjects.php" class="btn btn-outline-primary btn-sm">
                  <span class="me-1">⚙️</span>Manage Subjects
                </a>
              </div>
            </div>
          </div>

          <!-- Feature Cards -->
          <div class="row feature-cards">
            <div class="col-md-4 mb-3">
              <div class="feature-card">
                <div class="feature-icon">🧪</div>
                <h5>Quiz Generator</h5>
                <p>Create custom quizzes on any subject with AI-generated questions.</p>
                <a href="quiz.php" class="btn btn-primary btn-sm">Get Started</a>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="feature-card">
                <div class="feature-icon">🤖</div>
                <h5>AI Assistant</h5>
                <p>Ask questions and get instant, intelligent answers from GPT.</p>
                <a href="#ai-chat" class="btn btn-primary btn-sm">Ask Now</a>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h5>Progress Tracking</h5>
                <p>Monitor your learning progress and quiz results.</p>
                <a href="reporting.php" class="btn btn-primary btn-sm">View Reports</a>
              </div>
            </div>
          </div>

          <!-- AI Chat Section -->
          <div id="ai-chat" class="card shadow-sm">
            <div class="card-header">
              <h4 class="mb-0">🤖 Ask the AI Assistant</h4>
            </div>
            <div class="card-body">
              <p class="card-text">Ask any educational question and get an intelligent response:</p>

              <form method="post">
                <div class="mb-3">
                  <label for="question" class="form-label">Your Question</label>
                  <textarea name="question" id="question" class="form-control" rows="4" 
                            placeholder="e.g., Explain photosynthesis, How do atoms bond?, What is the Pythagorean theorem?" 
                            required><?php echo isset($_POST['question']) ? htmlspecialchars($_POST['question']) : ''; ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                  <span class="me-2">🚀</span>Ask AI
                </button>
              </form>

              <?php if ($errorMessage): ?>
                <div class="alert alert-danger mt-3">
                  <strong>❌ Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
              <?php endif; ?>

              <?php if ($responseText): ?>
                <div class="mt-4">
                  <h5>🤖 AI Response:</h5>
                  <div class="response-box"><?php echo htmlspecialchars($responseText); ?></div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="text-center mt-4">
            <a href="quiz.php" class="btn btn-success btn-lg me-3">
              <span class="me-2">📝</span>Create New Quiz
            </a>
            <a href="test_api.php" class="btn btn-info btn-lg me-3">
              <span class="me-2">🧪</span>Test API
            </a>
            <form method="post" class="d-inline">
              <button type="submit" name="logout" class="btn btn-outline-danger btn-lg">
                <span class="me-2">🚪</span>Log Out
              </button>
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
