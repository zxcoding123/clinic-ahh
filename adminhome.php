<?php
session_start();
require_once 'config.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  error_log("Unauthorized access to adminhome.php: No user_id in session, redirecting to /login");
  header("Location: /login.php");
  exit();
}


// Check connection
if ($conn->connect_error) {
  error_log("Database connection failed: " . $conn->connect_error);
  $_SESSION['error'] = "Database error. Please try again.";
  header("Location: /login.php");
  exit();
}

// Verify user is an admin
$userId = (int)$_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
  error_log("Admin verification query prepare failed: " . $conn->error);
  $_SESSION['error'] = "Database error. Please try again.";
  header("Location: /login.php");
  exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !in_array(trim($user['user_type']), ['Super Admin', 'Medical Admin', 'Dental Admin'], true)) {
  error_log("Non-admin user_id: $userId, user_type: " . ($user['user_type'] ?? 'none') . " attempted access to adminhome.php, redirecting to /homepage");
  header("Location: /homepage.php");
  exit();
}
error_log("Admin user_id: $userId, user_type: {$user['user_type']} accessed adminhome.php");

// Fetch dashboard counts
$dashboard_counts = [
  'total_users' => 0,
  'total_patients' => 0,
  'user_counts' => [
    'kindergarten' => 0,
    'elementary' => 0,
    'highschool' => 0,
    'seniorhigh' => 0,
    'employees' => 0
  ]
];

// Total users
$query = "SELECT COUNT(*) as total FROM users";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
  $dashboard_counts['total_users'] = $row['total'];
}

// Total patients
$query = "SELECT COUNT(*) as total FROM patients";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
  $dashboard_counts['total_patients'] = $row['total'];
}

// User category counts
$user_counts_queries = [
  'kindergarten' => "SELECT COUNT(*) as count FROM children WHERE type = 'Kindergarten'",
  'elementary' => "SELECT COUNT(*) as count FROM children WHERE type = 'Elementary'",
  'highschool' => "SELECT COUNT(*) as count FROM highschool_students",
  'seniorhigh' => "SELECT COUNT(*) as count FROM senior_high_students",
  'employees' => "SELECT COUNT(*) as count FROM employees"
];

foreach ($user_counts_queries as $category => $query) {
  $result = $conn->query($query);
  if ($result && $row = $result->fetch_assoc()) {
    $dashboard_counts['user_counts'][$category] = $row['count'];
  }
}

