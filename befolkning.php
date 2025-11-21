<?php

/**
 * Laddar miljövariabler från .env-fil
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception(".env-filen hittades inte: $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Hoppa över kommentarer
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parsa KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Sätt som miljövariabel
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

/**
 * Hämtar befolkningsdata från Falkenbergs API
 */
function hamtaBefolkning() {
    $url = getenv('FB_API_URL');
    $database = getenv('FB_DATABASE');
    $user = getenv('FB_USER');
    $password = getenv('FB_PASSWORD');

    // Bygg URL med query parameters
    $fullUrl = $url . "?Database=$database&User=$user&Password=$password";

    // Initiera cURL
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: text/plain'
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        throw new Exception("cURL-fel: $error");
    }

    if ($httpCode !== 200) {
        throw new Exception("HTTP-fel: $httpCode");
    }

    return $response;
}

/**
 * Sparar befolkningsdata till Directus
 */
function sparaTillDirectus($antal) {
    $url = getenv('DIRECTUS_URL');
    $token = getenv('DIRECTUS_TOKEN');

    // Initiera cURL
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{ "befolkning": ' . intval($antal) . ' }',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        throw new Exception("cURL-fel vid sparande till Directus: $error");
    }

    if ($httpCode !== 200 && $httpCode !== 201) {
        throw new Exception("HTTP-fel vid sparande till Directus: $httpCode - $response");
    }

    return $response;
}

/**
 * Skickar healthcheck-ping
 *
 * @param string $type 'start', 'success', eller 'fail'
 * @param string|null $message Felmeddelande (endast för 'fail')
 */
function healthcheckPing($type = 'success', $message = null) {
    $baseUrl = getenv('HEALTHCHECK_URL');

    if (!$baseUrl) {
        return; // Healthcheck inte konfigurerad
    }

    // Bygg rätt URL beroende på typ
    switch ($type) {
        case 'start':
            $url = $baseUrl . '/start';
            break;
        case 'fail':
            $url = $baseUrl . '/fail';
            break;
        case 'success':
        default:
            $url = $baseUrl;
            break;
    }

    $ch = curl_init();

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ];

    // Lägg till body för fail-meddelande
    if ($type === 'fail' && $message) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $message;
        $options[CURLOPT_HTTPHEADER] = ['Content-Type: text/plain'];
    }

    curl_setopt_array($ch, $options);
    curl_exec($ch);
    curl_close($ch);
}

// Huvudprogram
try {
    // Ladda miljövariabler
    loadEnv(__DIR__ . '/.env');

    // Healthcheck: Start
    healthcheckPing('start');
    echo "Healthcheck: Start signal skickad\n";

    // Hämta befolkningsdata
    echo "Hämtar befolkningsdata från Falkenbergs API...\n";
    $response = hamtaBefolkning();

    // Parsa JSON-svar
    $data = json_decode($response, true);

    if (!$data || !isset($data['data']['antalFolkbokforda'])) {
        throw new Exception("Kunde inte parsa API-svar: $response");
    }

    $antalFolkbokforda = $data['data']['antalFolkbokforda'];
    echo "Befolkning i Falkenberg: $antalFolkbokforda personer\n";

    // Spara till Directus
    echo "Sparar till Directus...\n";
    $directusResponse = sparaTillDirectus($antalFolkbokforda);
    echo "Sparat till Directus!\n";
    echo "Directus-svar: $directusResponse\n";

    // Healthcheck: Success
    healthcheckPing('success');
    echo "Healthcheck: Success signal skickad\n";

} catch (Exception $e) {
    $errorMessage = "Fel: " . $e->getMessage();
    echo "$errorMessage\n";

    // Healthcheck: Failure med felmeddelande
    healthcheckPing('fail', $errorMessage);
    echo "Healthcheck: Failure signal skickad\n";

    exit(1);
}