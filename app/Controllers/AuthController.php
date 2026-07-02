<?php

namespace App\Controllers;

use App\Models\Admin;
use App\Core\Session;

/**
 * Contrôleur AuthController
 * Gère l'authentification des administrateurs.
 */
class AuthController extends BaseController {
    private Admin $adminModel;

    public function __construct() {
        $this->adminModel = new Admin();
    }

    /**
     * Gère la connexion.
     */
    public function login() {
        // Si la requête est en POST, on valide le formulaire
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                Session::setFlash('error', 'Veuillez remplir tous les champs.');
                $this->render('auth/login', [], false);
                return;
            }

            try {
                // Tenter la connexion
                $admin = $this->adminModel->authenticate($email, $password);

                if ($admin) {
                    // Connecter l'admin
                    Session::login($admin);
                    Session::setFlash('success', "Bienvenue, {$admin['prenom']} !");
                    $this->redirect('dashboard');
                } else {
                    Session::setFlash('error', 'Identifiants invalides ou compte inactif.');
                    $this->render('auth/login', ['email' => $email], false);
                }
            } catch (\Exception $e) {
                // Afficher l'erreur technique pour le diagnostic
                Session::setFlash('error', 'Erreur technique : ' . $e->getMessage());
                $this->render('auth/login', ['email' => $email], false);
            }
        } else {
            // Afficher le formulaire de connexion (sans le layout d'administration)
            $this->render('auth/login', [], false);
        }
    }

    /**
     * Gère la déconnexion.
     */
    public function logout() {
        Session::logout();
        Session::start();
        Session::setFlash('success', 'Vous avez été déconnecté avec succès.');
        $this->redirect('login');
    }
}
