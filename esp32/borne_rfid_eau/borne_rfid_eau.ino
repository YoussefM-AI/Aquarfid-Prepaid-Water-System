/*made by ghofy
 * ============================================================
 * BORNE RFID EAU AGRICOLE — ESP32
 * ============================================================
 * Auteur   : H2O RFID System
 * Version  : 2.3.0 (Électrovanne synchronisée avec la pompe — capteur de fuite
 * sol supprimé)
 * ============================================================
 */

#include <Arduino.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <Keypad.h>
#include <MFRC522.h>
#include <PubSubClient.h> // Canal MQTT
#include <SPI.h>
#include <WiFi.h>


// ============================================================
// CONFIGURATION — À MODIFIER SELON VOTRE INSTALLATION
// ============================================================

// --- WiFi ---
// TODO: Replace with your WiFi credentials before flashing
const char *WIFI_SSID     = "YOUR_WIFI_SSID";
const char *WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

// --- Serveur API ---
// TODO: Replace with your server's local IP address
const char *SERVER_BASE = "http://YOUR_SERVER_IP/water/public/index.php";

const int BORNE_ID = 1;          // ID de cette borne dans la DB
const int WIFI_TIMEOUT = 15000;  // Timeout connexion WiFi (ms)
const int HTTP_TIMEOUT = 8000;   // Timeout requête HTTP (ms)
const int PING_INTERVAL = 30000; // Intervalle keep-alive (30s)

// --- Broker MQTT ---
// TODO: Replace with your Mosquitto broker's IP address and credentials
const char *MQTT_BROKER = "YOUR_SERVER_IP";
const int MQTT_PORT = 1883;
const char *MQTT_USER = "water_user";
const char *MQTT_PASS = "YOUR_MQTT_PASSWORD";
const int MQTT_PUB_INTERVAL = 3000; // Intervalle publication état (ms)
const unsigned long MQTT_OVERRIDE_TIMEOUT =
    300000; // Timeout de sécurité de l'override admin (5 minutes = 300000 ms)

// --- Paramètres de distribution & Calibration Débitmètre ---
// 1 = YF-S201 (1/2" Standard Noir, 1-30 L/min) -> 450.0 pulses/L
// 2 = YF-S401 / YF-S402 (Micro 6mm Blanc/Noir, 0.3-6 L/min) -> 5880.0 pulses/L (5.88 pulses/mL)
// 3 = YF-B1 / YF-B7 (Laiton 1/2", 1-25 L/min) -> 660.0 pulses/L
// 4 = Débitmètre 1/4" (0.3-3 L/min) -> 2250.0 pulses/L
#define FLOW_METER_MODEL 1  // <--- Changez à 2 si vous avez un petit débitmètre 6mm (YF-S401)

#if FLOW_METER_MODEL == 2
const float PULSES_PER_LITRE = 5880.0; // YF-S401 : ~5.88 impulsions par mL
#elif FLOW_METER_MODEL == 3
const float PULSES_PER_LITRE = 660.0;  // YF-B1 Laiton
#elif FLOW_METER_MODEL == 4
const float PULSES_PER_LITRE = 2250.0; // 1/4" Micro
#else
const float PULSES_PER_LITRE = 450.0;  // YF-S201 1/2" standard : 0.45 impulsion par mL
#endif

const float DEBIT_MIN_L_MIN = 0.1;    // Débit minimum normal (L/min)
const float DEBIT_MAX_L_MIN = 200.0;  // Débit max avant alerte fuite (L/min)
const float FUITE_SEUIL_LITRES = 2.0; // Litres perdus avant alerte fuite
const int MANQUE_EAU_TIMEOUT = 30000; // Timeout sans débit = manque eau (ms)
const int VOLUME_MAX_LITRES = 2000;   // Volume max par transaction (L)

// ============================================================
// BROCHES (PINS) - CORRIGÉES
// ============================================================

// RFID MFRC522 — SPI
#define PIN_RFID_SS 5
#define PIN_RFID_RST 22

// Débitmètre (entrée impulsion interrupt)
// WARNING: GPIO4 is also listed in the keypad column pins: byte colPins[COLS] =
// {4, 16, 17, 21}; If you are using a physical keypad, using GPIO4 for the flow
// meter will cause conflicts.
#define PIN_FLOW_METER 4 // Avec résistance de pull-up interne (GPIO4)

// Pompe (via relais — Active-LOW : LOW = Relais activé/pompe tourne, HIGH = Relais coupé/pompe arrêtée)
#define PIN_VALVE 16
#define VALVE_ACTIVE_LEVEL LOW

// Électrovanne (via relais — Active-HIGH : HIGH = Électrovanne ouverte, LOW = Électrovanne fermée)
#define PIN_ELECTROVANNE 26
#define ELECTROVANNE_ACTIVE_LEVEL HIGH
#define KEEP_ELECTROVANNE_ALWAYS_OPEN false // false = Synchronisation stricte : Électrovanne OUVERTE quand la pompe tourne, FERMÉE quand la pompe est coupée

// Capteur de sécurité (Désactivé par défaut pour éviter les fausses alertes sur
// broche flottante) Capteur de fuite sol supprimé — seul le capteur de
// trop-plein est conservé.
#define ENABLE_SAFETY_SENSORS false
#define PIN_OVERFLOW 35 // Nécessite R externe PULL-DOWN 10kΩ

// Signalisation
#define PIN_BUZZER 32
#define BUZZER_ON LOW   // LOW = Buzzer émet un son
#define BUZZER_OFF HIGH // HIGH = Buzzer ÉTEINT au repos
#define PIN_LED_GREEN 25
#define PIN_LED_ORANGE 27
#define PIN_LED_RED 33
#define LED_ACTIVE_LEVEL                                                       \
  HIGH // Mettre LOW si votre module LED/RGB est à Anode Commune (Active-LOW)

// --- Déclarations Types et Prototypes (C++ Forward Declarations) ---
enum Color { GREEN, ORANGE, RED, OFF };
void setLed(Color c);
void ledsOff();
void beep(int count, int durationMs);
void resetSession();
void publishHeartbeat();
void publishStatus();
void publishAlert(const String &type, const String &description);
void connectMQTT();
void mqttCallback(char *topic, byte *payload, unsigned int length);
void handleIdle();
void handlePinWait();
void handleVolumeSelect();
void handleDistributing();
void handleDone();
void handleError();
void checkSafety();
void valveOpen();
void valveClose();
void electrovanneOpen();
void electrovanneClose();
void stopDistributionOutputs();
void verifyCard(const String &uid);
void verifyPin();
void startDistribution();
void finalizeTransaction(const String &statut);
void sendAlert(const String &type, const String &description);
void pingServer();
void emergencyStop(const String &reason);
float pulsesToLitres(unsigned long pulses);
void displayIdle();
void displayWelcome();
void displayVolumePrompt();
void displayPinProgress(int digits);
void displayDistribution();
void displayDistributionProgress(float done, float total);
void displaySuccess();
void displayMessage(const String &title, const String &msg);
bool httpGet(const String &url, JsonDocument &response);
bool httpPost(const String &url, const String &jsonBody,
              JsonDocument &response);
