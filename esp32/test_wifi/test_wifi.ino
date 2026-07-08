#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SH110X.h>
#include <SPI.h>
#include <MFRC522.h>

#define WIFI_NETWORK "TP-Link_71FA"
#define WIFI_PASSWORD "46116147"
#define WIFI_TIMEOUT_MS 20000

// --- OLED 1.3" SH1106 ---
Adafruit_SH1106G display(128, 64, &Wire, -1);

// --- RC522 ---
#define SS_PIN  5
#define RST_PIN 15
MFRC522 rfid(SS_PIN, RST_PIN);

// --- Serveur (adapte le chemin selon ta structure) ---
const char* SERVER_BASE = "http://192.168.1.156/water/public/index.php";
const int   BORNE_ID    = 1;

// ============================================================
// OLED
// ============================================================
void afficherEcran(String l1, String l2 = "", String l3 = "") {
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SH110X_WHITE);
  display.setCursor(0, 0);
  display.println(l1);
  if (l2 != "") display.println(l2);
  if (l3 != "") display.println(l3);
  display.display();
}

// ============================================================
// WiFi
// ============================================================
void connectToWifi() {
  Serial.print("Connecting to WiFi");
  afficherEcran("Connexion WiFi...", WIFI_NETWORK);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_NETWORK, WIFI_PASSWORD);

  unsigned long startAttemptTime = millis();

  while (WiFi.status() != WL_CONNECTED &&
         millis() - startAttemptTime < WIFI_TIMEOUT_MS) {
    Serial.print(".");
    delay(100);
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println(" Failed!");
    afficherEcran("WiFi: ECHEC", "Verifie SSID", "et mot de passe");
  } else {
    Serial.print("Connected! IP: ");
    Serial.println(WiFi.localIP());
    afficherEcran("WiFi: CONNECTE", "IP:", WiFi.localIP().toString());
    delay(1500);
  }
}

// ============================================================
// Vérification de la carte auprès du serveur (retourne le nom du client)
// ============================================================
void verifierCarte(String uid) {
  afficherEcran("Carte scannee", uid, "Verification...");

  if (WiFi.status() != WL_CONNECTED) {
    afficherEcran("Erreur", "Pas de WiFi");
    return;
  }

  HTTPClient http;
  String url = String(SERVER_BASE) + "?route=api/cards/verify&uid=" + uid + "&borne_id=" + BORNE_ID;
  http.begin(url);
  http.setTimeout(8000);

  int code = http.GET();

  if (code > 0) {
    String payload = http.getString();
    Serial.println("Reponse: " + payload);

    StaticJsonDocument<512> doc;
    DeserializationError error = deserializeJson(doc, payload);

    if (!error) {
      const char* status = doc["status"];

      if (String(status) == "success") {
        const char* prenom = doc["card"]["farmer_prenom"];
        const char* nom = doc["card"]["farmer_nom"];
        float solde = doc["card"]["solde"];

        Serial.println("Client: " + String(prenom) + " " + String(nom));

        afficherEcran(String(prenom) + " " + String(nom),
                      "Solde: " + String(solde, 3) + " DT",
                      "Bienvenue !");
      } else {
        const char* message = doc["message"] | "Carte refusee";
        afficherEcran("Carte REFUSEE", String(message));
      }
    } else {
      afficherEcran("Erreur", "Reponse invalide");
    }
  } else {
    Serial.println("Erreur HTTP: " + String(code));
    afficherEcran("Erreur serveur", "Code: " + String(code));
  }

  http.end();
}

// ============================================================
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(500);

  // OLED
  if (!display.begin(0x3C, true)) {
    Serial.println("Erreur init ecran OLED !");
  }

  // WiFi
  connectToWifi();

  // RFID
  SPI.begin();
  rfid.PCD_Init();

  afficherEcran("Systeme pret", "Approchez carte");
  Serial.println("Systeme pret. Approchez une carte RFID...");
}

// ============================================================
// LOOP
// ============================================================
void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectToWifi();
  }

  if (!rfid.PICC_IsNewCardPresent()) return;
  if (!rfid.PICC_ReadCardSerial()) return;

  // Construire l'UID (sans espaces, majuscules)
  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();

  Serial.println("\n--- Carte scannee ---");
  Serial.println("UID: " + uid);

  verifierCarte(uid);

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  delay(3000); // laisse le message affiche 3s avant de revenir a l'accueil
  afficherEcran("Systeme pret", "Approchez carte");
}
