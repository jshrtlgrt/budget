<?php
session_start();

// Debug session info
error_log("Analytics.php - Session username: " . ($_SESSION['username'] ?? 'not set'));
error_log("Analytics.php - Session role: " . ($_SESSION['role'] ?? 'not set'));

// Check if user is VP Finance - allow all approver roles for testing
$allowed_roles = ['vp_finance', 'approver', 'department_head', 'dean'];
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], $allowed_roles)) {
    echo "<div style='padding: 20px; text-align: center; color: #dc3545;'>";
    echo "<h3>Access Denied</h3>";
    echo "<p>Current role: " . ($_SESSION['role'] ?? 'not set') . "</p>";
    echo "<p>Analytics dashboard is only available for VP Finance users.</p>";
    echo "</div>";
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "<div style='padding: 20px; text-align: center; color: #dc3545;'>";
    echo "<h3>Database Connection Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    exit();
}

// Get overall statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'more_info_requested' THEN 1 ELSE 0 END) as info_requested_count,
        SUM(proposed_budget) as total_budget,
        SUM(CASE WHEN status = 'approved' THEN proposed_budget ELSE 0 END) as approved_budget,
        AVG(proposed_budget) as avg_budget
    FROM budget_request
";

try {
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute();
    $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$overall_stats) {
        $overall_stats = [
            'total_requests' => 0,
            'approved_count' => 0,
            'rejected_count' => 0,
            'pending_count' => 0,
            'info_requested_count' => 0,
            'total_budget' => 0,
            'approved_budget' => 0,
            'avg_budget' => 0
        ];
    }
} catch (PDOException $e) {
    echo "<div style='padding: 20px; text-align: center; color: #dc3545;'>";
    echo "<h3>Database Query Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    exit();
}

// Get monthly statistics for all data (remove date filter to show all months)
$monthly_query = "
    SELECT 
        DATE_FORMAT(timestamp, '%Y-%m') as month,
        DATE_FORMAT(timestamp, '%M %Y') as month_name,
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(proposed_budget) as total_amount,
        SUM(CASE WHEN status = 'approved' THEN proposed_budget ELSE 0 END) as approved_amount
    FROM budget_request 
    GROUP BY DATE_FORMAT(timestamp, '%Y-%m')
    ORDER BY month ASC
";

$stmt = $pdo->prepare($monthly_query);
$stmt->execute();
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get department-wise statistics
$dept_query = "
    SELECT 
        br.department_code,
        d.college,
        COUNT(*) as total_requests,
        SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN br.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(br.proposed_budget) as total_budget,
        SUM(CASE WHEN br.status = 'approved' THEN br.proposed_budget ELSE 0 END) as approved_budget
    FROM budget_request br
    LEFT JOIN department d ON br.department_code = d.code
    GROUP BY br.department_code
    ORDER BY total_requests DESC
";

$stmt = $pdo->prepare($dept_query);
$stmt->execute();
$dept_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$recent_query = "
    SELECT 
        br.request_id,
        br.status,
        br.proposed_budget,
        br.timestamp,
        a.name as requester_name,
        d.college
    FROM budget_request br
    LEFT JOIN account a ON br.account_id = a.id
    LEFT JOIN department d ON br.department_code = d.code
    ORDER BY br.timestamp DESC
    LIMIT 10
";

