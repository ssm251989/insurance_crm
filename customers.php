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

// Get search query if provided
$search = trim($_GET['search'] ?? '');

// Scope filter for agent hierarchy
$scopeFilter = getAgentFilterSQL($role, 'u');
$params      = getAgentFilterParams($role, $userId);

// Search condition logic
$searchSQL = "";
if (!empty($search)) {
    $searchSQL = " AND (c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch Customers along with active policy counts
$stmtCustomers = $pdo->prepare("
    SELECT 
        c.*, 
        u.full_name AS agent_name,
        COUNT(p.id) AS active_policies_count
    FROM customers c
    LEFT JOIN users u ON c.agent_id = u.id
    LEFT JOIN policies p ON p.customer_id = c.id AND p.status = 'Active'
    WHERE $scopeFilter $searchSQL
    GROUP BY c.id
    ORDER BY c.id DESC
");
$stmtCustomers->execute($params);
$customers = $stmtCustomers->fetchAll();

// Calculate total metrics for overview stats
$totalCustomers = count($customers);
$totalActivePolicies = array_sum(array_column($customers, 'active_policies_count'));

// Include common header
include 'header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1 class="page-title" style="margin: 0;">Customer Directory</h1>
    <a href="convert_lead.php" class="btn btn-success">+ Add / Convert Customer</a>
</div>

<!-- Quick Stats Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TOTAL CUSTOMERS</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #2b3674; margin-top: 5px;"><?= $totalCustomers ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TOTAL ACTIVE POLICIES</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #2b3674; margin-top: 5px;"><?= $totalActivePolicies ?></div>
    </div>
</div>

<!-- Filter & Search Section -->
<div class="card" style="padding: 15px 20px; margin-bottom: 20px;">
    <form method="GET" action="customers.php" style="display: flex; gap: 10px; align-items: center;">
        <input 
            type="text" 
            name="search" 
            placeholder="Search by name, email, or phone..." 
            value="<?= htmlspecialchars($search) ?>" 
            style="flex: 1; margin: 0;"
        >
        <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Search</button>
        <?php if (!empty($search)): ?>
            <a href="customers.php" class="btn" style="background: #e0e0e0; color: #333; text-decoration: none; padding: 10px 15px;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Customer Directory Table -->
<div class="card">
    <h2 class="card-title">All Onboarded Customers</h2>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Assigned Agent</th>
                    <th style="text-align: center;">Active Policies</th>
                    <th>Onboarded Date</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong>#CUST-<?= htmlspecialchars($customer['id']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($customer['full_name'] ?? ($customer['first_name'] . ' ' . $customer['last_name'])) ?></strong>
                            </td>
                            <td>
                                <?= !empty($customer['email']) ? htmlspecialchars($customer['email']) : '<span style="color: #bbb;">N/A</span>' ?>
                            </td>
                            <td><?= htmlspecialchars($customer['phone']) ?></td>
                            <td>
                                <span class="user-badge" style="background: #e9ecef; color: #333; padding: 4px 8px;">
                                    <?= htmlspecialchars($customer['agent_name'] ?? 'Unassigned') ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" style="background-color: <?= $customer['active_policies_count'] > 0 ? '#28a745' : '#6c757d' ?>; color: #fff; font-size: 0.82rem; padding: 4px 10px; border-radius: 12px;">
                                    <?= $customer['active_policies_count'] ?> Policy<?= $customer['active_policies_count'] == 1 ? '' : 'ies' ?>
                                </span>
                            </td>
                            <td>
                                <?= !empty($customer['created_at']) ? date('M d, Y', strtotime($customer['created_at'])) : date('M d, Y') ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="policies.php?customer_id=<?= $customer['id'] ?>" class="btn" style="padding: 5px 10px; font-size: 0.8rem; margin-right: 4px; text-decoration: none;">View Policies</a>
                                <a href="add_policy.php?customer_id=<?= $customer['id'] ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8rem; text-decoration: none;">+ Policy</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            <?php if (!empty($search)): ?>
                                No customers match your search criteria "<strong><?= htmlspecialchars($search) ?></strong>".
                            <?php else: ?>
                                No customer records found. Convert leads or onboard customers to populate this list.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>