<?php

namespace App\Models;

use PDO;

/**
 * Modèle Admin
 * Gère les données et l'authentification des administrateurs.
 */
class Admin extends BaseModel {
    /**
     * Recherche un administrateur par son email.
     *
     * @param string $email
     * @return array|false
     */
    public function findByEmail(string $email) {
        $stmt = $this->db->prepare("SELECT * FROM administrateurs WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Tente de connecter un admin en validant l'email et le mot de passe.
     *
     * @param string $email
     * @param string $password
     * @return array|false Les données de l'admin en cas de succès, sinon false.
     */
    public function authenticate(string $email, string $password) {
        $admin = $this->findByEmail($email);
        
        if ($admin && $admin['statut'] === 'actif') {
            // Vérifier le mot de passe (hachage bcrypt)
            if (password_verify($password, $admin['password'])) {
                return $admin;
            }
        }
        
        return false;
    }
}
