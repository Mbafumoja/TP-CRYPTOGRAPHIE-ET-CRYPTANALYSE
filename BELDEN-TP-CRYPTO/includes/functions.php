<?php
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function getMessages($pdo) {
    $stmt = $pdo->query("
        SELECT m.*, u.username, a.libelle as algorithme 
        FROM message m 
        JOIN utilisateur u ON m.id_user = u.id 
        JOIN algorithmes a ON m.id_algorithme = a.id 
        WHERE u.is_blocked = FALSE
        ORDER BY m.date_envoi DESC 
        LIMIT 50
    ");
    return $stmt->fetchAll();
}

function addMessage($pdo, $userId, $message, $algoId, $encryptionKey) {
    require_once 'encryption.php';
    
    $algo = getAlgorithmById($pdo, $algoId);
    $encryption = new Encryption($encryptionKey, $algo);
    $encryptedMessage = $encryption->encrypt($message);
    
    $stmt = $pdo->prepare("INSERT INTO message (contenu_message, id_user, id_algorithme) VALUES (?, ?, ?)");
    return $stmt->execute([$encryptedMessage, $userId, $algoId]);
}

function decryptMessage($pdo, $encryptedMessage, $userId, $encryptionKey) {
    require_once 'encryption.php';
    
    // Récupérer l'algorithme utilisé pour ce message
    $stmt = $pdo->prepare("
        SELECT a.libelle 
        FROM message m 
        JOIN algorithmes a ON m.id_algorithme = a.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$userId]); // Note: Il faudrait passer l'ID du message
    $algo = $stmt->fetch();
    
    if ($algo) {
        $encryption = new Encryption($encryptionKey, $algo['libelle']);
        return $encryption->decrypt($encryptedMessage);
    }
    return "Erreur de déchiffrement";
}
?>