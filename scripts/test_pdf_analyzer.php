<?php
/**
 * Test script for PDF Analyzer
 * Run this to verify the system is working
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>PDF Analyzer Test</h1>\n";

// Check if PDFAnalyzer class exists
if (!file_exists('PDFAnalyzer.php')) {
    echo "<p style='color: red;'>❌ PDFAnalyzer.php not found!</p>\n";
    exit;
}

// Include the analyzer
require_once 'PDFAnalyzer.php';

// Check subject_specs directory
$subjectSpecsDir = realpath(__DIR__ . '/../subject_specs');
if (!$subjectSpecsDir || !is_dir($subjectSpecsDir)) {
    echo "<p style='color: red;'>❌ subject_specs directory not found!</p>\n";
    exit;
}

echo "<p>✅ Subject specs directory found: $subjectSpecsDir</p>\n";

// Test subjects
$subjects = ['Physics', 'Biology', 'Chemistry'];
$pdfAnalyzer = new PDFAnalyzer($subjectSpecsDir);

foreach ($subjects as $subject) {
    echo "<h2>Testing $subject</h2>\n";
    
    $examDir = $subjectSpecsDir . '/' . $subject . '/exams';
    if (!is_dir($examDir)) {
        echo "<p style='color: orange;'>⚠️ No exams directory for $subject</p>\n";
        continue;
    }
    
    $examFiles = glob($examDir . '/*.pdf');
    if (empty($examFiles)) {
        echo "<p style='color: orange;'>⚠️ No PDF files found in $subject/exams</p>\n";
        continue;
    }
    
    echo "<p>✅ Found " . count($examFiles) . " PDF files</p>\n";
    
    // Test analysis
    try {
        echo "<p>🔄 Analyzing PDFs...</p>\n";
        $startTime = microtime(true);
        
        $analytics = $pdfAnalyzer->analyzeSubject($subject);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        echo "<p>✅ Analysis completed in {$duration}ms</p>\n";
        
        // Display results
        echo "<h3>Analysis Results:</h3>\n";
        echo "<ul>\n";
        echo "<li>Command words found: " . count($analytics['commandData']) . "</li>\n";
        echo "<li>Topics detected: " . count($analytics['topicData']) . "</li>\n";
        echo "<li>Question types: " . count($analytics['questionTypeData']) . "</li>\n";
        echo "<li>Years covered: " . count($analytics['years']) . "</li>\n";
        echo "<li>Files processed: " . $analytics['totalFiles'] . "</li>\n";
        echo "</ul>\n";
        
        // Show sample data
        if (!empty($analytics['commandData'])) {
            echo "<h4>Top Command Words:</h4>\n";
            echo "<ul>\n";
            foreach (array_slice($analytics['commandData'], 0, 5) as $cmd) {
                echo "<li>{$cmd['command_word']}: {$cmd['frequency']}</li>\n";
            }
            echo "</ul>\n";
        }
        
        if (!empty($analytics['topicData'])) {
            echo "<h4>Top Topics:</h4>\n";
            echo "<ul>\n";
            foreach (array_slice($analytics['topicData'], 0, 5) as $topic) {
                echo "<li>{$topic['topic']}: {$topic['frequency']}</li>\n";
            }
            echo "</ul>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Analysis failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    echo "<hr>\n";
}

// Test individual PDF analysis
echo "<h2>Testing Individual PDF Analysis</h2>\n";
foreach ($subjects as $subject) {
    $examDir = $subjectSpecsDir . '/' . $subject . '/exams';
    if (!is_dir($examDir)) continue;
    
    $examFiles = glob($examDir . '/*.pdf');
    if (empty($examFiles)) continue;
    
    $testFile = $examFiles[0];
    echo "<p>🔄 Testing single PDF: " . basename($testFile) . "</p>\n";
    
    try {
        // Use reflection to test private method
        $reflection = new ReflectionClass('PDFAnalyzer');
        $method = $reflection->getMethod('analyzeSinglePDF');
        $method->setAccessible(true);
        
        $result = $method->invoke($pdfAnalyzer, $testFile);
        
        if ($result) {
            echo "<p>✅ Single PDF analysis successful</p>\n";
            echo "<ul>\n";
            echo "<li>Text length: " . number_format($result['text_length']) . " characters</li>\n";
            echo "<li>Command words: " . count($result['command_words']) . "</li>\n";
            echo "<li>Topics: " . count($result['topics']) . "</li>\n";
            echo "<li>Question types: " . count($result['question_types']) . "</li>\n";
            echo "</ul>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Single PDF analysis returned no data</p>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Single PDF analysis failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    break; // Only test one file
}

echo "<h2>System Check Complete</h2>\n";
echo "<p>If you see any ❌ errors above, please check the setup requirements in README_PDF_Analysis.md</p>\n";
echo "<p>If everything shows ✅, your PDF analyzer is working correctly!</p>\n";
?>

