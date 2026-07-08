#include <Arduino.h>
#include <WiFi.h>

const char* WIFI_SSID     = "TP-Link_71FA";
const char* WIFI_PASSWORD = "46116147";

void afficherStatutWiFi(int status) {
  switch (status) {
    case WL_IDLE_STATUS:     Serial.println("IDLE_STATUS"); break;
    case WL_NO_SSID_AVAIL:   Serial.println("NO_SSID_AVAIL -> Reseau introuvable !"); break;
    case WL_SCAN_COMPLETED:  Serial.println("SCAN_COMPLETED"); break;
    case WL_CONNECTED:       Serial.println("CONNECTED"); break;
    case WL_CONNECT_FAILED:  Serial.println("CONNECT_FAILED -> Mot de passe incorrect ?"); break;
    case WL_CONNECTION_LOST: Serial.println("CONNECTION_LOST"); break;
    case WL_DISCONNECTED:    Serial.println("DISCONNECTED"); break;
    default:                 Serial.println("Code inconnu: " + String(status)); break;
  }
}

void scannerReseaux() {
  Serial.println("\n=== Scan des reseaux WiFi visibles ===");
  int n = WiFi.scanNetworks();
  if (n == 0) {
    Serial.println("Aucun reseau trouve !");
  } else {
    for (int i = 0; i < n; i++) {
      Serial.print(i + 1);
      Serial.print(": ");
      Serial.print(WiFi.SSID(i));
      Serial.print(" (");
      Serial.print(WiFi.RSSI(i));
      Serial.print(" dBm) ");
      Serial.println((WiFi.encryptionType(i) == WIFI_AUTH_OPEN) ? "Ouvert" : "Securise");
    }
  }
  Serial.println("======================================\n");
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  delay(100);

  // Etape 1 : scanner les reseaux visibles AVANT de tenter la connexion
  scannerReseaux();

  Serial.println("Tentative de connexion a: " + String(WIFI_SSID));
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
}

void loop() {
  int status = WiFi.status();

  Serial.print("Statut WiFi: ");
  afficherStatutWiFi(status);

  if (status == WL_CONNECTED) {
    Serial.println("CONNECTE ! Adresse IP: " + WiFi.localIP().toString());
    Serial.println("Force du signal: " + String(WiFi.RSSI()) + " dBm");
  }

  delay(2000); // affiche le statut toutes les 2 secondes, en continu
}