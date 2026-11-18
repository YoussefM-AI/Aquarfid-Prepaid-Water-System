# 💧 AquaRFID — Smart Prepaid Water Distribution System

> An IoT-powered water distribution platform for agricultural use. Farmers tap an RFID card on a field station, the system validates their prepaid credit, opens the valve, measures dispensed water, and automatically debits their account — all in real time.

---

## 📸 System Overview

```
┌───────────────────────────────────────────────────────────────┐
│                     FIELD (IoT Layer)                         │
│   ┌──────────────────────────────────────────────────────┐    │
│   │  ESP32 Station                                       │    │
│   │  • MFRC522 RFID reader  • YF-S201 flow meter        │    │
│   │  • Solenoid valve       • Keypad (PIN entry)         │    │
│   │  • WiFi  ──► HTTP API + MQTT telemetry              │    │
│   └──────────────────────────────────────────────────────┘    │
└───────────────────────────────────────────────────────────────┘
                            │  HTTP REST + MQTT
                            ▼
┌───────────────────────────────────────────────────────────────┐
│                   SERVER (Backend Layer)                      │
│                                                               │
│   Apache / PHP 8.2 (MVC)   ◄──► MariaDB (water_db)          │
│                                                               │
│   • REST API  → card verify, debit, alerts, ping             │
│   • Admin Dashboard → farmers, cards, transactions           │
│   • MQTT Worker (daemon) → real-time telemetry bridge        │
│   • Mosquitto Broker → station↔dashboard WebSocket comm.     │
└───────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌───────────────────────────────────────────────────────────────┐
│               ADMIN DASHBOARD (Web UI)                        │
│   • Live station status (valve, flow rate, alerts)           │
│   • Manage farmers & RFID cards                              │
│   • Recharge card balances                                   │
│   • Transaction history & reports                            │
└───────────────────────────────────────────────────────────────┘
```

---

## ✨ Features

| Category | Details |
|---|---|
| **RFID Authentication** | Mifare card scan → PIN verification → balance check |
| **Prepaid Billing** | Per-litre pricing, automatic debit at session end |
| **Real-time Telemetry** | Flow rate, valve state, RSSI streamed via MQTT |
| **Admin Dashboard** | Farmer/card management, live station control, reports |
| **Alert System** | Leak detection, overflow, lost signal — logged instantly |
| **Remote Control** | Start/stop valve from dashboard via MQTT commands |
| **Multi-Station** | Unlimited stations, each with its own API key |

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| **Microcontroller** | ESP32 (Arduino framework) |
| **Backend** | PHP 8.2 — custom MVC (no framework) |
| **Database** | MariaDB (via PDO, prepared statements) |
| **Real-time** | MQTT (Mosquitto broker) + WebSockets for dashboard |
| **HTTP API** | REST/JSON — card verify, debit, alerts, ping |
| **UI** | Tailwind CSS |
| **Dependencies** | Composer (`php-mqtt/client`) |
| **Dev Environment** | Laragon (Windows) |

---

## 📁 Project Structure

```
water/
├── app/
│   ├── Controllers/        # HTTP request handlers (Auth, Card, Farmer, Dashboard, API)
│   ├── Core/               # Framework kernel (Router, Database PDO, Session)
│   ├── Models/             # Data access layer (Farmer, RFIDCard, Transaction, Borne, Alerte…)
│   └── Views/              # HTML templates (admin dashboard, auth, errors)
│       └── layout/         # Shared header / footer
│
├── config/
│   ├── config.example.php  # ✅ Copy → config.php and fill in your values
│   └── config.php          # 🔒 Gitignored — your local credentials
│
├── esp32/
│   └── borne_rfid_eau/     # Arduino sketch for the field station
│       └── borne_rfid_eau.ino
│
├── workers/
│   └── mqtt_worker.php     # PHP daemon — bridges MQTT ↔ MariaDB
│
├── public/
│   └── index.php           # Front controller (entry point for Apache)
│
├── db/
│   └── data.sql            # Seed data (optional)
│
├── database.sql            # Full DB schema + demo seed data
├── composer.json           # PHP dependencies
├── API_DOCUMENTATION.md    # REST API reference (ESP32 endpoints)
└── MQTT_DOCUMENTATION.md   # MQTT topics, payloads & broker setup guide
```

---

## 🚀 Getting Started

### Prerequisites

