<?php

namespace App\Core;

/**
 * Routeur simple basé sur des paramètres de requête.
 * Dirige les requêtes HTTP vers le bon contrôleur et la bonne méthode.
 */
class Router {
    private array $routes = [];

    public function __construct() {
        // Enregistrement des routes de l'application
        $this->registerRoutes();
    }

    /**
     * Enregistre les routes et les associe à des contrôleurs et méthodes.
     */
    private function registerRoutes() {
        $this->routes = [
            // Authentification
            'login'            => ['controller' => 'App\Controllers\AuthController', 'method' => 'login'],
            'logout'           => ['controller' => 'App\Controllers\AuthController', 'method' => 'logout'],

            // Tableau de bord
            'dashboard'        => ['controller' => 'App\Controllers\DashboardController', 'method' => 'index'],

            // Agriculteurs
            'farmers'          => ['controller' => 'App\Controllers\FarmerController', 'method' => 'index'],
            'farmers/create'   => ['controller' => 'App\Controllers\FarmerController', 'method' => 'create'],
            'farmers/store'    => ['controller' => 'App\Controllers\FarmerController', 'method' => 'store'],
            'farmers/edit'     => ['controller' => 'App\Controllers\FarmerController', 'method' => 'edit'],
            'farmers/update'   => ['controller' => 'App\Controllers\FarmerController', 'method' => 'update'],
            'farmers/delete'   => ['controller' => 'App\Controllers\FarmerController', 'method' => 'delete'],

            // Cartes RFID
            'cards'            => ['controller' => 'App\Controllers\CardController', 'method' => 'index'],
            'cards/recharge'   => ['controller' => 'App\Controllers\CardController', 'method' => 'recharge'],
            'cards/status'     => ['controller' => 'App\Controllers\CardController', 'method' => 'toggleStatus'],
            'cards/set-pin'    => ['controller' => 'App\Controllers\CardController', 'method' => 'setPin'],

            // Nouveaux endpoints unifiés du Dashboard
            'settings/save'    => ['controller' => 'App\Controllers\DashboardController', 'method' => 'saveSettings'],
            'admins/store'     => ['controller' => 'App\Controllers\DashboardController', 'method' => 'storeAdmin'],
            'admins/delete'    => ['controller' => 'App\Controllers\DashboardController', 'method' => 'deleteAdmin'],
            'clients/store'    => ['controller' => 'App\Controllers\DashboardController', 'method' => 'storeClient'],

            // API pour le module ESP32 et Kiosque
            'api/cards/list'          => ['controller' => 'App\Controllers\ApiController', 'method' => 'listCards'],
            'api/cards/verify'        => ['controller' => 'App\Controllers\ApiController', 'method' => 'verifyCard'],
            'api/cards/verify-pin'    => ['controller' => 'App\Controllers\ApiController', 'method' => 'verifyPin'],
            'api/transactions/store'  => ['controller' => 'App\Controllers\ApiController', 'method' => 'storeTransaction'],
            'api/alertes/store'       => ['controller' => 'App\Controllers\ApiController', 'method' => 'storeAlerte'],
            'api/bornes/ping'         => ['controller' => 'App\Controllers\ApiController', 'method' => 'pingBorne'],

            // API MQTT — Commandes pompe et supervision temps réel
            'api/bornes/commande'     => ['controller' => 'App\Controllers\ApiController', 'method' => 'commandeBorne'],
            'api/bornes/status-live'  => ['controller' => 'App\Controllers\ApiController', 'method' => 'statusLive'],
            'api/bornes/kiosk-status' => ['controller' => 'App\Controllers\ApiController', 'method' => 'kioskStatus'],
            'api/bornes/kiosk-cancel' => ['controller' => 'App\Controllers\ApiController', 'method' => 'kioskCancel'],

            // Gestion des alertes (dashboard admin)
            'alertes/resolve'         => ['controller' => 'App\Controllers\DashboardController', 'method' => 'resolveAlerte'],
        ];
    }

    /**
     * Résout la requête et appelle l'action correspondante.
     */
    public function dispatch() {
        // Récupérer le paramètre de route, par défaut 'login'
        $route = $_GET['route'] ?? 'login';

        // Si l'utilisateur est connecté et demande la page login, le rediriger vers le dashboard
        if (Session::isLoggedIn() && $route === 'login') {
            $this->redirect('dashboard');
        }

        // Si l'utilisateur n'est pas connecté et demande une page protégée (et non API), le renvoyer vers login
        if (!Session::isLoggedIn() && $route !== 'login' && strpos($route, 'api/') !== 0) {
            Session::setFlash('error', 'Veuillez vous connecter pour accéder à cette page.');
            $this->redirect('login');
        }

        // Vérifier si la route existe
        if (!array_key_exists($route, $this->routes)) {
            // Afficher une erreur 404
            http_response_code(404);
            $this->renderError(404, "Page introuvable");
            return;
        }

        $controllerName = $this->routes[$route]['controller'];
        $methodName = $this->routes[$route]['method'];

        // Instancier le contrôleur et appeler la méthode
        if (class_exists($controllerName)) {
            $controller = new $controllerName();
            if (method_exists($controller, $methodName)) {
                $controller->$methodName();
            } else {
                $this->renderError(500, "Méthode '{$methodName}' introuvable dans le contrôleur.");
            }
        } else {
            $this->renderError(500, "Contrôleur '{$controllerName}' introuvable.");
        }
    }

    /**
     * Utilitaire de redirection.
     */
    private function redirect(string $route) {
        header("Location: index.php?route=" . $route);
        exit();
    }

    /**
     * Affiche une page d'erreur basique.
     */
    private function renderError(int $code, string $message) {
        http_response_code($code);
        $viewsPath = dirname(__DIR__, 2) . '/app/Views/errors/error.php';
        if (file_exists($viewsPath)) {
            require $viewsPath;
        } else {
            echo "<h1>Erreur {$code}</h1>";
            echo "<p>{$message}</p>";
        }
    }
}