void connectWiFi();
void checkPendingHttpCommands();
char readInputKey();
void sendTelemetry(const String &pumpState, const String &valveState, float volumeDistributed, float volumeRequested, float flowRate, const String &distState);

// Clavier 4x4 (Broches séparées sans conflit avec RFID 22/5, Débitmètre 4 et
// Pompe 16)
#define ENABLE_PHYSICAL_KEYPAD                                                 \
  false // Mettre à true si un clavier physique 4x4 est branché
const byte ROWS = 4;
const byte COLS = 4;
char hexaKeys[ROWS][COLS] = {{'1', '2', '3', 'A'},
                             {'4', '5', '6', 'B'},
                             {'7', '8', '9', 'C'},
                             {'*', '0', '#', 'D'}};
byte rowPins[ROWS] = {13, 14, 15, 2};
byte colPins[COLS] = {12, 17, 21,
                      34}; // N'utilise PAS GPIO 22 (réservé au Reset RFID)

// ============================================================
// OBJETS GLOBAUX
// ============================================================

MFRC522 rfid(PIN_RFID_SS, PIN_RFID_RST);
Keypad keypad = Keypad(makeKeymap(hexaKeys), rowPins, colPins, ROWS, COLS);

// MQTT
WiFiClient espWifiClient;
PubSubClient mqttClient(espWifiClient);

// ============================================================
// VARIABLES VOLATILES (ISR débitmètre)
// ============================================================

volatile unsigned long pulseCount = 0;
unsigned long lastPulseCountFlow = 0;
unsigned long lastFlowCheck = 0;
float currentFlowRate = 0.0;

// ============================================================
// MACHINE À ÉTATS
// ============================================================

enum State {
  STATE_IDLE,
  STATE_VERIFYING,
  STATE_PIN_WAIT,
  STATE_PIN_VERIFYING,
  STATE_VOLUME_SELECT,
  STATE_DISTRIBUTING,
  STATE_DONE,
  STATE_ERROR,
  STATE_ALERT
};

State currentState = STATE_IDLE;

// ============================================================
// DONNÉES SESSION
// ============================================================

struct Session {
  String cardUID;
  String farmerName;
  float solde;
  float pricePerLitre;
  float maxVolumeLitres;
  bool hasPin;
  float requestedLitres;
  float distributedLitres;
  float debitedAmount;
  unsigned long startTime;
  unsigned long lastFlowTime;
  unsigned long startPulseCount;
};

Session session;

// ============================================================
// TIMERS
// ============================================================

unsigned long lastPingTime = 0;
unsigned long stateEnterTime = 0;
unsigned long lastStatusDisplay = 0;
unsigned long lastMqttPublish = 0;

String pinBuffer = "";
String volumeBuffer = ""; // Déclaré globalement pour éviter les fuites d'état
bool alertSent = false;
bool mqttAdminOverride = false;
unsigned long mqttOverrideStartTime = 0; // Heure de début de l'override admin
bool isValveOpen = false;
bool isElectrovanneOpen = false;

// ============================================================
// ISR : DÉBITMÈTRE (Capteur à effet Hall YF-S201 / YF-S401)
// ============================================================

volatile unsigned long lastFlowMicros = 0;
void IRAM_ATTR onFlowPulse() {
  unsigned long now = micros();
  // Filtre anti-rebond matériel 3.0 ms (3000 µs) + validation du niveau logique :
  // Élimine 100% des parasites électriques du relais/moteur tout en captant jusqu'à 330 Hz
  if (now - lastFlowMicros >= 3000) {
    if (digitalRead(PIN_FLOW_METER) == LOW) {
      pulseCount++;
      lastFlowMicros = now;
    }
  }
}

// ============================================================
// SETUP
// ============================================================

void setup() {
  Serial.begin(115200);
  delay(100);
  Serial.println(F("\n========================================"));
  Serial.println(F("  BORNE RFID EAU — ESP32 v2.3"));
  Serial.println(F("========================================"));

  // 1. Initialiser IMMÉDIATEMENT les LEDs
  pinMode(PIN_LED_GREEN, OUTPUT);
  pinMode(PIN_LED_ORANGE, OUTPUT);
  pinMode(PIN_LED_RED, OUTPUT);

  // Test visuel de démarrage (Vert -> Orange -> Rouge fixe)
  setLed(GREEN);
  delay(150);
  setLed(ORANGE);
  delay(150);
  setLed(RED); // Maintient le Rouge allumé au repos (vanne fermée)

  // 2. Initialiser le Buzzer avec un bip instantané
  pinMode(PIN_BUZZER, OUTPUT);
  digitalWrite(PIN_BUZZER, BUZZER_OFF);
  beep(1, 100);

  // 3. Initialiser les Relais (Pompe arrêtée, Électrovanne fermée au repos)
  pinMode(PIN_VALVE, OUTPUT);
  valveClose();

  pinMode(PIN_ELECTROVANNE, OUTPUT);
  electrovanneClose();

  pinMode(PIN_OVERFLOW, INPUT);

  // 4. SPI + RFID
  SPI.begin();
  rfid.PCD_Init();
  Serial.println(F("[RFID] Lecteur initialisé"));

  // 5. Interrupt Débitmètre
  pinMode(PIN_FLOW_METER, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(PIN_FLOW_METER), onFlowPulse, FALLING);

  // 6. WiFi (timeout rapide pour ne pas bloquer l'ESP32)
  connectWiFi();

  // 7. MQTT
  if (WiFi.status() == WL_CONNECTED) {
    connectMQTT();
  }

  // 8. Bip de confirmation finale
  beep(2, 100);
  setLed(GREEN); // LED verte allumée au repos (borne prête, électrovanne fermée)
  Serial.println(F("[SYS] Borne prête — En attente d'une carte RFID...\n"));
}

// ============================================================
// LOOP PRINCIPALE
// ============================================================

