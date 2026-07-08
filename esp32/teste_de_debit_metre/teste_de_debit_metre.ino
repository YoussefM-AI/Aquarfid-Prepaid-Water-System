/*
  Affichage du débit et du volume (litres) sur écran OLED I2C 1.3"
  - OLED I2C (SH1106, 128x64) : SCL -> GPIO22, SDA -> GPIO21
  - Débitmètre (capteur à effet Hall, type YF-S201/YF-S401) -> GPIO4

  IMPORTANT : GPIO4 dispose d'une résistance de pull-up INTERNE activable
  par logiciel (INPUT_PULLUP). Aucune résistance externe n'est nécessaire.
  (On évite GPIO35 car c'est une broche "input only" sans pull-up interne
  possible, ce qui causait de fausses impulsions dues au bruit électrique.)

  Cablage YF-S201 :
  - Rouge (VCC)    -> 5V
  - Noir (GND)     -> GND
  - Jaune (signal) -> GPIO4

  Bibliothèque nécessaire (à installer via le gestionnaire de bibliothèques) :
  - U8g2 (par oliver / olikraus)

  Si votre écran est un SSD1306 (et non un SH1106), remplacez la ligne
  de déclaration u8g2 par :
  U8G2_SSD1306_128X64_NONAME_F_HW_I2C u8g2(U8G2_R0, U8X8_PIN_NONE);
*/

#include <Wire.h>
#include <U8g2lib.h>

// Déclaration de l'écran OLED 1.3" (SH1106, 128x64) en I2C matériel
U8G2_SH1106_128X64_NONAME_F_HW_I2C u8g2(U8G2_R0, U8X8_PIN_NONE);

// ---------- Configuration du débitmètre ----------
#define PIN_DEBITMETRE 4

// Facteur de calibration : nombre d'impulsions par litre
// (YF-S201 : environ 450 pulses/litre)
float facteurCalibration = 450.0;

volatile unsigned long compteurImpulsions = 0;
unsigned long dernierTemps = 0;

float debitLparMin = 0.0;   // débit instantané en L/min
float volumeTotal = 0.0;    // volume total en litres

// ---------- Interruption avec anti-rebond ----------
volatile unsigned long dernierTempsImpulsion = 0;
const unsigned long DELAI_ANTIREBOND_MICROS = 2000; // 2 ms mini entre 2 impulsions valides

void IRAM_ATTR compterImpulsion() {
  unsigned long maintenant = micros();
  if (maintenant - dernierTempsImpulsion > DELAI_ANTIREBOND_MICROS) {
    compteurImpulsions++;
    dernierTempsImpulsion = maintenant;
  }
}

// ---------- Fonction d'affichage OLED ----------
void afficherEcran() {
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_ncenB08_tr);

  u8g2.drawStr(0, 12, "Debit (L/min):");
  u8g2.setFont(u8g2_font_ncenB14_tr);
  char bufDebit[10];
  dtostrf(debitLparMin, 4, 2, bufDebit);
  u8g2.drawStr(0, 32, bufDebit);

  u8g2.setFont(u8g2_font_ncenB08_tr);
  u8g2.drawStr(0, 48, "Volume total (L):");
  u8g2.setFont(u8g2_font_ncenB14_tr);
  char bufVol[10];
  dtostrf(volumeTotal, 4, 3, bufVol);
  u8g2.drawStr(0, 62, bufVol);

  u8g2.sendBuffer();
}

void setup() {
  Serial.begin(115200);

  // Initialisation I2C sur les broches personnalisées (SDA=21, SCL=22)
  Wire.begin(21, 22);
  u8g2.begin();

  // ---------- Message de démarrage ----------
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_ncenB08_tr);
  u8g2.drawStr(0, 25, "Debut du calcul");
  u8g2.drawStr(0, 40, "du litrage...");
  u8g2.sendBuffer();
  delay(2000);

  afficherEcran();

  // Configuration de la broche du débitmètre
  pinMode(PIN_DEBITMETRE, INPUT_PULLUP); // pull-up interne activée, pas de résistance externe requise
  attachInterrupt(digitalPinToInterrupt(PIN_DEBITMETRE), compterImpulsion, FALLING);

  dernierTemps = millis();
}

void loop() {
  // Calcul toutes les secondes
  if (millis() - dernierTemps >= 1000) {

    // On coupe temporairement les interruptions pour lire la valeur en toute sécurité
    detachInterrupt(digitalPinToInterrupt(PIN_DEBITMETRE));

    unsigned long tempsEcoule = millis() - dernierTemps;
    debitLparMin = (compteurImpulsions / facteurCalibration) * (60000.0 / tempsEcoule);

    // Filtre : le YF-S201 n'est pas fiable en dessous de 1 L/min.
    // On ignore le bruit résiduel pour éviter de fausser le volume total.
    const float SEUIL_MIN_LPM = 1.0;
    float volumeIntervalle = 0.0;
    if (debitLparMin >= SEUIL_MIN_LPM) {
      volumeIntervalle = compteurImpulsions / facteurCalibration;
      volumeTotal += volumeIntervalle;
    } else {
      debitLparMin = 0.0; // on affiche 0 plutôt qu'une valeur non fiable
    }

    compteurImpulsions = 0;
    dernierTemps = millis();

    attachInterrupt(digitalPinToInterrupt(PIN_DEBITMETRE), compterImpulsion, FALLING);

    // ---------- Affichage OLED ----------
    afficherEcran();

    // ---------- Affichage sur le moniteur série (debug) ----------
    Serial.print("Debit: ");
    Serial.print(debitLparMin);
    Serial.print(" L/min | Volume total: ");
    Serial.print(volumeTotal);
    Serial.println(" L");
  }
}