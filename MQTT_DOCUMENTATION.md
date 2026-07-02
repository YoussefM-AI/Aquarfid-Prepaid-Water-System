# Documentation de l'Intégration MQTT pour les Bornes de Distribution d'Eau

Cette documentation décrit l'architecture de communication temps réel basée sur le protocole MQTT (via le broker Mosquitto) intégrée au système de distribution d'eau RFID.

---

## 1. Schéma d'Architecture du Système

La communication est hybride : le flux transactionnel critique lié à la carte RFID (authentification, débit, validation du PIN) s'effectue via l'API HTTP REST existante. La télémétrie en continu, les alertes instantanées et le contrôle à distance de la vanne (relais) transitent en temps réel via MQTT.

```mermaid
flowchart TD
    subgraph Terrain
        ESP32[Borne ESP32]
    end

    subgraph Broker MQTT
        Mosquitto[Mosquitto Broker]
    end

    subgraph Backend & Daemon
        Worker[Worker PHP CLI\nworkers/mqtt_worker.php]
        MariaDB[(Base MariaDB)]
        Apache[Apache / PHP Web]
    end

    subgraph Interface Admin
        Browser[Navigateur\nDashboard Admin]
    end

    %% Communication MQTT Directe
    ESP32 <-->|Publish / Subscribe| Mosquitto
    Browser <-->|WebSockets port 9001\nPublish / Subscribe| Mosquitto
    Worker <-->|Subscribe state/alert/cmd| Mosquitto

    %% Communication HTTP REST (existant)
    ESP32 -->|HTTP POST / verify & store| Apache
    Browser -->|HTTP GET / status-live\n(hydratation initiale)| Apache

    %% Database & Web Access
    Worker -->|Enregistre télémétrie & commandes| MariaDB
    Apache <-->|Lecture / Écriture| MariaDB
```

---

## 2. Topics MQTT & Spécifications des Payloads

Tous les messages échangés sur le broker utilisent le format **JSON**.

### 2.1. Télémétrie Périodique (ESP32 → Broker → Worker PHP)

* **Topic :** `eau/bornes/{borne_id}/etat` (ex: `eau/bornes/1/etat`)
* **Direction :** ESP32 publie (toutes les 3 secondes), Worker PHP s'abonne.
* **Payload JSON :**
  ```json
  {
    "borne_id": 1,
    "etat_vanne": "ouverte",
    "debit": 45.2,
    "fuite": false,
    "trop_plein": false,
    "rssi": -68,
    "etat_machine": 5,
    "admin_override": false,
    "ts": 1248592
  }
  ```
* **Champs :**
  - `borne_id` (int) : ID unique de la borne émettrice.
  - `etat_vanne` (string) : `"ouverte"` ou `"fermee"`.
  - `debit` (float) : Débit instantané mesuré en L/min.
  - `fuite` (bool) : `true` si le capteur de fuite au sol est actif.
  - `trop_plein` (bool) : `true` si le flotteur de trop-plein est actif.
  - `rssi` (int) : Force du signal WiFi en dBm.
  - `etat_machine` (int) : État courant de la machine à états de l'ESP32.
  - `admin_override` (bool) : `true` si la vanne a été forcée par l'administrateur.
  - `ts` (int) : Timestamp interne de démarrage (uptime en ms).

---

### 2.2. Notification d'Alerte Immédiate (ESP32 → Broker → Worker PHP)

* **Topic :** `eau/bornes/{borne_id}/alerte` (ex: `eau/bornes/1/alerte`)
* **Direction :** ESP32 publie immédiatement lors d'un incident, Worker PHP s'abonne.
* **Payload JSON :**
  ```json
  {
    "borne_id": 1,
    "type": "fuite",
    "description": "Fuite sol détectée hors distribution (borne #1)",
    "ts": 1250100
  }
  ```
* **Types d'alertes reconnus :**
  - `"fuite"` : Infiltration d'eau au sol ou débit anormal détecté.
  - `"trop_plein"` : Alerte niveau cuve trop élevé.
  - `"manque_eau"` : Pas de débit mesuré malgré l'ouverture de la vanne.
  - `"com_perdue"` : Perte de liaison critique.

