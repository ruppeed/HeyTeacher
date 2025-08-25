<?php
require 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

$subject = $_POST['subject'] ?? 'Unknown Subject';
$topic = $_POST['topic'] ?? '';
$difficulty = $_POST['difficulty'] ?? '';
$totalQuestions = (int)($_POST['total_questions'] ?? 0);

// Build a comprehensive summary for GPT to grade
$quizSummary = "Please grade this quiz on '$subject'";
if ($topic) $quizSummary .= " (Topic: $topic)";
if ($difficulty) $quizSummary .= " - $difficulty level";
$quizSummary .= ".\n\n";

$quizSummary .= "Student: " . $_SESSION['username'] . "\n";
$quizSummary .= "Total Questions: $totalQuestions\n\n";

// Collect all questions and answers with full text
$questions = [];
$correctCount = 0;
for ($i = 1; $i <= $totalQuestions; $i++) {
    if (isset($_POST["question$i"]) && isset($_POST["q$i"])) {
        $question = $_POST["question$i"];
        $studentAnswerLetter = $_POST["q$i"];
        $studentAnswerText = $_POST["option{$i}_{$studentAnswerLetter}"] ?? $studentAnswerLetter;
        $correctAnswerLetter = $_POST["correct_answer{$i}"] ?? '';
        $correctAnswerText = $_POST["option{$i}_{$correctAnswerLetter}"] ?? '';
        
        $isCorrect = ($studentAnswerLetter === $correctAnswerLetter);
        if ($isCorrect) $correctCount++;
        
        $questions[] = [
            'number' => $i,
            'question' => $question,
            'student_answer_letter' => $studentAnswerLetter,
            'student_answer_text' => $studentAnswerText,
            'correct_answer_letter' => $correctAnswerLetter,
            'correct_answer_text' => $correctAnswerText,
            'is_correct' => $isCorrect
        ];
        
        $quizSummary .= "Question $i: $question\n";
        $quizSummary .= "Student's Answer: $studentAnswerText ($studentAnswerLetter)\n";
        $quizSummary .= "Correct Answer: $correctAnswerText ($correctAnswerLetter)\n";
        $quizSummary .= "Result: " . ($isCorrect ? "CORRECT" : "INCORRECT") . "\n\n";
    }
}

$percentage = round(($correctCount / $totalQuestions) * 100);

$quizSummary .= "Please provide detailed feedback in this EXACT format:\n\n";
$quizSummary .= "1. SCORE: $correctCount out of $totalQuestions ($percentage%)\n\n";
$quizSummary .= "2. QUESTION-BY-QUESTION ANALYSIS:\n";
foreach ($questions as $q) {
    if ($q['is_correct']) {
        $quizSummary .= "Question {$q['number']}: ✓ CORRECT - Well done! You correctly identified that {$q['correct_answer_text']} is the right answer.\n\n";
    } else {
        $quizSummary .= "Question {$q['number']}: ✗ INCORRECT - You selected '{$q['student_answer_text']}' but the correct answer is '{$q['correct_answer_text']}'. ";
        $quizSummary .= "This question tests your understanding of [specific concept]. ";
        $quizSummary .= "To improve, review [specific topic or concept].\n\n";
    }
}

$quizSummary .= "3. OVERALL ASSESSMENT:\n";
$quizSummary .= "Provide 2-3 sentences about the student's overall performance and understanding.\n\n";

$quizSummary .= "4. IMPROVEMENT SUGGESTIONS:\n";
$quizSummary .= "Provide 2-3 specific, actionable suggestions for improvement.\n\n";

$quizSummary .= "5. ENCOURAGEMENT:\n";
$quizSummary .= "End with a positive, encouraging message.\n\n";

$quizSummary .= "Make the feedback specific to the actual questions and answers provided. Be encouraging but honest about areas for improvement.";

// Call OpenAI Assistants API using your specific ChatGPT assistant for grading
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
    'content' => $quizSummary
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

