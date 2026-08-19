<?php
// Enable error reporting to catch any runtime issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth_check.php';

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role_name'];

// Get scope filter SQL and matching parameters
$scopeFilter = getAgentFilterSQL($role, 'u');
$params      = getAgentFilterParams($role, $userId);

// 10.1 Daywise Premium Revenue (Last 7 Days)
$stmtDay = $pdo->prepare("
    SELECT DATE(p.payment_date) as pay_date, SUM(p.amount_paid) as total_revenue
    FROM premium_payments p
    JOIN policies pol ON p.policy_id = pol.id
    JOIN customers c ON pol.customer_id = c.id
    JOIN users u ON c.agent_id = u.id
    WHERE $scopeFilter AND p.status = 'Paid' AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(p.payment_date)
    ORDER BY pay_date ASC
");
$stmtDay->execute($params);
$dailyReports = $stmtDay->fetchAll();

// 10.2 Agent-Wise Performance
$stmtAgent = $pdo->prepare("
    SELECT u.full_name, COUNT(DISTINCT c.id) as total_customers, COALESCE(SUM(p.amount_paid), 0) as revenue_generated
    FROM users u
    LEFT JOIN customers c ON u.id = c.agent_id
    LEFT JOIN policies pol ON c.id = pol.customer_id
    LEFT JOIN premium_payments p ON pol.id = p.policy_id AND p.status = 'Paid'
    WHERE $scopeFilter
    GROUP BY u.id
");
$stmtAgent->execute($params);
$agentReports = $stmtAgent->fetchAll();

// 10.3 Monthly Revenue Trend
$stmtMonthly = $pdo->prepare("
    SELECT DATE_FORMAT(p.payment_date, '%Y-%m') as month_year, DATE_FORMAT(p.payment_date, '%b %Y') as month_label, SUM(p.amount_paid) as total_revenue
    FROM premium_payments p
    JOIN policies pol ON p.policy_id = pol.id
    JOIN customers c ON pol.customer_id = c.id
    JOIN users u ON c.agent_id = u.id
    WHERE $scopeFilter AND p.status = 'Paid'
    GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
    ORDER BY month_year ASC
    LIMIT 12
");
$stmtMonthly->execute($params);
$monthlyReports = $stmtMonthly->fetchAll();

// 10.4 Weekly Revenue Trend
$stmtWeekly = $pdo->prepare("
    SELECT YEARWEEK(p.payment_date, 1) as year_week, 
           CONCAT('Week ', WEEK(p.payment_date, 1)) as week_label, 
           SUM(p.amount_paid) as total_revenue
    FROM premium_payments p
    JOIN policies pol ON p.policy_id = pol.id
    JOIN customers c ON pol.customer_id = c.id
    JOIN users u ON c.agent_id = u.id
    WHERE $scopeFilter AND p.status = 'Paid'
    GROUP BY YEARWEEK(p.payment_date, 1)
    ORDER BY year_week ASC
    LIMIT 8
");
$stmtWeekly->execute($params);
$weeklyReports = $stmtWeekly->fetchAll();

// Include common header (Renders main navbar and global CSS)
include 'header.php';
?>

<h1 class="page-title">Dashboard Overview</h1>

<!-- Welcome Banner -->
<div class="card">
    <h2 class="card-title">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h2>
    <p>Logged in as: <strong><?= htmlspecialchars($_SESSION['role_name']) ?></strong></p>
</div>

<!-- Grid Layout for Charts -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 20px;">

    <!-- Monthly Revenue Trend (Line Chart) -->
    <div class="card">
        <h3 class="card-title">Monthly Revenue Trend</h3>
        <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
    </div>

    <!-- Agent Performance (Bar Chart) -->
    <div class="card">
        <h3 class="card-title">Agent Performance (Revenue Generated)</h3>
        <canvas id="agentChart" style="max-height: 300px;"></canvas>
    </div>

    <!-- Weekly Revenue Trend (Bar Chart) -->
    <div class="card">
        <h3 class="card-title">Weekly Revenue Trend</h3>
        <canvas id="weeklyChart" style="max-height: 300px;"></canvas>
    </div>

    <!-- Daily Revenue (Line Chart) -->
    <div class="card">
        <h3 class="card-title">Daily Revenue (Last 7 Days)</h3>
        <canvas id="dailyChart" style="max-height: 300px;"></canvas>
    </div>

</div>

<!-- Performance Summary Table -->
<div class="card" style="margin-top: 20px;">
    <h3 class="card-title">Team Performance Breakdown</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Agent Name</th>
                    <th>Total Customers</th>
                    <th>Total Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($agentReports)): ?>
                    <?php foreach ($agentReports as $agent): ?>
                        <tr>
                            <td><?= htmlspecialchars($agent['full_name']) ?></td>
                            <td><?= htmlspecialchars($agent['total_customers']) ?></td>
                            <td>$<?= number_format($agent['revenue_generated'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">No performance data found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js Script to render interactive charts -->
<script>
// 1. Monthly Revenue Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthlyReports, 'month_label')) ?>,
        datasets: [{
            label: 'Revenue ($)',
            data: <?= json_encode(array_column($monthlyReports, 'total_revenue')) ?>,
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.2)',
            fill: true,
            tension: 0.3
        }]
    }
});

// 2. Agent Performance Chart
const agentCtx = document.getElementById('agentChart').getContext('2d');
new Chart(agentCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($agentReports, 'full_name')) ?>,
        datasets: [{
            label: 'Revenue ($)',
            data: <?= json_encode(array_column($agentReports, 'revenue_generated')) ?>,
            backgroundColor: '#2ecc71'
        }]
    }
});

// 3. Weekly Revenue Chart
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($weeklyReports, 'week_label')) ?>,
        datasets: [{
            label: 'Revenue ($)',
            data: <?= json_encode(array_column($weeklyReports, 'total_revenue')) ?>,
            backgroundColor: '#f39c12'
        }]
    }
});

// 4. Daily Revenue Chart
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($dailyReports, 'pay_date')) ?>,
        datasets: [{
            label: 'Revenue ($)',
            data: <?= json_encode(array_column($dailyReports, 'total_revenue')) ?>,
            borderColor: '#e74c3c',
            backgroundColor: 'rgba(231, 76, 60, 0.2)',
            fill: true,
            tension: 0.2
        }]
    }
});
</script>

<?php 
// Include common footer
include 'footer.php'; 
?>