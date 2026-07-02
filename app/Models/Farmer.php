<?php

namespace App\Models;

use PDO;

/**
 * Modèle Farmer
 * Gère le stockage et la logique des agriculteurs.
 */
class Farmer extends BaseModel {
    /**
     * Récupère tous les agriculteurs.
     */
    public function findAll(): array {
        $stmt = $this->db->query("SELECT * FROM agriculteurs ORDER BY nom ASC, prenom ASC");
        return $stmt->fetchAll();
    }

    /**
     * Recherche un agriculteur par son ID.
     */
    public function find(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM agriculteurs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Insère un nouvel agriculteur.
     */
    public function insert(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO agriculteurs (nom, prenom, telephone, email, statut) 
             VALUES (:nom, :prenom, :telephone, :email, :statut)"
        );
        return $stmt->execute([
            'nom'       => $data['nom'],
            'prenom'    => $data['prenom'],
            'telephone' => $data['telephone'],
            'email'     => !empty($data['email']) ? $data['email'] : null,
            'statut'    => $data['statut'] ?? 'actif'
        ]);
    }

    /**
     * Met à jour les informations d'un agriculteur.
     */
    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare(
            "UPDATE agriculteurs 
             SET nom = :nom, prenom = :prenom, telephone = :telephone, email = :email, statut = :statut 
             WHERE id = :id"
        );
        return $stmt->execute([
            'id'        => $id,
            'nom'       => $data['nom'],
            'prenom'    => $data['prenom'],
            'telephone' => $data['telephone'],
            'email'     => !empty($data['email']) ? $data['email'] : null,
            'statut'    => $data['statut']
        ]);
    }

    /**
     * Supprime un agriculteur.
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM agriculteurs WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Compte le nombre d'agriculteurs.
     */
    public function count(string $statut = null): int {
        if ($statut) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM agriculteurs WHERE statut = :statut");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $this->db->query("SELECT COUNT(*) FROM agriculteurs");
        }
        return (int) $stmt->fetchColumn();
    }
}
