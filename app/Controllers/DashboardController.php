<?php

namespace App\Controllers;

use App\Models\Farmer;
use App\Models\RFIDCard;
use App\Models\Transaction;
use App\Models\Alerte;
use App\Models\Borne;
use App\Core\Session;

/**
 * Contrôleur DashboardController
 * Rassemble les données et statistiques pour le panneau d'administration.
 */
class DashboardController extends BaseController {
    public function index() {
        $farmerModel = new Farmer();
        $cardModel = new RFIDCard();
        $transactionModel = new Transaction();
        $alerteModel = new Alerte();
        $borneModel = new Borne();

        // Récupération des compteurs généraux
        $stats = [
            'total_farmers'   => $farmerModel->count(),
            'active_cards'    => $cardModel->count('active'),
            'total_credit'    => $cardModel->totalSolde(),
            'total_volume'    => $transactionModel->totalVolume(),
            'total_revenue'   => $transactionModel->totalRevenue(),
            'active_alerts'   => $alerteModel->countActive(),
            'online_bornes'   => $borneModel->count('en ligne'),
            'total_bornes'    => $borneModel->count()
        ];

        // Récupérer les 6 dernières transactions
        $recentTransactions = $transactionModel->findAll(6);

        // Récupérer les alertes
        $alerts = $alerteModel->findAll();

        // Récupérer toutes les cartes RFID avec leurs statistiques unifiées
        $cards = $cardModel->findAll();

        // Récupérer tous les agriculteurs (pour pouvoir lier une carte à un agriculteur existant)
        $farmers = $farmerModel->findAll();

        // Récupérer tous les comptes d'administration
        $db = \App\Core\Database::getInstance()->getConnection();
        $admins = $db->query("SELECT * FROM administrateurs ORDER BY created_at DESC")->fetchAll();

        // Récupérer les configurations système
        $stmt = $db->query("SELECT param_key, param_value FROM configurations");
        $configs = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Rendre la vue du dashboard avec toutes les données unifiées
        $this->render('admin/dashboard', [
            'stats'              => $stats,
            'recentTransactions' => $recentTransactions,
            'alerts'             => $alerts,
            'cards'              => $cards,
            'farmers'            => $farmers,
            'admins'             => $admins,
            'configs'            => $configs
        ]);
    }

    /**
     * Enregistre les paramètres de configuration globale.
     */
    public function saveSettings() {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dashboard#config');
        }

        $price = (float) ($_POST['price_per_litre'] ?? 0.200);

        if ($price <= 0) {
            Session::setFlash('error', "Le tarif de l'eau doit être positif.");
            $this->redirect('dashboard#config');
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE configurations SET param_value = :val WHERE param_key = 'price_per_litre'");
        $success = $stmt->execute(['val' => sprintf("%.3f", $price)]);

        if ($success) {
            Session::setFlash('success', "Paramètres système sauvegardés avec succès.");
        } else {
            Session::setFlash('error', "Erreur lors de la sauvegarde des paramètres.");
        }

        $this->redirect('dashboard#config');
    }

    /**
     * Marque une alerte comme résolue (depuis le journal du dashboard).
     * Endpoint: index.php?route=alertes/resolve&id=X
     */
    public function resolveAlerte() {
        $this->requireLogin();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            Session::setFlash('error', "ID d'alerte invalide.");
            $this->redirect('dashboard#supervision');
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "UPDATE alertes SET resolu = 1, date_resolution = NOW() WHERE id = :id"
        );
        $ok = $stmt->execute(['id' => $id]);

        if ($ok && $stmt->rowCount() > 0) {
            Session::setFlash('success', 'Alerte #' . $id . ' marquée comme résolue.');
        } else {
            Session::setFlash('error', 'Alerte introuvable ou déjà résolue.');
        }

