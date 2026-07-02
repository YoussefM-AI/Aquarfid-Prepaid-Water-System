<?php

namespace App\Models;

use PDO;

/**
 * Modèle Transaction
 * Enregistre et analyse l'historique des distributions d'eau.
 */
class Transaction extends BaseModel {
    /**
     * Récupère toutes les transactions avec les infos de la carte, l'agriculteur et la borne.
     */
    public function findAll(int $limit = 100): array {
        $sql = "SELECT t.*, a.nom AS farmer_nom, a.prenom AS farmer_prenom, b.nom AS borne_nom
                FROM transactions t
                JOIN cartes_rfid c ON t.carte_rfid_uid = c.uid
                JOIN agriculteurs a ON c.agriculteur_id = a.id
                JOIN bornes b ON t.borne_id = b.id
                ORDER BY t.date_heure DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Insère une nouvelle transaction.
     */
    public function insert(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO transactions (carte_rfid_uid, borne_id, volume_demande, volume_distribue, montant_debite, statut) 
             VALUES (:carte_rfid_uid, :borne_id, :volume_demande, :volume_distribue, :montant_debite, :statut)"
        );
        return $stmt->execute([
            'carte_rfid_uid'   => $data['carte_rfid_uid'],
            'borne_id'         => $data['borne_id'],
            'volume_demande'   => $data['volume_demande'],
            'volume_distribue' => $data['volume_distribue'],
            'montant_debite'   => $data['montant_debite'],
            'statut'           => $data['statut'] ?? 'reussie'
        ]);
    }

    /**
     * Calcule le volume total d'eau distribué (en litres).
     */
    public function totalVolume(): float {
        return (float) $this->db->query("SELECT SUM(volume_distribue) FROM transactions WHERE statut = 'reussie' OR statut = 'interrompue_erreur'")->fetchColumn();
    }

    /**
     * Calcule le chiffre d'affaires total généré (en euros).
     */
    public function totalRevenue(): float {
        return (float) $this->db->query("SELECT SUM(montant_debite) FROM transactions WHERE statut = 'reussie' OR statut = 'interrompue_erreur'")->fetchColumn();
    }

    /**
     * Récupère l'historique des consommations quotidiennes des 7 derniers jours (pour les statistiques).
     */
    public function getWeeklyStats(): array {
        $sql = "SELECT DATE(date_heure) AS date, SUM(volume_distribue) AS total_volume
                FROM transactions
                WHERE date_heure >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND (statut = 'reussie' OR statut = 'interrompue_erreur')
                GROUP BY DATE(date_heure)
                ORDER BY DATE(date_heure) ASC";
        return $this->db->query($sql)->fetchAll();
    }
}
