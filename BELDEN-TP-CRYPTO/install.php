<?php
// install.php - Script d'installation automatique
// À SUPPRIMER APRÈS INSTALLATION !

$host = 'localhost';
$username = 'root';
$password = '';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Installation - Chat Belden</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .install-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .success {
            color: #48bb78;
            padding: 10px;
            background: #c6f6d5;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            color: #e53e3e;
            padding: 10px;
            background: #fed7d7;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #5a67d8;
        }
    </style>
</head>
<body>
    <div class='install-container'>
        <h1>📦 Installation de Chat Belden</h1>";

try {
    // Connexion sans base de données
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Connexion au serveur MySQL réussie</p>";
    
    // Création de la base de données
    $pdo->exec("CREATE DATABASE IF NOT EXISTS chat_belden");
    echo "<p>✅ Base de données 'chat_belden' créée</p>";
    
    // Utilisation de la base de données
    $pdo->exec("USE chat_belden");
    
    // Création de la table utilisateur
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS utilisateur (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            is_blocked BOOLEAN DEFAULT FALSE,
            is_admin BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p>✅ Table 'utilisateur' créée</p>";
    
    // Création de la table algorithmes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS algorithmes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            libelle VARCHAR(100) NOT NULL
        )
    ");
    echo "<p>✅ Table 'algorithmes' créée</p>";
    
    // Création de la table message
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS message (
            id INT PRIMARY KEY AUTO_INCREMENT,
            contenu_message TEXT NOT NULL,
            date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            id_user INT NOT NULL,
            id_algorithme INT NOT NULL,
            FOREIGN KEY (id_user) REFERENCES utilisateur(id) ON DELETE CASCADE,
            FOREIGN KEY (id_algorithme) REFERENCES algorithmes(id)
        )
    ");
    echo "<p>✅ Table 'message' créée</p>";
    
    // Insertion des algorithmes
    $stmt = $pdo->prepare("INSERT IGNORE INTO algorithmes (libelle) VALUES ('AES-256-CBC'), ('AES-128-CBC'), ('ChaCha20')");
    $stmt->execute();
    echo "<p>✅ Algorithmes de chiffrement insérés</p>";
    
    // Vérifier si l'admin existe déjà
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE username = 'admin'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Création d'un compte administrateur par défaut
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO utilisateur (username, password, is_admin) VALUES ('admin', ?, TRUE)");
        $stmt->execute([$adminPassword]);
        echo "<p>✅ Compte administrateur créé (admin / admin123)</p>";
    } else {
        echo "<p>ℹ️ Le compte administrateur existe déjà</p>";
    }
    
    echo "<div class='success'>
            <strong>✅ Installation terminée avec succès !</strong><br>
            Vous pouvez maintenant utiliser l'application.
          </div>";
    echo "<a href='index.php' class='btn'>🚀 Accéder à l'application</a>";
    echo "<br><br><small style='color: #999;'>⚠️ N'oubliez pas de supprimer ce fichier install.php pour des raisons de sécurité !</small>";
    
} catch(PDOException $e) {
    echo "<div class='error'>
            <strong>❌ Erreur d'installation:</strong><br>
            " . $e->getMessage() . "
          </div>";
    echo "<a href='javascript:history.back()' class='btn'>↻ Réessayer</a>";
}

echo "    </div>
</body>
</html>";
?>