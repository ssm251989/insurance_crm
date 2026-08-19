<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth_check.php';

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role_name'];
$message = '';
$error = '';

// Handle Form Submission for Creating a New Lead
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_lead') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if (!empty($firstName) && !empty($lastName) && !empty($phone)) {
        $stmt = $pdo->prepare("INSERT INTO leads (first_name, last_name, email, phone, agent_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'New', NOW())");
        if ($stmt->execute([$firstName, $lastName, $email, $phone, $userId])) {
            $message = "Lead successfully created!";
        } else {
            $error = "Failed to create lead. Please try again.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Fetch Leads Data based on agent hierarchy scope
$scopeFilter = getAgentFilterSQL($role, 'u');
$params      = getAgentFilterParams($role, $userId);

$stmtLeads = $pdo->prepare("
    SELECT l.*, u.full_name as agent_name 
    FROM leads l
    JOIN users u ON l.agent_id = u.id
    WHERE $scopeFilter
    ORDER BY l.id DESC
");
$stmtLeads->execute($params);
$leads = $stmtLeads->fetchAll();

// Include common layout header (Navbar & CSS)
include 'header.php';
?>

<h1 class="page-title">Lead Management</h1>

<!-- Alert Messages -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Create Lead Form Card -->
<div class="card">
    <h2 class="card-title">Create New Lead</h2>
    <form method="POST">
        <input type="hidden" name="action" value="create_lead">
        
        <div class="form-grid">
            <div class="form-group">
                <label>First Name <span style="color: red;">*</span></label>
                <input type="text" name="first_name" placeholder="John" required>
            </div>
            
            <div class="form-group">
                <label>Last Name <span style="color: red;">*</span></label>
                <input type="text" name="last_name" placeholder="Doe" required>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="john.doe@example.com">
            </div>
            
            <div class="form-group">
                <label>Phone Number <span style="color: red;">*</span></label>
                <input type="text" name="phone" placeholder="+1 (555) 000-0000" required>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: right;">
            <button type="submit" class="btn btn-success">Save Lead</button>
        </div>
    </form>
</div>

<!-- Lead Directory Table Card -->
<div class="card">
    <h2 class="card-title">Lead Directory</h2>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Contact Info</th>
                    <th>Assigned Agent</th>
                    <th>Status</th>
                    <th>Created Date</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($leads)): ?>
                    <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($lead['id']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']) ?></strong>
                            </td>
                            <td>
                                <div><strong>P:</strong> <?= htmlspecialchars($lead['phone']) ?></div>
                                <?php if (!empty($lead['email'])): ?>
                                    <div style="font-size: 0.82rem; color: var(--text-muted);"><?= htmlspecialchars($lead['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="user-badge" style="background: #e9ecef; color: #333; padding: 4px 8px;">
                                    <?= htmlspecialchars($lead['agent_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge status-<?= htmlspecialchars($lead['status']) ?>">
                                    <?= htmlspecialchars($lead['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($lead['created_at'])) ?></td>
                            <td style="text-align: center;">
                                <a href="followups.php?lead_id=<?= $lead['id'] ?>" class="btn" style="padding: 5px 10px; font-size: 0.8rem; margin-right: 4px;">Follow-up</a>
                                <a href="convert_lead.php?id=<?= $lead['id'] ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8rem;">Convert</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 20px;">
                            No leads found. Use the form above to add your first lead.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
// Include common footer
include 'footer.php'; 
?>