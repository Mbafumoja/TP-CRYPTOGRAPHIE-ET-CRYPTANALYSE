<?php
// Vérifier si une session n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function isBlocked($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT is_blocked FROM utilisateur WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result && $result['is_blocked'];
    } catch(PDOException $e) {
        return false;
    }
}

function checkUserStatus($pdo) {
    if (isLoggedIn()) {
        if (isBlocked($pdo, $_SESSION['user_id'])) {
            session_destroy();
            header('Location: login.php?error=account_blocked');
            exit();
        }
    }
}

function blockUser($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE utilisateur SET is_blocked = TRUE WHERE id = ?");
        return $stmt->execute([$userId]);
    } catch(PDOException $e) {
        return false;
    }
}

function unblockUser($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE utilisateur SET is_blocked = FALSE WHERE id = ?");
        return $stmt->execute([$userId]);
    } catch(PDOException $e) {
        return false;
    }
}
?>