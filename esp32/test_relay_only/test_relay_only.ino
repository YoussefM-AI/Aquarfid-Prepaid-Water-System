/*
  Test simple pour déterminer l'état électrique du relais
  Téléversez ce code et observez le comportement de la pompe et de la LED du relais.
*/

#include <Arduino.h>

#define PIN_POMPE 16

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println(F("\n========================================"));
  Serial.println(F("       DIAGNOSTIC PHYSIQUE DU RELAIS    "));
  Serial.println(F("========================================"));
}

void loop() {
  // État 1 : Sortie active à 0V
  Serial.println(F("1. Broche en SORTIE à l'état BAS (LOW / 0V)"));
  pinMode(PIN_POMPE, OUTPUT);
  digitalWrite(PIN_POMPE, LOW);
  delay(5000);

  // État 2 : Sortie active à 3.3V
  Serial.println(F("2. Broche en SORTIE à l'état HAUT (HIGH / 3.3V)"));
  pinMode(PIN_POMPE, OUTPUT);
  digitalWrite(PIN_POMPE, HIGH);
  delay(5000);

  // État 3 : Haute impédance (flottant)
  Serial.println(F("3. Broche en ENTRÉE (INPUT / Haute impédance / Flottant)"));
  pinMode(PIN_POMPE, INPUT);
  delay(5000);
  
  Serial.println(F("----------------------------------------"));
}
