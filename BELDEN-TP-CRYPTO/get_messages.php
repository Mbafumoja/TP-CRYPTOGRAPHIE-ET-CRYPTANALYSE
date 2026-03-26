<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/encryption.php';

if (!isLoggedIn()) {
    exit();
}

// Récupération des messages
$stmt = $pdo->query("
    SELECT m.*, u.username, a.libelle as algorithme 
    FROM message m 
    JOIN utilisateur u ON m.id_user = u.id 
    JOIN algorithmes a ON m.id_algorithme = a.id 
    WHERE u.is_blocked = FALSE
    ORDER BY m.date_envoi DESC 
    LIMIT 50
");
$messages = $stmt->fetchAll();
?>

<?php if (count($messages) == 0): ?>
    <div style="text-align: center; padding: 50px; color: #999;">
        💬 Aucun message pour le moment.
    </div>
<?php else: ?>
    <?php foreach (array_reverse($messages) as $msg): ?>
        <div class="message <?= $msg['id_user'] == $_SESSION['user_id'] ? 'message-right' : 'message-left' ?>">
            <div class="message-header">
                <strong><?= htmlspecialchars($msg['username']) ?></strong>
                <small><?= date('d/m/Y H:i', strtotime($msg['date_envoi'])) ?></small>
            </div>
            <div class="message-content">
                <?php 
                try {
                    $encryption = new Encryption($_SESSION['encryption_key'], $msg['algorithme']);
                    $decrypted = $encryption->decrypt($msg['contenu_message']);
                    echo nl2br(htmlspecialchars($decrypted));
                } catch(Exception $e) {
                    echo "[Message chiffré]";
                }
                ?>
            </div>
            <div class="message-footer">
                <small>🔒 Chiffré avec: <?= $msg['algorithme'] ?></small>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>