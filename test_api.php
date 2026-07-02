<?php
/**
 * Script de Test des API pour le module ESP32
 * Ce script effectue de réelles requêtes HTTP vers localhost pour valider les endpoints API.
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Démarrage des Tests des API ESP32 via HTTP ===\n\n";

$baseUrl = "http://localhost/water/public/index.php";

/**
 * Effectue un appel HTTP vers le routeur index.php
 */
function makeRequest(string $route, string $method, array $params = []): array {
    global $baseUrl;
    $url = $baseUrl . "?route=" . $route;
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => strtoupper($method),
            'ignore_errors' => true // Permet de récupérer la réponse même si le code HTTP est 4xx ou 5xx
        ]
    ];
    
    if (strtoupper($method) === 'POST') {
        $options['http']['content'] = http_build_query($params);
    } else {
        if (!empty($params)) {
            $url .= "&" . http_build_query($params);
        }
    }
    
    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    $statusCode = 0;
    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
        $statusCode = (int)$match[1];
    }
    
    return [
        'status_code' => $statusCode,
        'raw_body'    => $response,
        'json'        => json_decode($response, true)
    ];
}

// ----------------------------------------------------
// TEST 1 : Vérification d'une carte active existante
// ----------------------------------------------------
echo "Test 1 : Vérification d'une carte valide (UID: 04A32B125C6D80)\n";
$res1 = makeRequest('api/cards/verify', 'GET', ['uid' => '04A32B125C6D80', 'borne_id' => 1]);
if ($res1['status_code'] === 200 && isset($res1['json']['status']) && $res1['json']['status'] === 'success') {
    echo "  [OK] Carte validée avec succès.\n";
    echo "       Nom : " . $res1['json']['card']['farmer_prenom'] . " " . $res1['json']['card']['farmer_nom'] . "\n";
    echo "       Solde : " . $res1['json']['card']['solde'] . " DT\n";
    echo "       Volume Max : " . $res1['json']['max_volume_litres'] . " Litres (Tarif: " . $res1['json']['price_per_litre'] . " DT/L)\n";
} else {
    echo "  [ÉCHEC] Statut HTTP: " . $res1['status_code'] . "\n";
    echo "          Réponse: " . $res1['raw_body'] . "\n";
}
echo "\n";


// ----------------------------------------------------
// TEST 2 : Vérification d'une carte suspendue
// ----------------------------------------------------
echo "Test 2 : Vérification d'une carte bloquée/suspendue (UID: 04F5E8D21B7C90)\n";
$res2 = makeRequest('api/cards/verify', 'GET', ['uid' => '04F5E8D21B7C90']);
if ($res2['status_code'] === 400 && isset($res2['json']['status']) && $res2['json']['status'] === 'error') {
    echo "  [OK] La carte bloquée a été correctement rejetée.\n";
    echo "       Message d'erreur : " . $res2['json']['message'] . "\n";
} else {
    echo "  [ÉCHEC] Statut HTTP: " . $res2['status_code'] . "\n";
    echo "          Réponse: " . $res2['raw_body'] . "\n";
}
echo "\n";


// ----------------------------------------------------
// TEST 3 : Vérification d'une carte inexistante
// ----------------------------------------------------
echo "Test 3 : Vérification d'une carte inconnue (UID: INCONNU123)\n";
$res3 = makeRequest('api/cards/verify', 'GET', ['uid' => 'INCONNU123']);
if ($res3['status_code'] === 404 && isset($res3['json']['status']) && $res3['json']['status'] === 'error') {
    echo "  [OK] La carte inconnue a été rejetée avec un code 404.\n";
    echo "       Message d'erreur : " . $res3['json']['message'] . "\n";
} else {
    echo "  [ÉCHEC] Statut HTTP: " . $res3['status_code'] . "\n";
    echo "          Réponse: " . $res3['raw_body'] . "\n";
}
echo "\n";


// ----------------------------------------------------
// TEST 4 : Ping de borne
// ----------------------------------------------------
echo "Test 4 : Ping de la borne 1\n";
$res4 = makeRequest('api/bornes/ping', 'POST', ['borne_id' => 1]);
if ($res4['status_code'] === 200 && isset($res4['json']['status']) && $res4['json']['status'] === 'success') {
    echo "  [OK] Ping enregistré avec succès.\n";
} else {
    echo "  [ÉCHEC] Statut HTTP: " . $res4['status_code'] . "\n";
    echo "          Réponse: " . $res4['raw_body'] . "\n";
}
echo "\n";


// ----------------------------------------------------
// TEST 5 : Enregistrement d'alerte
// ----------------------------------------------------
echo "Test 5 : Remontée d'alerte de fuite d'eau (Borne 1)\n";
$res5 = makeRequest('api/alertes/store', 'POST', [
    'borne_id' => 1,
    'type_alerte' => 'fuite',
    'description' => 'Test simulation : Débit suspect persistant.'
]);
if ($res5['status_code'] === 200 && isset($res5['json']['status']) && $res5['json']['status'] === 'success') {
    echo "  [OK] Alerte technique créée avec succès.\n";
} else {
    echo "  [ÉCHEC] Statut HTTP: " . $res5['status_code'] . "\n";
    echo "          Réponse: " . $res5['raw_body'] . "\n";
}
echo "\n";


// ----------------------------------------------------
// TEST 6 : Simulation d'une transaction de distribution
// ----------------------------------------------------
echo "Test 6 : Distribution d'eau et débit de solde\n";

// Charger les modèles localement pour vérifier les valeurs en DB
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Models/BaseModel.php';
require_once __DIR__ . '/app/Models/RFIDCard.php';

try {
    $db = \App\Core\Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT solde FROM cartes_rfid WHERE uid = '04A32B125C6D80'");
    $stmt->execute();
    $soldeAvant = (float)$stmt->fetchColumn();
    echo "  Solde initial : " . $soldeAvant . " DT\n";

    $volumeDistribue = 10.0; // 10 Litres à 0.2 DT = 2.0 DT de débit
    echo "  Action : Enregistrement d'une distribution de {$volumeDistribue} Litres...\n";

    $res6 = makeRequest('api/transactions/store', 'POST', [
        'uid' => '04A32B125C6D80',
        'borne_id' => 1,
        'volume_demande' => 10.00,
        'volume_distribue' => $volumeDistribue,
        'statut' => 'reussie'
    ]);

    if ($res6['status_code'] === 200 && isset($res6['json']['status']) && $res6['json']['status'] === 'success') {
        echo "  [OK] Transaction enregistrée.\n";
        echo "       Coût débité : " . $res6['json']['montant_debite'] . " DT\n";
        echo "       Nouveau solde renvoyé : " . $res6['json']['nouveau_solde'] . " DT\n";
        
        // Vérifier en base que le solde a diminué
        $stmt->execute();
        $soldeApres = (float)$stmt->fetchColumn();
        echo "  Vérification base de données :\n";
        echo "       Solde en DB après débit : " . $soldeApres . " DT\n";
        
        if (abs(($soldeAvant - $res6['json']['montant_debite']) - $soldeApres) < 0.001) {
            echo "       [SUCCÈS] Le solde a été mis à jour correctement en DB !\n";
        } else {
            echo "       [ERREUR] Incohérence de solde en base de données.\n";
        }
    } else {
        echo "  [ÉCHEC] Statut HTTP: " . $res6['status_code'] . "\n";
        echo "          Réponse: " . $res6['raw_body'] . "\n";
    }
} catch (\Exception $e) {
    echo "  [ERREUR] Impossible de vérifier en DB : " . $e->getMessage() . "\n";
}

echo "\n=== Fin des Tests ===\n";
