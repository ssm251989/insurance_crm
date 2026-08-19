<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance CRM</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">🛡️ Insurance CRM</a>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="leads.php">Leads</a>
        <a href="followups.php">Follow-ups</a>
        <a href="customers.php">Customers</a>
        <a href="policies.php">Policies</a>
        <a href="premiums.php">Premiums</a>
        <?php if ($_SESSION['role_name'] === 'Super Agent'): ?>
            <a href="manage_assistants.php">Team</a>
            <a href="assign_leads.php">Delegation</a>
        <?php endif; ?>
        <span class="user-badge"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<?php endif; ?>

<div class="container">