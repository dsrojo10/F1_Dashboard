<?php
/**
 * OpenF1 API Proxy
 * Este archivo sirve como intermediario entre la interfaz web y la API de OpenF1
 */

// Nueva función para verificar si la API está activa y enviando datos
function checkApiStatus() {
    $baseUrl = 'https://api.openf1.org/v1/';
    $endpoints = ['sessions', 'car_data', 'timing_data', 'weather_data'];
    $result = [
        'api_available' => false,
        'active_session' => false,
        'latest_data' => null,
        'data_timestamp' => null
    ];
    
    // Verificar si la API está respondiendo
    $ch = curl_init($baseUrl . 'sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300 && !empty($response)) {
        $result['api_available'] = true;
        $sessions = json_decode($response, true);
        
        // Buscar la sesión más reciente
        if (!empty($sessions)) {
            usort($sessions, function($a, $b) {
                return strtotime($b['date_start']) - strtotime($a['date_start']);
            });
            $latestSession = $sessions[0];
            
            // Verificar si hay una sesión activa actualmente
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $sessionStart = new DateTime($latestSession['date_start']);
            $sessionEnd = isset($latestSession['date_end']) && !empty($latestSession['date_end']) 
                ? new DateTime($latestSession['date_end']) 
                : null;
            
            if ($sessionEnd === null || $now <= $sessionEnd) {
                $result['active_session'] = true;
                
                // Corregir el problema de "undefined"
                if (!isset($latestSession['meeting_name']) || empty($latestSession['meeting_name']) || $latestSession['meeting_name'] === "undefined") {
                    // Modificar para los tests de pretemporada
                    if (strpos(strtolower($latestSession['session_name']), 'day') !== false) {
                        $latestSession['meeting_name'] = "F1 Testing 2025";
                    } else {
                        $latestSession['meeting_name'] = "Sesión Actual";
                    }
                }
                
                $result['latest_session'] = $latestSession;
                
                // Verificar múltiples tipos de datos (car_data, timing_data, etc.)
                $sessionKey = $latestSession['session_key'];
                $dataTypes = ['car_data', 'timing_data', 'position_data', 'driver_list'];
                $foundData = false;
                
                foreach ($dataTypes as $dataType) {
                    $ch = curl_init($baseUrl . $dataType . '?session_key=' . $sessionKey . '&limit=1');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    $dataResponse = curl_exec($ch);
                    curl_close($ch);
                    
                    $responseData = json_decode($dataResponse, true);
                    if (!empty($responseData)) {
                        $result['latest_data'] = $responseData[0];
                        $result['data_type'] = $dataType;
                        if (isset($responseData[0]['date'])) {
                            $result['data_timestamp'] = $responseData[0]['date'];
                            // Verificar si los datos son recientes (últimos 30 minutos para tests)
                            $dataTime = new DateTime($responseData[0]['date']);
                            $diff = $now->getTimestamp() - $dataTime->getTimestamp();
                            $result['data_is_recent'] = ($diff <= 1800); // 30 minutos = 1800 segundos
                        } else {
                            // Algunos endpoint no tienen 'date', usamos la fecha actual
                            $result['data_timestamp'] = $now->format('Y-m-d\TH:i:s.u\Z');
                            $result['data_is_recent'] = true;
                        }
                        $foundData = true;
                        break; // Salir del bucle si encontramos datos
                    }
                }
                
                if (!$foundData) {
                    // Verificar específicamente las sesiones disponibles para los tests
                    $ch = curl_init($baseUrl . 'sessions?meeting_key=' . $latestSession['meeting_key']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    $sessionsResponse = curl_exec($ch);
                    curl_close($ch);
                    
                    $sessionsData = json_decode($sessionsResponse, true);
                    if (!empty($sessionsData)) {
                        $result['available_sessions'] = count($sessionsData);
                        $result['testing_active'] = true;
                    }
                }
            }
        }
    }
    
    return $result;
}

// Verificar si se está solicitando un chequeo de estado
if (isset($_GET['endpoint']) && $_GET['endpoint'] === 'check_status') {
    header('Content-Type: application/json');
    echo json_encode(checkApiStatus());
    exit;
}

// Continúa con el código original
if (!isset($_GET['endpoint'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Se requiere especificar un endpoint']);
    exit;
}

// Obtener parámetros
$endpoint = $_GET['endpoint'];
$params = isset($_GET['params']) ? $_GET['params'] : '';

// Construir la URL de la API
$baseUrl = 'https://api.openf1.org/v1/';
$url = $baseUrl . $endpoint;

// Agregar parámetros si existen
if (!empty($params)) {
    $url .= '?' . $params;
}

// Configurar la solicitud cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'User-Agent: OpenF1-PHP-Viewer/1.0'
]);

// Realizar la solicitud
$response = curl_exec($ch);

// Verificar errores
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al realizar la solicitud a la API de OpenF1',
        'curl_error' => curl_error($ch)
    ]);
    exit;
}

// Obtener el código de respuesta HTTP
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Cerrar la sesión cURL
curl_close($ch);

// Manejar la respuesta según el código HTTP
if ($httpCode >= 200 && $httpCode < 300) {
    // Agregar encabezados para evitar problemas de CORS
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    // Si estamos en endpoint 'latest', manejar de forma especial
    if ($endpoint === 'latest') {
        // Obtener la última sesión disponible
        $sessions = json_decode($response, true);
        if (!empty($sessions)) {
            // Ordenar sesiones por fecha descendente
            usort($sessions, function($a, $b) {
                return strtotime($b['date_start']) - strtotime($a['date_start']);
            });
            $latestSession = $sessions[0];
            echo json_encode($latestSession);
        } else {
            echo json_encode([]);
        }
    } else {
        // Devolver la respuesta sin modificar
        echo $response;
    }
} else {
    // Devolver error de la API
    http_response_code($httpCode);
    echo json_encode([
        'error' => 'La API de OpenF1 devolvió un error',
        'status_code' => $httpCode,
        'response' => json_decode($response, true)
    ]);
}

// Registrar la solicitud en un archivo de log (opcional)
$logFile = 'api_requests.log';
$date = date('Y-m-d H:i:s');
$user = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
$logEntry = "[$date] User: $user - Endpoint: $endpoint - Params: $params - Status: $httpCode\n";

// Añadir información adicional para depuración
$logEntry .= "Current User: dsrojo10\n";
$logEntry .= "Current UTC Time: " . gmdate('Y-m-d H:i:s') . "\n";
$logEntry .= "--------------------------------------------------------------\n";

// Escribir en el archivo de log si tiene permisos
if (is_writable(dirname(__FILE__))) {
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
?>