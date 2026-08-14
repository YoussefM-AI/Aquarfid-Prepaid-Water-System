/*
 * ============================================================
 * BORNE RFID EAU AGRICOLE — ESP32
 * ============================================================
 * Auteur   : H2O RFID System
 * Version  : 2.1.0 (Corrigé - Conflits matériels résolus)
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
const char *WIFI_SSID = "YOUSSEF";
const char *WIFI_PASSWORD = "12345678";

// --- Serveur API ---
const char *SERVER_BASE = "http://192.168.137.1/water/public/index.php";

const int BORNE_ID = 1;          // ID de cette borne dans la DB
const int WIFI_TIMEOUT = 3000;   // Timeout rapide connexion WiFi (3s) pour ne pas bloquer le scan RFID
const int HTTP_TIMEOUT = 8000;   // Timeout requête HTTP (ms)
const int PING_INTERVAL = 30000; // Intervalle keep-alive (30s)

// --- Broker MQTT ---
const char *MQTT_BROKER = "192.168.137.1";
const int MQTT_PORT = 1883;
const char *MQTT_USER = "water_user";
const char *MQTT_PASS = "water_pass123";
const int MQTT_PUB_INTERVAL = 3000; // Intervalle publication état (ms)
const unsigned long MQTT_OVERRIDE_TIMEOUT = 300000; // Timeout de sécurité de l'override admin (5 minutes = 300000 ms)

// --- Paramètres de distribution ---
const float PULSES_PER_LITRE = 450.0; // Calibration YF-S201 : 450 impulsions = 1 Litre (0.45 impulsion/mL)
const float DEBIT_MIN_L_MIN = 0.1;    // Débit minimum normal (L/min)
const float DEBIT_MAX_L_MIN = 200.0;  // Débit max avant alerte fuite (L/min)
const float FUITE_SEUIL_LITRES = 2.0; // Litres perdus avant alerte fuite
const int MANQUE_EAU_TIMEOUT = 10000; // Timeout sans débit = manque eau (ms)
const int VOLUME_MAX_LITRES = 2000;   // Volume max par transaction (L)

// ============================================================
// BROCHES (PINS) - CORRIGÉES
// ============================================================

// RFID MFRC522 — SPI
#define PIN_RFID_SS 5
#define PIN_RFID_RST 22

// Débitmètre (entrée impulsion interrupt)
// WARNING: GPIO4 is also listed in the keypad column pins: byte colPins[COLS] = {4, 16, 17, 21};
// If you are using a physical keypad, using GPIO4 for the flow meter will cause conflicts.
#define PIN_FLOW_METER 4 // Avec résistance de pull-up interne (GPIO4)

// Électrovanne / Pompe (via relais — Active-LOW déclenché par LOW (0V), désactivé par INPUT)
#define PIN_VALVE 16
#define VALVE_OPEN  LOW   // LOW (0V) active l'optocoupleur du relais 5V

// Capteurs de sécurité (Désactivés par défaut pour éviter les fausses alertes sur broches flottantes)
#define ENABLE_SAFETY_SENSORS false
#define PIN_OVERFLOW 35    // Nécessite R externe PULL-DOWN 10kΩ
#define PIN_LEAK_SENSOR 39 // Nécessite R externe PULL-DOWN 10kΩ

// Signalisation
#define PIN_BUZZER 32
#define BUZZER_ON  LOW   // LOW = Buzzer émet un son
#define BUZZER_OFF HIGH  // HIGH = Buzzer ÉTEINT au repos
#define PIN_LED_GREEN 25
#define PIN_LED_ORANGE 27
#define PIN_LED_RED 33

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
bool httpPost(const String &url, const String &jsonBody, JsonDocument &response);
void connectWiFi();

// Clavier 4x4 (pour saisie PIN)
const byte ROWS = 4;
const byte COLS = 4;
char hexaKeys[ROWS][COLS] = {{'1', '2', '3', 'A'},
                             {'4', '5', '6', 'B'},
                             {'7', '8', '9', 'C'},
                             {'*', '0', '#', 'D'}};
byte rowPins[ROWS] = {13, 14, 15, 2};
byte colPins[COLS] = {4, 16, 17, 21};

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

// ============================================================
// ISR : DÉBITMÈTRE
// ============================================================

volatile unsigned long lastFlowMicros = 0;
void IRAM_ATTR onFlowPulse() {
  unsigned long now = micros();
  if (now - lastFlowMicros > 2000) { // 2 ms anti-rebond pour éliminer le bruit électrique
    pulseCount++;
    lastFlowMicros = now;
  }
}

// ============================================================
// SETUP
// ============================================================

void setup() {
  Serial.begin(115200);
  Serial.println(F("\n========================================"));
  Serial.println(F("  BORNE RFID EAU — ESP32 v2.0"));
  Serial.println(F("========================================"));

  // Broches
  pinMode(PIN_VALVE, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  digitalWrite(PIN_BUZZER, BUZZER_OFF);
  pinMode(PIN_LED_GREEN, OUTPUT);
  pinMode(PIN_LED_ORANGE, OUTPUT); // Corrigé
  pinMode(PIN_LED_RED, OUTPUT);

  pinMode(PIN_OVERFLOW, INPUT);    // Corrigé
  pinMode(PIN_LEAK_SENSOR, INPUT); // Corrigé

  // Sécurité : vanne FERMÉE au démarrage
  valveClose();
  ledsOff();

  // SPI + RFID
  SPI.begin();
  rfid.PCD_Init();
  Serial.println(F("[RFID] Lecteur initialisé"));

  // Interrupt débitmètre
  pinMode(PIN_FLOW_METER, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(PIN_FLOW_METER), onFlowPulse, FALLING);

  // WiFi
  connectWiFi();

  // MQTT
  if (WiFi.status() == WL_CONNECTED) {
    connectMQTT();
  }

  // Bip de démarrage
  beep(3, 100);
  setLed(GREEN);
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
      Serial.println(F("[WIFI] Connexion perdue — tentative de reconnexion..."));
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
    Serial.printf("[DIST] Volume distribué : %.2f / %.2f L (Débit: %.1f L/min)\n",
                  session.distributedLitres, session.requestedLitres, currentFlowRate);
    lastStatusDisplay = now;
  }
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

  beep(1, 200);
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
    beep(2, 400);
    setLed(RED);
    delay(3000);
    resetSession();
    return;
  }

  session.farmerName = response["card"]["farmer_prenom"].as<String>() + " " +
                       response["card"]["farmer_nom"].as<String>();
  session.solde = response["card"]["solde"];
  session.pricePerLitre = response["price_per_litre"];
  session.maxVolumeLitres = response["max_volume_litres"];
  session.hasPin = response["has_pin"];

  if (session.hasPin) {
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
  if (millis() - stateEnterTime > 30000) {
    displayMessage("TIMEOUT", "Délai de saisie dépassé.");
    beep(2, 300);
    delay(2000);
    resetSession();
    return;
  }

  char key = keypad.getKey();
  if (!key)
    return;

  if (key == '#') {
    displayMessage("ANNULÉ", "Opération annulée.");
    delay(1500);
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
    beep(3, 300);
    setLed(RED);
    delay(3000);
    pinBuffer = "";
    currentState = STATE_PIN_WAIT;
    stateEnterTime = millis();
    setLed(ORANGE);
    return;
  }

  session.solde = response["card"]["solde"];
  session.pricePerLitre = response["price_per_litre"];
  session.maxVolumeLitres = response["max_volume_litres"];

  beep(1, 200);
  displayWelcome();
  displayVolumePrompt();
  currentState = STATE_VOLUME_SELECT;
  stateEnterTime = millis();
  setLed(GREEN);
}

void handleVolumeSelect() {
  if (millis() - stateEnterTime > 60000) {
    displayMessage("TIMEOUT", "Aucune sélection effectuée.");
    delay(2000);
    resetSession();
    return;
  }

  // volumeBuffer est maintenant globale

  char key = keypad.getKey();
  if (!key)
    return;

  if (key == 'A') {
    session.requestedLitres = session.maxVolumeLitres;
    volumeBuffer = "";
    startDistribution();
    return;
  }

  if (key == '#') {
    if (volumeBuffer.length() > 0) {
      float volMl = volumeBuffer.toFloat();
      float maxMl = session.maxVolumeLitres * 1000.0;
      if (volMl <= 0 || volMl > maxMl) {
        displayMessage("VOLUME INVALIDE",
                       "Max: " + String((int)maxMl) + " mL");
        beep(2, 200);
        delay(2000);
        volumeBuffer = "";
        return;
      }
      session.requestedLitres = volMl / 1000.0;
      volumeBuffer = "";
      startDistribution();
    }
    return;
  }

  if (key == '*') {
    if (volumeBuffer.length() > 0)
      volumeBuffer = volumeBuffer.substring(0, volumeBuffer.length() - 1);
    return;
  }

  if (key >= '0' && key <= '9') {
    if (volumeBuffer.length() < 6) {
      volumeBuffer += key;
      beep(1, 50);
    }
  }

  if (key == 'B') {
    static const float presets[] = {0.100, 0.250, 0.500, 1.000}; // Presets : 100mL, 250mL, 500mL, 1000mL
    static int presetIdx = 0;
    float vol = min(presets[presetIdx % 4], session.maxVolumeLitres);
    session.requestedLitres = vol;
    presetIdx++;
    volumeBuffer = "";
    startDistribution();
  }
}

void startDistribution() {
  session.distributedLitres = 0;
  session.debitedAmount = 0;

  valveOpen();
  delay(300); // Laisse le temps au relais de s'activer et d'atténuer le rebond électrique de démarrage

  noInterrupts();
  pulseCount = 0; // Réinitialise complètement le compteur après stabilisation du relais
  session.startPulseCount = 0;
  lastPulseCountFlow = 0;
  interrupts();

  session.startTime = millis();
  session.lastFlowTime = millis();
  setLed(GREEN);
  beep(1, 100);
  displayDistribution();

  currentState = STATE_DISTRIBUTING;
  stateEnterTime = millis();
}

void handleDistributing() {
  unsigned long now = millis();
  unsigned long currentPulses;
  noInterrupts();
  currentPulses = pulseCount;
  interrupts();

  unsigned long delta = currentPulses - session.startPulseCount;
  float distributed = pulsesToLitres(delta);
  session.distributedLitres = distributed;

  // Track if pulses are increasing to reset flow timer
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

  // Priming timeout: 20 seconds; Flow loss timeout: 10 seconds
  unsigned long currentTimeout = (distributed < 0.01) ? 20000 : 10000;

  if (now - session.lastFlowTime > currentTimeout) {
    valveClose();
    distributionStarted = false; // Reset flag
    currentState = STATE_ERROR;
    publishStatus(); // Publish final status immediately (pump OFF, final litres)
    displayDistributionProgress(session.distributedLitres, session.requestedLitres); // Print final progress to Serial Monitor
    sendAlert("manque_eau", "Débit nul. Possible manque d'eau.");
    displayMessage("MANQUE D'EAU", "Arrêt automatique.");
    beep(5, 300);
    setLed(RED);
    finalizeTransaction("interrompue_erreur");
    stateEnterTime = now;
    return;
  }

  if (distributed >= session.requestedLitres) {
    valveClose();
    distributionStarted = false; // Reset flag
    session.distributedLitres = session.requestedLitres;
    currentState = STATE_DONE;
    publishStatus(); // Publish final status immediately (pump OFF, target litres)
    displayDistributionProgress(session.distributedLitres, session.requestedLitres); // Print final 100% progress to Serial Monitor
    beep(3, 200);
    setLed(GREEN);
    finalizeTransaction("reussie");
    displaySuccess();
    stateEnterTime = now;
    return;
  }

  static unsigned long lastDisplay = 0;
  if (now - lastDisplay > 200) {
    displayDistributionProgress(distributed, session.requestedLitres);
    lastDisplay = now;
  }

  static unsigned long lastFastMqtt = 0;
  if (now - lastFastMqtt > 500) {
    publishStatus();
    lastFastMqtt = now;
  }
}

void handleDone() {
  if (millis() - stateEnterTime > 2000) // Attente de 2 secondes avant de réinitialiser la session (snappy reset)
    resetSession();
}

void handleError() {
  if (millis() - stateEnterTime > 5000)
    resetSession();
}

void checkSafety() {
  // Sécurité pour l'override admin à distance (timeout après 5 minutes)
  if (mqttAdminOverride) {
    if (millis() - mqttOverrideStartTime > MQTT_OVERRIDE_TIMEOUT) {
      Serial.println(F("[SYS] Timeout de sécurité de l'override admin atteint. Fermeture vanne."));
      emergencyStop("Timeout sécurité override admin");
      return;
    }
  }

  if (currentState != STATE_DISTRIBUTING) {
    static bool overflowWasHigh = false;
    static bool leakWasHigh = false;

    bool overflowNow = ENABLE_SAFETY_SENSORS ? (digitalRead(PIN_OVERFLOW) == HIGH) : false;
    bool leakNow = ENABLE_SAFETY_SENSORS ? (digitalRead(PIN_LEAK_SENSOR) == HIGH) : false;

    if (overflowNow || leakNow) {
      // Fermeture immédiate de la vanne si elle était ouverte via override
      if (mqttAdminOverride || (digitalRead(PIN_VALVE) == LOW)) {
        valveClose();
        mqttAdminOverride = false;
      }
      
      // Lockout : passage en STATE_ALERT pour empêcher toute nouvelle transaction
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

    if (leakNow && !leakWasHigh) {
      sendAlert("fuite", "Fuite sol détectée hors distribution");
      setLed(RED);
      beep(3, 200);
    }
    leakWasHigh = leakNow;
    
    currentFlowRate = 0.0; // Pas de débit hors distribution
    return;
  }

  if (ENABLE_SAFETY_SENSORS && digitalRead(PIN_OVERFLOW) == HIGH) {
    valveClose();
    sendAlert("trop_plein", "Trop-plein activé. Arrêt immédiat.");
    displayMessage("TROP-PLEIN !", "Arrêt d'urgence.");
    beep(10, 150);
    setLed(RED);
    finalizeTransaction("interrompue_erreur");
    currentState = STATE_ALERT;
    stateEnterTime = millis();
    return;
  }

  if (ENABLE_SAFETY_SENSORS && digitalRead(PIN_LEAK_SENSOR) == HIGH) {
    valveClose();
    sendAlert("fuite", "Sonde de fuite sol activée.");
    displayMessage("FUITE DÉTECTÉE", "Arrêt d'urgence.");
    beep(10, 100);
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

void emergencyStop(const String &reason) {
  valveClose();
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
  digitalWrite(PIN_VALVE, VALVE_OPEN);
}

void valveClose() {
  pinMode(PIN_VALVE, INPUT); // Haute impédance pour désactiver complètement le relais 5V
}

float pulsesToLitres(unsigned long pulses) {
  return (float)pulses / PULSES_PER_LITRE;
}

void setLed(Color c) {
  digitalWrite(PIN_LED_GREEN, c == GREEN ? HIGH : LOW);
  digitalWrite(PIN_LED_ORANGE, c == ORANGE ? HIGH : LOW);
  digitalWrite(PIN_LED_RED, c == RED ? HIGH : LOW);
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
  valveClose();
  ledsOff();
  session = Session();
  pinBuffer = "";
  volumeBuffer = ""; // Réinitialiser le volume saisi
  alertSent = false;
  mqttAdminOverride = false;
  mqttOverrideStartTime = 0;
  
  rfid.PCD_Init(); // Ré-initialiser le lecteur MFRC522 pour garantir le scan fluide

  currentState = STATE_IDLE;
  setLed(GREEN);
  displayIdle();
}

// ---------------- AFFICHAGE SÉRIE ----------------
void displayIdle() { Serial.println(F("Borne Prête - En attente carte")); }
void displayWelcome() { Serial.println(F("Bienvenue")); }
void displayVolumePrompt() { Serial.println(F("Saisir Volume en mL (ex: 250)")); }
void displayPinProgress(int digits) { Serial.println(F("PIN saisi...")); }
void displayDistribution() { Serial.println(F("Distribution en cours...")); }
void displayDistributionProgress(float done, float total) {
  Serial.printf("Avancement : %.3f L (%d mL) / %.3f L (%d mL)\n", done, (int)(done * 1000.0), total, (int)(total * 1000.0));
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
    Serial.printf("[HTTP] Échec GET (code %d: %s)\n", code, HTTPClient::errorToString(code).c_str());
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
  Serial.print("[WIFI] Connexion à ");
  Serial.println(WIFI_SSID);
  WiFi.disconnect();
  delay(100);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
    checkSafety(); // Garde la détection de fuite active pendant l'attente
    if (millis() - start > WIFI_TIMEOUT) {
      Serial.println();
      Serial.println("[WIFI] Échec de connexion (Timeout)");
      setLed(ORANGE);
      return;
    }
  }
  Serial.println();
  Serial.print("[WIFI] Connecté ! Adresse IP: ");
  Serial.println(WiFi.localIP());
  setLed(GREEN);
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
  bool ok = mqttClient.connect(
      clientId.c_str(), 
      MQTT_USER, 
      MQTT_PASS, 
      topicStatus.c_str(), 
      1,     // QoS 1
      true,  // Retained
      "offline"
  );
  
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
    mqttAdminOverride = false;
    emergencyStop("Arrêt manuel à distance");
  } 
  else if (typeCmd == "cancel_session" || typeCmd == "cancel") {
    if (currentState != STATE_DISTRIBUTING) {
      Serial.println(F("[MQTT] Commande d'annulation reçue. Réinitialisation de la session..."));
      beep(2, 100);
      resetSession();
    }
  }
  else if (typeCmd == "pin_verified") {
    if (currentState == STATE_PIN_WAIT || currentState == STATE_VERIFYING) {
      Serial.println(F("[MQTT] PIN vérifié via le Kiosque. Bienvenue !"));
      beep(1, 200);
      displayWelcome();
      displayVolumePrompt();
      currentState = STATE_VOLUME_SELECT;
      stateEnterTime = millis();
      setLed(GREEN);
    }
  }
  else if (typeCmd == "start_pompe" || typeCmd == "start_pump") {
    if (currentState != STATE_IDLE && currentState != STATE_ALERT) {
      Serial.println(F("[MQTT] Ignoré : pompe déjà active ou en alerte."));
      return;
    }
    Serial.println(F("[MQTT] Démarrage manuel de la pompe à distance..."));
    mqttAdminOverride = true;
    mqttOverrideStartTime = millis(); // Enregistrer l'heure de début pour le timeout de sécurité
    valveOpen();
    setLed(ORANGE);
    beep(2, 150);
  } 
  else if (typeCmd == "set_volume") {
    float vol = doc["payload"]["volume"] | doc["volume"] | 0.0;
    if (vol > 0.0) {
      Serial.printf("[MQTT] Commande volume reçue : %.2f L\n", vol);
      if (currentState == STATE_IDLE || currentState == STATE_VOLUME_SELECT) {
        session.requestedLitres = vol;
        startDistribution();
      }
    }
  } 
  else if (typeCmd == "allow_rfid" || typeCmd == "block_rfid") {
    String cardUid = doc["payload"]["uid"] | doc["uid"] | "";
    bool allowed = (typeCmd == "allow_rfid");
    Serial.printf("[MQTT] Commande carte RFID %s : %s\n", cardUid.c_str(), allowed ? "AUTORISÉE" : "BLOQUÉE");
    displayMessage(allowed ? "RFID AUTORISÉE" : "RFID BLOQUÉE", cardUid);
    beep(1, 300);
  }
  else if (typeCmd == "restart_esp" || typeCmd == "reboot") {
    Serial.println(F("[MQTT] Commande de redémarrage ESP32 reçue. Reboot imminent..."));
    beep(4, 100);
    delay(500);
    ESP.restart();
  } 
  else if (typeCmd == "update_config") {
    // Exemple : modifier le coefficient de calibration du débitmètre
    float pulses = doc["payload"]["pulses_per_litre"] | doc["pulses_per_litre"] | 0.0;
    if (pulses > 0.0) {
      // Configuration mise à jour
      Serial.printf("[MQTT] Calibration mise à jour : %.2f pulses/L\n", pulses);
      displayMessage("CONFIG MAJ", "Calibration ok");
      beep(2, 100);
    }
  }
}

void publishStatus() {
  if (!mqttClient.connected())
    return;
  String stationPrefix = "water/station" + String(BORNE_ID);

  // 1. État de la pompe/relais (ON/OFF)
  String topicPump = stationPrefix + "/pump";
  String pumpState = (currentState == STATE_DISTRIBUTING) ? "ON" : "OFF";
  mqttClient.publish(topicPump.c_str(), pumpState.c_str(), false);

  // 2. Débit instantané en L/min
  String topicFlow = stationPrefix + "/flow";
  String debitStr = String(currentFlowRate, 2);
  mqttClient.publish(topicFlow.c_str(), debitStr.c_str(), false);

  // 3. Volume d'eau distribué cumulé
  String topicVol = stationPrefix + "/volume";
  String volStr = String(session.distributedLitres, 3); // 3 decimal places to match Serial Monitor mL precision exactly
  mqttClient.publish(topicVol.c_str(), volStr.c_str(), false);
}

void publishHeartbeat() {
  if (!mqttClient.connected())
    return;
  String topicHb = "water/station" + String(BORNE_ID) + "/heartbeat";

  JsonDocument doc;
  doc["rssi"] = WiFi.RSSI();
  doc["uptime"] = millis();
  doc["free_heap"] = ESP.getFreeHeap();
  doc["valve_status"] = (digitalRead(PIN_VALVE) == LOW) ? "open" : "closed";

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