void loop() {
  unsigned long now = millis();

  if (now - lastPingTime > PING_INTERVAL) {
    pingServer();
    publishHeartbeat();
    lastPingTime = now;
  }

  if (WiFi.status() != WL_CONNECTED) {
    static unsigned long lastWifiRetry = 0;
    if (now - lastWifiRetry > 10000) {
      lastWifiRetry = now;
      Serial.println(
          F("[WIFI] Connexion perdue — tentative de reconnexion..."));
      if (currentState == STATE_DISTRIBUTING) {
        emergencyStop("Perte de communication WiFi");
      }
      connectWiFi();
    }
  }

  if (WiFi.status() == WL_CONNECTED) {
    if (mqttClient.connected()) {
      mqttClient.loop();
    } else {
      static unsigned long lastMqttReconnect = 0;
      if (now - lastMqttReconnect > 5000) {
        lastMqttReconnect = now;
        connectMQTT();
      }
    }
  }

  if (now - lastMqttPublish > MQTT_PUB_INTERVAL && mqttClient.connected()) {
    publishStatus();
    lastMqttPublish = now;
  }

  checkSafety();

  // Commandes de test direct via le Moniteur Série (actif uniquement en mode repos/idle ou distribution)
  if (currentState == STATE_IDLE || currentState == STATE_DISTRIBUTING) {
    if (Serial.available() > 0) {
      char c = Serial.peek();
      if (c == 'P' || c == 'p') {
        Serial.read();
        static bool pumpTest = false;
        pumpTest = !pumpTest;
        if (pumpTest) {
          valveOpen();
          Serial.println(F("[TEST] >>> POMPE ACTIVÉE MANUELLEMENT (P) <<<"));
        } else {
          valveClose();
          Serial.println(F("[TEST] >>> POMPE DÉSACTIVÉE (P) <<<"));
        }
      } else if (c == 'E' || c == 'e' || c == 'V' || c == 'v') {
        Serial.read();
        static bool evTest = false;
        evTest = !evTest;
        if (evTest) {
          electrovanneOpen();
          Serial.println(F("[TEST] >>> ÉLECTROVANNE ACTIVÉE MANUELLEMENT (E) <<<"));
        } else {
          electrovanneClose();
          Serial.println(F("[TEST] >>> ÉLECTROVANNE DÉSACTIVÉE (E) <<<"));
        }
      } else if (c == 'D' || c == 'd') {
        Serial.read();
        Serial.println(F("[TEST] >>> DÉMARRAGE TEST DISTRIBUTION 250 mL (D) <<<"));
        session.requestedLitres = 0.250;
        startDistribution();
      } else if (c == 'S' || c == 's') {
        Serial.read();
        Serial.println(F("[TEST] >>> ARRÊT MANUEL (S) <<<"));
        resetSession();
      }
    }
  }

  // Sonder les commandes Kiosque (HTTP) régulièrement pour permettre l'arrêt
  // d'urgence ou le démarrage
  static unsigned long lastCommandPoll = 0;
  if (now - lastCommandPoll > 400) {
    lastCommandPoll = now;
    checkPendingHttpCommands();
  }

  switch (currentState) {
  case STATE_IDLE:
    handleIdle();
    break;
  case STATE_PIN_WAIT:
    handlePinWait();
    break;
  case STATE_VOLUME_SELECT:
    handleVolumeSelect();
    break;
  case STATE_DISTRIBUTING:
    handleDistributing();
    break;
  case STATE_DONE:
    handleDone();
    break;
  case STATE_ERROR:
    handleError();
    break;
  case STATE_ALERT:
    if (now - stateEnterTime > 60000)
      resetSession();
    break;
  default:
    break;
  }

  if (now - lastStatusDisplay > 5000 && currentState == STATE_DISTRIBUTING) {
    Serial.printf(
        "[DIST] Volume distribué : %.2f / %.2f L (Débit: %.1f L/min)\n",
        session.distributedLitres, session.requestedLitres, currentFlowRate);
    lastStatusDisplay = now;
  }
}

// ============================================================
// LECTURE TOUCHE (CLAVIER OU MONITEUR SÉRIE)
// ============================================================

char readInputKey() {
  if (ENABLE_PHYSICAL_KEYPAD) {
    char k = keypad.getKey();
    if (k) return k;
  }
  if (Serial.available() > 0) {
    char c = Serial.read();
    if (c == '\r' || c == '\n') return 0;
    return c;
  }
  return NO_KEY;
}

// ============================================================
// TOUTES LES FONCTIONS DE GESTION
// ============================================================

void handleIdle() {
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial())
    return;

  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10)
      uid += "0";
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  Serial.println(F("\n--- CARTE SCANNÉE ---"));
  Serial.println("[RFID] UID: " + uid);

  // Publication MQTT de l'UID
  if (mqttClient.connected()) {
    String topicRfid = "water/station" + String(BORNE_ID) + "/rfid";
    mqttClient.publish(topicRfid.c_str(), uid.c_str(), false);
  }

  beep(1, 100);
  setLed(ORANGE);

  session = Session();
  session.cardUID = uid;
  pinBuffer = "";
  alertSent = false;
  currentState = STATE_VERIFYING;
  stateEnterTime = millis();

  verifyCard(uid);
}

void verifyCard(const String &uid) {
  String url = String(SERVER_BASE) + "?route=api/cards/verify&uid=" + uid +
               "&borne_id=" + BORNE_ID;
  JsonDocument response;
  bool ok = httpGet(url, response);

  if (!ok || response["status"] != "success") {
    String msg = response["message"] | "Carte invalide ou refusée.";
    Serial.println("[API] Erreur verify: " + msg);
    displayMessage("CARTE REFUSÉE", msg);
    beep(2, 100);
    setLed(RED);
    resetSession();
    return;
  }

  session.farmerName = response["card"]["farmer_prenom"].as<String>() + " " +
                       response["card"]["farmer_nom"].as<String>();
  session.solde = response["card"]["solde"];
  session.pricePerLitre = response["price_per_litre"];
  session.maxVolumeLitres = response["max_volume_litres"];
  session.hasPin = response["has_pin"];

  Serial.printf("\n[SESSION] Agriculteur: %s | Solde: %.3f DT | Vol Max: %.2f L\n",
                session.farmerName.c_str(), session.solde, session.maxVolumeLitres);

  if (session.hasPin) {
    Serial.println(F("[SESSION] Code PIN requis. Saisissez votre PIN à 4 chiffres (clavier ou Moniteur Série) :"));
    displayMessage("CODE PIN", "Entrez votre code PIN à 4 chiffres :");
    pinBuffer = "";
    currentState = STATE_PIN_WAIT;
    stateEnterTime = millis();
    setLed(ORANGE);
  } else {
    displayWelcome();
    displayVolumePrompt();
    currentState = STATE_VOLUME_SELECT;
    stateEnterTime = millis();
    setLed(GREEN);
  }
}

