<?php
/**
 * =================================================================
 * Script de Test Complet — API REST + Endpoints MQTT
 * =================================================================
 * Teste tous les endpoints du système de distribution d'eau RFID :
 *   - api/cards/verify
 *   - api/cards/verify-pin
 *   - api/transactions/store
 *   - api/alertes/store
 *   - api/bornes/ping
 *   - api/bornes/status-live   (nouveau — MQTT)
 *   - api/bornes/commande      (nouveau — MQTT)
 *
 * Usage : http://localhost/water/test_api_complet.php
 * =================================================================
 */

header('Content-Type: text/plain; charset=utf-8');

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  TESTS COMPLETS — API REST + Endpoints MQTT             ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

$baseUrl = "http://localhost/water/public/index.php";
$passed  = 0;
$failed  = 0;
$total   = 0;

// ── Helpers ─────────────────────────────────────────────────────────

function makeRequest(string $route, string $method, array $params = [], bool $json = false): array {
    global $baseUrl;
    $url = $baseUrl . "?route=" . $route;

    if (strtoupper($method) === 'GET' && !empty($params)) {
        $url .= "&" . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Partager les cookies de session (pour les endpoints admin-protégés)
    static $cookieFile = null;
    if ($cookieFile === null) {
        $cookieFile = tempnam(sys_get_temp_dir(), 'water_test_');
    }
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookieFile);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($json) {
            $body = json_encode($params);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
            ]);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
    }

    $body       = curl_exec($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['status_code' => 0, 'raw_body' => "cURL error: $curlError", 'json' => null];
    }

    return [
        'status_code' => $statusCode,
        'raw_body'    => $body,
        'json'        => json_decode($body, true),
    ];
}

function test(string $label, bool $condition, string $detail = ''): void {
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  ✅ [OK]    $label\n";
    } else {
        $failed++;
        echo "  ❌ [ÉCHEC] $label\n";
    }
    if ($detail) echo "             $detail\n";
}

// ── Pré-nettoyage : remettre la carte de test en état actif ─────────

require_once __DIR__ . '/app/Core/Database.php';
$db = \App\Core\Database::getInstance()->getConnection();

// Réactiver la carte 04A32B125C6D80 + recharger son solde à 150 DT
$db->exec("UPDATE cartes_rfid SET statut = 'active', solde = 150.000 WHERE uid = '04A32B125C6D80'");
$db->exec("UPDATE agriculteurs SET statut = 'actif' WHERE id = 1");
echo "🔧 Pré-nettoyage : carte 04A32B125C6D80 remise en active (solde 150.000 DT)\n\n";

// ════════════════════════════════════════════════════════════════════
// SECTION A : API REST existante (endpoints ESP32)
// ════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  SECTION A — API REST (endpoints ESP32)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// --- Test A1 : Vérification carte valide ---
echo "Test A1 : api/cards/verify — carte valide\n";
$r = makeRequest('api/cards/verify', 'GET', ['uid' => '04A32B125C6D80', 'borne_id' => 1]);
test('HTTP 200', $r['status_code'] === 200, "HTTP {$r['status_code']}");
test('status=success', ($r['json']['status'] ?? '') === 'success');
test('Solde retourné', isset($r['json']['card']['solde']),
    'Solde: ' . ($r['json']['card']['solde'] ?? 'N/A') . ' DT');
test('Prix/litre retourné', isset($r['json']['price_per_litre']),
    'Prix: ' . ($r['json']['price_per_litre'] ?? 'N/A') . ' DT/L');
test('Max volume retourné', isset($r['json']['max_volume_litres']),
    'Max: ' . ($r['json']['max_volume_litres'] ?? 'N/A') . ' L');
echo "\n";

// --- Test A2 : Vérification carte suspendue ---
echo "Test A2 : api/cards/verify — carte suspendue\n";
$r = makeRequest('api/cards/verify', 'GET', ['uid' => '04F5E8D21B7C90']);
test('HTTP 400', $r['status_code'] === 400, "HTTP {$r['status_code']}");
test('status=error', ($r['json']['status'] ?? '') === 'error');
echo "\n";

