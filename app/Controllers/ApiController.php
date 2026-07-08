<?php

namespace App\Controllers;

use App\Models\RFIDCard;
use App\Models\Borne;
use App\Models\Transaction;
use App\Models\Alerte;
use App\Models\CommandeBorne;
use Exception;

/**
 * Contrôleur ApiController
 * Gère les requêtes API REST du module ESP32 pour la borne RFID.
 * Inclut les nouveaux endpoints MQTT : commandeBorne, statusLive.
 */
class ApiController extends BaseController {

    /**
     * Helper pour renvoyer une réponse JSON
     */
    private function jsonResponse(array $data, int $statusCode = 200) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($data);
        exit();
    }

    /**
     * Extrait les paramètres de la requête de façon robuste (GET, POST ou JSON brut).
     */
    private function getRequestParams(): array {
        $params = $_POST;
        
        // Lire le flux JSON brut s'il y a lieu
        $json = file_get_contents('php://input');
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $params = array_merge($params, $decoded);
            }
        }
        
        // Compléter avec GET si nécessaire
        return array_merge($_GET, $params);
    }

    /**
     * Vérifie la validité d'une carte RFID
     * Endpoint: index.php?route=api/cards/verify
     */
    public function verifyCard() {
        $params = $this->getRequestParams();
        $uid = $params['uid'] ?? '';
        
        if (empty($uid)) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'L\'UID de la carte est obligatoire.'
            ], 400);
        }

        $uid = strtoupper(trim($uid));

        // Ping automatique de la borne si l'ID est fourni
        if (!empty($params['borne_id'])) {
            $borneId = (int)$params['borne_id'];
            $borneModel = new Borne();
            $borneModel->ping($borneId);
        }

        $cardModel = new RFIDCard();
        $card = $cardModel->find($uid);

        if (!$card) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Carte RFID introuvable dans le système.'
            ], 404);
        }

        // Vérification du statut de la carte
        if ($card['statut'] !== 'active') {
            // Si le solde est aussi à 0, on précise que c'est un problème de solde
            $soldePreview = (float)$card['solde'];
            if ($soldePreview <= 0) {
                $this->jsonResponse([
                    'status'  => 'error',
                    'message' => '💳 Solde insuffisant (0.000 DT). Veuillez recharger votre carte auprès de l\'administrateur.'
                ], 400);
            }
            $this->jsonResponse([
                'status'  => 'error',
                'message' => '🚫 Carte suspendue. Contactez votre administrateur.'
            ], 400);
        }

        // Vérification du statut de l'agriculteur
        if ($card['farmer_statut'] !== 'actif') {
            $this->jsonResponse([
                'status'  => 'error',
                'message' => '🚫 Accès refusé. Votre compte est suspendu. Contactez votre administrateur.'
            ], 400);
        }

        // Récupérer le tarif actuel de l'eau
        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT param_value FROM configurations WHERE param_key = 'price_per_litre'");
        $price = $stmt->fetchColumn();
        $price = ($price !== false) ? (float)$price : 0.200;

        // Calculer le volume maximum distribuable en litres (solde / prix)
        $solde = (float)$card['solde'];
        $maxVolumeLitres = ($price > 0) ? floor(($solde / $price) * 100) / 100 : 0.00;

        $this->jsonResponse([
            'status'          => 'success',
            'message'         => 'Carte validée avec succès.',
            'has_pin'         => !empty($card['code_pin']),
            'card'            => [
                'uid'           => $card['uid'],
                'solde'         => $solde,
                'farmer_nom'    => $card['farmer_nom'],
                'farmer_prenom' => $card['farmer_prenom']
            ],
            'price_per_litre'  => $price,
            'max_volume_litres' => $maxVolumeLitres
        ]);
    }

    /**
     * Vérifie un code PIN pour une carte RFID spécifique (après scan)
     * Endpoint: index.php?route=api/cards/verify-pin&uid=XXX&pin=1234
     */
    public function verifyPin() {
        $params = $this->getRequestParams();
        $uid    = strtoupper(trim($params['uid'] ?? ''));
        $pin    = trim($params['pin'] ?? '');

        if (empty($uid)) {
            $this->jsonResponse(['status' => 'error', 'message' => 'UID de carte manquant.'], 400);
        }

        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Le code PIN doit être 4 chiffres.'], 400);
        }

        // Ping borne si fourni
        if (!empty($params['borne_id'])) {
            $borneModel = new Borne();
            $borneModel->ping((int)$params['borne_id']);
        }

        // Récupérer la carte spécifique avec son hash PIN
        $db   = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT c.uid, c.solde, c.statut, c.code_pin,
                    a.nom AS farmer_nom, a.prenom AS farmer_prenom, a.statut AS farmer_statut
             FROM cartes_rfid c
             JOIN agriculteurs a ON c.agriculteur_id = a.id
             WHERE c.uid = :uid AND c.deleted_at IS NULL AND a.deleted_at IS NULL"
        );
        $stmt->execute(['uid' => $uid]);
        $card = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$card) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Carte introuvable.'], 404);
        }

        if (empty($card['code_pin'])) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Aucun code PIN défini pour cette carte.'], 400);
        }

        $pin_matches = false;
        if (password_verify($pin, $card['code_pin'])) {
            $pin_matches = true;
        } elseif ($pin === $card['code_pin']) {
            $pin_matches = true;
        }

        if (!$pin_matches) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Code PIN incorrect.'], 401);
        }

        if ($card['statut'] !== 'active') {
            $this->jsonResponse(['status' => 'error', 'message' => 'Carte bloquée.'], 400);
        }

        if ($card['farmer_statut'] !== 'actif') {
            $this->jsonResponse(['status' => 'error', 'message' => 'Compte agriculteur inactif.'], 400);
        }

        // Récupérer le tarif
        $stmt2 = $db->query("SELECT param_value FROM configurations WHERE param_key = 'price_per_litre'");
        $price = ($p = $stmt2->fetchColumn()) !== false ? (float)$p : 0.200;
        $solde = (float)$card['solde'];
        $maxVolumeLitres = ($price > 0) ? floor(($solde / $price) * 100) / 100 : 0.00;

        $this->jsonResponse([
            'status'            => 'success',
            'message'           => 'PIN validé.',
            'card'              => [
                'uid'           => $card['uid'],
                'solde'         => $solde,
                'farmer_nom'    => $card['farmer_nom'],
                'farmer_prenom' => $card['farmer_prenom'],
            ],
            'price_per_litre'   => $price,
            'max_volume_litres' => $maxVolumeLitres,
        ]);
    }

    /**
     * Enregistre une transaction (distribution terminée) et débite le solde
     * Endpoint: index.php?route=api/transactions/store
     */
    public function storeTransaction() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Méthode non autorisée. Utilisez POST.'
            ], 405);
        }

        $params = $this->getRequestParams();
        $uid = $params['uid'] ?? $params['carte_rfid_uid'] ?? '';
        $borneId = (int)($params['borne_id'] ?? 0);
        $volumeDemande = (float)($params['volume_demande'] ?? 0.00);
        $volumeDistribue = (float)($params['volume_distribue'] ?? 0.00);
        $statut = $params['statut'] ?? 'reussie';

        if (empty($uid) || $borneId <= 0 || $volumeDistribue < 0) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Paramètres invalides ou manquants (uid, borne_id, volume_distribue).'
            ], 400);
        }

        $uid = strtoupper(trim($uid));

        // Récupérer le tarif actuel de l'eau
        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT param_value FROM configurations WHERE param_key = 'price_per_litre'");
        $price = $stmt->fetchColumn();
        $price = ($price !== false) ? (float)$price : 0.200;

        $cardModel = new RFIDCard();
        
        try {
            // Débiter le solde de la carte (lève des exceptions si solde insuffisant ou inactive)
            $cardModel->debitBalance($uid, $volumeDistribue, $price);
        } catch (Exception $e) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Échec du débit : ' . $e->getMessage()
            ], 400);
        }

        // Calculer le coût total
        $cost = $volumeDistribue * $price;

        // Enregistrer la transaction
        $transactionModel = new Transaction();
        $success = $transactionModel->insert([
            'carte_rfid_uid'   => $uid,
            'borne_id'         => $borneId,
            'volume_demande'   => $volumeDemande,
            'volume_distribue' => $volumeDistribue,
            'montant_debite'   => $cost,
            'statut'           => $statut
        ]);

        // Ping de la borne pour la mettre en ligne
        $borneModel = new Borne();
        $borneModel->ping($borneId);

        // Obtenir le nouveau solde
        $card = $cardModel->find($uid);
        $newBalance = $card ? (float)$card['solde'] : 0.000;

        if ($success) {
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Transaction enregistrée avec succès.',
                'montant_debite' => $cost,
                'nouveau_solde' => $newBalance
            ]);
        } else {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors de l\'enregistrement de la transaction dans la base de données.'
            ], 500);
        }
    }

    /**
     * Enregistre une alerte technique envoyée par l'ESP32
     * Endpoint: index.php?route=api/alertes/store
     */
    public function storeAlerte() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Méthode non autorisée. Utilisez POST.'
            ], 405);
        }

        $params = $this->getRequestParams();
        $borneId = (int)($params['borne_id'] ?? 0);
        $typeAlerte = trim($params['type_alerte'] ?? '');
        $description = trim($params['description'] ?? '');

        if ($borneId <= 0 || empty($typeAlerte) || empty($description)) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Paramètres invalides ou manquants (borne_id, type_alerte, description).'
            ], 400);
        }

        // Vérifier si la borne existe
        $borneModel = new Borne();
        $borne = $borneModel->find($borneId);
        if (!$borne) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Borne introuvable.'
            ], 404);
        }

        // Enregistrer l'alerte
        $alerteModel = new Alerte();
        $success = $alerteModel->insert([
            'borne_id'    => $borneId,
            'type_alerte' => $typeAlerte,
            'description' => $description
        ]);

        // Ping de la borne
        $borneModel->ping($borneId);

        if ($success) {
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Alerte enregistrée avec succès.'
            ]);
        } else {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors de l\'enregistrement de l\'alerte.'
            ], 500);
        }
    }

    /**
     * Reçoit le signal keep-alive (ping) de la borne
     * Endpoint: index.php?route=api/bornes/ping
     */
    public function pingBorne() {
        $params = $this->getRequestParams();
        $borneId = (int)($params['borne_id'] ?? 0);

        if ($borneId <= 0) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'L\'ID de la borne (borne_id) est obligatoire.'
            ], 400);
        }

        $borneModel = new Borne();
        $borne = $borneModel->find($borneId);
        
        if (!$borne) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Borne introuvable.'
            ], 404);
        }

        $success = $borneModel->ping($borneId);

        if ($success) {
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Ping reçu avec succès. Statut mis à jour.'
            ]);
        } else {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors de la mise à jour du ping.'
            ], 500);
        }
    }

    // =========================================================================
    // MQTT — Nouveaux endpoints pour le canal temps réel
    // =========================================================================

    /**
     * Enregistre une commande de pompe à envoyer à l'ESP32 via MQTT.
     * Le worker MQTT poll cette table et publie la commande sur le broker.
     *
     * Endpoint : POST index.php?route=api/bornes/commande
     * Auth     : admin uniquement (session PHP)
     * Body JSON: { "borne_id": 1, "type_commande": "start_pompe" | "stop_pompe" }
     */
    public function commandeBorne() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['status' => 'error', 'message' => 'Méthode non autorisée. Utilisez POST.'], 405);
        }

        // Authentification admin obligatoire
        $admin = $this->requireAdmin();

        $params        = $this->getRequestParams();
        $borneId       = (int)($params['borne_id'] ?? 0);
        $typeCommande  = trim($params['type_commande'] ?? '');

        if ($borneId <= 0) {
            $this->jsonResponse(['status' => 'error', 'message' => 'borne_id invalide ou manquant.'], 400);
        }

        $allowedTypes = ['start_pompe', 'stop_pompe'];
        if (!in_array($typeCommande, $allowedTypes, true)) {
            $this->jsonResponse([
                'status'  => 'error',
                'message' => 'type_commande invalide. Valeurs acceptées : ' . implode(', ', $allowedTypes)
            ], 400);
        }

        // Vérifier que la borne existe
        $borneModel = new Borne();
        if (!$borneModel->find($borneId)) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Borne introuvable.'], 404);
        }

        // Insérer la commande en attente
        $commandeModel = new CommandeBorne();
        $commandeId    = $commandeModel->insert([
            'borne_id'      => $borneId,
            'type_commande' => $typeCommande,
            'demande_par'   => $admin['id'],
        ]);

        if ($commandeId === false) {
            $this->jsonResponse([
                'status'  => 'error',
                'message' => 'Erreur lors de l\'enregistrement de la commande.'
            ], 500);
        }

        $this->jsonResponse([
            'status'      => 'success',
            'message'     => 'Commande \'' . $typeCommande . '\' enregistrée. Le worker MQTT va la transmettre sous peu.',
            'commande_id' => $commandeId,
        ]);
    }

    /**
     * Retourne l'état en temps réel de toutes les bornes et des alertes actives.
     * Appelé toutes les 3s par le JavaScript du dashboard admin (polling AJAX).
     *
     * Endpoint : GET index.php?route=api/bornes/status-live
     * Auth     : administrateur connecté (session PHP)
     */
    public function statusLive() {
        // Authentification : n'importe quel admin connecté
        $admin = $this->getCurrentAdmin();
        if (!$admin) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Non authentifié.'], 401);
        }

        $borneModel  = new Borne();
        $alerteModel = new Alerte();

        $bornes  = $borneModel->findAllWithStatus();
        $alertes = $alerteModel->findActive();

        // Formater les données bornes pour le JS
        $bornesData = [];
        foreach ($bornes as $b) {
            $bornesData[] = [
                'id'                     => (int)$b['id'],
                'nom'                    => $b['nom'],
                'statut_connexion'       => $b['statut_connexion'],
                'etat_vanne'             => $b['etat_vanne'] ?? 'fermee',
                'debit_instantane'       => $b['debit_instantane'] !== null ? (float)$b['debit_instantane'] : null,
                'capteur_fuite'          => (bool)($b['capteur_fuite'] ?? 0),
                'capteur_trop_plein'     => (bool)($b['capteur_trop_plein'] ?? 0),
                'derniere_telemerie_mqtt'=> $b['derniere_telemerie_mqtt'],
                'alertes_actives'        => (int)($b['alertes_actives'] ?? 0),
                'mqtt_connecte'          => (bool)($b['mqtt_connecte'] ?? 0),
                'dernier_ping'           => $b['dernier_ping'],
            ];
        }

        // Formater les alertes actives
        $alertesData = [];
        foreach ($alertes as $a) {
            $alertesData[] = [
                'id'          => (int)$a['id'],
                'borne_id'    => (int)$a['borne_id'],
                'borne_nom'   => $a['borne_nom'],
                'type_alerte' => $a['type_alerte'],
                'description' => $a['description'],
                'source'      => $a['source'] ?? 'http',
                'date_heure'  => $a['date_heure'],
            ];
        }

        $this->jsonResponse([
            'status'  => 'success',
            'bornes'  => $bornesData,
            'alertes' => $alertesData,
            'ts'      => date('c'),
        ]);
    }

    /**
     * Retourne la liste dynamique des cartes RFID enregistrées pour le kiosque.
     * Endpoint: index.php?route=api/cards/list
     */
    public function listCards() {
        $cardModel = new \App\Models\RFIDCard();
        $cards = $cardModel->findAll();

        $result = [];
        foreach ($cards as $c) {
            $result[] = [
                'uid'           => $c['uid'],
                'farmer_nom'    => $c['farmer_nom'],
                'farmer_prenom' => $c['farmer_prenom'],
                'solde'         => (float)$c['solde'],
                'statut'        => $c['statut'],
                'farmer_statut' => $c['farmer_statut'] ?? 'actif'
            ];
        }

        $this->jsonResponse([
            'status' => 'success',
            'cards'  => $result
        ]);
    }
}
