# Prompt à donner à l'outil IA (Claude Code / Cursor / autre)

Copier-coller l'intégralité du bloc ci-dessous dans l'outil, à la racine du projet (le projet PHP `water` doit être accessible dans le répertoire de travail de l'outil).

---

## CONTEXTE DU PROJET

Tu travailles sur un projet existant : **système de distribution d'eau automatisée par carte RFID prépayée** pour agriculteurs (PHP MVC + MariaDB + Laragon, firmware ESP32 en Arduino C++). Le projet gère déjà :

- Authentification admin, gestion agriculteurs, cartes RFID, transactions, alertes (`app/Controllers`, `app/Models`, `app/Views`)
- Une API REST/JSON existante pour l'ESP32 (`app/Controllers/ApiController.php`, routée par `app/Core/Router.php`) avec les endpoints :
  - `api/cards/verify`, `api/cards/verify-pin`, `api/transactions/store`, `api/alertes/store`, `api/bornes/ping`
- Un firmware ESP32 existant (`esp32/borne_rfid_eau.ino`) qui gère déjà : lecteur RFID MFRC522, clavier 4x4, électrovanne (relais sur GPIO 26), débitmètre à impulsions, capteur de trop-plein (GPIO 35), capteur de fuite sol (GPIO 33), machine à états complète, et communique en **HTTP REST pur** avec le serveur (pas de MQTT actuellement).
- Une base MariaDB (`database.sql`) avec tables `agriculteurs`, `cartes_rfid`, `bornes`, `transactions`, `alertes`, `configurations`, `administrateurs`.
- Une classe `App\Core\Database` (singleton PDO) qui applique des migrations automatiques "self-healing" au démarrage (vérifie si une colonne existe, sinon l'ajoute via `ALTER TABLE`) — **réutilise ce pattern** pour toute nouvelle colonne/table, ne crée pas de script de migration séparé à exécuter manuellement.
- Un dashboard admin (`app/Views/admin/dashboard.php`) actuellement en rendu serveur classique (rechargement de page complet, pas de temps réel).

**NE PAS casser l'existant.** Les endpoints REST actuels doivent continuer à fonctionner (vérification carte, PIN, transactions, alertes ponctuelles) — ils restent le canal principal pour les opérations transactionnelles liées à la carte RFID.

## OBJECTIF DE CETTE TÂCHE

Ajouter un canal de communication **MQTT** entre le serveur et la/les carte(s) ESP32, en complément (pas en remplacement) de l'API REST existante, pour :

1. **Envoyer des ordres de démarrage/arrêt de la pompe (électrovanne)** depuis le serveur vers l'ESP32, à la demande de l'admin (override manuel, indépendant du flux normal carte RFID → volume → distribution).
2. **Connaître en continu l'état des capteurs** (fuite, trop-plein, état vanne ouverte/fermée, débit instantané, statut WiFi/MQTT de la borne) sans attendre qu'une alerte ponctuelle soit déclenchée.
3. **Afficher en temps réel l'état du système** dans le dashboard admin (rafraîchissement automatique, pas de rechargement de page).
4. **Recevoir et notifier immédiatement les alertes de fuite** (et autres incidents) dès qu'elles surviennent côté ESP32.

### Architecture technique imposée

- **Broker MQTT : Mosquitto**, installé localement (sur la même machine que Laragon, ou une VM/conteneur accessible sur le réseau local). Documenter l'installation sur Windows (service Mosquitto) dans le cadre de Laragon.
- **Pont MQTT ↔ PHP : worker PHP CLI permanent.** PHP exécuté par Apache/Laragon ne peut pas maintenir une connexion MQTT persistante (il s'exécute à la requête). Il faut donc un script PHP autonome, lancé en ligne de commande, qui :
  - Tourne en boucle infinie (process daemon),
  - Se connecte au broker Mosquitto en tant que client MQTT,
  - S'abonne aux topics publiés par les ESP32 (état capteurs, alertes, accusés de réception de commande),
  - Écrit/met à jour les données reçues directement dans MariaDB via les Models PHP existants (`Borne`, `Alerte`, ou de nouveaux Models si besoin),
  - Publie les commandes (start/stop pompe) sur les topics appropriés quand le serveur web (Apache) le lui demande.
  - Utiliser la librairie PHP **`php-mqtt/client`** (via Composer) pour le client MQTT côté PHP — c'est la librairie standard, pure PHP, sans extension native requise.
  - Le worker doit pouvoir être lancé/supervisé via un outil de supervision de process sur Windows (NSSM, ou tâche planifiée avec redémarrage automatique, ou `pm2` en mode générique) — documenter au moins une méthode simple et fiable pour du développement local sous Laragon/Windows.
- **Communication Apache (PHP web) → Worker MQTT pour déclencher une commande** : comme le serveur web PHP ne peut pas non plus publier directement sur MQTT à chaque requête sans librairie addionnelle légère, deux approches possibles — choisis et documente la plus simple à intégrer dans ce projet :
  - Option A (recommandée pour rester simple) : le contrôleur PHP web écrit la commande désirée dans une table MariaDB (`commandes_bornes` ou équivalent, avec statut `en_attente`/`envoyee`/`ack`), et le worker MQTT poll cette table à intervalle court (ex. 1s) et publie sur MQTT dès qu'il trouve une commande en attente, puis met à jour son statut.
  - Option B : le contrôleur web instancie aussi un client `php-mqtt/client` en mode "publish-and-disconnect" (connexion courte juste pour publier un message puis fermer) — fonctionne mais peut être plus lent/fragile en environnement web à fort trafic.
  - Documenter le choix retenu et pourquoi.
- **Affichage temps réel dans le dashboard : polling AJAX simple.** Pas de WebSocket. Le navigateur du dashboard admin doit faire un `fetch()` JavaScript toutes les 2 à 5 secondes vers un nouvel endpoint JSON léger (ex. `api/bornes/status-live` ou similaire) qui retourne l'état courant de toutes les bornes (vanne ouverte/fermée, dernières valeurs capteurs, alertes actives non résolues, statut connexion). Le JS met à jour le DOM sans recharger la page (mise à jour ciblée des éléments concernés : badges de statut, liste d'alertes, indicateurs vanne).
- **Côté ESP32** : modifier `esp32/borne_rfid_eau.ino` pour ajouter un client MQTT (librairie **`PubSubClient`**, standard pour ESP32 Arduino) **en parallèle** du client HTTP existant (ne pas supprimer les appels HTTP existants liés au flux carte RFID/PIN/transaction, qui restent inchangés) :
  - Se connecter au broker Mosquitto au démarrage (en plus du WiFi déjà géré).
  - S'abonner à un topic de commande dédié à sa `BORNE_ID` (ex. `eau/bornes/{borne_id}/commande`) pour recevoir les ordres start/stop pompe envoyés par l'admin.
  - Publier périodiquement (toutes les 2-5 secondes, ou sur changement d'état) l'état des capteurs sur un topic dédié (ex. `eau/bornes/{borne_id}/etat`) : état vanne, débit instantané, capteur trop-plein, capteur fuite, RSSI WiFi, etc., au format JSON.
  - Publier immédiatement une alerte sur un topic dédié (ex. `eau/bornes/{borne_id}/alerte`) dès qu'un capteur de fuite ou trop-plein se déclenche, en plus (ou à la place, à toi de juger en gardant la redondance par sécurité) de l'appel HTTP `api/alertes/store` existant — la redondance MQTT + HTTP pour les alertes critiques est acceptable et même souhaitable pour la fiabilité.
  - Important : une commande "stop pompe" reçue par MQTT doit pouvoir fermer l'électrovanne immédiatement même si l'ESP32 est dans n'importe quel état de sa machine à états actuelle (IDLE, DISTRIBUTING, etc.) — traiter ça comme un arrêt d'urgence prioritaire similaire à la fonction `emergencyStop()` déjà présente dans le code. Une commande "start pompe" en mode manuel/admin doit être clairement distinguée du flux normal de distribution piloté par carte RFID (ne pas mélanger les deux logiques métier : un démarrage manuel admin n'est pas une transaction agriculteur facturée).

## TÂCHES CONCRÈTES À RÉALISER

1. **Base de données** : ajouter via le pattern self-healing déjà présent dans `App\Core\Database` (pas de script SQL séparé à lancer à la main) :
   - Sur la table `bornes` : colonnes pour l'état courant de la vanne (`etat_vanne` : `ouverte`/`fermee`), dernier débit instantané connu, dernier état capteur fuite/trop-plein connu, timestamp de dernière télémétrie MQTT reçue.
   - Une nouvelle table `commandes_bornes` (id, borne_id, type_commande [`start_pompe`/`stop_pompe`], statut [`en_attente`/`envoyee`/`ack`/`echec`], demande_par (admin id), date_creation, date_envoi, date_ack).
   - Conserver la table `alertes` existante, l'utiliser aussi pour les alertes reçues via MQTT (ajouter une colonne `source` : `http`/`mqtt` si pertinent pour le suivi, sans casser les inserts existants).

2. **Worker PHP MQTT** (nouveau fichier, ex. `workers/mqtt_worker.php`) :
   - Installer `php-mqtt/client` via Composer.
   - Boucle infinie : connexion au broker, abonnement aux topics `eau/bornes/+/etat` et `eau/bornes/+/alerte`, traitement des messages reçus (mise à jour `bornes`, insertion `alertes`), et polling de la table `commandes_bornes` pour publier les commandes en attente.
   - Logging clair (fichier log ou stdout) pour debug : connexions, messages reçus/envoyés, erreurs.
   - Gestion de la reconnexion automatique en cas de perte de connexion au broker.

3. **Nouveaux endpoints API web (PHP, via `ApiController` ou un nouveau `BorneController`)** :
   - `api/bornes/commande` (POST, protégé par auth admin) : l'admin demande un start/stop pompe → insère une ligne dans `commandes_bornes` avec statut `en_attente`.
   - `api/bornes/status-live` (GET, protégé par auth admin pour le dashboard) : retourne en JSON l'état courant de toutes les bornes + alertes actives, pour le polling AJAX du dashboard.
   - Documenter et coder validations/erreurs dans le même style que les méthodes existantes de `ApiController.php` (mêmes conventions de réponse JSON `status`/`message`).

4. **Dashboard admin** (`app/Views/admin/dashboard.php` + JS associé) :
   - Ajouter une section "Supervision temps réel" si elle n'existe pas déjà clairement, ou enrichir l'existante, avec : indicateur visuel par borne (vanne ouverte/fermée, dernier débit, fuite/trop-plein actif ou non, dernière télémétrie reçue), boutons "Démarrer pompe" / "Arrêter pompe" par borne (appelant `api/bornes/commande` en AJAX), liste des alertes actives mise à jour en direct.
   - Script JS de polling (`setInterval`, 2-5s) appelant `api/bornes/status-live` et mettant à jour le DOM ciblé (pas de rechargement de page).

5. **Firmware ESP32** (`esp32/borne_rfid_eau.ino`) :
   - Ajouter la librairie `PubSubClient`, la configuration broker (IP, port, identifiants si besoin), la connexion MQTT au démarrage en parallèle du WiFi.
   - Ajouter les topics, le callback d'abonnement pour les commandes, la publication périodique de l'état, la publication immédiate des alertes.
   - Intégrer la commande MQTT "stop" comme un arrêt d'urgence prioritaire (cf. logique de `emergencyStop()`), et la commande "start" comme un mode manuel admin distinct du flux carte RFID.
   - Conserver tout le code HTTP REST existant intact.

6. **Documentation à produire** (nouveau fichier `MQTT_DOCUMENTATION.md`, en complément de `API_DOCUMENTATION.md` existant, sans le dupliquer ni le réécrire) :
   - Schéma d'architecture (texte ou Mermaid) montrant : ESP32 ↔ Mosquitto ↔ Worker PHP ↔ MariaDB ↔ Apache/PHP web ↔ Dashboard navigateur.
   - Liste complète des topics MQTT utilisés, avec direction (publish/subscribe), format JSON exact de chaque payload, exemples concrets.
   - Procédure d'installation et de lancement de Mosquitto sous Windows/Laragon.
   - Procédure de lancement et de supervision du worker PHP (`workers/mqtt_worker.php`) en développement local.
   - Description des nouveaux endpoints `api/bornes/commande` et `api/bornes/status-live` (même format que les sections existantes de `API_DOCUMENTATION.md` : route, méthode, paramètres, exemples requête/réponse, erreurs).
   - Procédure de test de bout en bout (déclencher un start/stop depuis le dashboard, simuler une fuite côté ESP32 ou via un client MQTT de test type `mosquitto_pub`/`MQTT Explorer`, vérifier la réception dans le dashboard).

## CONTRAINTES DE QUALITÉ

- Respecter scrupuleusement les conventions de code déjà en place dans le projet (namespaces `App\Controllers`, `App\Models`, `App\Core`, style des Models avec PDO préparé, style de réponse JSON de `ApiController`, structure des vues admin).
- Ne pas introduire de dépendance lourde ou de changement d'architecture non demandé (pas de framework JS, pas de WebSocket, pas de changement de moteur de templates).
- Le code ESP32 doit rester compilable avec les librairies Arduino déjà listées en en-tête du fichier `.ino`, en y ajoutant uniquement `PubSubClient`.
- Toute commande pompe envoyée doit être tracée (qui, quand, quel résultat) via la table `commandes_bornes`.
- Sécuriser les nouveaux endpoints admin (`api/bornes/commande`, `api/bornes/status-live`) avec le même mécanisme d'authentification que les autres routes du dashboard (`Session::isLoggedIn()` / `requireAdmin()` déjà utilisés dans `BaseController`/`DashboardController`).
- À la fin, fournir un résumé clair de tous les fichiers créés/modifiés et des étapes manuelles restantes pour l'utilisateur (ex. : installer Mosquitto, lancer Composer, configurer l'IP du broker dans le `.ino`, lancer le worker).

---

*Fin du prompt à copier-coller.*