// --- Test A3 : Carte inexistante ---
echo "Test A3 : api/cards/verify — carte inconnue\n";
$r = makeRequest('api/cards/verify', 'GET', ['uid' => 'AAABBBCCC']);
test('HTTP 404', $r['status_code'] === 404, "HTTP {$r['status_code']}");
test('status=error', ($r['json']['status'] ?? '') === 'error');
echo "\n";

// --- Test A4 : UID manquant ---
echo "Test A4 : api/cards/verify — UID vide\n";
$r = makeRequest('api/cards/verify', 'GET', []);
test('HTTP 400', $r['status_code'] === 400, "HTTP {$r['status_code']}");
echo "\n";

// --- Test A5 : Ping borne ---
echo "Test A5 : api/bornes/ping — borne 1\n";
$r = makeRequest('api/bornes/ping', 'POST', ['borne_id' => 1]);
test('HTTP 200', $r['status_code'] === 200, "HTTP {$r['status_code']}");
test('status=success', ($r['json']['status'] ?? '') === 'success');
echo "\n";

// --- Test A6 : Ping borne inexistante ---
echo "Test A6 : api/bornes/ping — borne inexistante\n";
$r = makeRequest('api/bornes/ping', 'POST', ['borne_id' => 9999]);
test('HTTP 404', $r['status_code'] === 404, "HTTP {$r['status_code']}");
echo "\n";

// --- Test A7 : Alerte technique ---
echo "Test A7 : api/alertes/store — alerte fuite\n";
$r = makeRequest('api/alertes/store', 'POST', [
    'borne_id'    => 1,
    'type_alerte' => 'fuite',
    'description' => '[TEST AUTOMATIQUE] Simulation fuite — ' . date('H:i:s'),
]);
test('HTTP 200', $r['status_code'] === 200, "HTTP {$r['status_code']}");
test('status=success', ($r['json']['status'] ?? '') === 'success');
echo "\n";

// --- Test A8 : Transaction de distribution ---
echo "Test A8 : api/transactions/store — distribution 10L\n";
$soldeAvant = (float)$db->query("SELECT solde FROM cartes_rfid WHERE uid='04A32B125C6D80'")->fetchColumn();
echo "         Solde avant : {$soldeAvant} DT\n";

$r = makeRequest('api/transactions/store', 'POST', [
    'uid'              => '04A32B125C6D80',
    'borne_id'         => 1,
    'volume_demande'   => 10.00,
    'volume_distribue' => 10.00,
    'statut'           => 'reussie',
]);
test('HTTP 200', $r['status_code'] === 200, "HTTP {$r['status_code']}");
test('status=success', ($r['json']['status'] ?? '') === 'success');

$soldeApres = (float)$db->query("SELECT solde FROM cartes_rfid WHERE uid='04A32B125C6D80'")->fetchColumn();
$montantDebite = $r['json']['montant_debite'] ?? 0;
echo "         Montant débité : {$montantDebite} DT | Solde après : {$soldeApres} DT\n";
test('Solde cohérent en DB', abs(($soldeAvant - $montantDebite) - $soldeApres) < 0.001,
    "Attendu: " . ($soldeAvant - $montantDebite) . " | Réel: {$soldeApres}");
echo "\n";

// ════════════════════════════════════════════════════════════════════
// SECTION B : Nouveaux endpoints MQTT
// ════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  SECTION B — Endpoints MQTT (commande pompe & status-live)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// ── B0 : Connexion admin (session) pour les endpoints protégés ──
echo "Test B0 : Authentification admin (session PHP)\n";
$r = makeRequest('login', 'POST', [
    'email'    => 'admin@water.local',
    'password' => 'admin123',
]);
// Le login redirige vers dashboard (302→200) mais partage les cookies
$isLogged = ($r['status_code'] === 200 || $r['status_code'] === 302);
test('Login admin OK', $isLogged, "HTTP {$r['status_code']}");
echo "\n";

