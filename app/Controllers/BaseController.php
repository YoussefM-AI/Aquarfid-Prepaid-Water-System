<?php

namespace App\Controllers;

use App\Core\Session;
use Exception;

/**
 * Contrôleur de base.
 * Fournit des méthodes utilitaires pour le rendu des vues et les redirections.
 */
abstract class BaseController {
    /**
     * Récupère l'administrateur actuellement connecté.
     * Déconnecte automatiquement si le compte est inactif ou supprimé.
     */
    protected function getCurrentAdmin() {
        if (!Session::isLoggedIn() || !isset($_SESSION['admin_id'])) {
            return null;
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM administrateurs WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin || $admin['statut'] !== 'actif') {
            Session::logout();
            Session::start();
            Session::setFlash('error', "Votre compte a été suspendu ou supprimé.");
            $this->redirect('login');
        }

        return $admin;
    }

    /**
     * Exige que l'utilisateur soit un administrateur principal.
     */
    protected function requireAdmin() {
        $admin = $this->getCurrentAdmin();
        if (!$admin || $admin['role'] !== 'admin') {
            Session::setFlash('error', "Accès refusé. Cette action requiert des droits d'administrateur principal.");
            $this->redirect('dashboard');
        }
        return $admin;
    }

    /**
     * Exige une permission spécifique (ou le rôle admin).
     */
    protected function requirePermission(string $permission) {
        $admin = $this->getCurrentAdmin();
        if (!$admin) {
            Session::setFlash('error', "Veuillez vous connecter pour continuer.");
            $this->redirect('login');
        }
        
        $hasPermission = ($admin['role'] === 'admin') || (isset($admin[$permission]) && (bool)$admin[$permission]);
        if (!$hasPermission) {
            Session::setFlash('error', "Accès refusé. Vous n'avez pas la permission requise.");
            $this->redirect('dashboard');
        }
        return $admin;
    }

    /**
     * Effectue le rendu d'une vue HTML avec ou sans layout.
     *
     * @param string $viewName Nom de la vue (chemin relatif par rapport à app/Views)
     * @param array $data Variables à rendre disponibles dans la vue
     * @param bool $withLayout Détermine si le header et le footer doivent être inclus
     */
    protected function render(string $viewName, array $data = [], bool $withLayout = true) {
        // Charger l'administrateur connecté pour l'en-tête global et les vues
        if (!isset($data['currentAdmin'])) {
            $data['currentAdmin'] = $this->getCurrentAdmin();
        }

        // Extraire les variables pour qu'elles soient disponibles dans le fichier de vue
        extract($data);

        $viewFile = dirname(__DIR__) . '/Views/' . $viewName . '.php';

        if (!file_exists($viewFile)) {
            throw new Exception("Fichier de vue introuvable : " . $viewFile);
        }

        if ($withLayout) {
            require dirname(__DIR__) . '/Views/layout/header.php';
        }

        require $viewFile;

        if ($withLayout) {
            require dirname(__DIR__) . '/Views/layout/footer.php';
        }
    }

    /**
     * Redirige l'utilisateur vers une autre route.
     *
     * @param string $route Nom de la route (ex: 'farmers', 'dashboard')
     */
    protected function redirect(string $route) {
        header("Location: index.php?route=" . $route);
        exit();
    }
}