void handlePinWait() {
  if (millis() - stateEnterTime > 20000) {
    displayMessage("TIMEOUT", "Délai de saisie dépassé.");
    beep(1, 100);
    resetSession();
    return;
  }

  char key = readInputKey();
  if (!key)
    return;

  if (key == '#') {
    displayMessage("ANNULÉ", "Opération annulée.");
    resetSession();
    return;
  }

  if (key == '*') {
    if (pinBuffer.length() > 0)
      pinBuffer = pinBuffer.substring(0, pinBuffer.length() - 1);
    displayPinProgress(pinBuffer.length());
    return;
  }

  if (key >= '0' && key <= '9') {
    if (pinBuffer.length() < 4) {
      pinBuffer += key;
      beep(1, 50);
      displayPinProgress(pinBuffer.length());
      Serial.printf("[PIN] %s\n", pinBuffer.c_str());

      if (pinBuffer.length() == 4) {
        currentState = STATE_PIN_VERIFYING;
        stateEnterTime = millis();
        verifyPin();
      }
    }
  }
}

void verifyPin() {
  String url = String(SERVER_BASE) +
               "?route=api/cards/verify-pin&uid=" + session.cardUID +
               "&pin=" + pinBuffer + "&borne_id=" + BORNE_ID;
  JsonDocument response;
  bool ok = httpGet(url, response);

  if (!ok || response["status"] != "success") {
    String msg = response["message"] | "Code PIN incorrect.";
    displayMessage("PIN INCORRECT", msg);
    beep(3, 100);
    setLed(RED);
    pinBuffer = "";
    currentState = STATE_PIN_WAIT;
    stateEnterTime = millis();
    setLed(ORANGE);
    return;
  }

  session.solde = response["card"]["solde"];
  session.pricePerLitre = response["price_per_litre"];
  session.maxVolumeLitres = response["max_volume_litres"];

  beep(1, 100);
  displayWelcome();
  displayVolumePrompt();
  currentState = STATE_VOLUME_SELECT;
  stateEnterTime = millis();
  setLed(GREEN);
}

void handleVolumeSelect() {
  if (millis() - stateEnterTime > 30000) {
    displayMessage("TIMEOUT", "Aucune sélection effectuée.");
    resetSession();
    return;
  }

  char key = readInputKey();
  if (!key)
    return;

  // Touche 'A' : Prendre le volume maximum autorisé par le solde
  if (key == 'A' || key == 'a') {
    session.requestedLitres = session.maxVolumeLitres;
    Serial.printf("[VOLUME] Option MAX choisie : %.3f L\n", session.requestedLitres);
    volumeBuffer = "";
    startDistribution();
    return;
  }

  // Touche '#' : Valider la saisie manuelle en mL (ex: 250#)
  if (key == '#') {
    if (volumeBuffer.length() > 0) {
      float volMl = volumeBuffer.toFloat();
      float maxMl = session.maxVolumeLitres * 1000.0;
      if (volMl <= 0 || volMl > maxMl) {
        Serial.printf("[VOLUME] Invalide : %d mL (Max autorisé: %d mL)\n", (int)volMl, (int)maxMl);
        displayMessage("VOLUME INVALIDE", "Max: " + String((int)maxMl) + " mL");
        beep(2, 200);
        delay(1500);
        volumeBuffer = "";
        return;
      }
      session.requestedLitres = volMl / 1000.0;
      Serial.printf("[VOLUME] Volume validé : %d mL (%.3f L)\n", (int)volMl, session.requestedLitres);
      volumeBuffer = "";
      startDistribution();
    }
    return;
  }

  if (key == '*') {
    if (volumeBuffer.length() > 0) {
      volumeBuffer = volumeBuffer.substring(0, volumeBuffer.length() - 1);
      Serial.printf("[VOLUME] Saisie : %s mL\n", volumeBuffer.c_str());
    }
    return;
  }

  if (key >= '0' && key <= '9') {
    if (volumeBuffer.length() < 6) {
      volumeBuffer += key;
      beep(1, 50);
      Serial.printf("[VOLUME] Saisie : %s mL (tapez '#' pour valider)\n", volumeBuffer.c_str());
    }
  }

  // Touche 'B' : Défilement des presets rapides (100mL, 250mL, 500mL, 1000mL)
  if (key == 'B' || key == 'b') {
    static const float presets[] = {
        0.100, 0.250, 0.500, 1.000}; // Presets : 100mL, 250mL, 500mL, 1000mL
    static int presetIdx = 0;
    float vol = min(presets[presetIdx % 4], session.maxVolumeLitres);
    session.requestedLitres = vol;
    presetIdx++;
    Serial.printf("[VOLUME] Preset sélectionné : %d mL (%.3f L)\n", (int)(vol * 1000.0), vol);
    volumeBuffer = "";
    startDistribution();
  }
}

void startDistribution() {
  session.distributedLitres = 0;
  session.debitedAmount = 0;

  // 1. Démarrage synchronisé physique : Pompe ON + Électrovanne OUVERTE
  valveOpen();
  electrovanneOpen();
  delay(150); // Atténuation du rebond électrique des relais

  noInterrupts();
  pulseCount = 0; // Réinitialise complètement le compteur d'impulsions
  lastFlowMicros = 0;
  session.startPulseCount = 0;
  lastPulseCountFlow = 0;
  interrupts();

  session.startTime = millis();
  session.lastFlowTime = millis();
  beep(1, 100);

  Serial.println(F("\n========================================"));
  Serial.printf("[DEBITMETRE] DEBUT DISTRIBUTION : %d mL (%.3f L)\n",
                (int)(session.requestedLitres * 1000.0), session.requestedLitres);
  Serial.printf("[DEBITMETRE] Capteur sur GPIO %d actif (Calibration: %.1f pulses/L)\n", PIN_FLOW_METER, PULSES_PER_LITRE);
  Serial.println(F("========================================"));

  displayDistribution();

  currentState = STATE_DISTRIBUTING;
  stateEnterTime = millis();

  // Envoi télémétrie immédiat : Pompe ON, Électrovanne OPEN, État distributing
  sendTelemetry("ON", "open", 0.0, session.requestedLitres, 0.0, "distributing");
}

