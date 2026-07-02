<?php

namespace App\Models;

use PDO;

/**
 * Modèle CommandeBorne
 * Gère les commandes de pompe (start/stop) envoyées aux bornes via MQTT.
 * 
 * Cycle de vie d'une commande :
 *   en_attente → (worker MQTT publie) → envoyee → (ESP32 acquitte) → ack
 *                                                                  → echec (timeout ou erreur)
 */
class CommandeBorne extends BaseModel {

    /**
     * Insère une nouvelle commande en attente d'envoi.
     *
     * @param array $data Clés : borne_id, type_commande (start_pompe|stop_pompe), demande_par (admin id)
     * @return int|false L'ID de la commande insérée, ou false en cas d'erreur
     */
    public function insert(array $data) {
        $stmt = $this->db->prepare(
            "INSERT INTO commandes_bornes (borne_id, type_commande, demande_par)
             VALUES (:borne_id, :type_commande, :demande_par)"
        );
        $ok = $stmt->execute([
            'borne_id'      => (int)$data['borne_id'],
            'type_commande' => $data['type_commande'],
            'demande_par'   => $data['demande_par'] ?? null,
        ]);
        return $ok ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Récupère toutes les commandes en attente d'envoi (statut = en_attente).
     * Utilisé par le worker MQTT pour son cycle de polling.
     *
     * @return array
     */
    public function findPending(): array {
        $stmt = $this->db->query(
            "SELECT * FROM commandes_bornes 
             WHERE statut = 'en_attente' 
             ORDER BY date_creation ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Marque une commande comme envoyée sur le broker MQTT.
     *
     * @param int $id ID de la commande
     */
    public function markEnvoyee(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE commandes_bornes 
             SET statut = 'envoyee', date_envoi = CURRENT_TIMESTAMP 
             WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Marque une commande comme accusée de réception par l'ESP32.
     *
     * @param int $id ID de la commande
     */
    public function markAck(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE commandes_bornes 
             SET statut = 'ack', date_ack = CURRENT_TIMESTAMP 
             WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Marque une commande comme échouée (timeout ou erreur de publication).
     *
     * @param int $id ID de la commande
     */
    public function markEchec(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE commandes_bornes SET statut = 'echec' WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Récupère l'historique des dernières commandes avec le nom de la borne.
     *
     * @param int $limit Nombre de lignes à retourner
     * @return array
     */
    public function findRecent(int $limit = 20): array {
        $stmt = $this->db->prepare(
            "SELECT c.*, b.nom AS borne_nom, 
                    CONCAT(a.prenom, ' ', a.nom) AS admin_nom
             FROM commandes_bornes c
             LEFT JOIN bornes b ON c.borne_id = b.id
             LEFT JOIN administrateurs a ON c.demande_par = a.id
             ORDER BY c.date_creation DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
