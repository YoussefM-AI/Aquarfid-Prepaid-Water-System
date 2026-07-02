<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Classe de base pour tous les modèles.
 * Fournit l'accès à la connexion PDO et des utilitaires génériques de requêtage.
 */
abstract class BaseModel {
    protected PDO $db;

    public function __construct() {
        // Récupérer la connexion via le Singleton Database
        $this->db = Database::getInstance()->getConnection();
    }
}