        $this->redirect('dashboard#supervision');
    }

    /**
     * Crée ou modifie un compte d'administration.
     */
    public function storeAdmin() {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dashboard#users');
        }

        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $statut = $_POST['statut'] ?? 'actif';
        $role = trim($_POST['role'] ?? 'secretaire');
        
        $perm_view_clients = isset($_POST['perm_view_clients']) ? 1 : 0;
        $perm_view_balances = isset($_POST['perm_view_balances']) ? 1 : 0;
        $perm_view_transactions = isset($_POST['perm_view_transactions']) ? 1 : 0;
        $perm_manage_clients = isset($_POST['perm_manage_clients']) ? 1 : 0;
        $perm_can_recharge = isset($_POST['perm_can_recharge']) ? 1 : 0;

        if (empty($nom) || empty($prenom) || empty($email)) {
            Session::setFlash('error', "Le nom, le prénom et l'email sont obligatoires.");
            $this->redirect('dashboard#users');
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            // Modification
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE administrateurs SET nom = :nom, prenom = :prenom, email = :email, password = :password, statut = :statut, role = :role, perm_view_clients = :perm_view_clients, perm_view_balances = :perm_view_balances, perm_view_transactions = :perm_view_transactions, perm_manage_clients = :perm_manage_clients, perm_can_recharge = :perm_can_recharge WHERE id = :id");
                $success = $stmt->execute([
                    'id' => $id,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'statut' => $statut,
                    'role' => $role,
                    'perm_view_clients' => $perm_view_clients,
                    'perm_view_balances' => $perm_view_balances,
                    'perm_view_transactions' => $perm_view_transactions,
                    'perm_manage_clients' => $perm_manage_clients,
                    'perm_can_recharge' => $perm_can_recharge
                ]);
            } else {
                $stmt = $db->prepare("UPDATE administrateurs SET nom = :nom, prenom = :prenom, email = :email, statut = :statut, role = :role, perm_view_clients = :perm_view_clients, perm_view_balances = :perm_view_balances, perm_view_transactions = :perm_view_transactions, perm_manage_clients = :perm_manage_clients, perm_can_recharge = :perm_can_recharge WHERE id = :id");
                $success = $stmt->execute([
                    'id' => $id,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'statut' => $statut,
                    'role' => $role,
                    'perm_view_clients' => $perm_view_clients,
                    'perm_view_balances' => $perm_view_balances,
                    'perm_view_transactions' => $perm_view_transactions,
                    'perm_manage_clients' => $perm_manage_clients,
                    'perm_can_recharge' => $perm_can_recharge
                ]);
            }
            if ($success) {
                Session::setFlash('success', "Compte personnel modifié avec succès.");
            } else {
                Session::setFlash('error', "Erreur lors de la modification du compte.");
            }
        } else {
            // Création
            if (empty($password)) {
                Session::setFlash('error', "Le mot de passe est obligatoire pour un nouveau compte.");
                $this->redirect('dashboard#users');
            }
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO administrateurs (nom, prenom, email, password, statut, role, perm_view_clients, perm_view_balances, perm_view_transactions, perm_manage_clients, perm_can_recharge) VALUES (:nom, :prenom, :email, :password, :statut, :role, :perm_view_clients, :perm_view_balances, :perm_view_transactions, :perm_manage_clients, :perm_can_recharge)");
            try {
                $success = $stmt->execute([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'statut' => $statut,
                    'role' => $role,
                    'perm_view_clients' => $perm_view_clients,
                    'perm_view_balances' => $perm_view_balances,
                    'perm_view_transactions' => $perm_view_transactions,
                    'perm_manage_clients' => $perm_manage_clients,
                    'perm_can_recharge' => $perm_can_recharge
                ]);
                if ($success) {
                    Session::setFlash('success', "Compte personnel créé avec succès.");
                } else {
                    Session::setFlash('error', "Erreur lors de la création du compte.");
                }
            } catch (\Exception $e) {
                Session::setFlash('error', "Cette adresse email est déjà utilisée.");
            }
        }

        $this->redirect('dashboard#users');
    }

    /**
     * Supprime un compte d'administration.
     */
    public function deleteAdmin() {
        $this->requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        
        // Empêcher de se supprimer soi-même
        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT email FROM administrateurs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $targetEmail = $stmt->fetchColumn();

        if (Session::isLoggedIn() && isset($_SESSION['admin_email']) && $_SESSION['admin_email'] === $targetEmail) {
            Session::setFlash('error', "Vous ne pouvez pas supprimer votre propre compte administrateur.");
            $this->redirect('dashboard#users');
        }

        $stmt = $db->prepare("DELETE FROM administrateurs WHERE id = :id");
        $success = $stmt->execute(['id' => $id]);

        if ($success) {
            Session::setFlash('success', "Compte personnel supprimé avec succès.");
        } else {
            Session::setFlash('error', "Erreur lors de la suppression du compte.");
        }

        $this->redirect('dashboard#users');
    }

    /**
     * Enregistre un nouveau client (agriculteur + association carte RFID).
     */
    public function storeClient() {
        $this->requirePermission('perm_manage_clients');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dashboard#supervision');
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);

        $sendJson = function($status, $message) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => $status, 'message' => $message]);
            exit;
        };

        $nom       = trim($_POST['nom'] ?? '');
        $prenom    = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $uid       = strtoupper(trim($_POST['uid'] ?? ''));
        $solde     = (float) ($_POST['solde'] ?? 0.00);
        $codePin   = trim($_POST['code_pin'] ?? '');

        if (empty($nom) || empty($prenom) || empty($telephone) || empty($uid)) {
            $msg = "Le nom, le prénom, le téléphone et l'UID de la carte sont obligatoires.";
            if ($isAjax) $sendJson('error', $msg);
            Session::setFlash('error', $msg);
            $this->redirect('dashboard#supervision');
            return;
        }

        $cardModel = new RFIDCard();

        if (!empty($codePin)) {
            if (strlen($codePin) !== 4 || !ctype_digit($codePin)) {
                $msg = "Le code PIN doit être exactement de 4 chiffres.";
                if ($isAjax) $sendJson('error', $msg);
                Session::setFlash('error', $msg);
                $this->redirect('dashboard#supervision');
                return;
            }
            if (!$cardModel->isPinUnique($codePin)) {
                $msg = "Ce code PIN est déjà utilisé par un autre client. Veuillez en choisir un autre unique.";
                if ($isAjax) $sendJson('error', $msg);
                Session::setFlash('error', $msg);
                $this->redirect('dashboard#supervision');
                return;
            }
        }

        // Vérifier si la carte existe déjà
        if ($cardModel->find($uid)) {
            $msg = "Cette carte RFID (UID: {$uid}) est déjà enregistrée.";
            if ($isAjax) $sendJson('error', $msg);
            Session::setFlash('error', $msg);
            $this->redirect('dashboard#supervision');
            return;
        }

        try {
            // Créer d'abord l'agriculteur
            $farmerModel = new Farmer();
            $successFarmer = $farmerModel->insert([
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $telephone,
                'email' => $email,
                'statut' => 'actif'
            ]);

            if ($successFarmer) {
                $db = \App\Core\Database::getInstance()->getConnection();
                $agriculteur_id = $db->lastInsertId();

                // Créer la carte liée
                $successCard = $cardModel->insert([
                    'uid' => $uid,
                    'agriculteur_id' => $agriculteur_id,
                    'solde' => $solde,
                    'statut' => 'active',
                    'code_pin' => !empty($codePin) ? $codePin : null
                ]);

                if ($successCard) {
                    $msg = "Le nouveau client et sa carte RFID ont été créés avec succès.";
                    Session::setFlash('success', $msg);
                    if ($isAjax) $sendJson('success', $msg);
                } else {
                    $msg = "Le profil client a été créé, mais l'association de la carte a échoué.";
                    if ($isAjax) $sendJson('error', $msg);
                    Session::setFlash('error', $msg);
                }
            } else {
                $msg = "Erreur lors de la création de l'agriculteur.";
                if ($isAjax) $sendJson('error', $msg);
                Session::setFlash('error', $msg);
            }
        } catch (\Throwable $e) {
            $msg = "Erreur base de données : " . $e->getMessage();
            if ($isAjax) $sendJson('error', $msg);
            Session::setFlash('error', $msg);
        }

        $this->redirect('dashboard#supervision');
    }
}