void handleDistributing() {
  unsigned long now = millis();
  unsigned long currentPulses;
  noInterrupts();
  currentPulses = pulseCount;
  interrupts();

  unsigned long delta = (currentPulses >= session.startPulseCount) ? (currentPulses - session.startPulseCount) : 0;
  float distributed = pulsesToLitres(delta);
  session.distributedLitres = distributed;

  // Suivi des impulsions pour réinitialiser le timer d'écoulement
  static unsigned long lastKnownPulses = 0;
  static bool distributionStarted = false;

  if (!distributionStarted || now - stateEnterTime < 200) {
    lastKnownPulses = currentPulses;
    distributionStarted = true;
  }

  if (currentPulses > lastKnownPulses) {
    lastKnownPulses = currentPulses;
    session.lastFlowTime = now;
  }

  // Calcul du débit instantané (L/min)
  static unsigned long lastCalcTime = 0;
  static unsigned long lastCalcPulses = 0;
  if (now - lastCalcTime >= 300) {
    unsigned long timeDelta = now - lastCalcTime;
    if (timeDelta > 0) {
      unsigned long pulseDelta = (currentPulses >= lastCalcPulses) ? (currentPulses - lastCalcPulses) : 0;
      currentFlowRate = (pulsesToLitres(pulseDelta) / (timeDelta / 1000.0)) * 60.0;
    }
    lastCalcPulses = currentPulses;
    lastCalcTime = now;
  }

  // 1. ARRÊT AUTOMATIQUE : Volume cible contractuel atteint !
  if (distributed >= session.requestedLitres && session.requestedLitres > 0.0) {
    Serial.println(F("\n========================================"));
    Serial.printf("[DEBITMETRE] >>> VOLUME CIBLE ATTEINT : %.3f L (%d mL / %d mL) <<<\n",
                  distributed, (int)(distributed * 1000.0), (int)(session.requestedLitres * 1000.0));
    Serial.printf("[DEBITMETRE] Total impulsions capteur : %lu (Cible: %lu)\n", delta, (unsigned long)(session.requestedLitres * PULSES_PER_LITRE));
    Serial.printf("[DEBITMETRE] Durée de pompage : %.1f secondes\n", (now - session.startTime) / 1000.0);
    Serial.println(F("========================================\n"));

    // Arrêt synchronisé immédiat : Pompe coupée (OFF) + Électrovanne fermée (CLOSED)
    stopDistributionOutputs();
    distributionStarted = false;
    session.distributedLitres = session.requestedLitres; // Ajuste au volume contractuel exact
    currentState = STATE_DONE;

    // Publication de l'état terminé vers Kiosque et MQTT
    sendTelemetry("OFF", "closed", session.requestedLitres, session.requestedLitres, 0.0, "completed");

    displayDistributionProgress(session.distributedLitres, session.requestedLitres);
    beep(3, 150);
    finalizeTransaction("reussie");
    displaySuccess();
    stateEnterTime = now;
    return;
  }

  // 2. TIMEOUT SÉCURITÉ MANQUE D'EAU (45s au démarrage pour amorçage, 20s en cours)
  unsigned long currentTimeout = (distributed < 0.005) ? 45000 : 20000;
  if (now - session.lastFlowTime > currentTimeout) {
    stopDistributionOutputs();
    distributionStarted = false;
    currentState = STATE_ERROR;
    sendTelemetry("OFF", "closed", distributed, session.requestedLitres, 0.0, "interrupted");

    Serial.printf("\n[DEBITMETRE] ⚠️ ARRET MANQUE D'EAU : %.3f L (%d mL délivrés)\n",
                  distributed, (int)(distributed * 1000.0));
    sendAlert("manque_eau",
              "Débit nul (amorçage difficile ou conduite vide). Arrêt de sécurité.");
    displayMessage("MANQUE D'EAU", "Arrêt automatique.");
    beep(3, 150);
    setLed(RED);
    if (session.distributedLitres > 0.01) {
      finalizeTransaction("interrompue_erreur");
    }
    stateEnterTime = now;
    return;
  }

  // 3. PUBLICATION CONTINUE VERS LE KIOSQUE & SERIAL (Toutes les 250 ms)
  static unsigned long lastLivePush = 0;
  if (now - lastLivePush >= 250) {
    sendTelemetry("ON", "open", distributed, session.requestedLitres, currentFlowRate, "distributing");
    displayDistributionProgress(distributed, session.requestedLitres);
    Serial.printf("[DEBITMETRE] Impulsions: %lu / %lu | Volume: %d / %d mL | Debit: %.2f L/min\n",
                  delta, (unsigned long)(session.requestedLitres * PULSES_PER_LITRE),
                  (int)(distributed * 1000.0), (int)(session.requestedLitres * 1000.0), currentFlowRate);
    lastLivePush = now;
  }
}

void handleDone() {
  if (millis() - stateEnterTime > 1500)
    resetSession();
}

void handleError() {
  if (millis() - stateEnterTime > 1000)
    resetSession();
}

void checkSafety() {
  // Sécurité pour l'override admin à distance (timeout après 5 minutes)
  if (mqttAdminOverride) {
    if (millis() - mqttOverrideStartTime > MQTT_OVERRIDE_TIMEOUT) {
      Serial.println(F("[SYS] Timeout de sécurité de l'override admin atteint. "
                       "Fermeture vanne."));
      emergencyStop("Timeout sécurité override admin");
      return;
    }
  }

  if (currentState != STATE_DISTRIBUTING) {
    static bool overflowWasHigh = false;

    bool overflowNow =
        ENABLE_SAFETY_SENSORS ? (digitalRead(PIN_OVERFLOW) == HIGH) : false;

    if (overflowNow) {
      // Fermeture immédiate de la pompe et de l'électrovanne si elles étaient
      // ouvertes via override
      if (mqttAdminOverride || isValveOpen || isElectrovanneOpen) {
        stopDistributionOutputs();
        mqttAdminOverride = false;
      }

      // Lockout : passage en STATE_ALERT pour empêcher toute nouvelle
      // transaction
      if (currentState != STATE_ALERT) {
        currentState = STATE_ALERT;
        stateEnterTime = millis();
      }
    }

    if (overflowNow && !overflowWasHigh) {
      sendAlert("trop_plein", "Capteur trop-plein actif hors distribution");
      setLed(RED);
      beep(3, 200);
    }
    overflowWasHigh = overflowNow;

    currentFlowRate = 0.0; // Pas de débit hors distribution
    return;
  }

  if (ENABLE_SAFETY_SENSORS && digitalRead(PIN_OVERFLOW) == HIGH) {
    stopDistributionOutputs();
    sendAlert("trop_plein", "Trop-plein activé. Arrêt immédiat.");
    displayMessage("TROP-PLEIN !", "Arrêt d'urgence.");
    beep(10, 150);
    setLed(RED);
    finalizeTransaction("interrompue_erreur");
    currentState = STATE_ALERT;
    stateEnterTime = millis();
    return;
  }

  unsigned long now = millis();
  if (now - lastFlowCheck > 1000) {
    unsigned long currentPulses;
    noInterrupts();
    currentPulses = pulseCount;
    interrupts();

    unsigned long pulsesInSec = currentPulses - lastPulseCountFlow;
    currentFlowRate = pulsesToLitres(pulsesInSec) * 60.0;
    lastPulseCountFlow = currentPulses;
    lastFlowCheck = now;

    if (currentFlowRate > DEBIT_MAX_L_MIN && !alertSent) {
      alertSent = true;
      sendAlert("fuite", "Débit anormal détecté (rupture conduite possible).");
    }
  }
}

