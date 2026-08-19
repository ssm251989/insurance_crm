<div style="background: #333; padding: 10px; color: #fff; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <strong>Insurance CRM</strong> | 
        <a href="dashboard.php" style="color: #fff; margin-right: 10px;">Dashboard</a>
        <a href="leads.php" style="color: #fff; margin-right: 10px;">Leads</a>
        <a href="followups.php" style="color: #fff; margin-right: 10px;">Follow-ups</a>
        <a href="customers.php" style="color: #fff; margin-right: 10px;">Customers</a>
        <a href="policies.php" style="color: #fff; margin-right: 10px;">Policies</a>
        <a href="premiums.php" style="color: #fff; margin-right: 10px;">Premiums</a>
        <?php if ($_SESSION['role_name'] === 'Super Agent'): ?>
            <a href="manage_assistants.php" style="color: #fff; margin-right: 10px;">Team</a>
            <a href="assign_leads.php" style="color: #fff; margin-right: 10px;">Delegation</a>
        <?php endif; ?>
    </div>
    <div>
        <span><?= htmlspecialchars($_SESSION['full_name']) ?></span> | 
        <a href="logout.php" style="color: #ff6b6b; font-weight: bold; text-decoration: none;">Logout</a>
    </div>
</div>