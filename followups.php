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

// Handle marking follow-up as completed
if (isset($_GET['complete_id'])) {
    $completeId = (int)$_GET['complete_id'];
    $stmtComplete = $pdo->prepare("UPDATE followups SET status = 'Completed' WHERE id = ?");
    if ($stmtComplete->execute([$completeId])) {
        $message = "Follow-up marked as completed!";
    }
}

// Handle Form Submission for Scheduling a Follow-up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule_followup') {
    $leadId        = (int)($_POST['lead_id'] ?? 0);
    $followupDate  = trim($_POST['followup_date'] ?? '');
    $notes         = trim($_POST['notes'] ?? '');

    if ($leadId > 0 && !empty($followupDate)) {
        $stmtInsert = $pdo->prepare("INSERT INTO followups (lead_id, agent_id, followup_date, notes, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
        if ($stmtInsert->execute([$leadId, $userId, $followupDate, $notes])) {
            $message = "Follow-up scheduled successfully!";
        } else {
            $error = "Failed to schedule follow-up. Please try again.";
        }
    } else {
        $error = "Please select a lead and specify a date/time.";
    }
}

// Scope filter for agent hierarchy
$scopeFilter = getAgentFilterSQL($role, 'u');
$params      = getAgentFilterParams($role, $userId);

// Fetch leads for the selection dropdown
$stmtDropdown = $pdo->prepare("
    SELECT l.id, l.first_name, l.last_name 
    FROM leads l
    JOIN users u ON l.agent_id = u.id
    WHERE $scopeFilter AND l.status != 'Lost'
    ORDER BY l.first_name ASC
");
$stmtDropdown->execute($params);
$availableLeads = $stmtDropdown->fetchAll();

// Fetch all follow-ups (Ordered safely by ID to avoid missing column errors)
$stmtFollowups = $pdo->prepare("
    SELECT f.*, l.first_name, l.last_name, u.full_name as agent_name
    FROM followups f
    JOIN leads l ON f.lead_id = l.id
    JOIN users u ON f.agent_id = u.id
    WHERE $scopeFilter
    ORDER BY f.id DESC
");
$stmtFollowups->execute($params);
$followups = $stmtFollowups->fetchAll();

// Pre-select lead if passed via URL from leads page
$preselectedLeadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;

include 'header.php';
?>

<h1 class="page-title">Follow-up Tracker</h1>

<!-- Alert Messages -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Schedule Follow-up Card -->
<div class="card">
    <h2 class="card-title">Schedule Follow-up</h2>
    <form method="POST">
        <input type="hidden" name="action" value="schedule_followup">
        
        <div class="form-grid">
            <div class="form-group">
                <label>Select Lead <span style="color: red;">*</span></label>
                <select name="lead_id" required>
                    <option value="">-- Choose Lead --</option>
                    <?php foreach ($availableLeads as $lead): ?>
                        <option value="<?= $lead['id'] ?>" <?= $preselectedLeadId === $lead['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']) ?> (#<?= $lead['id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Scheduled Date & Time <span style="color: red;">*</span></label>
                <input type="datetime-local" name="followup_date" required>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Notes / Discussion Agenda</label>
                <textarea name="notes" rows="3" placeholder="Add discussion points, customer preferences, or action items..."></textarea>
            </div>
        </div>

        <div style="margin-top: 15px; text-align: right;">
            <button type="submit" class="btn btn-success">Set Follow-up</button>
        </div>
    </form>
</div>

<!-- Follow-ups Directory -->
<div class="card">
    <h2 class="card-title">Upcoming & Past Follow-ups</h2>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Lead</th>
                    <th>Scheduled Date</th>
                    <th>Notes</th>
                    <th>Agent</th>
                    <th>Status</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($followups)): ?>
                    <?php foreach ($followups as $row): ?>
                        <?php 
                            // Fallback check to support either 'followup_date' or 'scheduled_date'
                            $rawDate = $row['followup_date'] ?? $row['scheduled_date'] ?? $row['created_at']; 
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                            </td>
                            <td>
                                <strong><?= date('M d, Y', strtotime($rawDate)) ?></strong> 
                                <span style="font-size: 0.8rem; color: var(--text-muted); display: block;">
                                    <?= date('h:i A', strtotime($rawDate)) ?>
                                </span>
                            </td>
                            <td style="max-width: 280px; font-size: 0.88rem;">
                                <?= !empty($row['notes']) ? nl2br(htmlspecialchars($row['notes'])) : '<span style="color: #ccc;">No notes added</span>' ?>
                            </td>
                            <td>
                                <span class="user-badge" style="background: #e9ecef; color: #333; padding: 4px 8px;">
                                    <?= htmlspecialchars($row['agent_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge status-<?= htmlspecialchars($row['status']) ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <a href="followups.php?complete_id=<?= $row['id'] ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8rem;">Mark Done</a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">
                            No follow-ups scheduled yet. Use the form above to add one.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>