// --- Test B1 : api/bornes/status-live ---
echo "Test B1 : api/bornes/status-live — GET (état temps réel)\n";
$r = makeRequest('api/bornes/status-live', 'GET');
test('HTTP 200', $r['status_code'] === 200, "HTTP {$r['status_code']}");
test('status=success', ($r['json']['status'] ?? '') === 'success');
test('bornes[] présent', isset($r['json']['bornes']) && is_array($r['json']['bornes']),
    'Nombre de bornes: ' . count($r['json']['bornes'] ?? []));
test('alertes[] présent', isset($r['json']['alertes']) && is_array($r['json']['alertes']),
    'Alertes actives: ' . count($r['json']['alertes'] ?? []));
test('ts présent', isset($r['json']['ts']));

// Vérifier la structure d'une borne si disponible
if (!empty($r['json']['bornes'])) {
    $b = $r['json']['bornes'][0];
    echo "         → Borne #" . $b['id'] . " : " . $b['nom'] . "\n";
    echo "           Vanne: " . $b['etat_vanne'] . " | Débit: " . ($b['debit_instantane'] ?? '—') . " L/min\n";
    echo "           Fuite: " . ($b['capteur_fuite'] ? 'OUI' : 'non') . " | Trop-plein: " . ($b['capteur_trop_plein'] ? 'OUI' : 'non') . "\n";
    echo "           MQTT connecté: " . ($b['mqtt_connecte'] ? 'OUI' : 'non') . "\n";
    test('Champs borne complets', isset($b['etat_vanne']) && isset($b['capteur_fuite']));
}
echo "\n";

// --- Test B2 : api/bornes/commande — start_pompe ---
echo "Test B2 : api/bornes/commande — POST start_pompe (borne 1)\n";
$r = makeRequest('api/bornes/commande', 'POST', [
    'borne_id'       => 1,
    'type_commande'  => 'start_pompe',
], true);
test('HTTP 200', $r['status_code'] === 200, "HTTP {$r['status_code']}");
test('status=success', ($r['json']['status'] ?? '') === 'success');
$startCmdId = $r['json']['commande_id'] ?? null;
test('commande_id retourné', $startCmdId !== null, "ID: {$startCmdId}");

// Vérifier en DB
if ($startCmdId) {
    $cmd = $db->query("SELECT * FROM commandes_bornes WHERE id = {$startCmdId}")->fetch(PDO::FETCH_ASSOC);
    test('Commande en DB statut=en_attente', ($cmd['statut'] ?? '') === 'en_attente');
    test('Type commande DB=start_pompe', ($cmd['type_commande'] ?? '') === 'start_pompe');
}
echo "\n";

// --- Test B3 : api/bornes/commande — stop_pompe ---
echo "Test B3 : api/bornes/commande — POST stop_pompe (borne 1)\n";
$r = makeRequest('api/bornes/commande', 'POST', [
    'borne_id'       => 1,
    'type_commande'  => 'stop_pompe',
], true);
test('HTTP 200', $r['status_code'] === 200, "HTTP {$r['status_code']}");
test('status=success', ($r['json']['status'] ?? '') === 'success');
$stopCmdId = $r['json']['commande_id'] ?? null;
test('commande_id retourné', $stopCmdId !== null, "ID: {$stopCmdId}");
echo "\n";

// --- Test B4 : api/bornes/commande — type invalide ---
echo "Test B4 : api/bornes/commande — type_commande invalide\n";
$r = makeRequest('api/bornes/commande', 'POST', [
    'borne_id'       => 1,
    'type_commande'  => 'exploser_pompe',
], true);
test('HTTP 400', $r['status_code'] === 400, "HTTP {$r['status_code']}");
test('status=error', ($r['json']['status'] ?? '') === 'error');
echo "\n";