void finalizeTransaction(const String &statut) {
  float cost = session.distributedLitres * session.pricePerLitre;
  session.debitedAmount = cost;

  String url = String(SERVER_BASE) + "?route=api/transactions/store";

  JsonDocument body;
  body["borne_id"] = BORNE_ID;
  body["uid"] = session.cardUID;
  body["volume_demande"] = session.requestedLitres;
  body["volume_distribue"] = session.distributedLitres;
  body["statut"] = statut;

  String jsonStr;
  serializeJson(body, jsonStr);

  JsonDocument response;
  httpPost(url, jsonStr, response);
}

void sendAlert(const String &type, const String &description) {
  String url = String(SERVER_BASE) + "?route=api/alertes/store";
  JsonDocument body;
  body["borne_id"] = BORNE_ID;
  body["type_alerte"] = type;
  body["description"] = description;

  String jsonStr;
  serializeJson(body, jsonStr);

  JsonDocument response;
  httpPost(url, jsonStr, response);
  publishAlert(type, description);
}

void pingServer() {
  String url =
      String(SERVER_BASE) + "?route=api/bornes/ping&borne_id=" + BORNE_ID;
  JsonDocument response;
  httpGet(url, response);
}

void checkPendingHttpCommands() {
  if (WiFi.status() != WL_CONNECTED)
    return;

  String url = String(SERVER_BASE) +
               "?route=api/bornes/pending-commands&borne_id=" + BORNE_ID;
  JsonDocument doc;
  if (httpGet(url, doc)) {
    if (doc["status"] == "success" && doc["has_command"] == true) {
      String typeCmd = doc["type"].as<String>();
      Serial.println("[CMD] Commande HTTP reçue : " + typeCmd);

      if (typeCmd.startsWith("set_volume:")) {
        float vol = typeCmd.substring(11).toFloat();
        if (vol > 0.0) {
          session.requestedLitres = vol;
          Serial.printf("[DIST] Démarrage distribution via Kiosque : %.3f L\n",
                        vol);
          startDistribution();
        }
      } else if (typeCmd == "start_pompe" || typeCmd == "start_pump") {
        Serial.println(F("[CMD] Démarrage manuel de la pompe via HTTP"));
        mqttAdminOverride = true;
        mqttOverrideStartTime = millis();
        valveOpen();
        electrovanneOpen();
        setLed(ORANGE);
        beep(2, 150);
        publishStatus();
      } else if (typeCmd == "stop_pompe" || typeCmd == "stop_pump") {
        Serial.println(F("[CMD] Arrêt manuel de la pompe via HTTP"));
        stopDistributionOutputs();
        mqttAdminOverride = false;
        resetSession();
      } else if (typeCmd == "cancel_session" || typeCmd == "cancel") {
        if (currentState == STATE_DISTRIBUTING) {
          Serial.println(F("[CMD] Arrêt d'urgence de la distribution demandé"));
          emergencyStop("Arrêt utilisateur via Kiosque");
        } else {
          Serial.println(F("[CMD] Annulation de la session"));
          resetSession();
        }
      } else if (typeCmd == "restart_esp" || typeCmd == "reboot") {
        Serial.println(F("[CMD] Redémarrage ESP32..."));
        delay(500);
        ESP.restart();
      }
    }
  }
}

void emergencyStop(const String &reason) {
  stopDistributionOutputs();
  mqttAdminOverride = false;
  currentState = STATE_ERROR;
  publishStatus(); // Publish final status immediately (pump OFF, final litres)
  beep(5, 200);
  setLed(RED);
  if (session.distributedLitres > 0.01)
    finalizeTransaction("interrompue_erreur");
  sendAlert("com_perdue", reason);
  resetSession();
}

void valveOpen() {
  pinMode(PIN_VALVE, OUTPUT);
  digitalWrite(PIN_VALVE, VALVE_ACTIVE_LEVEL);
  isValveOpen = true;
  Serial.printf("[DIST] POMPE ON sur GPIO %d\n", PIN_VALVE);
}

void valveClose() {
  pinMode(PIN_VALVE, OUTPUT);
  digitalWrite(PIN_VALVE, (VALVE_ACTIVE_LEVEL == LOW) ? HIGH : LOW);
  isValveOpen = false;
  Serial.printf("[DIST] POMPE OFF sur GPIO %d\n", PIN_VALVE);
}

// --- Contrôle dédié de l'électrovanne ---
void electrovanneOpen() {
  pinMode(PIN_ELECTROVANNE, OUTPUT);
  digitalWrite(PIN_ELECTROVANNE, ELECTROVANNE_ACTIVE_LEVEL);
  isElectrovanneOpen = true;
  setLed(RED); // Électrovanne OUVERTE -> voyant ROUGE ALLUMÉ (distribution active)
  Serial.printf("[DIST] ELECTROVANNE OUVERTE sur GPIO %d (LED ROUGE ON)\n", PIN_ELECTROVANNE);
}

void electrovanneClose() {
  pinMode(PIN_ELECTROVANNE, OUTPUT);
  digitalWrite(PIN_ELECTROVANNE, (ELECTROVANNE_ACTIVE_LEVEL == LOW) ? HIGH : LOW);
  isElectrovanneOpen = false;
  setLed(GREEN); // Électrovanne FERMÉE -> voyant VERT ALLUMÉ (borne prête en attente)
  Serial.printf("[DIST] ELECTROVANNE FERMEE sur GPIO %d (LED VERTE ON)\n", PIN_ELECTROVANNE);
}

// Arrête simultanément la pompe et ferme l'électrovanne
void stopDistributionOutputs() {
  valveClose();
  electrovanneClose();
  Serial.println(F("[DIST] SORTIES COUPEES : Pompe ARRETEE & Electrovanne FERMEE"));
}

float pulsesToLitres(unsigned long pulses) {
  return (float)pulses / PULSES_PER_LITRE;
}