$feedback = '';
$error = '';

if ($messagesHttpCode === 200 && $messagesResponse) {
    $messagesData = json_decode($messagesResponse, true);
    
    if (isset($messagesData['data'][0]['content'][0]['text']['value'])) {
        $feedback = $messagesData['data'][0]['content'][0]['text']['value'];
    } else {
        $error = 'Failed to get grading response from assistant.';
    }
} else {
    $error = "Failed to grade quiz. HTTP Error: $messagesHttpCode.";
    if ($messagesResponse) {
        $errorData = json_decode($messagesResponse, true);
        if (isset($errorData['error']['message'])) {
            $error .= " API Error: " . $errorData['error']['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Results - HeyTeacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/heyteacher.css">
    <style>
        .result-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .score-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 25px;
        }
        .feedback-content {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .question-review {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .answer-comparison {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        .student-answer {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 3px;
            padding: 5px 8px;
            margin: 5px 0;
            display: inline-block;
        }
        .correct-answer-display {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 3px;
            padding: 5px 8px;
            margin: 5px 0;
            display: inline-block;
        }
        .correct-answer {
            color: #28a745;
            font-weight: bold;
        }
        .incorrect-answer {
            color: #dc3545;
            font-weight: bold;
        }
        .result-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 10px;
        }
        .result-correct {
            background: #d4edda;
            color: #155724;
        }
        .result-incorrect {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="result-container">
                    <div class="text-center mb-4">
                        <h2>📊 Quiz Results</h2>
                        <p class="text-muted">
                            Subject: <strong><?php echo htmlspecialchars($subject); ?></strong>
                            <?php if ($topic): ?>
                                | Topic: <strong><?php echo htmlspecialchars($topic); ?></strong>
                            <?php endif; ?>
                            <?php if ($difficulty): ?>
                                | Level: <strong><?php echo htmlspecialchars($difficulty); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <h5>❌ Error</h5>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php else: ?>
                        <!-- Quiz Summary -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">📝 Quiz Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <h4><?php echo $totalQuestions; ?></h4>
                                        <p class="text-muted">Total Questions</p>
                                    </div>
                                    <div class="col-md-4">
                                        <h4><?php echo $_SESSION['username']; ?></h4>
                                        <p class="text-muted">Student</p>
                                    </div>
                                    <div class="col-md-4">
                                        <h4><?php echo date('M j, Y'); ?></h4>
                                        <p class="text-muted">Date Taken</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Question Review Section -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">🔍 Question Review</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($questions as $q): ?>
                                    <div class="question-review">
                                        <h6>
                                            Question <?php echo $q['number']; ?>
                                            <span class="result-indicator <?php echo $q['is_correct'] ? 'result-correct' : 'result-incorrect'; ?>">
                                                <?php echo $q['is_correct'] ? '✓ Correct' : '✗ Incorrect'; ?>
                                            </span>
                                        </h6>
                                        <p class="mb-2"><?php echo htmlspecialchars($q['question']); ?>?</p>
                                        
                                        <div class="answer-comparison">
                                            <div class="mb-2">
                                                <strong>Your Answer:</strong> 
                                                <span class="student-answer">
                                                    <?php echo htmlspecialchars($q['student_answer_text']); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if (!$q['is_correct']): ?>
                                                <!-- Only show correct answer if student got it wrong -->
                                                <div class="mb-2">
                                                    <strong>Correct Answer:</strong> 
                                                    <span class="correct-answer-display">
                                                        <?php echo htmlspecialchars($q['correct_answer_text']); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- AI Teacher Feedback Section -->
                        <div class="feedback-content">
                            <h5>🤖 AI Teacher Feedback</h5>
                            <div style="white-space: pre-wrap; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                <?php echo htmlspecialchars($feedback); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="quiz.php" class="btn btn-primary btn-lg me-3">Create New Quiz</a>
                        <a href="dashboard.php" class="btn btn-secondary btn-lg">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
