/**
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
const char *WIFI_SSID = "VOTRE_WIFI_SSID";
const char *WIFI_PASSWORD = "VOTRE_WIFI_PASSWORD";

// --- Serveur API ---
const char *SERVER_BASE = "http://192.168.1.100/water/public/index.php";

const int BORNE_ID = 1;          // ID de cette borne dans la DB
const int WIFI_TIMEOUT = 15000;  // Timeout connexion WiFi (ms)
const int HTTP_TIMEOUT = 8000;   // Timeout requête HTTP (ms)
const int PING_INTERVAL = 30000; // Intervalle keep-alive (30s)

// --- Broker MQTT ---
const char *MQTT_BROKER = "192.168.1.100";
const int MQTT_PORT = 1883;
const char *MQTT_USER = "";
const char *MQTT_PASS = "";
const int MQTT_PUB_INTERVAL = 3000; // Intervalle publication état (ms)

// --- Paramètres de distribution ---
const float PULSES_PER_LITRE = 10.0;  // Impulsions par litre (à calibrer)
const float DEBIT_MIN_L_MIN = 5.0;    // Débit minimum normal (L/min)
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
#define PIN_FLOW_METER 34 // Nécessite R externe PULL-UP 10kΩ

// Électrovanne (via relais — LOW = ouvert, HIGH = fermé)
#define PIN_VALVE 26

// Capteurs de sécurité
#define PIN_OVERFLOW 35    // Nécessite R externe PULL-DOWN 10kΩ
#define PIN_LEAK_SENSOR 39 // Nécessite R externe PULL-DOWN 10kΩ

// Signalisation
#define PIN_BUZZER 32
#define PIN_LED_GREEN 25
#define PIN_LED_ORANGE 27
#define PIN_LED_RED 33

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
unsigned long lastPulseCount = 0;
unsigned long lastFlowCheck = 0;

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
bool alertSent = false;
bool mqttAdminOverride = false;

// ============================================================
// ISR : DÉBITMÈTRE
// ============================================================

void IRAM_ATTR onFlowPulse() { pulseCount++; }

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
    lastPingTime = now;
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println(F("[WIFI] Connexion perdue — tentative de reconnexion..."));
    if (currentState == STATE_DISTRIBUTING) {
      emergencyStop("Perte de communication WiFi");
    }
    connectWiFi();
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
    float litres = pulsesToLitres(pulseCount - lastPulseCount);
    Serial.printf("[DIST] Volume distribué : %.2f / %.2f L\n",
                  session.distributedLitres + litres, session.requestedLitres);
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

  static String volumeBuffer = "";

  if (millis() - stateEnterTime < 200) {
    displayVolumePrompt();
  }

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
      float vol = volumeBuffer.toFloat();
      if (vol <= 0 || vol > session.maxVolumeLitres) {
        displayMessage("VOLUME INVALIDE",
                       "Max: " + String(session.maxVolumeLitres, 1) + " L");
        beep(2, 200);
        delay(2000);
        volumeBuffer = "";
        return;
      }
      session.requestedLitres = vol;
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
    static const int presets[] = {50, 100, 200, 500};
    static int presetIdx = 0;
    float vol = min((float)presets[presetIdx % 4], session.maxVolumeLitres);
    session.requestedLitres = vol;
    presetIdx++;
    volumeBuffer = "";
    startDistribution();
  }
}

void startDistribution() {
  noInterrupts();
  lastPulseCount = pulseCount;
  interrupts();

  session.distributedLitres = 0;
  session.debitedAmount = 0;
  session.startTime = millis();
  session.lastFlowTime = millis();

  valveOpen();
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

  unsigned long delta = currentPulses - lastPulseCount;
  float distributed = pulsesToLitres(delta);
  session.distributedLitres = distributed;

  if (distributed > 0.01) {
    session.lastFlowTime = now;
  } else if (now - session.lastFlowTime > MANQUE_EAU_TIMEOUT) {
    valveClose();
    sendAlert("manque_eau", "Débit nul. Possible manque d'eau.");
    displayMessage("MANQUE D'EAU", "Arrêt automatique.");
    beep(5, 300);
    setLed(RED);
    finalizeTransaction("interrompue_erreur");
    currentState = STATE_ALERT;
    stateEnterTime = now;
    return;
  }

  if (distributed >= session.requestedLitres) {
    valveClose();
    session.distributedLitres = session.requestedLitres;
    beep(3, 200);
    setLed(GREEN);
    finalizeTransaction("reussie");
    displaySuccess();
    currentState = STATE_DONE;
    stateEnterTime = now;
    return;
  }

  static unsigned long lastDisplay = 0;
  if (now - lastDisplay > 500) {
    displayDistributionProgress(distributed, session.requestedLitres);
    lastDisplay = now;
  }
}

void handleDone() {
  if (millis() - stateEnterTime > 10000)
    resetSession();
}

void handleError() {
  if (millis() - stateEnterTime > 5000)
    resetSession();
}

void checkSafety() {
  if (currentState != STATE_DISTRIBUTING) {
    static bool overflowWasHigh = false;
    static bool leakWasHigh = false;

    bool overflowNow = (digitalRead(PIN_OVERFLOW) == HIGH);
    bool leakNow = (digitalRead(PIN_LEAK_SENSOR) == HIGH);

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
    return;
  }

  if (digitalRead(PIN_OVERFLOW) == HIGH) {
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

  if (digitalRead(PIN_LEAK_SENSOR) == HIGH) {
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

    unsigned long pulsesInSec = currentPulses - lastPulseCount;
    float debitLMin = pulsesToLitres(pulsesInSec) * 60.0;
    lastPulseCount = currentPulses;
    lastFlowCheck = now;

    if (debitLMin > DEBIT_MAX_L_MIN && !alertSent) {
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
  beep(5, 200);
  setLed(RED);
  if (session.distributedLitres > 0.01)
    finalizeTransaction("interrompue_erreur");
  sendAlert("com_perdue", reason);
  resetSession();
}

void valveOpen() { digitalWrite(PIN_VALVE, LOW); }
void valveClose() { digitalWrite(PIN_VALVE, HIGH); }

float pulsesToLitres(unsigned long pulses) {
  return (float)pulses / PULSES_PER_LITRE;
}

enum Color { GREEN, ORANGE, RED, OFF };
void setLed(Color c) {
  digitalWrite(PIN_LED_GREEN, c == GREEN ? HIGH : LOW);
  digitalWrite(PIN_LED_ORANGE, c == ORANGE ? HIGH : LOW);
  digitalWrite(PIN_LED_RED, c == RED ? HIGH : LOW);
}
void ledsOff() { setLed(OFF); }

void beep(int count, int durationMs) {
  for (int i = 0; i < count; i++) {
    digitalWrite(PIN_BUZZER, HIGH);
    delay(durationMs);
    digitalWrite(PIN_BUZZER, LOW);
    if (i < count - 1)
      delay(durationMs / 2);
  }
}

void resetSession() {
  valveClose();
  ledsOff();
  session = Session();
  pinBuffer = "";
  alertSent = false;
  currentState = STATE_IDLE;
  setLed(GREEN);
  displayIdle();
}

// ---------------- AFFICHAGE SÉRIE ----------------
void displayIdle() { Serial.println(F("Borne Prête - En attente carte")); }
void displayWelcome() { Serial.println(F("Bienvenue")); }
void displayVolumePrompt() { Serial.println(F("Saisir Volume")); }
void displayPinProgress(int digits) { Serial.println(F("PIN saisi...")); }
void displayDistribution() { Serial.println(F("Distribution en cours...")); }
void displayDistributionProgress(float done, float total) {
  Serial.println(F("Avancement..."));
}
void displaySuccess() { Serial.println(F("Succès")); }
void displayMessage(const String &title, const String &msg) {
  Serial.println(title + " : " + msg);
}

// ---------------- HTTP ----------------
bool httpGet(const String &url, JsonDocument &response) {
  if (WiFi.status() != WL_CONNECTED)
    return false;
  HTTPClient http;
  http.begin(url);
  http.setTimeout(HTTP_TIMEOUT);
  http.addHeader("Accept", "application/json");
  int code = http.GET();
  String body = http.getString();
  http.end();
  if (code <= 0)
    return false;
  deserializeJson(response, body);
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
  deserializeJson(response, body);
  return true;
}

void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    if (millis() - start > WIFI_TIMEOUT) {
      setLed(ORANGE);
      return;
    }
  }
  setLed(GREEN);
}

// ---------------- MQTT ----------------
void connectMQTT() {
  if (mqttClient.connected())
    return;
  String clientId = "esp32_borne_" + String(BORNE_ID) + "_" +
                    String((uint32_t)ESP.getEfuseMac(), HEX);
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setKeepAlive(60);

  bool ok = (strlen(MQTT_USER) > 0)
                ? mqttClient.connect(clientId.c_str(), MQTT_USER, MQTT_PASS)
                : mqttClient.connect(clientId.c_str());
  if (ok) {
    String topicCmd = "eau/bornes/" + String(BORNE_ID) + "/commande";
    mqttClient.subscribe(topicCmd.c_str(), 1);
  }
}

void mqttCallback(char *topic, byte *payload, unsigned int length) {
  String msg;
  for (unsigned int i = 0; i < length; i++)
    msg += (char)payload[i];
  JsonDocument doc;
  if (deserializeJson(doc, msg) != DeserializationError::Ok)
    return;

  String typeCmd = doc["type"] | "";
  if (typeCmd == "stop_pompe") {
    mqttAdminOverride = false;
    emergencyStop("Arrêt manuel");
  } else if (typeCmd == "start_pompe") {
    if (currentState != STATE_IDLE && currentState != STATE_ALERT)
      return;
    mqttAdminOverride = true;
    valveOpen();
    setLed(ORANGE);
    beep(2, 150);
  }
}

void publishStatus() {
  if (!mqttClient.connected())
    return;
  String topicEtat = "eau/bornes/" + String(BORNE_ID) + "/etat";

  JsonDocument doc;
  doc["borne_id"] = BORNE_ID;
  doc["etat_vanne"] = (digitalRead(PIN_VALVE) == LOW) ? "ouverte" : "fermee";
  doc["fuite"] = (digitalRead(PIN_LEAK_SENSOR) == HIGH);
  doc["trop_plein"] = (digitalRead(PIN_OVERFLOW) == HIGH);
  doc["rssi"] = WiFi.RSSI();
  doc["etat_machine"] = (int)currentState;
  doc["admin_override"] = mqttAdminOverride;
  doc["ts"] = millis();

  String jsonStr;
  serializeJson(doc, jsonStr);
  mqttClient.publish(topicEtat.c_str(), jsonStr.c_str(), false);
}

void publishAlert(const String &type, const String &description) {
  if (!mqttClient.connected())
    return;
  String topicAlerte = "eau/bornes/" + String(BORNE_ID) + "/alerte";

  JsonDocument doc;
  doc["borne_id"] = BORNE_ID;
  doc["type"] = type;
  doc["description"] = description;
  doc["ts"] = millis();

  String jsonStr;
  serializeJson(doc, jsonStr);
  mqttClient.publish(topicAlerte.c_str(), jsonStr.c_str(), false);
}