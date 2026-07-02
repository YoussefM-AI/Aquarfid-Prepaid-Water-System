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

        if (!empty($codePin)) {
            if (strlen($codePin) !== 4 || !ctype_digit($codePin)) {
                $msg = "Le code PIN doit être exactement de 4 chiffres.";
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit; }
                Session::setFlash('error', $msg);
                $this->redirect('dashboard#supervision');
                return;
            }

            $db = \App\Core\Database::getInstance()->getConnection();
            $stmtCard = $db->prepare("SELECT uid FROM cartes_rfid WHERE agriculteur_id = :farmer_id");
            $stmtCard->execute(['farmer_id' => $id]);
            $currentCard = $stmtCard->fetch();
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
                $db = \App\Core\Database::getInstance()->getConnection();
                $stmt = $db->prepare("UPDATE cartes_rfid SET code_pin = :pin WHERE agriculteur_id = :farmer_id");
                $stmt->execute([
                    'pin' => !empty($codePin) ? $codePin : null,
                    'farmer_id' => $id
                ]);

                $msg = "Les informations de l'agriculteur et de sa carte ont été mises à jour.";
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
