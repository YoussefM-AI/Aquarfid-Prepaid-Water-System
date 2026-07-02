<?php

namespace App\Core;

/**
 * Classe utilitaire pour la gestion des sessions et des messages flash.
 */
class Session {
    /**
     * Démarre la session si elle n'est pas déjà active.
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Connecte un administrateur en enregistrant ses infos en session.
     */
    public static function login(array $adminData) {
        self::start();
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $adminData['id'];
        $_SESSION['admin_email'] = $adminData['email'];
        $_SESSION['admin_name'] = $adminData['prenom'] . ' ' . $adminData['nom'];
    }

    /**
     * Déconnecte l'administrateur en détruisant les données de session.
     */
    public static function logout() {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Vérifie si un administrateur est connecté.
     */
    public static function isLoggedIn(): bool {
        self::start();
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    /**
     * Récupère le nom de l'administrateur connecté.
     */
    public static function getAdminName(): string {
        self::start();
        return $_SESSION['admin_name'] ?? 'Administrateur';
    }

    /**
     * Définit un message flash temporaire (succès ou erreur).
     */
    public static function setFlash(string $key, string $message) {
        self::start();
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Récupère et supprime un message flash s'il existe.
     */
    public static function getFlash(string $key): ?string {
        self::start();
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }

    /**
     * Vérifie si un message flash existe.
     */
    public static function hasFlash(string $key): bool {
        self::start();
        return isset($_SESSION['flash'][$key]);
    }
}