// Handle AJAX requests for consultation counts
if (isset($_GET['fetch_counts'])) {
  header('Content-Type: application/json');

  try {
    $dashboard_type = $_GET['dashboard_type'] ?? 'medical';
    $time_period = $_GET['time_period'] ?? 'daily';
    $consultation_type = $_GET['consultation_type'] ?? 'all';
    $user_category = $_GET['user_category'] ?? 'all';

    error_log("AJAX request received - dashboard_type: $dashboard_type, time_period: $time_period, consultation_type: $consultation_type, user_category: $user_category");

    $start_date = match ($time_period) {
      'daily' => date('Y-m-d'),
      'weekly' => date('Y-m-d', strtotime('-7 days')),
      'monthly' => date('Y-m-d', strtotime('-30 days')),
      default => date('Y-m-d')
    };

    $response = ['consultation_count' => 0, 'trending_issues' => []];

    // Consultation count
    $query = "SELECT COUNT(*) as count FROM consultations WHERE consultation_type = ? AND consultation_date >= ?";
    $params = [$dashboard_type, $start_date];
    $types = 'ss';

    if ($consultation_type !== 'all') {
      $query .= " AND LOWER(complaints) LIKE ?";
      $params[] = '%' . strtolower($consultation_type) . '%';
      $types .= 's';
    }

    // For now, we'll use a simpler approach that doesn't rely on complex JOINs
    // The user category filter will be handled in the frontend for now
    // This prevents the 500 error from complex SQL queries

    $stmt = $conn->prepare($query);
    if ($stmt) {
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($row = $result->fetch_assoc()) {
        $response['consultation_count'] = $row['count'];
      }
      $stmt->close();
    } else {
      error_log("Consultation count query prepare failed: " . $conn->error);
      $response['error'] = "Database query failed";
    }

    // Trending issues
    $query = "SELECT diagnosis, COUNT(*) as count FROM consultations WHERE consultation_type = ? AND consultation_date >= ? GROUP BY diagnosis ORDER BY count DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    if ($stmt) {
      $stmt->bind_param('ss', $dashboard_type, $start_date);
      $stmt->execute();
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
        $response['trending_issues'][] = [
          'name' => $row['diagnosis'] ?: 'Unknown',
          'count' => $row['count']
        ];
      }
      $stmt->close();
    } else {
      error_log("Trending issues query prepare failed: " . $conn->error);
    }

    echo json_encode($response);
  } catch (Exception $e) {
    error_log("Error in AJAX consultation count request: " . $e->getMessage());
    echo json_encode([
      'consultation_count' => 0,
      'trending_issues' => [],
      'error' => 'An error occurred while fetching data'
    ]);
  }

  $conn->close();
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homepage - WMSU Health Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/adminhome.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Poppins:wght@400;500;700&display=swap">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">

  <style>
    body,
    .sidebar,
    .sidebar-nav,
    .sidebar-footer,
    .dropdown-menu,
    .btn-crimson,
    .dropdown-item {
      font-family: 'Poppins', sans-serif;
    }

    .dashboard-header,
    h1,
    h2,
    h3,
    .brand-text {
      font-family: 'Cinzel', serif;
    }

    .btn-dark-crimson:hover {
      color: white !important;
    }

    .card-body {
      position: relative;

    }

    #patientChart,
    #distributionChart {
      width: 100% !important;
      height: 100% !important;
    }

    .dashboard-analytics canvas {
      max-width: 100%;
      max-height: 300px;
      /* Limit vertical stretching */
      width: auto !important;
      /* Allow natural width based on content */
      height: auto !important;
      /* Allow natural height */
    }

    .card-body {
      position: relative;
   
      /* Maximum height to prevent excessive stretching */
      overflow: auto;
      /* Handle overflow if data exceeds max-height */
    }

    @media (max-width: 768px) {
      .dashboard-analytics canvas {
        max-height: 200px;
        /* Reduced height for mobile */
      }

      .card-body {
        max-height: 300px;
        /* Adjusted max-height for mobile */
      }
    }
  </style>
</head>

