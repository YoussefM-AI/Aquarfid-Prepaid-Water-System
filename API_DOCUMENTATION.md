# Documentation des API pour les Bornes de Distribution d'Eau (ESP32)

Cette documentation décrit les points d'accès (endpoints) API exposés par le serveur backend pour permettre aux bornes intelligentes (basées sur des microcontrôleurs ESP32 ou automates PLC) de communiquer avec l'application centrale.

---

## Informations Générales

* **Base URL :** `http://<ip_serveur_ou_domaine>/index.php`
* **Format des données :** `JSON` (Toutes les réponses de l'API sont retournées avec le header `Content-Type: application/json`).
* **Méthodes d'authentification des requêtes :** Les paramètres peuvent être passés en **GET** (dans l'URL), en **POST** (form-urlencoded) ou sous forme de corps **JSON brut** (`application/json`).
* **Format de l'UID :** Les UID des cartes Mifare doivent être envoyés sous forme de chaînes hexadécimales en un seul bloc (ex: `04A32B125C6D80`). Ils sont insensibles à la casse côté serveur.

---

## Liste des Endpoints

### 1. Vérification de Carte RFID (`api/cards/verify`)

Cet endpoint permet d'authentifier une carte RFID scannée sur une borne, de valider l'état du compte de l'agriculteur et de récupérer le solde disponible ainsi que le tarif de l'eau.

* **Route :** `api/cards/verify`
* **Méthode HTTP recommandée :** `POST` ou `GET`
* **Paramètres de requête :**
  | Paramètre | Type | Requis | Description |
  | :--- | :--- | :--- | :--- |
  | `uid` | String | Oui | UID hexadécimal de la carte RFID. |
  | `borne_id` | Integer | Non | ID de la borne émettrice. Si fourni, met à jour le statut de connexion de la borne à "en ligne". |

#### Exemple de Requête (JSON) :
```json
{
  "uid": "04A32B125C6D80",
  "borne_id": 1
}
```

#### Réponse de Succès (HTTP 200) :
```json
{
  "status": "success",
  "message": "Carte validée avec succès.",
  "card": {
    "uid": "04A32B125C6D80",
    "solde": 150.000,
    "farmer_nom": "Ben Ali",
    "farmer_prenom": "Mohamed"
  },
  "price_per_litre": 0.200,
  "max_volume_litres": 750.00
}
```

#### Réponses d'Erreurs :
* **Carte manquante dans la requête (HTTP 400) :**
  ```json
  {
    "status": "error",
    "message": "L'UID de la carte est obligatoire."
  }
  ```
* **Carte introuvable dans la base (HTTP 404) :**
  ```json
  {
    "status": "error",
    "message": "Carte RFID introuvable dans le système."
  }
  ```
* **Carte bloquée / suspendue (HTTP 400) :**
  ```json
  {
    "status": "error",
    "message": "Cette carte RFID est bloquée ou marquée comme perdue.",
    "card_status": "suspendue"
  }
  ```
* **Compte agriculteur suspendu (HTTP 400) :**
  ```json
  {
    "status": "error",
    "message": "Le compte de l'agriculteur associé à cette carte est inactif."
  }
  ```

---

### 2. Enregistrement de Transaction & Débit (`api/transactions/store`)

À la fin de la distribution d'eau (quand la vanne est fermée et que le débitmètre a calculé la quantité consommée), cet endpoint permet de débiter le solde de la carte RFID et d'archiver la transaction.

* **Route :** `api/transactions/store`
* **Méthode HTTP requise :** `POST`
* **Paramètres de requête :**
  | Paramètre | Type | Requis | Description |
  | :--- | :--- | :--- | :--- |
  | `uid` | String | Oui | UID hexadécimal de la carte RFID. |
  | `borne_id` | Integer | Oui | ID de la borne sur laquelle s'est faite la distribution. |
  | `volume_demande` | Float | Oui | Volume initialement demandé (en Litres). |
  | `volume_distribue` | Float | Oui | Volume réellement distribué (en Litres). |
  | `statut` | String | Non | Statut final de l'opération (`reussie`, `interrompue_erreur`, `annulee`). Par défaut : `reussie`. |

#### Exemple de Requête (JSON) :
```json
{
  "uid": "04A32B125C6D80",
  "borne_id": 1,
  "volume_demande": 100.00,
  "volume_distribue": 98.50,
  "statut": "reussie"
}
```

