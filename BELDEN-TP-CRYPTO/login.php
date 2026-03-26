<?php
// Démarrer la session une seule fois
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

$error = '';

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: chat.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_blocked']) {
                $error = "⛔ Votre compte a été bloqué par l'administrateur";
            } else {
                // Stocker les informations utilisateur dans la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];
                $_SESSION['encryption_key'] = $password; // Stocker le mot de passe pour le chiffrement
                $_SESSION['login_time'] = time(); // Pour suivre la connexion
                
                // Redirection vers le chat
                header('Location: chat.php');
                exit();
            }
        } else {
            $error = "❌ Nom d'utilisateur ou mot de passe incorrect";
        }
    } catch(PDOException $e) {
        $error = "❌ Erreur de connexion à la base de données";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Connexion - Chat Belden</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            animation: fadeInUp 0.5s ease-out;
        }
        
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert i {
            font-size: 1.1rem;
        }
        
        .btn-primary {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>
                <i class="fas fa-lock" style="color: var(--primary);"></i>
                Connexion
            </h2>
            
            <?php if (isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Inscription réussie ! Connectez-vous maintenant.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error']) && $_GET['error'] == 'account_blocked'): ?>
                <div class="alert alert-error">
                    <i class="fas fa-ban"></i>
                    Votre compte a été bloqué. Contactez l'administrateur.
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user"></i> Nom d'utilisateur
                    </label>
                    <input 
                        type="text" 
                        name="username" 
                        required 
                        autofocus
                        placeholder="Entrez votre nom d'utilisateur"
                        value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                    >
                </div>
                <div class="form-group">
                    <label>
                        <i class="fas fa-key"></i> Mot de passe
                    </label>
                    <input 
                        type="password" 
                        name="password" 
                        required
                        placeholder="Entrez votre mot de passe"
                    >
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
            </form>
            
            <div style="margin-top: 20px; text-align: center; padding-top: 20px; border-top: 1px solid var(--border);">
                <p style="margin-bottom: 10px;">
                    <i class="fas fa-user-plus"></i> Pas de compte ?
                    <a href="register.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                        S'inscrire
                    </a>
                </p>
                <p style="font-size: 0.8rem; color: var(--gray); margin-top: 10px;">
                    <i class="fas fa-shield-alt"></i> Messages chiffrés de bout en bout
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Empêcher la soumission multiple du formulaire
        let isSubmitting = false;
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion en cours...';
                    button.disabled = true;
                }
            });
        }
        
        // Effacer les messages d'erreur après 5 secondes
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.style.display = 'none';
                    }
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>