<body>
  <div id="app" class="d-flex">
    <button id="burger-btn" class="burger-btn">☰</button>
    <?php include 'include/sidebar.php'; ?>

    <div class="main-content">
      <!-- Dashboard Header -->
      <div class="dashboard-header d-flex align-items-center mb-3 gap-3 flex-wrap">
        <!-- Dashboard Selection Dropdown -->
        <div class="dashboard-selector">
          <select id="dashboard-type" class="form-select">
            <option value="medical" selected>Medical Dashboard</option>
            <option value="dental">Dental Dashboard</option>
          </select>
        </div>
        <!-- User Category Filter Dropdown -->
        <div class="user-category-filter">
          <select id="user-category-filter" class="form-select">
            <option value="all" selected>All Users</option>
            <option value="kindergarten">Kindergarten</option>
            <option value="elementary">Elementary</option>
            <option value="highschool">High School</option>
            <option value="seniorhigh">Senior High</option>
            <option value="employees">Employees</option>
          </select>
        </div>
        <!-- Print Reports Button -->
        <button class="btn btn-dark-crimson" id="print-reports-btn">Print Reports</button>
      </div>

      <!-- Dashboard Content -->
      <div id="dashboard-content">
        <!-- User and Patient Counts -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">User and Patient Overview</h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <h4>Total Users: <span id="total-users-count"><?php echo $dashboard_counts['total_users']; ?></span></h4>
              </div>
              <div class="col-md-6">
                <h4>Total Patients: <span id="total-patients-count"><?php echo $dashboard_counts['total_patients']; ?></span></h4>
              </div>
            </div>
          </div>
        </div>

        <!-- Medical Dashboard -->
        <div id="medical-dashboard">
          <h2 class="dashboard-title">Medical Dashboard</h2>

          <!-- Consultations Overview Card -->
          <div class="card consultations">
            <div class="card-header">
              <h3 class="card-title">Consultations Overview</h3>
              <div class="filter-options d-flex align-items-center gap-3">
                <select id="medical-time-filter" class="form-select">
                  <option value="daily" selected>Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
                <select id="medical-consultation-filter" class="form-select">
                  <option value="all" selected>All Consultations</option>
                  <option value="regular">Regular Checkup</option>
                  <option value="illness">Illness</option>
                  <option value="injury">Injury</option>
                  <option value="vaccination">Vaccination</option>
                </select>
              </div>
            </div>
            <div class="card-body">
              <div class="consultation-count">
                <span id="medical-consultation-count">0</span> Consultations
              </div>
            </div>
          </div>


          <!-- Analytics Section -->
          <div class="dashboard-analytics-container ">
            <!-- Bar Chart Card -->
            <div class="card dashboard-analytics">
              <div class="card-header">
                <h3 class="card-title">Number of Patients</h3>
                <div class="time-filter">
                  <select class="form-select time-select">
                    <option>Last 7 Days</option>
                    <option>Last 30 Days</option>
                    <option>Last 90 Days</option>
                  </select>
                </div>
              </div>
              <div class="card-body col-md-12">
                <canvas id="patientChart"></canvas>
              </div>
            </div>

            <!-- Pie Chart Card -->
            <div class="card patient-distribution">
              <div class="card-header">
                <h3 class="card-title">Patient Visit Analytics</h3>
              </div>
              <div class="card-body">
                <canvas id="distributionChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Trending Illnesses Card -->
          <div class="card trending-illnesses">
            <div class="card-header">
              <h3 class="card-title">Trending Illnesses</h3>
              <span class="update-time">Updated: <?php echo date('F j, Y h:i A'); ?></span>
            </div>
            <div class="card-body">
              <ul id="medical-trends" class="illness-list"></ul>
            </div>
          </div>
        </div>

        <!-- Dental Dashboard -->
        <div id="dental-dashboard" style="display: none;">
          <h2 class="dashboard-title">Dental Dashboard</h2>

          <!-- Dental Consultations Overview Card -->
          <div class="card consultations">
            <div class="card-header">
              <h3 class="card-title">Dental Consultations Overview</h3>
              <div class="filter-options d-flex align-items-center gap-3">
                <select id="dental-time-filter" class="form-select">
                  <option value="daily" selected>Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
                <select id="dental-consultation-filter" class="form-select">
                  <option value="all" selected>All Dental Consultations</option>
                  <option value="checkup">Dental Checkup</option>
                  <option value="cleaning">Teeth Cleaning</option>
                  <option value="filling">Filling</option>
                  <option value="extraction">Tooth Extraction</option>
                  <option value="orthodontics">Orthodontics</option>
                </select>
              </div>
            </div>
            <div class="card-body">
              <div class="consultation-count">
                <span id="dental-consultation-count">0</span> Consultations
              </div>
            </div>
          </div>


          <!-- Dental Analytics Section -->
          <div class="dashboard-analytics-container container-fluid">
         

              <!-- Bar Chart Card -->
            
                <div class="card dashboard-analytics h-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Number of Patients</h3>
                    <div class="time-filter">
                      <select class="form-select time-select">
                        <option>Last 7 Days</option>
                        <option>Last 30 Days</option>
                        <option>Last 90 Days</option>
                      </select>
                    </div>
                  </div>
                  <div class="card-body col-md-12">
                    <canvas id="dentalPatientChart"></canvas>
                  </div>
                </div>
             
              <!-- Pie Chart Card -->
            
                <div class="card patient-distribution ">
                  <div class="card-header">
                    <h3 class="card-title mb-0">Patient Visit Analytics</h3>
                  </div>
                  <div class="card-body col-md-12">
                    <canvas id="dentalDistributionChart"></canvas>
                  </div>
           
              </div>

            </div>
          </div>


          <!-- Common Dental Issues Card -->
          <div class="card trending-illnesses">
            <div class="card-header">
              <h3 class="card-title">Common Dental Issues</h3>
              <span class="update-time">Updated: <?php echo date('F j, Y h:i A'); ?></span>
            </div>
            <div class="card-body">
              <ul id="dental-trends" class="illness-list"></ul>
            </div>
          </div>
        </div>

        <!-- Printable Report Section (Hidden by Default) -->
        <div id="printable-report" class="d-none">
          <div class="report-header">
            <img src="images/clinic.png" alt="WMSU Clinic Logo" class="report-logo">
            <h1>WMSU Health Services</h1>
            <h2 id="report-title">Medical Dashboard Report</h2>
            <p id="report-date">Generated: <?php echo date('F j, Y'); ?></p>
          </div>
          <div class="report-section">
            <h3>Consultations Overview</h3>
            <p id="report-consultation-count">0 Consultations (Daily)</p>
            <p id="report-consultation-type">Type: All Consultations</p>
            <p id="report-user-category">User Category: All Users</p>
          </div>
          <div class="report-section">
            <h3 id="report-illness-title">Trending Illnesses</h3>
            <ul id="report-illness-list" class="illness-list"></ul>
          </div>
          <div class="report-section">
            <h3>Patient Analytics</h3>
            <table class="report-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Number of Patients</th>
                  <th>Percentage</th>
                </tr>
              </thead>
              <tbody id="report-patient-table"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
  <script>
    const counts = <?php echo json_encode($dashboard_counts); ?>;
    let medicalPatientChart, medicalDistChart, dentalPatientChart, dentalDistChart;

    // Fetch consultation data via AJAX
    async function fetchConsultationData(dashboardType, timePeriod, consultationType, userCategory) {
      try {
        const params = new URLSearchParams({
          fetch_counts: 1,
          dashboard_type: dashboardType,
          time_period: timePeriod,
          consultation_type: consultationType,
          user_category: userCategory
        });
        const response = await fetch(`adminhome.php?${params}`);
        if (!response.ok) {
          console.error(`HTTP error: ${response.status}`);
          throw new Error(`HTTP error: ${response.status}`);
        }
        const data = await response.json();

        // Check if there's an error in the response
        if (data.error) {
          console.error('Server error:', data.error);
          return {
            consultation_count: 0,
            trending_issues: []
          };
        }

        return data;
      } catch (error) {
        console.error('Fetch error:', error);
        return {
          consultation_count: 0,
          trending_issues: []
        };
      }
    }

    // Show Dashboard
    function showDashboard() {
      document.getElementById('dashboard-content').style.display = 'block';
      closeSidebarOnMobile();
    }

    // Close sidebar on mobile
    function closeSidebarOnMobile() {
      if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('active');
      }
    }

    // Update consultation count and trending issues
    async function updateConsultationCount(dashboardType, timePeriod, consultationType) {
      const countElement = document.getElementById(`${dashboardType}-consultation-count`);
      const userCategory = document.getElementById('user-category-filter').value;
      const data = await fetchConsultationData(dashboardType, timePeriod, consultationType, userCategory);
      countElement.textContent = data.consultation_count || 0;

      const illnessList = document.getElementById(`${dashboardType}-trends`);
      illnessList.innerHTML = data.trending_issues.length ?
        data.trending_issues.map(issue => `<li><span class="illness-name">${issue.name}</span><span class="illness-count">${issue.count} cases</span></li>`).join('') :
        '<li>No recent consultations</li>';
    }

    // Update charts based on user category
    function updateCharts(dashboardType, userCategory) {
      const data = userCategory === 'all' ? [
        counts.user_counts.kindergarten,
        counts.user_counts.elementary,
        counts.user_counts.highschool,
        counts.user_counts.seniorhigh,
        counts.user_counts.employees
      ] : [counts.user_counts[userCategory] || 0];

      const labels = userCategory === 'all' ? [
        'Kindergarten', 'Elementary', 'High School', 'Senior High', 'Employees'
      ] : [userCategory.charAt(0).toUpperCase() + userCategory.slice(1)];

      const chartConfig = {
        labels,
        datasets: [{
          label: dashboardType === 'medical' ? 'Number of Patients' : 'Number of Dental Patients',
          data,
          backgroundColor: [
            'rgba(0, 123, 255, 0.7)',
            'rgba(40, 167, 69, 0.7)',
            'rgba(255, 193, 7, 0.7)',
            'rgba(23, 162, 184, 0.7)',
            'rgba(220, 20, 60, 0.7)'
          ].slice(0, userCategory === 'all' ? 5 : 1),
          borderColor: [
            'rgba(0, 123, 255, 1)',
            'rgba(40, 167, 69, 1)',
            'rgba(255, 193, 7, 1)',
            'rgba(23, 162, 184, 1)',
            'rgba(220, 20, 60, 1)'
          ].slice(0, userCategory === 'all' ? 5 : 1),
          borderWidth: 1
        }]
      };

      // Update bar chart
      const barChart = dashboardType === 'medical' ? medicalPatientChart : dentalPatientChart;
      barChart.data = chartConfig;
      barChart.update();

      // Update pie chart
      const pieChart = dashboardType === 'medical' ? medicalDistChart : dentalDistChart;
      pieChart.data = {
        labels,
        datasets: [{
          data,
          backgroundColor: chartConfig.datasets[0].backgroundColor,
          borderColor: '#fff',
          borderWidth: 2
        }]
      };
      pieChart.update();

      // Update report patient table
      const patientTable = document.getElementById('report-patient-table');
      patientTable.innerHTML = '';
      const total = data.reduce((a, b) => a + b, 0);
      data.forEach((value, index) => {
        if (value > 0 || userCategory !== 'all') {
          const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
          patientTable.innerHTML += `<tr><td>${labels[index]}</td><td>${value}</td><td>${percentage}%</td></tr>`;
        }
      });
    }

    // Generate printable report
    async function generateReport() {
      const dashboardType = document.getElementById('dashboard-type').value;
      const userCategory = document.getElementById('user-category-filter').value;
      const timePeriod = document.getElementById(`${dashboardType}-time-filter`).value;
      const consultationType = document.getElementById(`${dashboardType}-consultation-filter`).value;

      // Fetch latest consultation data
      const data = await fetchConsultationData(dashboardType, timePeriod, consultationType, userCategory);

      // Update report content
      document.getElementById('report-title').textContent = dashboardType === 'medical' ? 'Medical Dashboard Report' : 'Dental Dashboard Report';
      document.getElementById('report-date').textContent = `Generated: ${new Date().toLocaleDateString()}`;
      document.getElementById('report-consultation-count').textContent = `${data.consultation_count} Consultations (${timePeriod.charAt(0).toUpperCase() + timePeriod.slice(1)})`;
      document.getElementById('report-consultation-type').textContent = `Type: ${consultationType === 'all' ? 'All Consultations' : consultationType.charAt(0).toUpperCase() + consultationType.slice(1)}`;
      document.getElementById('report-user-category').textContent = `User Category: ${userCategory === 'all' ? 'All Users' : userCategory.charAt(0).toUpperCase() + userCategory.slice(1)}`;
      document.getElementById('report-illness-title').textContent = dashboardType === 'medical' ? 'Trending Illnesses' : 'Common Dental Issues';

      // Update illness list
      const illnessList = document.getElementById('report-illness-list');
      illnessList.innerHTML = data.trending_issues.length ?
        data.trending_issues.map(issue => `<li><span class="illness-name">${issue.name}</span><span class="illness-count">${issue.count} cases</span></li>`).join('') :
        '<li>No recent consultations</li>';

      // Show report and trigger print
      document.getElementById('dashboard-content').classList.add('d-none');
      document.getElementById('printable-report').classList.remove('d-none');
      setTimeout(() => {
        window.print();
        document.getElementById('dashboard-content').classList.remove('d-none');
        document.getElementById('printable-report').classList.add('d-none');
      }, 100);
    }

    // Initialize charts
    function initializeCharts() {
      const chartOptions = {
        bar: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: '#2c3e50',
              titleFont: {
                size: 14,
                weight: 'bold'
              },
              bodyFont: {
                size: 12
              },
              padding: 12,
              displayColors: false
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                drawBorder: false,
                color: '#eee'
              },
              ticks: {
                stepSize: 5
              }
            },
            x: {
              grid: {
                display: false
              }
            }
          }
        },
        pie: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            tooltip: {
              backgroundColor: '#2c3e50',
              titleFont: {
                size: 14,
                weight: 'bold'
              },
              bodyFont: {
                size: 12
              },
              padding: 12,
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.raw || 0;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                  return `${label}: ${value} (${percentage}%)`;
                }
              }
            },
            legend: {
              position: 'bottom',
              labels: {
                padding: 20,
                usePointStyle: true,
                pointStyle: 'circle',
                font: {
                  size: 12
                }
              }
            }
          }
        }
      };

      const chartData = [
        counts.user_counts.kindergarten,
        counts.user_counts.elementary,
        counts.user_counts.highschool,
        counts.user_counts.seniorhigh,
        counts.user_counts.employees
      ];

      medicalPatientChart = new Chart(document.getElementById('patientChart')?.getContext('2d'), {
        type: 'bar',
        data: {
          labels: ['Kindergarten', 'Elementary', 'High School', 'Senior High', 'Employees'],
          datasets: [{
            label: 'Number of Patients',
            data: chartData,
            backgroundColor: [
              'rgba(0, 123, 255, 0.7)',
              'rgba(40, 167, 69, 0.7)',
              'rgba(255, 193, 7, 0.7)',
              'rgba(23, 162, 184, 0.7)',
              'rgba(220, 20, 60, 0.7)'
            ],
            borderColor: [
              'rgba(0, 123, 255, 1)',
              'rgba(40, 167, 69, 1)',
              'rgba(255, 193, 7, 1)',
              'rgba(23, 162, 184, 1)',
              'rgba(220, 20, 60, 1)'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });

      medicalDistChart = new Chart(document.getElementById('distributionChart')?.getContext('2d'), {
        type: 'pie',
        data: {
          labels: ['Kindergarten', 'Elementary', 'High School', 'Senior High', 'Employees'],
          datasets: [{
            data: chartData,
            backgroundColor: [
              'rgba(0, 123, 255, 0.7)',
              'rgba(40, 167, 69, 0.7)',
              'rgba(255, 193, 7, 0.7)',
              'rgba(23, 162, 184, 0.7)',
              'rgba(220, 20, 60, 0.7)'
            ],
            borderColor: '#fff',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });

      dentalPatientChart = new Chart(document.getElementById('dentalPatientChart')?.getContext('2d'), {
        type: 'bar',
        data: {
          labels: ['Kindergarten', 'Elementary', 'High School', 'Senior High', 'Employees'],
          datasets: [{
            label: 'Number of Dental Patients',
            data: chartData,
            backgroundColor: [
              'rgba(0, 123, 255, 0.7)',
              'rgba(40, 167, 69, 0.7)',
              'rgba(255, 193, 7, 0.7)',
              'rgba(23, 162, 184, 0.7)',
              'rgba(220, 20, 60, 0.7)'
            ],
            borderColor: [
              'rgba(0, 123, 255, 1)',
              'rgba(40, 167, 69, 1)',
              'rgba(255, 193, 7, 1)',
              'rgba(23, 162, 184, 1)',
              'rgba(220, 20, 60, 1)'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          aspectRatio: 2, // Suggests a 2:1 width-to-height ratio as a guideline
          layout: {
            padding: {
              top: 10,
              bottom: 10,
              left: 10,
              right: 10
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              max: Math.max(...chartData) * 1.2 || 10 // Dynamic max based on data
            },
            x: {
              ticks: {
                padding: 5
              }
            }
          }
        }
      });

      dentalDistChart = new Chart(document.getElementById('dentalDistributionChart')?.getContext('2d'), {
        type: 'pie',
        data: {
          labels: ['Kindergarten', 'Elementary', 'High School', 'Senior High', 'Employees'],
          datasets: [{
            data: chartData,
            backgroundColor: [
              'rgba(0, 123, 255, 0.7)',
              'rgba(40, 167, 69, 0.7)',
              'rgba(255, 193, 7, 0.7)',
              'rgba(23, 162, 184, 0.7)',
              'rgba(220, 20, 60, 0.7)'
            ],
            borderColor: '#fff',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          aspectRatio: 1, // Suggests a 1:1 width-to-height ratio for pie chart
          layout: {
            padding: 10
          }
        }
      });

    }

    // Update dashboard
    async function updateDashboard() {
      const dashboardType = document.getElementById('dashboard-type').value;
      const timePeriod = document.getElementById(`${dashboardType}-time-filter`).value;
      const consultationType = document.getElementById(`${dashboardType}-consultation-filter`).value;
      const userCategory = document.getElementById('user-category-filter').value;

      await updateConsultationCount(dashboardType, timePeriod, consultationType);
      updateCharts(dashboardType, userCategory);
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
      const burgerBtn = document.getElementById('burger-btn');
      const sidebar = document.getElementById('sidebar');
      const dashboardType = document.getElementById('dashboard-type');
      const medicalDashboard = document.getElementById('medical-dashboard');
      const dentalDashboard = document.getElementById('dental-dashboard');
      const medicalTimeFilter = document.getElementById('medical-time-filter');
      const dentalTimeFilter = document.getElementById('dental-time-filter');
      const medicalConsultationFilter = document.getElementById('medical-consultation-filter');
      const dentalConsultationFilter = document.getElementById('dental-consultation-filter');
      const userCategoryFilter = document.getElementById('user-category-filter');
      const printReportsBtn = document.getElementById('print-reports-btn');

      // Initialize dashboard and charts
      showDashboard();
      initializeCharts();
      updateDashboard();

      // Burger button toggle
      if (burgerBtn) {
        burgerBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          sidebar.classList.toggle('active');
        });
      }

      // Close sidebar when clicking outside on mobile
      document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
          const isClickInsideSidebar = sidebar.contains(event.target);
          const isClickOnBurgerBtn = burgerBtn.contains(event.target);
          if (!isClickInsideSidebar && !isClickOnBurgerBtn) {
            sidebar.classList.remove('active');
          }
        }
      });

      // Close sidebar when clicking sidebar buttons on mobile
      const sidebarButtons = document.querySelectorAll('#sidebar .btn-crimson:not(#cmsDropdown), #sidebar .dropdown-item');
      sidebarButtons.forEach(button => {
        button.addEventListener('click', closeSidebarOnMobile);
      });

      // Dashboard toggle
      dashboardType.addEventListener('change', function() {
        const dashboard = this.value;
        medicalDashboard.style.display = dashboard === 'medical' ? 'block' : 'none';
        dentalDashboard.style.display = dashboard === 'dental' ? 'block' : 'none';
        updateDashboard();
      });

      // Time filter for consultations
      medicalTimeFilter.addEventListener('change', function() {
        updateConsultationCount('medical', this.value, medicalConsultationFilter.value);
      });
      dentalTimeFilter.addEventListener('change', function() {
        updateConsultationCount('dental', this.value, dentalConsultationFilter.value);
      });

      // Consultation type filter
      medicalConsultationFilter.addEventListener('change', function() {
        updateConsultationCount('medical', medicalTimeFilter.value, this.value);
      });
      dentalConsultationFilter.addEventListener('change', function() {
        updateConsultationCount('dental', dentalTimeFilter.value, this.value);
      });

      // User category filter
      userCategoryFilter.addEventListener('change', function() {
        updateDashboard();
      });

      // Print reports
      printReportsBtn.addEventListener('click', generateReport);
    });
  </script>
  <?php include('notifications_admin.php') ?>
</body>

</html>
<?php
$conn->close();
?>