<?php

namespace App\Models;

use PDO;

/**
 * Modèle Borne
 * Gère les bornes physiques de distribution et leur supervision.
 */
class Borne extends BaseModel {
    /**
     * Récupère toutes les bornes.
     */
    public function findAll(): array {
        $stmt = $this->db->query("SELECT * FROM bornes ORDER BY nom ASC");
        return $stmt->fetchAll();
    }

    /**
     * Recherche une borne par son ID.
     */
    public function find(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM bornes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Met à jour le ping de la borne (mise en ligne).
     */
    public function ping(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE bornes 
             SET statut_connexion = 'en ligne', dernier_ping = CURRENT_TIMESTAMP 
             WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Compte les bornes en fonction de leur statut.
     */
    public function count(string $statut = null): int {
        if ($statut) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM bornes WHERE statut_connexion = :statut");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $this->db->query("SELECT COUNT(*) FROM bornes");
        }
        return (int) $stmt->fetchColumn();
    }

    /**
     * Met à jour la télémétrie MQTT d'une borne.
     * Appelé par le worker MQTT à chaque message reçu sur eau/bornes/{id}/etat.
     *
     * @param int   $id   ID de la borne
     * @param array $data Tableau associatif avec clés : etat_vanne, debit_instantane,
     *                    capteur_fuite, capteur_trop_plein
     */
    public function updateTelemetry(int $id, array $data): bool {
        $stmt = $this->db->prepare(
            "UPDATE bornes SET
                etat_vanne              = :etat_vanne,
                debit_instantane        = :debit_instantane,
                capteur_fuite           = :capteur_fuite,
                capteur_trop_plein      = :capteur_trop_plein,
                derniere_telemerie_mqtt = CURRENT_TIMESTAMP,
                statut_connexion        = 'en ligne',
                dernier_ping            = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        return $stmt->execute([
            'id'                => $id,
            'etat_vanne'        => $data['etat_vanne']        ?? 'fermee',
            'debit_instantane'  => $data['debit_instantane']  ?? null,
            'capteur_fuite'     => (int)($data['capteur_fuite']      ?? 0),
            'capteur_trop_plein'=> (int)($data['capteur_trop_plein'] ?? 0),
        ]);
    }

    /**
     * Retourne toutes les bornes avec leur état temps réel pour le dashboard.
     * Inclut les alertes actives non résolues par borne.
     */
    public function findAllWithStatus(): array {
        $stmt = $this->db->query(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM alertes a WHERE a.borne_id = b.id AND a.resolu = 0) AS alertes_actives,
                    CASE 
                        WHEN b.derniere_telemerie_mqtt IS NOT NULL 
                             AND TIMESTAMPDIFF(SECOND, b.derniere_telemerie_mqtt, NOW()) < 15
                        THEN 1 ELSE 0 
                    END AS mqtt_connecte
             FROM bornes b
             ORDER BY b.nom ASC"
        );
        return $stmt->fetchAll();
    }
}
