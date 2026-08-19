<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth_check.php';

$userId  = $_SESSION['user_id'];
$role    = $_SESSION['role_name'] ?? $_SESSION['role'] ?? 'Super Agent';
$message = '';
$error   = '';

// Handle Single or Bulk Lead Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_lead') {
    $targetAgentId = (int)($_POST['target_agent_id'] ?? 0);
    $selectedLeads = $_POST['lead_ids'] ?? [];

    if (!empty($_POST['lead_id'])) {
        $selectedLeads[] = (int)$_POST['lead_id'];
    }

    if ($targetAgentId > 0 && !empty($selectedLeads)) {
        $placeholders = implode(',', array_fill(0, count($selectedLeads), '?'));
        $queryParams  = array_merge([$targetAgentId], $selectedLeads);

        $stmtAssign = $pdo->prepare("
            UPDATE leads 
            SET agent_id = ? 
            WHERE id IN ($placeholders)
        ");
        
        if ($stmtAssign->execute($queryParams)) {
            $count = count($selectedLeads);
            $message = "Successfully assigned {$count} lead(s) to the selected agent!";
        } else {
            $error = "Failed to assign leads. Please try again.";
        }
    } else {
        $error = "Please select at least one lead and a target agent.";
    }
}

// Fetch available team agents for assignment dropdown
$agentScope = getAgentFilterSQL($role, 'u');
$stmtAgents = $pdo->prepare("
    SELECT u.* 
    FROM users u 
    WHERE $agentScope
    ORDER BY u.full_name ASC
");
$stmtAgents->execute(getAgentFilterParams($role, $userId));
$teamAgents = $stmtAgents->fetchAll();

// Fetch Leads list with currently assigned agent names
$stmtLeads = $pdo->prepare("
    SELECT l.*, u.full_name AS agent_name 
    FROM leads l
    LEFT JOIN users u ON l.agent_id = u.id
    ORDER BY l.id DESC
");
$stmtLeads->execute();
$leads = $stmtLeads->fetchAll();

// Calculate Summary Statistics
$totalLeads = count($leads);
$unassignedCount = 0;
foreach ($leads as $lead) {
    if (empty($lead['agent_id'])) {
        $unassignedCount++;
    }
}
$assignedCount = $totalLeads - $unassignedCount;

include 'header.php';
?>

<h1 class="page-title">Assign & Reallocate Leads</h1>

<!-- Alert Messages -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TOTAL LEADS</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #2b3674; margin-top: 5px;"><?= $totalLeads ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">ASSIGNED LEADS</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #28a745; margin-top: 5px;"><?= $assignedCount ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; text-align: center; padding: 15px;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">UNASSIGNED LEADS</span>
        <div style="font-size: 1.8rem; font-weight: 700; color: #dc3545; margin-top: 5px;"><?= $unassignedCount ?></div>
    </div>
</div>

<!-- Bulk Lead Assignment Form Card -->
<form method="POST">
    <input type="hidden" name="action" value="assign_lead">

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
            <h2 class="card-title" style="margin-bottom: 0;">Lead Assignment Roster</h2>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <select name="target_agent_id" required style="padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; min-width: 220px;">
                    <option value="">-- Assign Selected To Agent --</option>
                    <?php foreach ($teamAgents as $agent): ?>
                        <?php $agentRole = $agent['role_name'] ?? $agent['role'] ?? 'Agent'; ?>
                        <option value="<?= $agent['id'] ?>">
                            <?= htmlspecialchars($agent['full_name']) ?> (<?= htmlspecialchars($agentRole) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-success" style="padding: 8px 16px;">Assign Selected</button>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                        </th>
                        <th>Lead ID</th>
                        <th>Lead Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Currently Assigned Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($leads)): ?>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="lead_ids[]" value="<?= $lead['id'] ?>" class="lead-checkbox">
                                </td>
                                <td><strong>#LEAD-<?= str_pad($lead['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><strong><?= htmlspecialchars($lead['full_name'] ?? $lead['name'] ?? 'N/A') ?></strong></td>
                                <td><?= htmlspecialchars($lead['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($lead['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge status-<?= htmlspecialchars($lead['status'] ?? 'New') ?>">
                                        <?= htmlspecialchars($lead['status'] ?? 'New') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($lead['agent_name'])): ?>
                                        <span class="user-badge" style="background: #e9ecef; color: #333; padding: 4px 8px; border-radius: 4px;">
                                            <?= htmlspecialchars($lead['agent_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-weight: 600; font-size: 0.85rem;">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                No lead records found in the database.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<script>
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.lead-checkbox');
    checkboxes.forEach(cb => cb.checked = master.checked);
}
</script>

<?php include 'footer.php'; ?>