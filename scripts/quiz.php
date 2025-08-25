<?php
require 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create a Quiz - HeyTeacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/heyteacher.css">
    <style>
        .quiz-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .question-block {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        .option {
            margin: 8px 0;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background-color 0.2s;
        }
        .option:hover {
            background-color: #e9ecef;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'header.php'; ?>
    
    <!-- Loading Overlay for Quiz Generation -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h4>Generating Quiz...</h4>
            <p>This may take a few moments. Please wait.</p>
        </div>
    </div>
    
    <!-- Loading Overlay for Quiz Submission -->
    <div class="loading-overlay" id="submissionLoadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h4>Submitting Quiz...</h4>
            <p>Grading your quiz with AI. Please wait.</p>
        </div>
    </div>

    <div class="container py-5">
        <h1 class="text-center mb-4">Custom Quiz Generator</h1>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <form method="POST" id="quizForm">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Select Subject</label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Choose a subject...</option>
                                    <option value="Biology">Biology</option>
                                    <option value="Chemistry">Chemistry</option>
                                    <option value="Physics">Physics</option>
                                    <option value="Mathematics">Mathematics</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="History">History</option>
                                    <option value="Geography">Geography</option>
                                    <option value="Literature">Literature</option>
                                    <option value="Art">Art</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="topic" class="form-label">Specific Topic (Optional)</label>
                                <input type="text" class="form-control" id="topic" name="topic" 
                                       placeholder="e.g., Cell Biology, Organic Chemistry, Mechanics...">
                            </div>

                            <div class="mb-3">
                                <label for="difficulty" class="form-label">Difficulty Level</label>
                                <select class="form-select" id="difficulty" name="difficulty" required>
                                    <option value="">Choose difficulty...</option>
                                    <option value="Beginner">Beginner</option>
                                    <option value="Intermediate">Intermediate</option>
                                    <option value="Advanced">Advanced</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="amount" class="form-label">Number of Questions</label>
                                <select class="form-select" id="amount" name="amount" required>
                                    <option value="">Choose...</option>
                                    <?php for ($i = 5; $i <= 20; $i += 5): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100" id="generateBtn">
                                <span id="btnText">Generate Quiz</span>
                                <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                            </button>
                        </form>
                    </div>
                </div>

                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $subject = htmlspecialchars($_POST['subject']);
                    $topic = htmlspecialchars($_POST['topic'] ?? '');
                    $difficulty = htmlspecialchars($_POST['difficulty']);
                    $amount = (int)$_POST['amount'];

                    // Create a comprehensive prompt for GPT
                    $topicText = $topic ? " specifically on '$topic'" : "";
                    $prompt = "Create a $difficulty level quiz with $amount multiple choice questions about $subject$topicText. 

                    CRITICAL: Use this EXACT format with each question on separate lines:

                    Question 1: [Question text here]?
                    A) [Option A text]
                    B) [Option B text] 
                    C) [Option C text]
                    D) [Option D text]
                    Correct Answer: A

                    Question 2: [Question text here]?
                    A) [Option A text]
                    B) [Option B text] 
                    C) [Option C text]
                    D) [Option D text]
                    Correct Answer: B

                    Continue this exact format for all questions.

                    Requirements:
                    - Questions are appropriate for $difficulty level
                    - All options are plausible but only one is correct
                    - Questions test understanding, not just memorization
                    - Use clear, concise language
                    - Include the question mark at the end of each question
                    - Number questions sequentially starting from 1
                    - MUST use 'Question X:' format (not just numbers)
                    - MUST put each option on a separate line with A) B) C) D)
                    - MUST include 'Correct Answer: [Letter]' on a separate line
                    - Put a blank line between each question
                    - Do not combine questions and options on the same line";

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
                        'content' => $prompt
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
                            $quizContent = $messagesData['data'][0]['content'][0]['text']['value'];
                            
                            // Parse the quiz content with flexible parsing
                            $lines = explode("\n", $quizContent);
                            $questions = [];
                            $currentQuestion = null;
                            $currentOptions = [];
                            $correctAnswers = [];
                            
                            // More flexible question detection patterns
                            $questionPatterns = [
                                '/^Question\s*(\d+)[:\.]\s*(.+?)(?:\?|$)/i',
                                '/^(\d+)[:\.]\s*(.+?)(?:\?|$)/i',
                                '/^Q(\d+)[:\.]\s*(.+?)(?:\?|$)/i',
                                '/^(\d+)\)\s*(.+?)(?:\?|$)/i'
                            ];
                            
                            // Enhanced option detection patterns to handle various formats
                            $optionPatterns = [
                                '/^[A-D][:\.\)]\s*(.+?)(?=\s*[A-D][:\.\)]|\s*Correct\s+Answer|\s*$)/i',
                                '/^[A-D][:\.\)]\s*(.+?)(?:\s*$|$)/i',
                                '/^[A-D]\)\s*(.+?)(?:\s*$|$)/i',
                                '/^[A-D]\.\s*(.+?)(?:\s*$|$)/i'
                            ];
                            
                            // More flexible option detection patterns
                            $optionPatterns = [
                                '/^[A-D][:\.\)]\s*(.+?)(?:\s*$|$)/i',
                                '/^[A-D]\)\s*(.+?)(?:\s*$|$)/i',
                                '/^[A-D]\.\s*(.+?)(?:\s*$|$)/i'
                            ];
                            
                            // More flexible correct answer detection patterns
                            $answerPatterns = [
                                '/correct\s+answer[^:]*:\s*([A-D])/i',
                                '/answer[^:]*:\s*([A-D])/i',
                                '/correct[^:]*:\s*([A-D])/i',
                                '/key[^:]*:\s*([A-D])/i'
                            ];
                            
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (empty($line)) continue;
                                
                                // Try to match question patterns
                                $questionFound = false;
                                foreach ($questionPatterns as $pattern) {
                                    if (preg_match($pattern, $line, $matches)) {
                                        // If we have a previous question, save it first
                                        if ($currentQuestion && !empty($currentOptions)) {
                                            $currentQuestion['options'] = $currentOptions;
                                            $questions[] = $currentQuestion;
                                        }
                                        
                                        // Clean up the question text - remove any options that might be included
                                        $questionText = trim($matches[2]);
                                        
                                        // Remove any options that might be included in the question text
                                        $questionText = preg_replace('/\s*[A-D][:\.\)]\s*[^A-D]*/i', '', $questionText);
                                        $questionText = preg_replace('/\s*Correct\s+Answer\s*:\s*[A-D]/i', '', $questionText);
                                        $questionText = trim($questionText, '? ');
                                        
                                        // Start new question
                                        $currentQuestion = [
                                            'number' => $matches[1],
                                            'question' => $questionText,
                                            'options' => []
                                        ];
                                        $currentOptions = [];
                                        $questionFound = true;
                                        break;
                                    }
                                }
                                
                                if ($questionFound) continue;
                                
                                // Try to match option patterns
                                $optionFound = false;
                                foreach ($optionPatterns as $pattern) {
                                    if (preg_match($pattern, $line, $matches)) {
                                        if ($currentQuestion) {
                                            $currentOptions[] = trim($matches[1]);
                                            $optionFound = true;
                                            break;
                                        }
                                    }
                                }
                                
                                if ($optionFound) continue;
                                
                                // Try to match correct answer patterns
                                foreach ($answerPatterns as $pattern) {
                                    if (preg_match($pattern, $line, $matches)) {
                                        if ($currentQuestion) {
                                            $correctAnswers[$currentQuestion['number']] = strtoupper($matches[1]);
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            // Debug: Show what we found
                            if (empty($questions)) {
                                echo '<div class="alert alert-info mt-4">';
                                echo '<strong>Parsing Debug:</strong><br>';
                                echo 'Total lines: ' . count($lines) . '<br>';
                                echo 'First 10 lines:<br>';
                                for ($i = 0; $i < min(10, count($lines)); $i++) {
                                    echo ($i + 1) . ': "' . htmlspecialchars($lines[$i]) . '"<br>';
                                }
                                echo '</div>';
                            }
                            
                            // Don't forget to add the last question
                            if ($currentQuestion && !empty($currentOptions)) {
                                $currentQuestion['options'] = $currentOptions;
                                $questions[] = $currentQuestion;
                            }
                            
                            // If we still don't have questions, try alternative parsing
                            if (empty($questions)) {
                                // Try to find questions in a different format
                                $content = strtolower($quizContent);
                                if (strpos($content, 'question') !== false || strpos($content, '1.') !== false) {
                                    // Look for numbered items that might be questions
                                    preg_match_all('/(\d+)[:\.\)]\s*(.+?)(?=\n\d+[:\.\)]|\n\n|$)/s', $quizContent, $matches, PREG_SET_ORDER);
                                    
                                    foreach ($matches as $match) {
                                        $questionText = trim($match[2]);
                                        if (strlen($questionText) > 10) { // Likely a question if it's long enough
                                            $questions[] = [
                                                'number' => $match[1],
                                                'question' => $questionText,
                                                'options' => ['Option A', 'Option B', 'Option C', 'Option D'] // Default options
                                            ];
                                            $correctAnswers[$match[1]] = 'A'; // Default correct answer
                                        }
                                    }
                                }
                                
                                // If still no questions, try parsing the entire content as a single block
                                if (empty($questions)) {
                                    // Look for patterns like "Question 1: [question] A) [option] B) [option] C) [option] D) [option] Correct Answer: [letter]"
                                    preg_match_all('/Question\s*(\d+)[:\.]\s*(.+?)(?=Question\s*\d+[:\.]|Correct\s+Answer|$)/is', $quizContent, $matches, PREG_SET_ORDER);
                                    
                                    foreach ($matches as $match) {
                                        $fullQuestionBlock = $match[2];
                                        
                                        // Extract the question text (before the first option)
                                        $questionText = preg_replace('/\s*[A-D][:\.\)].*$/s', '', $fullQuestionBlock);
                                        $questionText = trim($questionText, '? ');
                                        
                                        // Extract options
                                        preg_match_all('/[A-D][:\.\)]\s*([^A-D]*?)(?=[A-D][:\.\)]|Correct\s+Answer|$)/is', $fullQuestionBlock, $optionMatches, PREG_SET_ORDER);
                                        $options = [];
                                        foreach ($optionMatches as $optionMatch) {
                                            $options[] = trim($optionMatch[1]);
                                        }
                                        
                                        // Extract correct answer
                                        preg_match('/Correct\s+Answer[^:]*:\s*([A-D])/i', $fullQuestionBlock, $answerMatch);
                                        $correctAnswer = isset($answerMatch[1]) ? strtoupper($answerMatch[1]) : 'A';
                                        
                                        if (!empty($questionText) && count($options) >= 2) {
                                            $questions[] = [
                                                'number' => $match[1],
                                                'question' => $questionText,
                                                'options' => $options
                                            ];
                                            $correctAnswers[$match[1]] = $correctAnswer;
                                        }
                                    }
                                }
                            }
                            
                            // Debug output for troubleshooting
                            if (empty($questions)) {
                                echo '<div class="alert alert-info mt-4">';
                                echo '<strong>Debug Info:</strong><br>';
                                echo 'Lines processed: ' . count($lines) . '<br>';
                                echo 'Content preview: ' . htmlspecialchars(substr($quizContent, 0, 200)) . '...<br>';
                                echo '</div>';
                            }

                            if (!empty($questions)) {
                                echo '<div class="quiz-container mt-4">';
                                echo "<h3 class='mb-4'>Generated Quiz: $subject";
                                if ($topic) echo " - $topic";
                                echo " ($difficulty Level)</h3>";
                                
                                echo '<form method="POST" action="submit_quiz.php">';
                                echo "<input type='hidden' name='subject' value='" . htmlspecialchars($subject, ENT_QUOTES) . "'>";
                                echo "<input type='hidden' name='topic' value='" . htmlspecialchars($topic, ENT_QUOTES) . "'>";
                                echo "<input type='hidden' name='difficulty' value='" . htmlspecialchars($difficulty, ENT_QUOTES) . "'>";
                                echo "<input type='hidden' name='total_questions' value='" . count($questions) . "'>";

                                foreach ($questions as $q) {
                                    echo '<div class="question-block">';
                                    echo "<h5>Question {$q['number']}: {$q['question']}?</h5>";
                                    echo "<input type='hidden' name='question{$q['number']}' value='" . htmlspecialchars($q['question'], ENT_QUOTES) . "'>";
                                    
                                    if (empty($q['options'])) {
                                        echo '<div class="alert alert-warning">No options found for this question!</div>';
                                    } else {
                                        foreach ($q['options'] as $index => $option) {
                                            $optionLetter = chr(65 + $index); // A, B, C, D
                                            echo "<div class='option'>";
                                            echo "<input type='radio' class='form-check-input' name='q{$q['number']}' value='$optionLetter' id='q{$q['number']}_{$optionLetter}' required>";
                                            echo "<label class='form-check-label ms-2' for='q{$q['number']}_{$optionLetter}'>$optionLetter) $option</label>";
                                            echo "</div>";
                                            
                                            // Store the full option text for later comparison
                                            echo "<input type='hidden' name='option{$q['number']}_{$optionLetter}' value='" . htmlspecialchars($option, ENT_QUOTES) . "'>";
                                        }
                                    }
                                    
                                    // Store the correct answer
                                    if (isset($correctAnswers[$q['number']])) {
                                        echo "<input type='hidden' name='correct_answer{$q['number']}' value='" . $correctAnswers[$q['number']] . "'>";
                                    } else {
                                        echo '<div class="alert alert-warning">No correct answer found for this question!</div>';
                                    }
                                    
                                    echo '</div>';
                                }

                                echo '<div class="text-center mt-4">';
                                echo '<button type="submit" class="btn btn-success btn-lg px-5">Submit Quiz for Grading</button>';
                                echo '</div>';
                                echo '</form>';
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-warning mt-4">Quiz generated but could not parse the questions properly. Please try again.</div>';
                                echo '<div class="mt-3"><strong>Raw response:</strong><pre>' . htmlspecialchars($quizContent) . '</pre></div>';
                            }
                        } else {
                            echo '<div class="alert alert-danger mt-4">Failed to generate quiz content from GPT response.</div>';
                        }
                    } else {
                        echo '<div class="alert alert-danger mt-4">';
                        echo 'Failed to generate quiz. ';
                        if ($httpCode !== 200) {
                            echo "HTTP Error: $httpCode. ";
                        }
                        if ($response) {
                            $errorData = json_decode($response, true);
                            if (isset($errorData['error']['message'])) {
                                echo "API Error: " . htmlspecialchars($errorData['error']['message']);
                            }
                        }
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('quizForm').addEventListener('submit', function() {
            const btn = document.getElementById('generateBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            // Show button loading state
            btn.disabled = true;
            btnText.textContent = 'Generating Quiz...';
            btnSpinner.classList.remove('d-none');
            
            // Show full screen loading overlay
            loadingOverlay.style.display = 'flex';
        });
        
        // Handle quiz submission form loading
        document.addEventListener('DOMContentLoaded', function() {
            // Find all quiz submission forms (they're generated dynamically)
            document.addEventListener('submit', function(e) {
                if (e.target.action && e.target.action.includes('submit_quiz.php')) {
                    const submissionLoadingOverlay = document.getElementById('submissionLoadingOverlay');
                    if (submissionLoadingOverlay) {
                        submissionLoadingOverlay.style.display = 'flex';
                    }
                }
            });
        });
    </script>
</body>
</html>
