<?php

namespace App\Controllers;

use App\Models\RFIDCard;
use App\Models\Farmer;
use App\Core\Session;
use Exception;

/**
 * Contrôleur CardController
 * Gère les cartes RFID (association, blocage, recharge de crédit).
 */
class CardController extends BaseController {
    private RFIDCard $cardModel;
    private Farmer $farmerModel;

    public function __construct() {
        $this->cardModel = new RFIDCard();
        $this->farmerModel = new Farmer();
    }

    /**
     * Liste des cartes RFID et formulaire d'association.
     */
    public function index() {
        $this->redirect('dashboard#supervision');
    }

    /**
     * Crée/Associe une nouvelle carte RFID.
     */
    public function store() {
        $this->requirePermission('perm_manage_clients');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dashboard#supervision');
        }

        $uid            = trim($_POST['uid'] ?? '');
        $agriculteur_id = (int) ($_POST['agriculteur_id'] ?? 0);
        $solde          = (float) ($_POST['solde'] ?? 0.00);
        $statut         = trim($_POST['statut'] ?? 'active');

        if (empty($uid) || $agriculteur_id <= 0) {
            Session::setFlash('error', "L'UID de la carte et l'agriculteur sont obligatoires.");
            $this->redirect('dashboard#supervision');
        }

        // Vérifier si la carte existe déjà
        $existing = $this->cardModel->find($uid);
        if ($existing) {
            Session::setFlash('error', "Cette carte RFID (UID: {$uid}) est déjà associée à " . $existing['farmer_prenom'] . " " . $existing['farmer_nom'] . ".");
            $this->redirect('dashboard#supervision');
        }

        try {
            $success = $this->cardModel->insert([
                'uid'            => $uid,
                'agriculteur_id' => $agriculteur_id,
                'solde'          => $solde,
                'statut'         => $statut
            ]);

            if ($success) {
                Session::setFlash('success', "La carte RFID a été associée avec succès.");
            } else {
                Session::setFlash('error', "Impossible d'associer la carte RFID.");
            }
        } catch (Exception $e) {
            Session::setFlash('error', "Erreur : " . $e->getMessage());
        }

        $this->redirect('dashboard#supervision');
    }

    /**
     * Recharge le crédit d'une carte.
     */
    public function recharge() {
        $this->requirePermission('perm_can_recharge');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dashboard#supervision');
        }

        $uid    = trim($_POST['uid'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0.00);

        if (empty($uid) || $amount <= 0) {
            Session::setFlash('error', "UID de carte invalide ou montant de recharge incorrect.");
            $this->redirect('dashboard#supervision');
        }

        try {
            $success = $this->cardModel->recharge($uid, $amount);
            if ($success) {
                Session::setFlash('success', "Recharge de " . number_format($amount, 3) . " DT effectuée avec succès.");
            } else {
                Session::setFlash('error', "Échec de la recharge de la carte.");
            }
        } catch (Exception $e) {
            Session::setFlash('error', $e->getMessage());
        }

        $this->redirect('dashboard#supervision');
    }

    /**
     * Modifie directement le solde d'une carte.
     */
    public function updateBalance() {
        $this->requirePermission('perm_can_recharge');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dashboard#supervision');
        }

        $uid     = trim($_POST['uid'] ?? '');
        $balance = (float) ($_POST['balance'] ?? 0.00);

        if (empty($uid) || $balance < 0) {
            Session::setFlash('error', "UID de carte invalide ou solde incorrect.");
            $this->redirect('dashboard#supervision');
        }

        try {
            $success = $this->cardModel->updateBalance($uid, $balance);
            if ($success) {
                Session::setFlash('success', "Le solde de la carte a été mis à jour à " . number_format($balance, 3) . " DT avec succès.");
            } else {
                Session::setFlash('error', "Échec de la mise à jour du solde de la carte.");
            }
        } catch (Exception $e) {
            Session::setFlash('error', $e->getMessage());
        }

        $this->redirect('dashboard#supervision');
    }

    /**
     * Active ou suspend une carte RFID.
     */
    public function toggleStatus() {
        $this->requirePermission('perm_manage_clients');

        $uid    = trim($_GET['uid'] ?? '');
        $status = trim($_GET['status'] ?? '');

        if (empty($uid) || !in_array($status, ['active', 'suspendue', 'perdue'])) {
            Session::setFlash('error', "Action ou UID de carte invalide.");
            $this->redirect('dashboard#supervision');
        }

        $success = $this->cardModel->updateStatus($uid, $status);
        if ($success) {
            // Synchroniser le statut de l'agriculteur
            $farmerStatut = ($status === 'active') ? 'actif' : 'suspendu';
            $db = \App\Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE agriculteurs a JOIN cartes_rfid c ON c.agriculteur_id = a.id SET a.statut = :farmer_statut WHERE c.uid = :uid");
            $stmt->execute(['farmer_statut' => $farmerStatut, 'uid' => $uid]);

            Session::setFlash('success', "Le statut de la carte {$uid} et du client a été mis à jour ({$status}).");
        } else {
            Session::setFlash('error', "Échec de la mise à jour du statut de la carte.");
        }

        $this->redirect('dashboard#supervision');
    }

    /**
     * Définit ou met à jour le code PIN d'une carte RFID.
     * Endpoint: index.php?route=cards/set-pin (POST)
     */
    public function setPin() {
        $this->requirePermission('perm_manage_clients');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dashboard#supervision');
        }

        $uid = strtoupper(trim($_POST['uid'] ?? ''));
        $pin = trim($_POST['pin'] ?? '');

        if (empty($uid)) {
            Session::setFlash('error', "UID de carte obligatoire.");
            $this->redirect('dashboard#supervision');
        }

        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            Session::setFlash('error', "Le code PIN doit être exactement 4 chiffres.");
            $this->redirect('dashboard#supervision');
            return;
        }

        if ($pin !== '0000') {
            $cardModel = new \App\Models\RFIDCard();
            if (!$cardModel->isPinUnique($pin, $uid)) {
                Session::setFlash('error', "Ce code PIN est déjà utilisé par un autre client. Veuillez en choisir un autre unique.");
                $this->redirect('dashboard#supervision');
                return;
            }
        }

        $db = \App\Core\Database::getInstance()->getConnection();

        // Si PIN vide transmis (on veut supprimer le PIN)
        if ($pin === '0000') {
            $stmt = $db->prepare("UPDATE cartes_rfid SET code_pin = NULL WHERE uid = :uid");
            $stmt->execute(['uid' => $uid]);
            Session::setFlash('success', "Code PIN supprimé pour la carte {$uid}.");
        } else {
            $hash = password_hash($pin, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE cartes_rfid SET code_pin = :hash WHERE uid = :uid");
            $stmt->execute(['hash' => $hash, 'uid' => $uid]);
            Session::setFlash('success', "Code PIN défini avec succès pour la carte {$uid}.");
        }

        $this->redirect('dashboard#supervision');
    }
}
