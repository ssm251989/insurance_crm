<?php
require_once 'db.php';

// Generate a valid local hash for 'password123'
$newHash = password_hash('password123', PASSWORD_BCRYPT);

// Update all demo users with the new hash
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email IN ('super@agency.com', 'assistant1@agency.com', 'assistant2@agency.com')");
$stmt->execute([$newHash]);

echo "<h2 style='color:green;'>Success! Passwords updated successfully.</h2>";
echo "<p>You can now log in at <a href='login.php'>login.php</a> using:</p>";
echo "<ul><li><strong>Email:</strong> super@agency.com</li><li><strong>Password:</strong> password123</li></ul>";
?>