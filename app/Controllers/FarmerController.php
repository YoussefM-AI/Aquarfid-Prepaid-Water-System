<?php

namespace App\Controllers;

use App\Models\Farmer;
use App\Core\Session;

/**
 * Contrôleur FarmerController
 * Gère le CRUD des agriculteurs.
 */
class FarmerController extends BaseController {
    private Farmer $farmerModel;

    public function __construct() {
        $this->farmerModel = new Farmer();
    }

    /**
     * Liste des agriculteurs.
     */
    public function index() {
        $this->redirect('dashboard#supervision');
    }

    /**
     * Formulaire d'ajout.
     */
    public function create() {
        $this->redirect('dashboard#supervision');
    }

    /**
     * Enregistre un nouvel agriculteur.
     */
    public function store() {
        $this->requirePermission('perm_manage_clients');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('farmers');
        }

        $nom       = trim($_POST['nom'] ?? '');
        $prenom    = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $statut    = trim($_POST['statut'] ?? 'actif');

        if (empty($nom) || empty($prenom) || empty($telephone)) {
            Session::setFlash('error', 'Le nom, le prénom et le téléphone sont obligatoires.');
            $this->render('admin/farmers/create', $_POST);
            return;
        }

        $success = $this->farmerModel->insert([
            'nom'       => $nom,
            'prenom'    => $prenom,
            'telephone' => $telephone,
            'email'     => $email,
            'statut'    => $statut
        ]);

