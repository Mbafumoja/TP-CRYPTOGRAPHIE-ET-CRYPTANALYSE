<?php
// Démarrer la session une seule fois
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/encryption.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Vérifier le statut du compte
checkUserStatus($pdo);

// Initialisation des variables
$success = '';
$error = '';
$encrypted_result = '';
$decrypted_result = '';
$original_message = '';
$encryption_key = '';
$decryption_key = '';
$selected_algo = 1;

// Récupération des algorithmes
try {
    $algos = $pdo->query("SELECT * FROM algorithmes")->fetchAll();
} catch(PDOException $e) {
    $algos = [];
}

// Traitement du formulaire de chiffrement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // SUPPRESSION D'UN MESSAGE
    if (isset($_POST['delete_message'])) {
        $message_id = intval($_POST['message_id_to_delete']);
        
        try {
            // Vérifier si l'utilisateur a le droit de supprimer (admin ou propriétaire du message)
            $stmt = $pdo->prepare("SELECT id_user FROM message WHERE id = ?");
            $stmt->execute([$message_id]);
            $message = $stmt->fetch();
            
            if ($message) {
                if (isAdmin() || $message['id_user'] == $_SESSION['user_id']) {
                    $stmt = $pdo->prepare("DELETE FROM message WHERE id = ?");
                    $stmt->execute([$message_id]);
                    $success = "✅ Message #" . $message_id . " supprimé avec succès !";
                } else {
                    $error = "❌ Vous n'avez pas le droit de supprimer ce message";
                }
            } else {
                $error = "❌ Message non trouvé";
            }
        } catch(PDOException $e) {
            $error = "❌ Erreur lors de la suppression: " . $e->getMessage();
        }
    }
    
    // CHIFFREMENT D'UN MESSAGE
    if (isset($_POST['encrypt_message']) && !empty(trim($_POST['message_to_encrypt']))) {
        $original_message = trim($_POST['message_to_encrypt']);
        $encryption_key = $_POST['encrypt_key'];
        $selected_algo = intval($_POST['encrypt_algorithme']);
        
        if (empty($encryption_key)) {
            $error = "❌ Veuillez entrer une clé de chiffrement";
        } else {
            try {
                // Récupérer l'algorithme
                $algoStmt = $pdo->prepare("SELECT libelle FROM algorithmes WHERE id = ?");
                $algoStmt->execute([$selected_algo]);
                $algo = $algoStmt->fetch();
                
                if (!$algo) {
                    throw new Exception("Algorithme non trouvé");
                }
                
                // Chiffrer le message
                $encryption = new Encryption($encryption_key, $algo['libelle']);
                $encrypted_result = $encryption->encrypt($original_message);
                
                $success = "✅ Message chiffré avec succès !";
                
                // Optionnel : Sauvegarder dans la base de données
                if (isset($_POST['save_to_database'])) {
                    $stmt = $pdo->prepare("INSERT INTO message (contenu_message, id_user, id_algorithme) VALUES (?, ?, ?)");
                    $stmt->execute([$encrypted_result, $_SESSION['user_id'], $selected_algo]);
                    $success .= " Message sauvegardé dans la base de données (ID: " . $pdo->lastInsertId() . ")";
                }
                
            } catch(Exception $e) {
                $error = "❌ Erreur de chiffrement: " . $e->getMessage();
            }
        }
    }
    
    // DÉCHIFFREMENT D'UN MESSAGE
    if (isset($_POST['decrypt_message']) && !empty(trim($_POST['message_to_decrypt']))) {
        $encrypted_message = trim($_POST['message_to_decrypt']);
        $decryption_key = $_POST['decrypt_key'];
        $selected_algo = intval($_POST['decrypt_algorithme']);
        
        if (empty($decryption_key)) {
            $error = "❌ Veuillez entrer une clé de déchiffrement";
        } else {
            try {
                // Récupérer l'algorithme
                $algoStmt = $pdo->prepare("SELECT libelle FROM algorithmes WHERE id = ?");
                $algoStmt->execute([$selected_algo]);
                $algo = $algoStmt->fetch();
                
                if (!$algo) {
                    throw new Exception("Algorithme non trouvé");
                }
                
                // Déchiffrer le message
                $encryption = new Encryption($decryption_key, $algo['libelle']);
                $decrypted_result = $encryption->decrypt($encrypted_message);
                
                $success = "✅ Message déchiffré avec succès !";
                
            } catch(Exception $e) {
                $error = "❌ Erreur de déchiffrement: Clé incorrecte ou message corrompu";
            }
        }
    }
    
    // DÉCHIFFREMENT D'UN MESSAGE DE LA BASE DE DONNÉES
    if (isset($_POST['decrypt_from_db']) && !empty(trim($_POST['db_message_id']))) {
        $message_id = intval($_POST['db_message_id']);
        $decryption_key = $_POST['db_decrypt_key'];
        
        try {
            $stmt = $pdo->prepare("
                SELECT m.*, a.libelle as algorithme 
                FROM message m 
                JOIN algorithmes a ON m.id_algorithme = a.id 
                WHERE m.id = ?
            ");
            $stmt->execute([$message_id]);
            $message_data = $stmt->fetch();
            
            if ($message_data) {
                if (empty($decryption_key)) {
                    throw new Exception("Veuillez entrer une clé de déchiffrement");
                }
                
                $encryption = new Encryption($decryption_key, $message_data['algorithme']);
                $decrypted_result = $encryption->decrypt($message_data['contenu_message']);
                
                $success = "✅ Message de la base de données déchiffré avec succès !";
            } else {
                $error = "Message non trouvé dans la base de données";
            }
        } catch(Exception $e) {
            $error = "❌ Erreur de déchiffrement: Clé incorrecte ou message corrompu";
        }
    }
    
    // SAUVEGARDER UN MESSAGE DÉJÀ CHIFFRÉ DANS LA BASE
    if (isset($_POST['save_encrypted']) && !empty(trim($_POST['encrypted_to_save']))) {
        $encrypted_to_save = $_POST['encrypted_to_save'];
        $algoId = intval($_POST['save_algorithme']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO message (contenu_message, id_user, id_algorithme) VALUES (?, ?, ?)");
            $stmt->execute([$encrypted_to_save, $_SESSION['user_id'], $algoId]);
            $success = "✅ Message chiffré sauvegardé dans la base de données (ID: " . $pdo->lastInsertId() . ")";
        } catch(Exception $e) {
            $error = "❌ Erreur lors de la sauvegarde: " . $e->getMessage();
        }
    }
}

