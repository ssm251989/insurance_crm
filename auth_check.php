<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

// Role-based hierarchy scope filter using positional placeholders
function getAgentFilterSQL($role, $alias = 'u') {
    if ($role === 'Super Agent') {
        return "($alias.id = ? OR $alias.parent_agent_id = ?)";
    }
    return "$alias.id = ?";
}

// Helper function to return parameter array based on role
function getAgentFilterParams($role, $userId) {
    if ($role === 'Super Agent') {
        return [$userId, $userId];
    }
    return [$userId];
}
?>