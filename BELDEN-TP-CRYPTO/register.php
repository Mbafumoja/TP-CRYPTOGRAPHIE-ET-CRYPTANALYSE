<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Vérification que les mots de passe correspondent
    if ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif (strlen($password) < 4) {
        $error = "Le mot de passe doit contenir au moins 4 caractères";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO utilisateur (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hashed_password]);
            $success = "Inscription réussie ! Vous allez être redirigé vers la page de connexion...";
            // Redirection après 2 secondes
            header("refresh:2;url=login.php");
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Ce nom d'utilisateur existe déjà";
            } else {
                $error = "Erreur lors de l'inscription: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Chat Belden</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>📝 Inscription</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>
            
            <form method="POST" action="register.php">
                <div class="form-group">
                    <label>👤 Nom d'utilisateur</label>
                    <input type="text" name="username" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>
                <div class="form-group">
                    <label>🔒 Mot de passe</label>
                    <input type="password" name="password" required minlength="4">
                </div>
                <div class="form-group">
                    <label>🔒 Confirmer le mot de passe</label>
                    <input type="password" name="confirm_password" required minlength="4">
                </div>
                <button type="submit" class="btn btn-primary">S'inscrire</button>
            </form>
            <p style="margin-top: 20px; text-align: center;">
                Déjà un compte ? <a href="login.php">Se connecter</a>
            </p>
        </div>
    </div>
</body>
</html>