- [Laragon](https://laragon.org/) (or any LAMP/WAMP stack with PHP ≥ 8.2 + MariaDB)
- [Composer](https://getcomposer.org/)
- [Mosquitto MQTT Broker](https://mosquitto.org/download/)
- Arduino IDE with ESP32 board support (for the hardware firmware)

---

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/water.git
cd water
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Configure the Application

```bash
cp config/config.example.php config/config.php
```

Edit `config/config.php` and set your database credentials and app URL.

### 4. Set Up the Database

Import the schema and demo data into MariaDB:

```bash
mysql -u root -p < database.sql
```

> **Default demo admin:** `admin@water.local` / password: `admin123`  
> Change this immediately after first login.

### 5. Configure Mosquitto Broker

Edit `C:\Program Files\mosquitto\mosquitto.conf` (Windows) and add:

```ini
listener 1883 0.0.0.0
allow_anonymous false
password_file C:\Program Files\mosquitto\passwd
```

Create a user for the worker:

```cmd
mosquitto_passwd -c "C:\Program Files\mosquitto\passwd" water_user
```

Then start the Mosquitto service (`services.msc`).

### 6. Set Environment Variables for the MQTT Worker

The worker reads credentials from environment variables to avoid hardcoding them:

```bash
# Windows (PowerShell)
$env:MQTT_USERNAME = "water_user"
$env:MQTT_PASSWORD = "your_mqtt_password"
```

Or create a `.env` file and load it in your shell before running the worker.

### 7. Launch the MQTT Worker

```bash
php workers/mqtt_worker.php
```

For background / production use, register it as a Windows service with [NSSM](https://nssm.cc/):

```cmd
nssm install WaterMqttWorker
# Path: C:\laragon\bin\php\php-8.x\php.exe
# Arguments: workers/mqtt_worker.php
nssm start WaterMqttWorker
```

### 8. Configure the Virtual Host (Laragon)

Point your virtual host document root to the `public/` directory so that `http://water.test` maps to `public/index.php`.

---

## 🔌 ESP32 Firmware

Open `esp32/borne_rfid_eau/borne_rfid_eau.ino` in the Arduino IDE.

Before flashing, update the configuration block at the top of the file:

```cpp
// --- WiFi ---
const char *WIFI_SSID     = "YOUR_WIFI_SSID";
const char *WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

// --- Backend API ---
const char *SERVER_BASE   = "http://YOUR_SERVER_IP/water/public/index.php";
const int   BORNE_ID      = 1;   // This station's ID in the database

// --- MQTT Broker ---
const char *MQTT_BROKER   = "YOUR_SERVER_IP";
const char *MQTT_USER     = "water_user";
const char *MQTT_PASS     = "YOUR_MQTT_PASSWORD";
```

#### Required Arduino Libraries

Install via the **Library Manager** (`Sketch → Include Library → Manage Libraries`):

| Library | Purpose |
|---|---|
| `ArduinoJson` | JSON serialization |
| `MFRC522` | RFID reader |
| `PubSubClient` | MQTT client |
| `Keypad` | 4×4 keypad input |
| `HTTPClient` | REST API calls |

---

## 📡 API Reference

The ESP32 communicates with the backend via four REST endpoints:

| Endpoint | Method | Purpose |
|---|---|---|
| `api/cards/verify` | POST | Authenticate RFID card, return balance & price/litre |
| `api/transactions/store` | POST | Record water session and debit the card |
| `api/alertes/store` | POST | Report a hardware alert (leak, overflow…) |
| `api/bornes/ping` | GET/POST | Keep-alive heartbeat |

👉 Full request/response schemas: **[API_DOCUMENTATION.md](./API_DOCUMENTATION.md)**

---

## 📶 MQTT Topics

| Topic | Direction | Purpose |
|---|---|---|
| `water/station{id}/status` | ESP32 → Broker | Online/offline status |
| `water/station{id}/flow` | ESP32 → Broker | Instantaneous flow rate (L/min) |
| `water/station{id}/heartbeat` | ESP32 → Broker | Periodic RSSI + uptime |
| `water/station{id}/pump` | ESP32 → Broker | Valve state (ON/OFF) |
| `water/station{id}/command` | Broker → ESP32 | Remote valve control |
| `water/station{id}/log` | ESP32 → Broker | Error & alert messages |

👉 Full payload schemas and broker setup: **[MQTT_DOCUMENTATION.md](./MQTT_DOCUMENTATION.md)**

---

## 🗄️ Database Schema

Five core tables:

```
agriculteurs   — Farmer accounts (name, phone, status)
cartes_rfid    — RFID cards linked to farmers (UID, balance, status)
bornes         — Water stations (location, connection status, API key)
transactions   — Water dispensing history (volume, cost, timestamp)
alertes        — Technical alerts (type, description, resolved flag)
```

Extended tables for MQTT real-time layer:

```
mqtt_messages  — Log of all MQTT messages in/out
device_status  — Station telemetry snapshots (RSSI, uptime, volume)
pump_logs      — Valve open/close event log
commandes_bornes — Remote command queue (dashboard → station)
```

---

## 🔒 Security Notes

- `config/config.php` is **gitignored** — never commit it.
- RFID UID lookups use **PDO prepared statements** to prevent SQL injection.
- Station API keys are stored per-borne in the database; rotate them after setup.
- Change the default admin password in `database.sql` before deploying.
- In production, serve behind HTTPS and use authenticated MQTT (no anonymous access).

---

## 📄 License

This project was built as an academic / engineering prototype. No license is currently applied — contact the authors before reuse.

---

## 👥 Team

| Name | Role |
|---|---|
| **Ghofran** | Software — PHP MVC backend, REST API, admin dashboard, MQTT worker |
| **Nesrine** | Hardware — Hydraulics, sensors, solenoid valve, flow meter |
| **Rania** | Hardware — Electrical cabinet, PLC integration, RFID/HMI mounting |