// --- Test B5 : api/bornes/commande — borne inexistante ---
echo "Test B5 : api/bornes/commande — borne inexistante\n";
$r = makeRequest('api/bornes/commande', 'POST', [
    'borne_id'       => 9999,
    'type_commande'  => 'start_pompe',
], true);
test('HTTP 404', $r['status_code'] === 404, "HTTP {$r['status_code']}");
echo "\n";

// --- Test B6 : api/bornes/commande — méthode GET (interdit) ---
echo "Test B6 : api/bornes/commande — GET (méthode interdite)\n";
$r = makeRequest('api/bornes/commande', 'GET');
test('HTTP 405', $r['status_code'] === 405, "HTTP {$r['status_code']}");
echo "\n";

// --- Test B7 : api/bornes/status-live — vérifier que les alertes du test s'affichent ---
echo "Test B7 : api/bornes/status-live — vérification des alertes récentes\n";
$r = makeRequest('api/bornes/status-live', 'GET');
$alertes = $r['json']['alertes'] ?? [];
$found = false;
foreach ($alertes as $a) {
    if (strpos($a['description'] ?? '', 'TEST AUTOMATIQUE') !== false) {
        $found = true;
        break;
    }
}
test('Alerte de test visible dans status-live', $found,
    $found ? "Alerte ID #{$a['id']} trouvée" : "Alerte de test non trouvée dans les résultats");
echo "\n";

// ── Nettoyage : résoudre les alertes de test ────────────────────────
$db->exec("UPDATE alertes SET resolu = 1 WHERE description LIKE '%TEST AUTOMATIQUE%'");
// Supprimer les commandes de test
if ($startCmdId) $db->exec("DELETE FROM commandes_bornes WHERE id = {$startCmdId}");
if ($stopCmdId)  $db->exec("DELETE FROM commandes_bornes WHERE id = {$stopCmdId}");

// ════════════════════════════════════════════════════════════════════
// SECTION C : Vérification structure DB (migrations self-healing)
// ════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  SECTION C — Vérification structure DB (migrations MQTT)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Table commandes_bornes
$tables = $db->query("SHOW TABLES LIKE 'commandes_bornes'")->fetchAll();
test('Table commandes_bornes existe', count($tables) > 0);

// Colonnes MQTT sur bornes
$cols = $db->query("SHOW COLUMNS FROM bornes")->fetchAll(PDO::FETCH_COLUMN, 0);
test('Colonne bornes.etat_vanne', in_array('etat_vanne', $cols));
test('Colonne bornes.debit_instantane', in_array('debit_instantane', $cols));
test('Colonne bornes.capteur_fuite', in_array('capteur_fuite', $cols));
test('Colonne bornes.capteur_trop_plein', in_array('capteur_trop_plein', $cols));
test('Colonne bornes.derniere_telemerie_mqtt', in_array('derniere_telemerie_mqtt', $cols));

// Colonne source sur alertes
$alertCols = $db->query("SHOW COLUMNS FROM alertes")->fetchAll(PDO::FETCH_COLUMN, 0);
test('Colonne alertes.source', in_array('source', $alertCols));
test('Colonne alertes.date_resolution', in_array('date_resolution', $alertCols));
echo "\n";

// ════════════════════════════════════════════════════════════════════
// RÉSUMÉ FINAL
// ════════════════════════════════════════════════════════════════════

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  RÉSUMÉ DES TESTS                                       ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
printf("║  ✅ Réussis  : %2d / %2d                                  ║\n", $passed, $total);
printf("║  ❌ Échoués  : %2d / %2d                                  ║\n", $failed, $total);
echo "╠══════════════════════════════════════════════════════════╣\n";
if ($failed === 0) {
    echo "║  🎉 TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS !            ║\n";
} else {
    echo "║  ⚠️  CERTAINS TESTS ONT ÉCHOUÉ — Vérifiez ci-dessus.    ║\n";
}
echo "╚══════════════════════════════════════════════════════════╝\n";
