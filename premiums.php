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

// Handle Marking Premium Payment as Paid
if (isset($_GET['mark_paid_id'])) {
    $paymentId  = (int)$_GET['mark_paid_id'];
    $amountPaid = isset($_GET['amount']) ? (float)$_GET['amount'] : 0.00;
    
    $stmtPaid = $pdo->prepare("
        UPDATE premiums 
        SET status = 'Paid', amount_paid = ?, payment_date = NOW() 
        WHERE id = ?
    ");
    if ($stmtPaid->execute([$amountPaid, $paymentId])) {
        $message = "Payment record #{$paymentId} successfully marked as Paid!";
    } else {
        $error = "Failed to update payment record.";
    }
}

// Handle Form Submission for Scheduling Premium Due Date
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule_premium') {
    $policyId  = (int)($_POST['policy_id'] ?? 0);
    $dueDate   = trim($_POST['due_date'] ?? '');
    $amountDue = (float)($_POST['amount_due'] ?? 0);

    if ($policyId > 0 && !empty($dueDate) && $amountDue > 0) {
        $stmtInsert = $pdo->prepare("
            INSERT INTO premiums (policy_id, due_date, amount_due, amount_paid, status, created_at) 
            VALUES (?, ?, ?, 0.00, 'Pending', NOW())
        ");
        if ($stmtInsert->execute([$policyId, $dueDate, $amountDue])) {
            $message = "Premium schedule added successfully!";
        } else {
            $error = "Failed to add payment schedule. Please try again.";
        }
    } else {
        $error = "Please fill in all required fields with valid values.";
    }
}

// Scope filter for agent hierarchy
$scopeFilter = getAgentFilterSQL($role, 'u');
$params      = getAgentFilterParams($role, $userId);

// Fetch policies for dropdown selection
$stmtPolicies = $pdo->prepare("
    SELECT p.id, p.policy_number, c.full_name AS customer_name 
    FROM policies p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u ON c.agent_id = u.id
    WHERE $scopeFilter AND p.status = 'Active'
    ORDER BY p.policy_number ASC
");
$stmtPolicies->execute($params);
$availablePolicies = $stmtPolicies->fetchAll();

// Fetch Premium Records
$stmtPremiums = $pdo->prepare("
    SELECT pr.*, p.policy_number, c.full_name AS customer_name
    FROM premiums pr
    JOIN policies p ON pr.policy_id = p.id
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u ON c.agent_id = u.id
    WHERE $scopeFilter
    ORDER BY pr.due_date ASC
");
$stmtPremiums->execute($params);
$premiums = $stmtPremiums->fetchAll();

// Calculate Summary Statistics
$totalDue = 0;
$totalCollected = 0;
$pendingCount = 0;

foreach ($premiums as $pr) {
    $totalDue += $pr['amount_due'];
    if ($pr['status'] === 'Paid') {
        $totalCollected += ($pr['amount_paid'] > 0 ? $pr['amount_paid'] : $pr['amount_due']);
    } else {
        $pendingCount++;
    }
}

include 'header.php';
?>

<h1 class="page-title">Premium Tracking Details</h1>

<!-- Alert Messages -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Financial Metrics Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TOTAL PREMIUM DUE</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #2b3674; margin-top: 5px;">$<?= number_format($totalDue, 2) ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TOTAL COLLECTED</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #28a745; margin-top: 5px;">$<?= number_format($totalCollected, 2) ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">PENDING PAYMENTS</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #dc3545; margin-top: 5px;"><?= $pendingCount ?></div>
    </div>
</div>

<!-- Schedule Premium Form Card -->
<div class="card">
    <h2 class="card-title">Schedule Premium Due Date</h2>
    <form method="POST">
        <input type="hidden" name="action" value="schedule_premium">

        <div class="form-grid">
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Policy <span style="color: red;">*</span></label>
                <select name="policy_id" required>
                    <option value="">-- Select Policy --</option>
                    <?php foreach ($availablePolicies as $pol): ?>
                        <option value="<?= $pol['id'] ?>">
                            <?= htmlspecialchars($pol['policy_number']) ?> &ndash; <?= htmlspecialchars($pol['customer_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Due Date <span style="color: red;">*</span></label>
                <input type="date" name="due_date" required>
            </div>

            <div class="form-group">
                <label>Amount Due ($) <span style="color: red;">*</span></label>
                <input type="number" step="0.01" name="amount_due" placeholder="500.00" required>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: right;">
            <button type="submit" class="btn btn-success">Add Payment Schedule</button>
        </div>
    </form>
</div>

<!-- Payment Ledger Table Card -->
<div class="card">
    <h2 class="card-title">Payment Ledger & Tracking Schedule</h2>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Policy No.</th>
                    <th>Customer</th>
                    <th>Due Date</th>
                    <th>Amount Due</th>
                    <th>Amount Paid</th>
                    <th>Payment Date</th>
                    <th>Status</th>
                    <th style="text-align: center;">Record Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($premiums)): ?>
                    <?php foreach ($premiums as $row): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($row['id']) ?></td>
                            <td><strong><?= htmlspecialchars($row['policy_number']) ?></strong></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td>
                                <strong><?= date('M d, Y', strtotime($row['due_date'])) ?></strong>
                            </td>
                            <td>
                                <strong style="color: #2b3674;">$<?= number_format($row['amount_due'], 2) ?></strong>
                            </td>
                            <td>
                                <?= $row['amount_paid'] > 0 ? '$' . number_format($row['amount_paid'], 2) : '<span style="color: #bbb;">$0.00</span>' ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?= !empty($row['payment_date']) ? date('M d, Y', strtotime($row['payment_date'])) : '<span style="color: #bbb;">&ndash;</span>' ?>
                            </td>
                            <td>
                                <span class="badge status-<?= htmlspecialchars($row['status']) ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($row['status'] !== 'Paid'): ?>
                                    <a href="premiums.php?mark_paid_id=<?= $row['id'] ?>&amount=<?= $row['amount_due'] ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8rem; text-decoration: none;">Mark Paid</a>
                                <?php else: ?>
                                    <span style="color: #28a745; font-weight: 600; font-size: 0.85rem;">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No payment schedule records found. Use the form above to schedule a premium due date.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>