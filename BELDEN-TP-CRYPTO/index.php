<?php
session_start();

// Si l'utilisateur est déjà connecté, rediriger vers le chat
if (isset($_SESSION['user_id'])) {
    header('Location: chat.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Sécurisé Belden</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="hero">
            <h1>💬 Chat Sécurisé Belden</h1>
            <p>Messages chiffrés de bout en bout avec AES-256</p>
            <div class="buttons">
                <a href="login.php" class="btn btn-primary">🔐 Se connecter</a>
                <a href="register.php" class="btn btn-secondary">📝 S'inscrire</a>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #f7fafc; border-radius: 10px;">
                <h3 style="color: #667eea;">✨ Fonctionnalités</h3>
                <ul style="list-style: none; margin-top: 10px;">
                    <li>🔒 Messages chiffrés (AES-256, AES-128, ChaCha20)</li>
                    <li>👥 Chat en temps réel</li>
                    <li>🛡️ Administration et modération</li>
                    <li>📱 Interface responsive</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>