$stmt = $pdo->prepare($recent_query);
$stmt->execute();
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for JavaScript
$chart_data = [
    'status_distribution' => [
        'approved' => (int)$overall_stats['approved_count'],
        'rejected' => (int)$overall_stats['rejected_count'],
        'pending' => (int)$overall_stats['pending_count'],
        'info_requested' => (int)$overall_stats['info_requested_count']
    ],
    'monthly_data' => $monthly_stats,
    'department_data' => $dept_stats
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-container {
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #015c2e 0%, #28a745 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(1, 92, 46, 0.2);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }
        
        .stat-card h3 {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        
        .stat-card p {
            margin: 0;
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .stat-card.approved {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .stat-card.rejected {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        
        .stat-card.pending {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .stat-card.budget {
            background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
        }
        
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .chart-container h3 {
            color: #015c2e;
            margin-bottom: 20px;
            font-size: 1.4em;
            border-bottom: 2px solid #015c2e;
            padding-bottom: 10px;
        }
        
        .reports-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .reports-section h3 {
            color: #015c2e;
            margin-bottom: 20px;
            font-size: 1.4em;
            border-bottom: 2px solid #015c2e;
            padding-bottom: 10px;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .monthly-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .monthly-table th,
        .monthly-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .monthly-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #015c2e;
        }
        
        .monthly-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .recent-activity {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            padding: 15px;
            border-left: 4px solid #015c2e;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }
        
        .activity-item.approved {
            border-left-color: #28a745;
        }
        
        .activity-item.rejected {
            border-left-color: #dc3545;
        }
        
        .activity-item.pending {
            border-left-color: #ffc107;
        }
        
        .activity-meta {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="analytics-container">
    <!-- Statistics Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo number_format($overall_stats['total_requests']); ?></h3>
            <p>Total Requests</p>
        </div>
        <div class="stat-card approved">
            <h3><?php echo number_format($overall_stats['approved_count']); ?></h3>
            <p>Approved</p>
        </div>
        <div class="stat-card rejected">
            <h3><?php echo number_format($overall_stats['rejected_count']); ?></h3>
            <p>Rejected</p>
        </div>
        <div class="stat-card pending">
            <h3><?php echo number_format($overall_stats['pending_count']); ?></h3>
            <p>Pending</p>
        </div>
        <div class="stat-card budget">
            <h3>₱<?php echo number_format($overall_stats['approved_budget'], 0); ?></h3>
            <p>Approved Budget</p>
        </div>
        <div class="stat-card">
            <h3>₱<?php echo number_format($overall_stats['avg_budget'], 0); ?></h3>
            <p>Average Request</p>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-container">
            <h3>📊 Status Distribution</h3>
            <canvas id="statusPieChart" width="400" height="300"></canvas>
        </div>
        <div class="chart-container">
            <h3>📈 Monthly Requests</h3>
            <canvas id="monthlyBarChart" width="400" height="300"></canvas>
        </div>
    </div>

    <!-- Reports Section -->
    <div class="reports-section">
        <div class="reports-grid">
            <div>
                <h3>📅 Monthly Reports</h3>
                <div style="overflow-x: auto;">
                    <table class="monthly-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Requests</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Total Amount</th>
                                <th>Approved Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_stats as $month): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($month['month_name']); ?></td>
                                <td><?php echo number_format($month['total_requests']); ?></td>
                                <td style="color: #28a745; font-weight: bold;"><?php echo number_format($month['approved']); ?></td>
                                <td style="color: #dc3545; font-weight: bold;"><?php echo number_format($month['rejected']); ?></td>
                                <td>₱<?php echo number_format($month['total_amount'], 2); ?></td>
                                <td style="color: #28a745; font-weight: bold;">₱<?php echo number_format($month['approved_amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div>
                <h3>🕒 Recent Activity</h3>
                <div class="recent-activity">
                    <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item <?php echo strtolower($activity['status']); ?>">
                        <strong><?php echo htmlspecialchars($activity['request_id']); ?></strong>
                        <span style="float: right; color: <?php 
                            echo $activity['status'] === 'approved' ? '#28a745' : 
                                ($activity['status'] === 'rejected' ? '#dc3545' : '#ffc107'); 
                        ?>; font-weight: bold;">
                            <?php echo strtoupper($activity['status']); ?>
                        </span>
                        <br>
                        <span><?php echo htmlspecialchars($activity['requester_name']); ?> - <?php echo htmlspecialchars($activity['college']); ?></span>
                        <br>
                        <strong>₱<?php echo number_format($activity['proposed_budget'], 2); ?></strong>
                        <div class="activity-meta">
                            <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Chart data from PHP
const chartData = <?php echo json_encode($chart_data); ?>;

// Status Distribution Pie Chart
const statusCtx = document.getElementById('statusPieChart').getContext('2d');
new Chart(statusCtx, {
    type: 'pie',
    data: {
        labels: ['Approved', 'Rejected', 'Pending', 'Info Requested'],
        datasets: [{
            data: [
                chartData.status_distribution.approved,
                chartData.status_distribution.rejected,
                chartData.status_distribution.pending,
                chartData.status_distribution.info_requested
            ],
            backgroundColor: [
                '#28a745',
                '#dc3545',
                '#ffc107',
                '#17a2b8'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Monthly Bar Chart
const monthlyCtx = document.getElementById('monthlyBarChart').getContext('2d');
const monthlyLabels = chartData.monthly_data.map(item => item.month_name || 'No Data');
const monthlyApproved = chartData.monthly_data.map(item => parseInt(item.approved) || 0);
const monthlyRejected = chartData.monthly_data.map(item => parseInt(item.rejected) || 0);

new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: [
            {
                label: 'Approved',
                data: monthlyApproved,
                backgroundColor: '#28a745',
                borderColor: '#1e7e34',
                borderWidth: 1
            },
            {
                label: 'Rejected',
                data: monthlyRejected,
                backgroundColor: '#dc3545',
                borderColor: '#bd2130',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                position: 'top'
            }
        }
    }
});
</script>

</body>
</html>