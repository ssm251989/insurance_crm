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

// Handle Onboarding New Assistant Agent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_assistant') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($fullName) && !empty($email) && !empty($password)) {
        // Check if email already exists
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        
        if ($stmtCheck->fetch()) {
            $error = "An account with email '{$email}' already exists.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            // Insert new assistant assigned to the current user (super agent/manager)
            $stmtInsert = $pdo->prepare("
                INSERT INTO users (full_name, email, password, role_name, manager_id, created_at) 
                VALUES (?, ?, ?, 'Assistant Agent', ?, NOW())
            ");
            
            if ($stmtInsert->execute([$fullName, $email, $hashedPassword, $userId])) {
                $message = "Assistant Agent '{$fullName}' successfully onboarded!";
            } else {
                $error = "Failed to create assistant account. Please try again.";
            }
        }
    } else {
        $error = "Please fill in all required fields marked with an asterisk (*).";
    }
}

// Fetch Mapped Assistant Agents along with lead and customer counts
$stmtTeam = $pdo->prepare("
    SELECT 
        u.*,
        COUNT(DISTINCT l.id) AS assigned_leads_count,
        COUNT(DISTINCT c.id) AS active_customers_count
    FROM users u
    LEFT JOIN leads l ON l.agent_id = u.id
    LEFT JOIN customers c ON c.agent_id = u.id
    WHERE u.manager_id = ? OR u.id = ?
    GROUP BY u.id
    ORDER BY u.id ASC
");
$stmtTeam->execute([$userId, $userId]);
$teamMembers = $stmtTeam->fetchAll();

// Calculate Summary Statistics
$totalTeamMembers = count($teamMembers);
$totalAssignedLeads = array_sum(array_column($teamMembers, 'assigned_leads_count'));
$totalActiveCustomers = array_sum(array_column($teamMembers, 'active_customers_count'));

include 'header.php';
?>

<h1 class="page-title">Team Management <span style="font-size: 1rem; color: var(--text-muted); font-weight: 400;">(Super Agent Dashboard)</span></h1>

<!-- Alert Messages -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Summary Metric Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TEAM MEMBERS</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #2b3674; margin-top: 5px;"><?= $totalTeamMembers ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">ASSIGNED LEADS</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #2b3674; margin-top: 5px;"><?= $totalAssignedLeads ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">ACTIVE CUSTOMERS</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #28a745; margin-top: 5px;"><?= $totalActiveCustomers ?></div>
    </div>
</div>

<!-- Onboard New Assistant Agent Card -->
<div class="card">
    <h2 class="card-title">Onboard New Assistant Agent</h2>
    <form method="POST">
        <input type="hidden" name="action" value="create_assistant">

        <div class="form-grid">
            <div class="form-group">
                <label>Full Name <span style="color: red;">*</span></label>
                <input type="text" name="full_name" placeholder="John Doe" required>
            </div>

            <div class="form-group">
                <label>Email Address (Key) <span style="color: red;">*</span></label>
                <input type="email" name="email" placeholder="assistant@agency.com" required>
            </div>

            <div class="form-group">
                <label>Default Password <span style="color: red;">*</span></label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: right;">
            <button type="submit" class="btn btn-success">Create Assistant Account</button>
        </div>
    </form>
</div>

<!-- Mapped Assistant Agents Roster Table Card -->
<div class="card">
    <h2 class="card-title">Mapped Assistant Agents Roster</h2>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Agent ID</th>
                    <th>Full Name</th>
                    <th>Email Key</th>
                    <th style="text-align: center;">Assigned Leads</th>
                    <th style="text-align: center;">Active Customers</th>
                    <th>Joined Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($teamMembers)): ?>
                    <?php foreach ($teamMembers as $member): ?>
                        <tr>
                            <td><strong>AGT-<?= str_pad($member['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($member['full_name']) ?></strong>
                                <?php if ($member['id'] == $userId): ?>
                                    <span style="font-size: 0.75rem; background: #2b3674; color: #fff; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($member['email']) ?></td>
                            <td style="text-align: center;">
                                <span class="badge" style="background: #e9ecef; color: #333; font-size: 0.85rem; padding: 4px 10px; border-radius: 12px;">
                                    <?= $member['assigned_leads_count'] ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" style="background-color: <?= $member['active_customers_count'] > 0 ? '#28a745' : '#6c757d' ?>; color: #fff; font-size: 0.85rem; padding: 4px 10px; border-radius: 12px;">
                                    <?= $member['active_customers_count'] ?>
                                </span>
                            </td>
                            <td>
                                <?= !empty($member['created_at']) ? date('Y-m-d', strtotime($member['created_at'])) : date('Y-m-d') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No mapped team members found. Add an assistant agent using the form above.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>