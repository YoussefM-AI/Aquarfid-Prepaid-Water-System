<?php

namespace App\Models;

use PDO;

/**
 * Modèle Alerte
 * Gère l'historique et la résolution des incidents de sécurité (fuites, trop-plein, etc.).
 */
class Alerte extends BaseModel {
    /**
     * Récupère toutes les alertes avec les infos de la borne émettrice.
     */
    public function findAll(): array {
        $sql = "SELECT a.*, b.nom AS borne_nom 
                FROM alertes a
                JOIN bornes b ON a.borne_id = b.id
                ORDER BY a.resolu ASC, a.date_heure DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Crée une nouvelle alerte de sécurité (canal HTTP REST, source par défaut 'http').
     */
    public function insert(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO alertes (borne_id, type_alerte, description) 
             VALUES (:borne_id, :type_alerte, :description)"
        );
        return $stmt->execute([
            'borne_id'    => $data['borne_id'],
            'type_alerte' => $data['type_alerte'],
            'description' => $data['description']
        ]);
    }

    /**
     * Crée une nouvelle alerte avec la source spécifiée (http ou mqtt).
     * Utilisé par le worker MQTT pour les alertes reçues via broker.
     */
    public function insertWithSource(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO alertes (borne_id, type_alerte, description, source) 
             VALUES (:borne_id, :type_alerte, :description, :source)"
        );
        return $stmt->execute([
            'borne_id'    => $data['borne_id'],
            'type_alerte' => $data['type_alerte'],
            'description' => $data['description'],
            'source'      => $data['source'] ?? 'http',
        ]);
    }

    /**
     * Marque une alerte comme résolue.
     */
    public function resolve(int $id): bool {
        $stmt = $this->db->prepare("UPDATE alertes SET resolu = 1 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Compte le nombre d'alertes actives (non résolues).
     */
    public function countActive(): int {
        return (int) $this->db->query("SELECT COUNT(*) FROM alertes WHERE resolu = 0")->fetchColumn();
    }

    /**
     * Retourne les alertes actives (non résolues) avec le nom de la borne,
     * triées par date décroissante. Utilisé par api/bornes/status-live.
     */
    public function findActive(): array {
        $sql = "SELECT a.*, b.nom AS borne_nom 
                FROM alertes a
                JOIN bornes b ON a.borne_id = b.id
                WHERE a.resolu = 0
                ORDER BY a.date_heure DESC
                LIMIT 50";
        return $this->db->query($sql)->fetchAll();
    }
}
