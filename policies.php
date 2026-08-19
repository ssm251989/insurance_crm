<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth_check.php';

$userId  = $_SESSION['user_id'];
$role    = $_SESSION['role_name'];
$message = '';
$error   = '';

// Pre-select customer if passed via URL parameter
$preselectedCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// Handle Form Submission for Registering a New Policy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_policy') {
    $customerId       = (int)($_POST['customer_id'] ?? 0);
    $policyNumber     = trim($_POST['policy_number'] ?? '');
    $policyType       = trim($_POST['policy_type'] ?? '');
    $sumAssured       = (float)($_POST['sum_assured'] ?? 0);
    $premiumAmount    = (float)($_POST['premium_amount'] ?? 0);
    $paymentFrequency = trim($_POST['payment_frequency'] ?? '');
    $startDate        = trim($_POST['start_date'] ?? '');
    $endDate          = trim($_POST['end_date'] ?? '');

    if ($customerId > 0 && !empty($policyNumber) && !empty($policyType) && $sumAssured > 0 && $premiumAmount > 0 && !empty($startDate) && !empty($endDate)) {
        $stmtInsert = $pdo->prepare("
            INSERT INTO policies (customer_id, policy_number, policy_type, sum_assured, premium_amount, payment_frequency, start_date, end_date, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())
        ");
        if ($stmtInsert->execute([$customerId, $policyNumber, $policyType, $sumAssured, $premiumAmount, $paymentFrequency, $startDate, $endDate])) {
            $message = "Policy #{$policyNumber} successfully registered!";
        } else {
            $error = "Failed to register policy. Please verify input data.";
        }
    } else {
        $error = "Please fill in all required fields marked with an asterisk (*).";
    }
}

// Scope filter for agent hierarchy
$scopeFilter = getAgentFilterSQL($role, 'u');
$params      = getAgentFilterParams($role, $userId);

// Fetch customers for the selection dropdown
$stmtCustomers = $pdo->prepare("
    SELECT c.id, c.full_name 
    FROM customers c
    LEFT JOIN users u ON c.agent_id = u.id
    WHERE $scopeFilter
    ORDER BY c.full_name ASC
");
$stmtCustomers->execute($params);
$availableCustomers = $stmtCustomers->fetchAll();

// Fetch Policy Records (Joining users via c.agent_id)
$stmtPolicies = $pdo->prepare("
    SELECT p.*, c.full_name AS customer_name, u.full_name AS agent_name
    FROM policies p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u ON c.agent_id = u.id
    WHERE $scopeFilter
    ORDER BY p.id DESC
");
$stmtPolicies->execute($params);
$policies = $stmtPolicies->fetchAll();

// Calculate total policy metrics
$totalPoliciesCount = count($policies);
$totalSumAssured    = array_sum(array_column($policies, 'sum_assured'));

include 'header.php';
?>

<h1 class="page-title">Policy Details</h1>

<!-- Alert Messages -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Overview Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">ACTIVE POLICIES</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #2b3674; margin-top: 5px;"><?= $totalPoliciesCount ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TOTAL SUM ASSURED</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #2b3674; margin-top: 5px;">$<?= number_format($totalSumAssured, 2) ?></div>
    </div>
</div>

<!-- Register New Policy Card -->
<div class="card">
    <h2 class="card-title">Register New Policy</h2>
    <form method="POST">
        <input type="hidden" name="action" value="register_policy">

        <div class="form-grid">
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Customer <span style="color: red;">*</span></label>
                <select name="customer_id" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($availableCustomers as $cust): ?>
                        <option value="<?= $cust['id'] ?>" <?= $preselectedCustomerId === $cust['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cust['full_name']) ?> (#CUST-<?= $cust['id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Policy Number <span style="color: red;">*</span></label>
                <input type="text" name="policy_number" placeholder="e.g. POL-100293" required>
            </div>

            <div class="form-group">
                <label>Policy Type <span style="color: red;">*</span></label>
                <select name="policy_type" required>
                    <option value="Life Insurance">Life Insurance</option>
                    <option value="Health Insurance">Health Insurance</option>
                    <option value="Auto Insurance">Auto Insurance</option>
                    <option value="Property Insurance">Property Insurance</option>
                    <option value="Term Insurance">Term Insurance</option>
                </select>
            </div>

            <div class="form-group">
                <label>Sum Assured ($) <span style="color: red;">*</span></label>
                <input type="number" step="0.01" name="sum_assured" placeholder="100000.00" required>
            </div>

            <div class="form-group">
                <label>Premium Amount ($) <span style="color: red;">*</span></label>
                <input type="number" step="0.01" name="premium_amount" placeholder="500.00" required>
            </div>

            <div class="form-group">
                <label>Payment Frequency <span style="color: red;">*</span></label>
                <select name="payment_frequency" required>
                    <option value="Monthly">Monthly</option>
                    <option value="Quarterly">Quarterly</option>
                    <option value="Semi-Annually">Semi-Annually</option>
                    <option value="Annually">Annually</option>
                </select>
            </div>

            <div class="form-group">
                <label>Start Date <span style="color: red;">*</span></label>
                <input type="date" name="start_date" required>
            </div>

            <div class="form-group">
                <label>End Date <span style="color: red;">*</span></label>
                <input type="date" name="end_date" required>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: right;">
            <button type="submit" class="btn btn-success">Save Policy</button>
        </div>
    </form>
</div>

<!-- Active Policy Records Directory -->
<div class="card">
    <h2 class="card-title">Active Policy Records</h2>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Policy No.</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Sum Assured</th>
                    <th>Premium</th>
                    <th>Frequency</th>
                    <th>Term</th>
                    <th>Status</th>
                    <th>Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($policies)): ?>
                    <?php foreach ($policies as $pol): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pol['policy_number']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($pol['customer_name']) ?></strong>
                            </td>
                            <td>
                                <span style="font-weight: 500;"><?= htmlspecialchars($pol['policy_type']) ?></span>
                            </td>
                            <td>
                                <strong style="color: #2b3674;">$<?= number_format($pol['sum_assured'], 2) ?></strong>
                            </td>
                            <td>
                                $<?= number_format($pol['premium_amount'], 2) ?>
                            </td>
                            <td><?= htmlspecialchars($pol['payment_frequency']) ?></td>
                            <td style="font-size: 0.82rem; color: var(--text-muted);">
                                <?= date('M Y', strtotime($pol['start_date'])) ?> &ndash; <?= date('M Y', strtotime($pol['end_date'])) ?>
                            </td>
                            <td>
                                <span class="badge status-<?= htmlspecialchars($pol['status']) ?>">
                                    <?= htmlspecialchars($pol['status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="user-badge" style="background: #e9ecef; color: #333; padding: 4px 8px;">
                                    <?= htmlspecialchars($pol['agent_name'] ?? 'Unassigned') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No policy records found. Register a new policy using the form above.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>