---

### 2.3. Commande de Pompe (Worker PHP → Broker → ESP32)

* **Topic :** `eau/bornes/{borne_id}/commande` (ex: `eau/bornes/1/commande`)
* **Direction :** Worker PHP publie, ESP32 s'abonne.
* **Payload JSON :**
  ```json
  {
    "commande_id": 42,
    "type": "start_pompe",
    "ts": "2026-06-25T23:00:00+01:00"
  }
  ```
* **Commandes supportées (`type`) :**
  - `"start_pompe"` : Ouvre la vanne en mode manuel administrateur (sans facturation RFID).
  - `"stop_pompe"` : Ferme la vanne immédiatement (arrêt d'urgence prioritaire).

---

## 3. Installation et Configuration du Broker Mosquitto

### Installation sous Windows (avec Laragon)

1. **Téléchargement** :
   Téléchargez l'installateur Windows officiel (64-bits) sur le site de [Mosquitto](https://mosquitto.org/download/).

2. **Installation** :
   Exécutez le programme d'installation. Par défaut, Mosquitto s'installe dans `C:\Program Files\mosquitto`.

3. **Configuration pour l'accès local** :
   Par défaut, Mosquitto v2.x n'autorise que les connexions sur `localhost` (127.0.0.1) et interdit les connexions anonymes. Pour permettre aux ESP32 du réseau local de se connecter :
   - Ouvrez le fichier `C:\Program Files\mosquitto\mosquitto.conf` en mode Administrateur.
   - Ajoutez ou modifiez les lignes suivantes :
     ```ini
     listener 1883 0.0.0.0
     allow_anonymous true
     ```
   - Enregistrez le fichier.

4. **Lancement du service** :
   - Ouvrez la console des services Windows (`services.msc`).
   - Recherchez le service **Mosquitto Broker**.
   - Faites un clic droit -> **Démarrer** (et configurez le démarrage sur **Automatique**).

---

## 4. Lancement et Supervision du Worker PHP

Le worker PHP fait office de pont bidirectionnel permanent. Il doit s'exécuter en arrière-plan de manière continue.

### Lancement manuel (développement)
Exécutez la commande suivante à la racine du projet :
```bash
php workers/mqtt_worker.php
```
Le worker affiche les logs en temps réel sur la sortie standard (console) et les écrit également dans le fichier `workers/mqtt_worker.log`.

### Supervision en production ou arrière-plan sous Windows

Il est recommandé d'utiliser **NSSM (Non-Sucking Service Manager)** pour enregistrer le worker en tant que service Windows natif :

1. Téléchargez NSSM et ouvrez une invite de commandes en mode Administrateur dans le répertoire de NSSM.
2. Créez le service :
   ```cmd
   nssm install WaterMqttWorker
   ```
3. Dans l'interface graphique NSSM qui s'ouvre :
   - **Path** : Sélectionnez le chemin de votre exécutable PHP (ex: `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe`).
   - **Startup directory** : Le chemin racine du projet (ex: `C:\laragon\www\water`).
   - **Arguments** : `workers/mqtt_worker.php`.
4. Cliquez sur **Install service**.
5. Démarrez le service :
   ```cmd
   nssm start WaterMqttWorker
   ```

---

## 5. Nouveaux Endpoints de l'API Web Dashboard

Ces endpoints sont sécurisés et réservés aux administrateurs authentifiés.

### 5.1. Émission d'une Commande de Pompe (`api/bornes/commande` — Fallback / API REST)

Note : Cet endpoint est conservé comme canal de secours HTTP ou pour les intégrations tierces. Le dashboard principal utilise désormais directement le protocole WebSockets via MQTT pour émettre les commandes de pompe sans latence.

* **Route :** `api/bornes/commande`
* **Méthode HTTP :** `POST`
* **Format Request Body (JSON) :**
  ```json
  {
    "borne_id": 1,
    "type_commande": "start_pompe"
  }
  ```
  *(Valeurs acceptées pour `type_commande` : `"start_pompe"`, `"stop_pompe"`)*

* **Réponse de Succès (HTTP 200) :**
  ```json
  {
    "status": "success",
    "message": "Commande 'start_pompe' enregistrée. Le worker MQTT va la transmettre sous peu.",
    "commande_id": 15
  }
  ```

---

### 5.2. Récupération du Statut en Temps Réel (`api/bornes/status-live`)

Retourne l'état courant de toutes les bornes et la liste des alertes actives non résolues. Cet endpoint est interrogé par le dashboard toutes les 3 secondes.

* **Route :** `api/bornes/status-live`
* **Méthode HTTP :** `GET`

* **Réponse de Succès (HTTP 200) :**
  ```json
  {
    "status": "success",
    "bornes": [
      {
        "id": 1,
        "nom": "Borne Nord - DN100",
        "statut_connexion": "en ligne",
        "etat_vanne": "fermee",
        "debit_instantane": 0.0,
        "capteur_fuite": false,
        "capteur_trop_plein": false,
        "derniere_telemerie_mqtt": "2026-06-25 23:01:45",
        "alertes_actives": 0,
        "mqtt_connecte": true,
        "dernier_ping": "2026-06-25 23:01:40"
      }
    ],
    "alertes": [
      {
        "id": 8,
        "borne_id": 1,
        "borne_nom": "Borne Nord - DN100",
        "type_alerte": "fuite",
        "description": "Détection d'une fuite sol au démarrage",
        "source": "mqtt",
        "date_heure": "2026-06-25 23:01:10"
      }
    ],
    "ts": "2026-06-25T23:01:50+01:00"
  }
  ```

---

## 6. Procédure de Test de Bout en Bout

Pour valider l'ensemble du système de communication :

### Étape 1 : Lancement du Broker et du Worker
1. Assurez-vous que le service **Mosquitto** est démarré.
2. Lancez le worker en ligne de commande : `php workers/mqtt_worker.php`.

### Étape 2 : Simulation de l'ESP32 (avec un client MQTT tiers)
Si vous ne disposez pas de l'ESP32 physique branché en USB, vous pouvez simuler son comportement à l'aide d'outils comme **MQTT Explorer** ou les commandes Mosquitto en CLI.

* **Simuler l'envoi de télémétrie périodique (Toutes les 3s)** :
  Publiez sur le topic `eau/bornes/1/etat` le JSON suivant :
  ```json
  {"borne_id":1,"etat_vanne":"fermee","debit":0.0,"fuite":false,"trop_plein":false,"rssi":-60}
  ```
  *Vérification* : Dans le dashboard d'administration, la borne #1 doit passer au statut **"MQTT connecté"** et **"Vanne fermée"** avec un débit de `0.0 L/min`.

* **Simuler le déclenchement d'une alerte de fuite** :
  Publiez sur le topic `eau/bornes/1/alerte` :
  ```json
  {"borne_id":1,"type":"fuite","description":"Détection d'écoulement anormal"}
  ```
  *Vérification* : Le dashboard affiche immédiatement un encadré rouge clignotant signalant l'alerte active, et le journal des alertes s'incrémente d'une ligne d'origine **"MQTT"**.

### Étape 3 : Test de commande depuis le Dashboard
1. Sur le dashboard de supervision, cliquez sur le bouton **"Démarrer"** de la borne #1.
2. *Vérification* :
   - Le dashboard publie instantanément un message JSON `{ "type": "start_pompe", ... }` sur le topic `eau/bornes/1/commande` via le protocole WebSockets.
   - L'ESP32 (ou votre outil de simulation abonné) reçoit immédiatement cette commande et l'exécute.
   - Le worker PHP (qui est abonné au topic `eau/bornes/+/commande`) intercepte la commande à des fins d'historique et l'enregistre en base de données dans la table `commandes_bornes` avec le statut `envoyee`.