#### Réponse de Succès (HTTP 200) :
```json
{
  "status": "success",
  "message": "Transaction enregistrée avec succès.",
  "montant_debite": 19.700,
  "nouveau_solde": 130.300
}
```

#### Réponses d'Erreurs :
* **Paramètres manquants ou erronés (HTTP 400) :**
  ```json
  {
    "status": "error",
    "message": "Paramètres invalides ou manquants (uid, borne_id, volume_distribue)."
  }
  ```
* **Solde insuffisant pour le débit (HTTP 400) :**
  ```json
  {
    "status": "error",
    "message": "Échec du débit : Solde insuffisant pour la distribution demandée (19.700 DT requis, 10.000 DT disponibles)."
  }
  ```

---

### 3. Signalement d'Alerte Technique (`api/alertes/store`)

Permet à l'ESP32 de signaler au serveur les pannes physiques détectées (fuite d'eau, capteur défaillant, problème de vanne, etc.).

* **Route :** `api/alertes/store`
* **Méthode HTTP requise :** `POST`
* **Paramètres de requête :**
  | Paramètre | Type | Requis | Description |
  | :--- | :--- | :--- | :--- |
  | `borne_id` | Integer | Oui | ID de la borne émettant l'alerte. |
  | `type_alerte` | String | Oui | Type d'incident (`fuite`, `trop_plein`, `manque_eau`, `com_perdue`). |
  | `description` | String | Oui | Message descriptif expliquant l'incident de terrain. |

#### Exemple de Requête (JSON) :
```json
{
  "borne_id": 1,
  "type_alerte": "fuite",
  "description": "Détection d'un écoulement continu après fermeture de l'électrovanne."
}
```

#### Réponse de Succès (HTTP 200) :
```json
{
  "status": "success",
  "message": "Alerte enregistrée avec succès."
}
```

---

### 4. Signal Keep-Alive de Borne (`api/bornes/ping`)

Signal périodique envoyé par l'ESP32 pour indiquer au serveur qu'il est connecté et fonctionne normalement (Supervision de la connectivité).

* **Route :** `api/bornes/ping`
* **Méthode HTTP recommandée :** `POST` ou `GET`
* **Paramètres de requête :**
  | Paramètre | Type | Requis | Description |
  | :--- | :--- | :--- | :--- |
  | `borne_id` | Integer | Oui | ID de la borne effectuant le ping. |

#### Exemple de Requête (JSON) :
```json
{
  "borne_id": 1
}
```

#### Réponse de Succès (HTTP 200) :
```json
{
  "status": "success",
  "message": "Ping reçu avec succès. Statut mis à jour."
}
```

---

## Exemple d'implémentation côté ESP32 (C++/Arduino)

Voici un exemple simple de fonction en C++ pour l'ESP32 permettant de valider une carte RFID en envoyant une requête HTTP POST :

```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

const char* serverUrl = "http://192.168.1.100/index.php?route=api/cards/verify";
const int borneId = 1; // ID de la borne courante

struct VerificationResult {
    bool valid;
    float solde;
    float pricePerLitre;
    float maxVolume;
    String message;
};

VerificationResult verifyRFIDCard(String uid) {
    VerificationResult result = {false, 0.0, 0.0, 0.0, ""};
    
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin(serverUrl);
        http.addHeader("Content-Type", "application/json");
        
        // Préparation du corps JSON
        StaticJsonDocument<200> doc;
        doc["uid"] = uid;
        doc["borne_id"] = borneId;
        
        String requestBody;
        serializeJson(doc, requestBody);
        
        int httpResponseCode = http.POST(requestBody);
        
        if (httpResponseCode > 0) {
            String response = http.getString();
            
            StaticJsonDocument<500> responseDoc;
            DeserializationError error = deserializeJson(responseDoc, response);
            
            if (!error) {
                String status = responseDoc["status"];
                if (status == "success") {
                    result.valid = true;
                    result.solde = responseDoc["card"]["solde"];
                    result.pricePerLitre = responseDoc["price_per_litre"];
                    result.maxVolume = responseDoc["max_volume_litres"];
                    result.message = "Carte validée";
                } else {
                    result.message = responseDoc["message"].as<String>();
                }
            } else {
                result.message = "Erreur de décodage JSON";
            }
        } else {
            result.message = "Erreur HTTP : " + String(httpResponseCode);
        }
        http.end();
    } else {
        result.message = "WiFi non connecté";
    }
    
    return result;
}
```
