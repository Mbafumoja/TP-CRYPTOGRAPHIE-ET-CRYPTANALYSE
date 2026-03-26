<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit();
}

// Gestion du blocage/déblocage
if (isset($_GET['block'])) {
    blockUser($pdo, $_GET['block']);
    header('Location: admin.php');
    exit();
}

if (isset($_GET['unblock'])) {
    unblockUser($pdo, $_GET['unblock']);
    header('Location: admin.php');
    exit();
}

// Récupération des utilisateurs
$users = $pdo->query("SELECT * FROM utilisateur ORDER BY id DESC")->fetchAll();

// Statistiques
$totalUsers = $pdo->query("SELECT COUNT(*) FROM utilisateur")->fetchColumn();
$totalMessages = $pdo->query("SELECT COUNT(*) FROM message")->fetchColumn();
$blockedUsers = $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE is_blocked = TRUE")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Chat Belden</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h2>👑 Panneau d'administration - Chat Belden</h2>
            <a href="chat.php" class="btn-back">💬 Retour au chat</a>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?= $totalUsers ?></h3>
                <p>Utilisateurs</p>
            </div>
            <div class="stat-card">
                <h3><?= $totalMessages ?></h3>
                <p>Messages</p>
            </div>
            <div class="stat-card">
                <h3><?= $blockedUsers ?></h3>
                <p>Comptes bloqués</p>
            </div>
        </div>
        
        <h3>Gestion des utilisateurs</h3>
        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom d'utilisateur</th>
                    <th>Date d'inscription</th>
                    <th>Status</th>
                    <th>Rôle</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <?php if ($user['is_blocked']): ?>
                            <span class="badge badge-blocked">🔴 Bloqué</span>
                        <?php else: ?>
                            <span class="badge badge-active">🟢 Actif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['is_admin']): ?>
                            <span class="badge badge-admin">👑 Administrateur</span>
                        <?php else: ?>
                            <span class="badge badge-user">👤 Utilisateur</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$user['is_admin']): ?>
                            <?php if ($user['is_blocked']): ?>
                                <a href="?unblock=<?= $user['id'] ?>" class="btn-unblock" onclick="return confirm('Débloquer cet utilisateur ?')">✅ Débloquer</a>
                            <?php else: ?>
                                <a href="?block=<?= $user['id'] ?>" class="btn-block" onclick="return confirm('Bloquer cet utilisateur ?')">⛔ Bloquer</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">⚠️ Protégé</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>