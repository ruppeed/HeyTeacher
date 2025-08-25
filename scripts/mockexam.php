<?php
session_start();
require_once 'config.php';

// Surface PHP errors during debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

// Give this script more time for AI generation (default is 30s)
// Coordinated timeout values for consistent behavior
set_time_limit(300); // PHP execution time limit (5 minutes)

$subjects = [
    'Physics' => 'Physics',
    'Biology' => 'Biology', 
    'Chemistry' => 'Chemistry',
    'Art and Design' => 'Art and Design',
    'Latin' => 'Latin',
    'Classical Greek' => 'Classical Greek',
    'Chinese' => 'Chinese',
    'Extended Project' => 'Extended Project'
];

$exam_content = '';
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_subject = $_POST['subject'] ?? '';
    
    if (empty($selected_subject)) {
        $error_message = 'Please select a subject.';
    } else {
        try {
            // Create prompt for mock exam generation
                         $prompt = "Create a comprehensive GCSE style mock exam for $selected_subject. The exam should include:
             
1. A main title at the top in the format: '$selected_subject IGCSE MOCK' (no hashtags, just plain text)
2. Immediately after the title, include test information on the first page:
   - Time allowed (e.g., 'Time: 1 hour 30 minutes')
   - Total marks available (e.g., 'Total marks: 60')
   - Instructions for students (e.g., 'Instructions: Answer all questions in the spaces provided')
3. Multiple choice questions (10 questions) - include [A] [B] [C] [D] options for students to circle, with marks shown next to each question (e.g., 'Question 1 [2 marks]')
4. Short answer questions (5 questions) - include lines like 'Answer: _________________' for students to write in, with marks shown next to each question (e.g., 'Question 11 [3 marks]')
5. Essay/long answer questions (2 questions) - include large blank spaces like 'Answer: 

_________________________________________________________________________________
_________________________________________________________________________________
_________________________________________________________________________________
_________________________________________________________________________________
_________________________________________________________________________________

' for students to write their essays, with marks shown next to each question (e.g., 'Question 16 [10 marks]')

Format the exam in a clear, professional manner suitable for students. Include realistic questions that test different levels of understanding. Make sure to include text boxes, lines, and spaces where students can write their answers. Do NOT include a mark scheme or answer key.";

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
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $threadResponse = curl_exec($ch);
            $threadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception('cURL error creating thread: ' . $curlError);
            }
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $messageResponse = curl_exec($ch);
            $messageHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception('cURL error adding message: ' . $curlError);
            }
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $runResponse = curl_exec($ch);
            $runHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception('cURL error running assistant: ' . $curlError);
            }
            if ($runHttpCode !== 200) {
                throw new Exception('Failed to run assistant: ' . $runResponse);
            }

            $runData = json_decode($runResponse, true);
            $runId = $runData['id'];

            // Wait for the run to complete and get the response
            $status = 'queued';
            $maxWaitTime = 60; // Maximum wait time in seconds (matching quiz.php)
            $startTime = time();
            
            while ($status !== 'completed' && $status !== 'failed' && (time() - $startTime) < $maxWaitTime) {
                sleep(2); // Wait 2 seconds before checking again
                
                $statusUrl = 'https://api.openai.com/v1/threads/' . $threadId . '/runs/' . $runId;
                $ch = curl_init($statusUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                
                $statusResponse = curl_exec($ch);
                $statusHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($curlError) {
                    throw new Exception('cURL error checking status: ' . $curlError);
                }
                if ($statusHttpCode === 200) {
                    $statusData = json_decode($statusResponse, true);
                    $status = $statusData['status'];
                }
            }

            if ($status === 'completed') {
                // Get the messages from the thread
                $messagesUrl = 'https://api.openai.com/v1/threads/' . $threadId . '/messages';
                $ch = curl_init($messagesUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                
                $messagesResponse = curl_exec($ch);
                $messagesHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($curlError) {
                    throw new Exception('cURL error retrieving messages: ' . $curlError);
                }
                if ($messagesHttpCode === 200) {
                    $messagesData = json_decode($messagesResponse, true);
                    if (isset($messagesData['data'][0]['content'][0]['text']['value'])) {
                        $exam_content = $messagesData['data'][0]['content'][0]['text']['value'];
                        $success_message = "Mock exam generated successfully for $selected_subject!";
                    } else {
                        throw new Exception('No content found in assistant response');
                    }
                } else {
                    throw new Exception('Failed to retrieve messages: ' . $messagesResponse);
                }
            } else if ($status === 'failed') {
                throw new Exception('Assistant run failed. Please try again.');
            } else {
                throw new Exception('Assistant run timed out after ' . $maxWaitTime . ' seconds. Please try again.');
            }
            
        } catch (Exception $e) {
            $error_message = "Error generating exam: " . $e->getMessage();
            // Log the error for debugging (optional)
            error_log("Mock exam generation error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mock Exam Generator - HeyTeacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/heyteacher.css" rel="stylesheet">
    <style>
        .exam-content {
            background: white;
            border: 2px solid #000;
            border-radius: 0;
            padding: 40px;
            margin: 20px 0;
            white-space: pre-wrap;
            font-family: 'Times New Roman', serif;
            line-height: 1.8;
            font-size: 14px;
            color: #000;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        /* GCSE Paper Header Styling */
        .exam-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #f0f0f0;
            border-bottom: 2px solid #000;
        }
        
        /* Question Numbering */
        .exam-content h1, .exam-content h2, .exam-content h3 {
            color: #000;
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        /* Main Title Styling */
        .exam-content h3:first-of-type,
        .exam-content h1:first-of-type,
        .exam-content h2:first-of-type {
            font-size: 28px;
            font-weight: 900;
            text-align: center;
            margin: 20px 0 30px 0;
            color: #000;
        }

        /* Explicit title element for plain-text outputs */
        .exam-title {
            display: block;
            font-size: 28px;
            font-weight: 900;
            text-align: center;
            margin: 10px 0 30px 0;
            color: #000;
        }
        
        /* Multiple Choice Options */
        .exam-content .mcq-option {
            margin: 8px 0;
            padding-left: 20px;
        }
        
        /* Answer Lines */
        .exam-content .answer-line {
            border-bottom: 1px solid #000;
            min-width: 200px;
            display: inline-block;
            margin: 0 5px;
        }
        
        /* Essay Answer Spaces */
        .exam-content .essay-space {
            border: 1px solid #ccc;
            min-height: 100px;
            margin: 10px 0;
            padding: 10px;
            background: #fafafa;
        }
        
        /* GCSE Paper Specific Styling */
        .exam-content .question-number {
            font-weight: bold;
            color: #000;
            margin-right: 10px;
        }
        
        .exam-content .marks-indicator {
            font-style: italic;
            color: #666;
            margin-left: 10px;
        }
        
        .exam-content .section-header {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin: 30px 0 20px 0;
        }
        
        /* Print Styles */
        @media print {
            .exam-content {
                border: 2px solid #000;
                box-shadow: none;
                page-break-inside: avoid;
            }
            
            .exam-content::before {
                display: none;
            }
            
            .section-header {
                border-bottom: 2px solid #000;
            }
        }
        .download-btn {
            margin: 10px 5px;
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
            backdrop-filter: blur(2px);
        }
        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        .spinner {
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
<body>
    <?php include 'header.php'; ?>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h4>Generating Mock Exam...</h4>
            <p>This may take a few moments. Please wait.</p>
        </div>
    </div>
    
    <div class="container mt-5 pt-5">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Mock Exam Generator</h1>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Generate New Mock Exam</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="mockExamForm" action="mockexam.php">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Select Subject:</label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Choose a subject...</option>
                                    <?php foreach ($subjects as $key => $subject): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="generate_exam" value="1">
                            <button type="submit" name="generate_exam" class="btn btn-primary" id="generateBtn">
                                Generate Mock Exam
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php if ($exam_content): ?>
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Generated Mock Exam</h5>
                            <div>
                                <button class="btn btn-success download-btn" onclick="downloadAsText()">
                                    Download as Text
                                </button>
                                <button class="btn btn-info download-btn" onclick="printExam()">
                                    Print Exam
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="exam-content" id="examContent">
                                <?php 
                                // Clean hashtags from the content
                                $clean_content = preg_replace('/^#{1,6}\s*/m', '', $exam_content);
                                
                                // Split into lines and find first non-empty line
                                $lines = preg_split("/\r\n|\r|\n/", $clean_content);
                                $first_index = null;
                                for ($i = 0; $i < count($lines); $i++) {
                                    if (trim($lines[$i]) !== '') { $first_index = $i; break; }
                                }
                                
                                // Escape each line except the first non-empty which is the title
                                $processed = [];
                                for ($i = 0; $i < count($lines); $i++) {
                                    if ($first_index !== null && $i === $first_index) {
                                        $processed[] = '<span class="exam-title">' . htmlspecialchars(trim($lines[$i])) . '</span>';
                                    } else {
                                        $processed[] = htmlspecialchars($lines[$i]);
                                    }
                                }
                                echo nl2br(implode("\n", $processed));
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadAsText() {
            const content = document.getElementById('examContent').innerText;
            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'mock_exam.txt';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
        
        function printExam() {
            const printContent = document.getElementById('examContent').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Mock Exam</title>
                        <style>
                            body { 
                                font-family: 'Times New Roman', serif; 
                                line-height: 1.8; 
                                margin: 20px; 
                                font-size: 14px;
                                color: #000;
                            }
                            .exam-content { 
                                white-space: pre-wrap; 
                                background: white;
                                border: 2px solid #000;
                                padding: 40px;
                                line-height: 1.8;
                                font-size: 14px;
                            }
                            .exam-content::before {
                                content: '';
                                position: absolute;
                                top: 0;
                                left: 0;
                                right: 0;
                                height: 60px;
                                background: #f0f0f0;
                                border-bottom: 2px solid #000;
                            }
                            h1, h2, h3 {
                                color: #000;
                                font-weight: bold;
                                margin-top: 30px;
                                margin-bottom: 15px;
                                font-size: 16px;
                            }
                            
                            /* Main title styling for print */
                            h1:first-of-type, h2:first-of-type, h3:first-of-type, .exam-title:first-of-type {
                                font-size: 28px !important;
                                font-weight: 900 !important;
                                text-align: center !important;
                                margin: 20px 0 30px 0 !important;
                                color: #000 !important;
                            }
                            
                            @media print {
                                .exam-content {
                                    border: 2px solid #000;
                                    page-break-inside: avoid;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="exam-content">${printContent}</div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Show loading screen when form is submitted
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('mockExamForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (!form || !loadingOverlay) return;

            form.addEventListener('submit', function(e) {
                const subject = document.getElementById('subject').value;
                if (!subject) return;
                
                // Show overlay and let form submit naturally - no infinite loop
                loadingOverlay.style.display = 'flex';
                
                // Keep button enabled to preserve POST field order and values
            });
        });
    </script>
</body>
</html>