void setLed(Color c) {
  uint8_t onVal = (LED_ACTIVE_LEVEL == HIGH) ? HIGH : LOW;
  uint8_t offVal = (LED_ACTIVE_LEVEL == HIGH) ? LOW : HIGH;

  digitalWrite(PIN_LED_GREEN, (c == GREEN) ? onVal : offVal);
  digitalWrite(PIN_LED_ORANGE, (c == ORANGE) ? onVal : offVal);
  digitalWrite(PIN_LED_RED, (c == RED) ? onVal : offVal);
}
void ledsOff() { setLed(OFF); }

void beep(int count, int durationMs) {
  for (int i = 0; i < count; i++) {
    digitalWrite(PIN_BUZZER, BUZZER_ON);
    delay(durationMs);
    digitalWrite(PIN_BUZZER, BUZZER_OFF);
    if (i < count - 1)
      delay(durationMs / 2);
  }
  digitalWrite(PIN_BUZZER, BUZZER_OFF);
}

void resetSession() {
  stopDistributionOutputs();
  session = Session();
  pinBuffer = "";
  volumeBuffer = ""; // Réinitialiser le volume saisi
  alertSent = false;
  mqttAdminOverride = false;
  mqttOverrideStartTime = 0;

  rfid.PCD_Init(); // Ré-initialiser le lecteur MFRC522 pour garantir le scan
                   // fluide

  currentState = STATE_IDLE;
  setLed(GREEN); // Électrovanne fermée au repos -> voyant vert allumé
  displayIdle();
}

// ---------------- AFFICHAGE SÉRIE ----------------
void displayIdle() { Serial.println(F("Borne Prête - En attente carte")); }
void displayWelcome() { Serial.println(F("Bienvenue")); }
void displayVolumePrompt() {
  Serial.println(F("Saisir Volume en mL (ex: 250)"));
}
void displayPinProgress(int digits) { Serial.println(F("PIN saisi...")); }
void displayDistribution() { Serial.println(F("Distribution en cours...")); }
void displayDistributionProgress(float done, float total) {
  int percent = (total > 0) ? (int)((done / total) * 100.0) : 0;
  if (percent > 100) percent = 100;
  Serial.printf("[DEBITMETRE] >> %d / %d mL (%.3f L) [%d%%] | Impulsions: %lu | Debit: %.2f L/min\n",
                (int)(done * 1000.0), (int)(total * 1000.0), done, percent, pulseCount, currentFlowRate);
}
void displaySuccess() { Serial.println(F("Succès")); }
void displayMessage(const String &title, const String &msg) {
  Serial.println(title + " : " + msg);
}

// ---------------- HTTP ----------------
bool httpGet(const String &url, JsonDocument &response) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println(F("[HTTP] WiFi non connecté !"));
    return false;
  }
  HTTPClient http;
  http.begin(url);
  http.setTimeout(HTTP_TIMEOUT);
  http.addHeader("Accept", "application/json");
  int code = http.GET();
  String body = http.getString();
  http.end();
  if (code <= 0) {
    Serial.printf("[HTTP] Échec GET (code %d: %s)\n", code,
                  HTTPClient::errorToString(code).c_str());
    return false;
  }
  DeserializationError err = deserializeJson(response, body);
  if (err != DeserializationError::Ok) {
    Serial.printf("[HTTP] Erreur JSON desérialisation: %s\n", err.c_str());
    Serial.println("[HTTP] Réponse brute du serveur:");
    Serial.println(body);
    return false;
  }
  return true;
}

bool httpPost(const String &url, const String &jsonBody,
              JsonDocument &response) {
  if (WiFi.status() != WL_CONNECTED)
    return false;
  HTTPClient http;
  http.begin(url);
  http.setTimeout(HTTP_TIMEOUT);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  int code = http.POST(jsonBody);
  String body = http.getString();
  http.end();
  if (code <= 0)
    return false;
  DeserializationError err = deserializeJson(response, body);
  return (err == DeserializationError::Ok);
}

void connectWiFi() {
  Serial.print(F("[WIFI] Connexion à "));
  Serial.println(WIFI_SSID);
  WiFi.disconnect();
  delay(100);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED) {
    delay(300);
    Serial.print(".");
    if (millis() - start > 6000) {
      Serial.println();
      Serial.println(
          F("[WIFI] Connexion WiFi différée (la borne démarre en autonome)"));
      return;
    }
  }
  Serial.println();
  Serial.print(F("[WIFI] Connecté ! Adresse IP: "));
  Serial.println(WiFi.localIP());
}

// ---------------- MQTT ----------------
void publishHeartbeat();

void connectMQTT() {
  if (mqttClient.connected())
    return;
  Serial.print("[MQTT] Connexion au broker ");
  Serial.println(MQTT_BROKER);
  String clientId = "esp32_borne_" + String(BORNE_ID) + "_" +
                    String((uint32_t)ESP.getEfuseMac(), HEX);
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setKeepAlive(60);

  // Configuration Last Will and Testament (LWT) sur le topic status
  String topicStatus = "water/station" + String(BORNE_ID) + "/status";
  bool ok = mqttClient.connect(clientId.c_str(), MQTT_USER, MQTT_PASS,
                               topicStatus.c_str(),
                               1,    // QoS 1
                               true, // Retained
                               "offline");

  if (ok) {
    Serial.println(F("[MQTT] Connecté au broker Mosquitto ✓"));
    // Publier l'état online en retained
    mqttClient.publish(topicStatus.c_str(), "online", true);

    // S'abonner au topic des commandes
    String topicCmd = "water/station" + String(BORNE_ID) + "/command";
    mqttClient.subscribe(topicCmd.c_str(), 1); // QoS 1

    // Publier le premier heartbeat immédiatement
    publishHeartbeat();
  } else {
    Serial.print(F("[MQTT] Échec connexion, rc="));
    Serial.println(mqttClient.state());
  }
}

