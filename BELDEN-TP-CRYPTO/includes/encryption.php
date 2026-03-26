<?php
class Encryption {
    private $key;
    private $cipher;
    
    public function __construct($userKey, $algorithm = 'AES-256-CBC') {
        $this->cipher = $algorithm;
        // Dériver une clé de 32 bytes à partir du mot de passe utilisateur
        $this->key = hash('sha256', $userKey, true);
    }
    
    public function encrypt($data) {
        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($encryptedData) {
        $data = base64_decode($encryptedData);
        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        return openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
    }
}

// Récupérer l'algorithme de la base de données
function getAlgorithmById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT libelle FROM algorithmes WHERE id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    return $result ? $result['libelle'] : 'AES-256-CBC';
}
?>