        if ($success) {
            Session::setFlash('success', "L'agriculteur a été ajouté avec succès.");
            $this->redirect('farmers');
        } else {
            Session::setFlash('error', "Une erreur est survenue lors de l'enregistrement.");
            $this->render('admin/farmers/create', $_POST);
        }
    }

    /**
     * Formulaire de modification.
     */
    public function edit() {
        $this->redirect('dashboard#supervision');
    }

    /**
     * Met à jour un agriculteur.
     */
    public function update() {
        $this->requirePermission('perm_manage_clients');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dashboard#supervision');
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);

        $id        = (int) ($_POST['id'] ?? 0);
        $nom       = trim($_POST['nom'] ?? '');
        $prenom    = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $statut    = trim($_POST['statut'] ?? 'actif');
        $newUid    = strtoupper(trim($_POST['uid'] ?? ''));
        $codePin   = trim($_POST['code_pin'] ?? '');

        $farmer = $this->farmerModel->find($id);
        if (!$farmer) {
            $msg = "Agriculteur introuvable.";
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit; }
            Session::setFlash('error', $msg);
            $this->redirect('dashboard#supervision');
            return;
        }

        if (empty($nom) || empty($prenom) || empty($telephone)) {
            $msg = "Le nom, le prénom et le téléphone sont obligatoires.";
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit; }
            Session::setFlash('error', $msg);
            $this->redirect('dashboard#supervision');
            return;
        }

        if (empty($newUid)) {
            $msg = "L'identifiant UID de la carte RFID est obligatoire.";
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit; }
            Session::setFlash('error', $msg);
            $this->redirect('dashboard#supervision');
            return;
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        $stmtCard = $db->prepare("SELECT * FROM cartes_rfid WHERE agriculteur_id = :farmer_id AND deleted_at IS NULL");
        $stmtCard->execute(['farmer_id' => $id]);
        $currentCard = $stmtCard->fetch();

        // Si l'UID change, vérifier si le nouvel UID est déjà pris par un autre agriculteur
        if (!$currentCard || $newUid !== strtoupper(trim($currentCard['uid']))) {
            $stmtCheck = $db->prepare("SELECT uid, agriculteur_id FROM cartes_rfid WHERE uid = :new_uid AND deleted_at IS NULL");
            $stmtCheck->execute(['new_uid' => $newUid]);
            $existingCard = $stmtCheck->fetch();
            if ($existingCard && (int)$existingCard['agriculteur_id'] !== $id) {
                $msg = "Cette carte RFID (UID: {$newUid}) est déjà attribuée à un autre client.";
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit; }
                Session::setFlash('error', $msg);
                $this->redirect('dashboard#supervision');
                return;
            }
        }

        if (!empty($codePin)) {
            if (strlen($codePin) !== 4 || !ctype_digit($codePin)) {
                $msg = "Le code PIN doit être exactement de 4 chiffres.";
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit; }
                Session::setFlash('error', $msg);
                $this->redirect('dashboard#supervision');
                return;
            }

            $excludeUid = $currentCard ? $currentCard['uid'] : null;
            $cardModel = new \App\Models\RFIDCard();
            if (!$cardModel->isPinUnique($codePin, $excludeUid)) {
                $msg = "Ce code PIN est déjà utilisé par un autre client. Veuillez en choisir un autre unique.";
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit; }
                Session::setFlash('error', $msg);
                $this->redirect('dashboard#supervision');
                return;
            }
        }

        try {
            $success = $this->farmerModel->update($id, [
                'nom'       => $nom,
                'prenom'    => $prenom,
                'telephone' => $telephone,
                'email'     => $email,
                'statut'    => $statut
            ]);

            if ($success) {
                if ($currentCard) {
                    $oldUid = $currentCard['uid'];
                    if ($newUid !== strtoupper(trim($oldUid))) {
                        // Mise à jour de la clé primaire de la carte et des transactions liées
                        $db->beginTransaction();
                        try {
                            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                            $stmtUpdateCard = $db->prepare("UPDATE cartes_rfid SET uid = :new_uid WHERE uid = :old_uid");
                            $stmtUpdateCard->execute(['new_uid' => $newUid, 'old_uid' => $oldUid]);

                            $stmtUpdateTx = $db->prepare("UPDATE transactions SET carte_rfid_uid = :new_uid WHERE carte_rfid_uid = :old_uid");
                            $stmtUpdateTx->execute(['new_uid' => $newUid, 'old_uid' => $oldUid]);
                            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                            $db->commit();
                        } catch (\Throwable $e) {
                            $db->rollBack();
                            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                            throw $e;
                        }
                    }

                    if (!empty($codePin)) {
                        $stmtPin = $db->prepare("UPDATE cartes_rfid SET code_pin = :pin WHERE uid = :uid");
                        $stmtPin->execute([
                            'pin' => $codePin,
                            'uid' => $newUid
                        ]);
                    }

                    // Synchroniser le statut de la carte avec le statut du client
                    $cardStatut = ($statut === 'actif') ? 'active' : 'suspendue';
                    $stmtSyncCard = $db->prepare("UPDATE cartes_rfid SET statut = :card_statut WHERE agriculteur_id = :agriculteur_id");
                    $stmtSyncCard->execute([
                        'card_statut'     => $cardStatut,
                        'agriculteur_id' => $id
                    ]);
                } else {
                    $cardStatut = ($statut === 'actif') ? 'active' : 'suspendue';
                    $stmtInsert = $db->prepare("INSERT INTO cartes_rfid (uid, agriculteur_id, solde, statut, code_pin) VALUES (:uid, :agriculteur_id, 0.000, :statut, :pin)");
                    $stmtInsert->execute([
                        'uid'            => $newUid,
                        'agriculteur_id' => $id,
                        'statut'         => $cardStatut,
                        'pin'            => !empty($codePin) ? $codePin : null
                    ]);
                }

                $msg = "Les informations de l'agriculteur et de sa carte (UID: {$newUid}) ont été mises à jour.";
                Session::setFlash('success', $msg);
                if ($isAjax) {
                    if (ob_get_length()) ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => $msg]);
                    exit;
                }
            } else {
                $msg = "Une erreur est survenue lors de la mise à jour.";
                if ($isAjax) {
                    if (ob_get_length()) ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => $msg]);
                    exit;
                }
                Session::setFlash('error', $msg);
            }
        } catch (\Throwable $e) {
            $msg = "Erreur base de données : " . $e->getMessage();
            if ($isAjax) {
                if (ob_get_length()) ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $msg]);
                exit;
            }
            Session::setFlash('error', $msg);
        }
        $this->redirect('dashboard#supervision');
    }

    /**
     * Supprime un agriculteur.
     */
    public function delete() {
        $this->requirePermission('perm_manage_clients');

        $id = (int) ($_GET['id'] ?? 0);
        $farmer = $this->farmerModel->find($id);

        if (!$farmer) {
            Session::setFlash('error', "Agriculteur introuvable.");
            $this->redirect('dashboard#supervision');
        }

        $success = $this->farmerModel->delete($id);

        if ($success) {
            Session::setFlash('success', "L'agriculteur a été supprimé.");
        } else {
            Session::setFlash('error', "Impossible de supprimer l'agriculteur (il possède probablement des cartes actives).");
        }
        
        $this->redirect('dashboard#supervision');
    }
    
}