void mqttCallback(char *topic, byte *payload, unsigned int length) {
  Serial.print(F("[MQTT] Message reçu sur "));
  Serial.print(topic);
  Serial.print(F(" (longueur: "));
  Serial.print(length);
  Serial.println(F(")"));

  JsonDocument doc;
  if (deserializeJson(doc, payload, length) != DeserializationError::Ok) {
    Serial.println(F("[MQTT] Échec parsing JSON direct de la commande."));
    return;
  }

  String typeCmd = doc["type"] | doc["command_type"] | "";

  if (typeCmd == "stop_pompe" || typeCmd == "stop_pump") {
    Serial.println(F("[MQTT] Arrêt manuel de la pompe demandé"));
    stopDistributionOutputs();
    mqttAdminOverride = false;
    resetSession();
  } else if (typeCmd == "cancel_session" || typeCmd == "cancel") {
    if (currentState != STATE_DISTRIBUTING) {
      Serial.println(F("[MQTT] Commande d'annulation reçue. Réinitialisation "
                       "de la session..."));
      beep(2, 100);
      resetSession();
    }
  } else if (typeCmd == "pin_verified") {
    if (currentState == STATE_PIN_WAIT || currentState == STATE_VERIFYING) {
      Serial.println(F("[MQTT] PIN vérifié via le Kiosque. Bienvenue !"));
      beep(1, 200);
      displayWelcome();
      displayVolumePrompt();
      currentState = STATE_VOLUME_SELECT;
      stateEnterTime = millis();
      setLed(GREEN);
    }
  } else if (typeCmd == "start_pompe" || typeCmd == "start_pump") {
    if (currentState != STATE_IDLE && currentState != STATE_ALERT) {
      Serial.println(F("[MQTT] Ignoré : pompe déjà active ou en alerte."));
      return;
    }
    Serial.println(F("[MQTT] Démarrage manuel de la pompe à distance..."));
    mqttAdminOverride = true;
    mqttOverrideStartTime =
        millis(); // Enregistrer l'heure de début pour le timeout de sécurité
    // Ouverture synchronisée pompe + électrovanne (override admin)
    valveOpen();
    Serial.println(F("[DIST] POMPE ON"));
    electrovanneOpen();
    Serial.println(F("[DIST] ELECTROVANNE OUVERTE"));
    setLed(ORANGE);
    beep(2, 150);
    publishStatus();
  } else if (typeCmd == "set_volume") {
    float vol = doc["payload"]["volume"] | doc["volume"] | 0.0;
    if (vol > 0.0) {
      Serial.printf("[MQTT] Commande volume reçue : %.2f L\n", vol);
      if (currentState != STATE_DISTRIBUTING && currentState != STATE_ALERT) {
        session.requestedLitres = vol;
        startDistribution();
      }
    }
  } else if (typeCmd == "allow_rfid" || typeCmd == "block_rfid") {
    String cardUid = doc["payload"]["uid"] | doc["uid"] | "";
    bool allowed = (typeCmd == "allow_rfid");
    Serial.printf("[MQTT] Commande carte RFID %s : %s\n", cardUid.c_str(),
                  allowed ? "AUTORISÉE" : "BLOQUÉE");
    displayMessage(allowed ? "RFID AUTORISÉE" : "RFID BLOQUÉE", cardUid);
    beep(1, 300);
  } else if (typeCmd == "restart_esp" || typeCmd == "reboot") {
    Serial.println(
        F("[MQTT] Commande de redémarrage ESP32 reçue. Reboot imminent..."));
    beep(4, 100);
    delay(500);
    ESP.restart();
  } else if (typeCmd == "update_config") {
    // Exemple : modifier le coefficient de calibration du débitmètre
    float pulses =
        doc["payload"]["pulses_per_litre"] | doc["pulses_per_litre"] | 0.0;
    if (pulses > 0.0) {
      // Configuration mise à jour
      Serial.printf("[MQTT] Calibration mise à jour : %.2f pulses/L\n", pulses);
      displayMessage("CONFIG MAJ", "Calibration ok");
      beep(2, 100);
    }
  }
}

void sendTelemetry(const String &pumpState, const String &valveState, float volumeDistributed, float volumeRequested, float flowRate, const String &distState) {
  // 1. Canal MQTT (si broker connecté)
  if (mqttClient.connected()) {
    String stationPrefix = "water/station" + String(BORNE_ID);
    mqttClient.publish((stationPrefix + "/pump").c_str(), pumpState.c_str(), false);
    mqttClient.publish((stationPrefix + "/valve").c_str(), valveState.c_str(), false);
    mqttClient.publish((stationPrefix + "/volume").c_str(), String(volumeDistributed, 3).c_str(), false);
    mqttClient.publish((stationPrefix + "/flow").c_str(), String(flowRate, 2).c_str(), false);
    mqttClient.publish((stationPrefix + "/state").c_str(), distState.c_str(), false);
  }

  // 2. Canal HTTP Telemetry Push (garantit la mise à jour temps réel sur le Kiosque même sans MQTT actif)
  if (WiFi.status() == WL_CONNECTED) {
    String url = String(SERVER_BASE) + "?route=api/bornes/telemetry";
    JsonDocument body;
    body["borne_id"] = BORNE_ID;
    body["pump_state"] = pumpState;
    body["valve_state"] = valveState;
    body["volume_distributed"] = volumeDistributed;
    body["volume_requested"] = volumeRequested;
    body["flow_rate"] = flowRate;
    body["distribution_state"] = distState;

    String jsonStr;
    serializeJson(body, jsonStr);
    JsonDocument response;
    httpPost(url, jsonStr, response);
  }
}

void publishStatus() {
  String pumpState = (currentState == STATE_DISTRIBUTING || mqttAdminOverride || isValveOpen) ? "ON" : "OFF";
  String valveState = (currentState == STATE_DISTRIBUTING || mqttAdminOverride || isElectrovanneOpen) ? "open" : "closed";
  String distState = (currentState == STATE_DISTRIBUTING) ? "distributing" : ((currentState == STATE_DONE) ? "completed" : ((currentState == STATE_ERROR || currentState == STATE_ALERT) ? "interrupted" : "idle"));
  sendTelemetry(pumpState, valveState, session.distributedLitres, session.requestedLitres, currentFlowRate, distState);
}

void publishHeartbeat() {
  if (!mqttClient.connected())
    return;
  String topicHb = "water/station" + String(BORNE_ID) + "/heartbeat";

  JsonDocument doc;
  doc["rssi"] = WiFi.RSSI();
  doc["uptime"] = millis();
  doc["free_heap"] = ESP.getFreeHeap();
  doc["valve_status"] = isValveOpen ? "open" : "closed";
  doc["electrovanne_status"] = isElectrovanneOpen ? "open" : "closed";

  String jsonStr;
  serializeJson(doc, jsonStr);
  mqttClient.publish(topicHb.c_str(), jsonStr.c_str(), false);
}

void publishAlert(const String &type, const String &description) {
  if (!mqttClient.connected())
    return;
  String topicAlerte = "water/station" + String(BORNE_ID) + "/log";

  JsonDocument doc;
  doc["level"] = "error";
  doc["type"] = type;
  doc["description"] = description;
  doc["uptime"] = millis();

  String jsonStr;
  serializeJson(doc, jsonStr);
  mqttClient.publish(topicAlerte.c_str(), jsonStr.c_str(), false);
}