<?php

namespace App\Models;

use PDO;
use Exception;

/**
 * Modèle RFIDCard
 * Gère les cartes RFID et les soldes prépayés.
 */
class RFIDCard extends BaseModel {
    /**
     * Récupère toutes les cartes RFID avec les infos de l'agriculteur associé.
     */
    public function findAll(): array {
        $sql = "SELECT c.*, a.nom AS farmer_nom, a.prenom AS farmer_prenom, a.telephone AS farmer_telephone, a.email AS farmer_email,
                COALESCE((SELECT SUM(volume_distribue) FROM transactions WHERE carte_rfid_uid = c.uid), 0) AS total_distribue
                FROM cartes_rfid c
                JOIN agriculteurs a ON c.agriculteur_id = a.id
                WHERE c.deleted_at IS NULL AND a.deleted_at IS NULL
                ORDER BY c.date_association DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Recherche une carte par son UID.
     */
    public function find(string $uid) {
        $sql = "SELECT c.*, a.nom AS farmer_nom, a.prenom AS farmer_prenom, a.statut AS farmer_statut
                FROM cartes_rfid c
                JOIN agriculteurs a ON c.agriculteur_id = a.id
                WHERE c.uid = :uid AND c.deleted_at IS NULL AND a.deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $uid]);
        return $stmt->fetch();
    }

    /**
     * Crée une association de carte RFID.
     */
    public function insert(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO cartes_rfid (uid, agriculteur_id, solde, statut, code_pin) 
             VALUES (:uid, :agriculteur_id, :solde, :statut, :code_pin)"
        );
        return $stmt->execute([
            'uid'            => strtoupper(trim($data['uid'])),
            'agriculteur_id' => $data['agriculteur_id'],
            'solde'          => $data['solde'] ?? 0.00,
            'statut'         => $data['statut'] ?? 'active',
            'code_pin'       => !empty($data['code_pin']) ? trim($data['code_pin']) : null
        ]);
    }

    /**
     * Met à jour le statut d'une carte.
     */
    public function updateStatus(string $uid, string $status): bool {
        $stmt = $this->db->prepare("UPDATE cartes_rfid SET statut = :statut WHERE uid = :uid");
        return $stmt->execute([
            'uid'    => $uid,
            'statut' => $status
        ]);
    }

    /**
     * Ajoute du crédit au solde d'une carte.
     */
    public function recharge(string $uid, float $amount): bool {
        if ($amount <= 0) {
            throw new Exception("Le montant de recharge doit être positif.");
        }
        
        $stmt = $this->db->prepare("UPDATE cartes_rfid SET solde = solde + :amount WHERE uid = :uid");
        return $stmt->execute([
            'uid'    => $uid,
            'amount' => $amount
        ]);
    }

    /**
     * Débite le solde de la carte (pour le processus de distribution d'eau).
     */
    public function debitBalance(string $uid, float $volumeLitres, float $pricePerLitre): bool {
        $cost = $volumeLitres * $pricePerLitre;
        
        // D'abord récupérer les données de la carte
        $card = $this->find($uid);
        if (!$card) {
            throw new Exception("Carte RFID introuvable.");
        }
        
        if ($card['statut'] !== 'active') {
            throw new Exception("La carte RFID est " . $card['statut'] . ".");
        }

        if ($card['farmer_statut'] !== 'actif') {
            throw new Exception("Le compte de l'agriculteur est inactif.");
        }

        if ($card['solde'] < $cost) {
            throw new Exception("Solde insuffisant pour la distribution demandée (" . number_format($cost, 3) . " DT requis, " . number_format($card['solde'], 3) . " DT disponibles).");
        }

        $stmt = $this->db->prepare(
            "UPDATE cartes_rfid 
             SET solde = solde - :cost, derniere_utilisation = CURRENT_TIMESTAMP 
             WHERE uid = :uid"
        );
        return $stmt->execute([
            'uid'  => $uid,
            'cost' => $cost
        ]);
    }

    /**
     * Compte les cartes RFID.
     */
    public function count(string $statut = null): int {
        if ($statut) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM cartes_rfid WHERE statut = :statut AND deleted_at IS NULL");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $this->db->query("SELECT COUNT(*) FROM cartes_rfid WHERE deleted_at IS NULL");
        }
        return (int) $stmt->fetchColumn();
    }

    /**
     * Calcule le solde total accumulé en circulation.
     */
    public function totalSolde(): float {
        return (float) $this->db->query("SELECT SUM(solde) FROM cartes_rfid WHERE deleted_at IS NULL")->fetchColumn();
    }

    /**
     * Vérifie si un code PIN est unique parmi toutes les cartes RFID enregistrées.
     */
    public function isPinUnique(string $pin, ?string $excludeUid = null): bool {
        $pin = trim($pin);
        if (empty($pin)) {
            return true;
        }

        $sql = "SELECT uid, code_pin FROM cartes_rfid WHERE code_pin IS NOT NULL AND code_pin != '' AND deleted_at IS NULL";
        $params = [];
        if (!empty($excludeUid)) {
            $sql .= " AND uid != :excludeUid";
            $params['excludeUid'] = strtoupper(trim($excludeUid));
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cards as $card) {
            $storedPin = $card['code_pin'];
            if (empty($storedPin)) continue;

            if ($storedPin === $pin || password_verify($pin, $storedPin)) {
                return false;
            }
        }

        return true;
    }
}