// Récupération des messages existants
try {
    $stmt = $pdo->query("
        SELECT m.*, u.username, a.libelle as algorithme 
        FROM message m 
        JOIN utilisateur u ON m.id_user = u.id 
        JOIN algorithmes a ON m.id_algorithme = a.id 
        WHERE u.is_blocked = FALSE
        ORDER BY m.date_envoi DESC 
        LIMIT 20
    ");
    $messages = $stmt->fetchAll();
} catch(PDOException $e) {
    $messages = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Chat Belden - Chiffrement/Déchiffrement</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .crypto-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .crypto-title {
            font-size: 1.3rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
        }
        
        .crypto-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .form-row-crypto {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .form-row-crypto > div {
            flex: 1;
        }
        
        .result-box {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            word-break: break-all;
        }
        
        .result-box strong {
            color: var(--primary);
            display: block;
            margin-bottom: 8px;
        }
        
        .result-content {
            font-family: monospace;
            font-size: 0.9rem;
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .message-item {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .message-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            background: var(--danger-dark);
            transform: translateY(-1px);
        }
        
        .message-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        @media (max-width: 768px) {
            .form-row-crypto {
                flex-direction: column;
            }
            
            .message-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h2>
                <i class="fas fa-lock"></i>
                Chiffrement/Déchiffrement de Messages
            </h2>
            <div class="user-info">
                <span>
                    <i class="fas fa-user"></i>
                    <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                </span>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="btn-admin">
                        <i class="fas fa-crown"></i> Admin
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin: 15px;">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin: 15px;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- SECTION CHIFFREMENT -->
        <div class="crypto-section">
            <div class="crypto-title">
                <i class="fas fa-lock"></i>
                Chiffrer un message
            </div>
            
            <form method="POST" action="chat.php" class="crypto-form">
                <div class="form-row-crypto">
                    <div style="flex: 2;">
                        <label><i class="fas fa-pencil-alt"></i> Message à chiffrer :</label>
                        <textarea 
                            name="message_to_encrypt" 
                            rows="3" 
                            placeholder="Entrez le message que vous voulez chiffrer..."
                            style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 8px; margin-top: 5px;"
                        ><?= htmlspecialchars($original_message) ?></textarea>
                    </div>
                </div>
                
                <div class="form-row-crypto">
                    <div>
                        <label><i class="fas fa-key"></i> Clé de chiffrement :</label>
                        <input 
                            type="password" 
                            name="encrypt_key" 
                            value="<?= htmlspecialchars($encryption_key) ?>"
                            placeholder="Entrez la clé de chiffrement..."
                            style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 8px; margin-top: 5px;"
                        >
                    </div>
                    <div>
                        <label><i class="fas fa-cog"></i> Algorithme :</label>
                        <select name="encrypt_algorithme" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 8px; margin-top: 5px;">
                            <?php foreach ($algos as $algo): ?>
                                <option value="<?= $algo['id'] ?>" <?= $selected_algo == $algo['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($algo['libelle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="checkbox-label">
                        <input type="checkbox" name="save_to_database" value="1">
                        <i class="fas fa-database"></i> Sauvegarder le message chiffré dans la base de données
                    </label>
                </div>
                
                <button type="submit" name="encrypt_message" class="btn btn-primary">
                    <i class="fas fa-lock"></i> Chiffrer le message
                </button>
            </form>
            
            <?php if ($encrypted_result): ?>
                <div class="result-box">
                    <strong><i class="fas fa-lock"></i> Message chiffré :</strong>
                    <div class="result-content">
                        <?= htmlspecialchars($encrypted_result) ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <button onclick="copyToClipboard('<?= addslashes($encrypted_result) ?>')" class="btn-small" style="background: var(--primary); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-copy"></i> Copier le message chiffré
                        </button>
                        
                        <form method="POST" action="chat.php" style="display: inline; margin-left: 10px;">
                            <input type="hidden" name="encrypted_to_save" value="<?= htmlspecialchars($encrypted_result) ?>">
                            <input type="hidden" name="save_algorithme" value="<?= $selected_algo ?>">
                            <button type="submit" name="save_encrypted" class="btn-small" style="background: var(--secondary); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                                <i class="fas fa-save"></i> Sauvegarder dans la BD
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- SECTION DÉCHIFFREMENT MANUEL -->
        <div class="crypto-section">
            <div class="crypto-title">
                <i class="fas fa-unlock-alt"></i>
                Déchiffrer un message
            </div>
            
            <form method="POST" action="chat.php" class="crypto-form">
                <div class="form-row-crypto">
                    <div style="flex: 2;">
                        <label><i class="fas fa-code"></i> Message chiffré :</label>
                        <textarea 
                            name="message_to_decrypt" 
                            rows="3" 
                            placeholder="Collez le message chiffré ici..."
                            style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 8px; margin-top: 5px;"
                        ><?= htmlspecialchars($_POST['message_to_decrypt'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="form-row-crypto">
                    <div>
                        <label><i class="fas fa-key"></i> Clé de déchiffrement :</label>
                        <input 
                            type="password" 
                            name="decrypt_key" 
                            placeholder="Entrez la clé de déchiffrement (doit être identique à la clé de chiffrement)..."
                            style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 8px; margin-top: 5px;"
                        >
                    </div>
                    <div>
                        <label><i class="fas fa-cog"></i> Algorithme :</label>
                        <select name="decrypt_algorithme" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 8px; margin-top: 5px;">
                            <?php foreach ($algos as $algo): ?>
                                <option value="<?= $algo['id'] ?>">
                                    <?= htmlspecialchars($algo['libelle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="decrypt_message" class="btn btn-secondary">
                    <i class="fas fa-unlock"></i> Déchiffrer le message
                </button>
            </form>
            
            <?php if ($decrypted_result): ?>
                <div class="result-box">
                    <strong><i class="fas fa-check-circle"></i> Message déchiffré :</strong>
                    <div class="result-content">
                        <?= nl2br(htmlspecialchars($decrypted_result)) ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <button onclick="copyToClipboard('<?= addslashes($decrypted_result) ?>')" class="btn-small" style="background: var(--secondary); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-copy"></i> Copier le message déchiffré
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- SECTION DÉCHIFFREMENT DEPUIS LA BASE DE DONNÉES -->
        <div class="crypto-section">
            <div class="crypto-title">
                <i class="fas fa-database"></i>
                Déchiffrer un message de la base de données
            </div>
            
            <form method="POST" action="chat.php" class="crypto-form">
                <div class="form-row-crypto">
                    <div>
                        <label><i class="fas fa-hashtag"></i> ID du message :</label>
                        <input 
                            type="number" 
                            name="db_message_id" 
                            placeholder="Entrez l'ID du message..."
                            style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 8px; margin-top: 5px;"
                        >
                    </div>
                    <div>
                        <label><i class="fas fa-key"></i> Clé de déchiffrement :</label>
                        <input 
                            type="password" 
                            name="db_decrypt_key" 
                            placeholder="Entrez la clé de déchiffrement..."
                            style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 8px; margin-top: 5px;"
                        >
                    </div>
                </div>
                
                <button type="submit" name="decrypt_from_db" class="btn btn-info">
                    <i class="fas fa-database"></i> Déchiffrer depuis la base
                </button>
            </form>
        </div>
        
        <!-- LISTE DES MESSAGES EXISTANTS AVEC SUPPRESSION -->
        <div class="crypto-section">
            <div class="crypto-title">
                <i class="fas fa-history"></i>
                Messages récents dans la base de données
            </div>
            
            <?php if (count($messages) == 0): ?>
                <div style="text-align: center; padding: 30px; color: var(--gray);">
                    <i class="fas fa-inbox" style="font-size: 2rem;"></i>
                    <p>Aucun message dans la base de données</p>
                </div>
            <?php else: ?>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-item">
                            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                                <strong><i class="fas fa-user"></i> <?= htmlspecialchars($msg['username']) ?></strong>
                                <small><i class="far fa-clock"></i> <?= date('d/m/Y H:i', strtotime($msg['date_envoi'])) ?></small>
                                <small><i class="fas fa-tag"></i> ID: #<?= $msg['id'] ?></small>
                            </div>
                            <div style="margin-top: 8px;">
                                <small><i class="fas fa-shield-alt"></i> Algorithme: <?= htmlspecialchars($msg['algorithme']) ?></small>
                            </div>
                            <div style="margin-top: 8px; font-family: monospace; font-size: 0.8rem; background: var(--light); padding: 8px; border-radius: 5px; word-break: break-all;">
                                <?= htmlspecialchars(substr($msg['contenu_message'], 0, 100)) ?>...
                            </div>
                            <div class="message-actions">
                                <button onclick="document.querySelector('input[name=\"db_message_id\"]').value = '<?= $msg['id'] ?>'; document.querySelector('input[name=\"db_decrypt_key\"]').focus();" class="btn-small" style="background: var(--primary); color: white; border: none; padding: 4px 8px; border-radius: 5px; cursor: pointer;">
                                    <i class="fas fa-key"></i> Utiliser cet ID
                                </button>
                                <button onclick="copyToClipboard('<?= addslashes($msg['contenu_message']) ?>')" class="btn-small" style="background: var(--gray); color: white; border: none; padding: 4px 8px; border-radius: 5px; cursor: pointer;">
                                    <i class="fas fa-copy"></i> Copier
                                </button>
                                
                                <!-- Formulaire de suppression -->
                                <form method="POST" action="chat.php" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce message ? Cette action est irréversible.');">
                                    <input type="hidden" name="message_id_to_delete" value="<?= $msg['id'] ?>">
                                    <button type="submit" name="delete_message" class="btn-delete">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Fonction pour copier dans le presse-papier
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Texte copié dans le presse-papier !');
            }).catch(() => {
                alert('Erreur lors de la copie');
            });
        }
        
        // Scroll automatique
        var messagesDiv = document.getElementById('chatMessages');
        if (messagesDiv) {
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
        
        // Disparition automatique des messages d'alerte après 5 secondes
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