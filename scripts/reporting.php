<!DOCTYPE html>
<html>
<head>
    <title>GCSE Science Revision Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }
        .kpi-value { font-size: 1.8rem; font-weight: 700; }
        .kpi-label { color: #6c757d; font-size: .9rem; }
        .dashboard-card { border-radius: 14px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .chart-wrap { position: relative; height: 340px; }
        .section-title { font-size: 1.1rem; font-weight: 600; }
        .sidebar-sticky { position: sticky; top: 90px; max-height: calc(100vh - 120px); overflow-y: auto; }
        .subject-selector {
            background: #007bff; /* clean blue */
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: white;
        }
        .subject-selector select {
            background: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            color: #333;
        }
        .exam-file {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 0.6rem 0.75rem;
            margin-bottom: 0.5rem;
        }
        .exam-file:hover { background: #eef2f7; }
    </style>
</head>
<body class="bg-light">
    <?php 
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['username'])) {
        header("Location: index.php");
        exit();
    }
    
    include 'header.php'; 
    ?>

    <?php
    // Include the PDF analyzer
    require_once 'PDFAnalyzer.php';
    
    // Get user's allowed subjects from database
    $userSubjects = [];
    if (isset($_SESSION['username'])) {
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
    }
    
    // If no subjects found, default to all subjects
    if (empty($userSubjects)) {
        $userSubjects = ['Physics', 'Biology', 'Chemistry'];
    }
    
    // Selected subject (define BEFORE HTML usage)
    $selectedSubject = isset($_GET['subject']) && in_array($_GET['subject'], $userSubjects)
        ? $_GET['subject']
        : $userSubjects[0];

    // Build list of all PDFs in subject_specs (for sidebar)
    $pdfIndex = [];
    $subjectSpecsDir = realpath(__DIR__ . '/../subject_specs');
    if ($subjectSpecsDir && is_dir($subjectSpecsDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($subjectSpecsDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) { continue; }
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $absPath = $file->getPathname();
                $relative = str_replace('\\', '/', substr($absPath, strlen($subjectSpecsDir)));
                if (substr($relative, 0, 1) === '/') { $relative = substr($relative, 1); }
                $webPath = '/subject_specs/' . $relative; // path from web root
                $subject = basename(dirname($absPath));
                $pdfIndex[] = [
                    'subject' => $subject,
                    'name' => $file->getFilename(),
                    'path' => $webPath,
                ];
            }
        }
    }
    usort($pdfIndex, function($a, $b) {
        return ($a['subject'] === $b['subject'])
            ? strcasecmp($a['name'], $b['name'])
            : strcasecmp($a['subject'], $b['subject']);
    });

    // Analyze exam PDFs for selected subject
    $examFiles = [];
    $examDir = $subjectSpecsDir ? ($subjectSpecsDir . '/' . $selectedSubject . '/exams') : null;
    if ($examDir && is_dir($examDir)) {
        $dirIt = new DirectoryIterator($examDir);
        foreach ($dirIt as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'pdf') {
                $examFiles[] = [
                    'name' => $f->getFilename(),
                    'size' => $f->getSize(),
                    'mtime' => $f->getMTime(),
                ];
            }
        }
    }

    // Use real PDF analysis instead of synthetic data
    $analysisStatus = '';
    $pdfAnalyzer = new PDFAnalyzer($subjectSpecsDir);
    
    try {
        $realAnalytics = $pdfAnalyzer->analyzeSubject($selectedSubject);
        $analysisStatus = 'success';
        
        // Extract real analytics data
        $commandData = $realAnalytics['commandData'];
        $topicData = $realAnalytics['topicData'];
        $marksData = $realAnalytics['marksData'];
        $questionTypeData = $realAnalytics['questionTypeData'];
        $markWeightData = $realAnalytics['markWeightData'];
        $bloomsData = $realAnalytics['bloomsData'];
        $errorData = $realAnalytics['errorData'];
        $uniqueYears = $realAnalytics['years'];
        
        // Years label for display
        $yearsLabel = count($uniqueYears) ? (min($uniqueYears) . ' - ' . max($uniqueYears)) : 'N/A';
        
    } catch (Exception $e) {
        // Fallback to empty data if analysis fails
        $analysisStatus = 'error';
        error_log("PDF Analysis Error: " . $e->getMessage());
        
        $commandData = [];
        $topicData = [];
        $marksData = [];
        $questionTypeData = [];
        $markWeightData = [];
        $bloomsData = [];
        $errorData = [];
        $uniqueYears = [];
        $yearsLabel = 'N/A';
    }

    // KPIs based on real data
    $totalExams = count($examFiles);
    $topicCount = count($topicData);
    $totalMarks = array_sum(array_map(fn($m) => (int)$m['total_marks'], $marksData));
    $avgMarks = $topicCount ? round($totalMarks / $topicCount, 1) : 0;
    ?>

    <div class="container py-5">
        <div class="text-center mb-4">
            <h1 class="mb-1">GCSE <?= htmlspecialchars($selectedSubject) ?> Revision Dashboard</h1>
            <p class="text-muted mb-0">Interactive insights across topics, years and question types</p>
            
            <?php if ($analysisStatus === 'success'): ?>
                <div class="alert alert-success mt-3 d-inline-block" role="alert">
                    <i class="bi bi-check-circle"></i> Real-time PDF analysis completed
                </div>
            <?php elseif ($analysisStatus === 'error'): ?>
                <div class="alert alert-warning mt-3 d-inline-block" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> PDF analysis failed - showing limited data
                </div>
            <?php endif; ?>
        </div>

        <!-- Subject Selector -->
        <div class="subject-selector">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3 class="mb-2">Select Subject to Analyze</h3>
                    <p class="mb-0 opacity-75">Choose a science subject to view detailed analytics and insights</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <form method="GET" action="" id="subjectForm">
                        <select name="subject" id="subjectSelect" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($userSubjects as $subject): ?>
                                <option value="<?= htmlspecialchars($subject) ?>" <?= $selectedSubject === $subject ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-9 order-lg-1">
            <!-- KPI Row -->
            <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card kpi-card p-3 h-100">
                    <div class="kpi-value"><?= number_format($totalExams) ?></div>
                    <div class="kpi-label">Exam Papers Available</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card kpi-card p-3 h-100">
                    <div class="kpi-value"><?= number_format($topicCount) ?></div>
                    <div class="kpi-label">Topics Covered</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card kpi-card p-3 h-100">
                    <div class="kpi-value"><?= number_format($avgMarks) ?></div>
                    <div class="kpi-label">Avg Marks per Topic</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card kpi-card p-3 h-100">
                    <div class="kpi-value"><?= htmlspecialchars($yearsLabel) ?></div>
                    <div class="kpi-label">Years Covered</div>
                </div>
            </div>
            </div>

            <!-- Charts Grid -->
            <div class="row g-4">
            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="section-title">Most Common Command Words</div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="commandChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="section-title">Most Frequent Topics</div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="topicChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="section-title">Total Marks by Topic</div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="marksChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="section-title">Question Type Distribution</div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="questionTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="section-title">Mark Weighting per Topic (by Year)</div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="markWeightingChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="section-title">Bloom's Taxonomy Classification</div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="bloomsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="section-title mb-2">Common Student Errors and Fixes</div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($errorData as $error): ?>
                                <li class="list-group-item">
                                    <strong><?= htmlspecialchars($error['error_description']) ?>:</strong>
                                    <?= htmlspecialchars($error['suggested_fix']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="col-lg-3 order-lg-2">
            <div class="card dashboard-card sidebar-sticky">
              <div class="card-body">
                <div class="section-title mb-2">Available Exam Papers (<?= htmlspecialchars($selectedSubject) ?>)</div>
                <?php if (empty($examFiles)): ?>
                    <div class="text-muted small">No exam PDFs found in <code>subject_specs/<?= htmlspecialchars($selectedSubject) ?>/exams</code>.</div>
                <?php else: ?>
                    <?php foreach ($examFiles as $exam): ?>
                        <div class="exam-file d-flex justify-content-between align-items-center">
                            <div class="small fw-semibold"><?= htmlspecialchars($exam['name']) ?></div>
                            <div class="text-muted small"><?= number_format($exam['size']/1024, 1) ?> KB</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <hr class="my-3">

                <div class="section-title mb-2">Subject Specifications</div>
                <?php if (empty($pdfIndex)): ?>
                    <div class="text-muted small">No PDFs found in <code>/subject_specs</code>.</div>
                <?php else: ?>
                    <?php $currentSubject = null; ?>
                    <?php foreach ($pdfIndex as $pdf): ?>
                        <?php if ($currentSubject !== $pdf['subject']): ?>
                            <?php if ($currentSubject !== null): ?></ul><?php endif; ?>
                            <div class="text-uppercase text-muted small fw-bold mt-3"><?= htmlspecialchars($pdf['subject']) ?></div>
                            <ul class="list-unstyled mb-0">
                            <?php $currentSubject = $pdf['subject']; ?>
                        <?php endif; ?>
                        <li class="py-1">
                            <a href="<?= htmlspecialchars($pdf['path']) ?>" target="_blank" class="link-primary text-decoration-none">
                                <?= htmlspecialchars($pdf['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($currentSubject !== null): ?></ul><?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
    </div>

    <script>
        // Palette helpers
        const palette = ['#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f','#edc949','#af7aa1','#ff9da7','#9c755f','#bab0ab'];
        const rgba = (hex, a) => {
            const n = parseInt(hex.slice(1), 16);
            const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
            return `rgba(${r}, ${g}, ${b}, ${a})`;
        };

        // Data from PHP
        const commandData = <?php echo json_encode($commandData); ?>;
        const topicData = <?php echo json_encode($topicData); ?>;
        const marksData = <?php echo json_encode($marksData); ?>;
        const qTypeRaw = <?php echo json_encode($questionTypeData); ?>;
        const markWeightRaw = <?php echo json_encode($markWeightData); ?>;
        const bloomsRaw = <?php echo json_encode($bloomsData); ?>;
        const errorData = <?php echo json_encode($errorData); ?>;

        // Command chart
        new Chart(document.getElementById('commandChart'), {
            type: 'bar',
            data: {
                labels: commandData.map(d => d.command_word),
                datasets: [{ label: 'Frequency', data: commandData.map(d => d.frequency), backgroundColor: rgba(palette[0], 0.6) }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Topic frequency
        new Chart(document.getElementById('topicChart'), {
            type: 'bar',
            data: {
                labels: topicData.map(d => d.topic),
                datasets: [{ label: 'Frequency', data: topicData.map(d => d.frequency), backgroundColor: rgba(palette[1], 0.6) }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Marks by topic
        new Chart(document.getElementById('marksChart'), {
            type: 'bar',
            data: {
                labels: marksData.map(d => d.topic),
                datasets: [{ label: 'Total Marks', data: marksData.map(d => d.total_marks), backgroundColor: rgba(palette[2], 0.6) }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Question type stacked by year
        const years = [...new Set(qTypeRaw.map(r => r.year))];
        const types = [...new Set(qTypeRaw.map(r => r.question_type))];
        const qTypeDatasets = types.map((t, i) => ({
            label: t,
            data: years.map(y => (qTypeRaw.find(r => r.year === y && r.question_type === t)?.count) || 0),
            backgroundColor: rgba(palette[i % palette.length], 0.6)
        }));
        new Chart(document.getElementById('questionTypeChart'), {
            type: 'bar',
            data: { labels: years, datasets: qTypeDatasets },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } } }
        });

        // Mark weighting per topic by year
        const markYears = [...new Set(markWeightRaw.map(r => r.year))];
        const markTopics = [...new Set(markWeightRaw.map(r => r.topic))];
        const markDatasets = markTopics.map((t, i) => ({
            label: t,
            data: markYears.map(y => (markWeightRaw.find(r => r.year === y && r.topic === t)?.marks) || 0),
            backgroundColor: rgba(palette[i % palette.length], 0.6)
        }));
        new Chart(document.getElementById('markWeightingChart'), {
            type: 'bar',
            data: { labels: markYears, datasets: markDatasets },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } } }
        });

        // Blooms taxonomy by year
        const bloomYears = [...new Set(bloomsRaw.map(r => r.year))];
        const bloomLevels = [...new Set(bloomsRaw.map(r => r.level))];
        const bloomDatasets = bloomLevels.map((lvl, i) => ({
            label: lvl,
            data: bloomYears.map(y => (bloomsRaw.find(r => r.year === y && r.level === lvl)?.question_count) || 0),
            backgroundColor: rgba(palette[i % palette.length], 0.6)
        }));
        new Chart(document.getElementById('bloomsChart'), {
            type: 'bar',
            data: { labels: bloomYears, datasets: bloomDatasets },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } } }
        });
    </script>
</body>
</html>



