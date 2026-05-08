<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$db_file = 'database.json';

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

function readDB($file) {
    if (!file_exists($file) || filesize($file) === 0) {
        return [
            "stokData" => [],
            "inputLogHistory" => [],
            "databaseTokoLengkap" => [],
            "databaseToko" => (object)[]
        ];
    }
    $json = file_get_contents($file);
    if (empty($json)) {
        return [
            "stokData" => [],
            "inputLogHistory" => [],
            "databaseTokoLengkap" => [],
            "databaseToko" => (object)[]
        ];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [
        "stokData" => [],
        "inputLogHistory" => [],
        "databaseTokoLengkap" => [],
        "databaseToko" => (object)[]
    ];
}

function writeDB($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = readDB($db_file);
    switch ($action) {
        case 'get_stock':
            echo json_encode(["status" => "success", "data" => $db['stokData'] ?? []]);
            break;
        case 'get_logs':
            echo json_encode(["status" => "success", "data" => $db['inputLogHistory'] ?? []]);
            break;
        case 'get_toko':
            echo json_encode(["status" => "success", "data" => $db['databaseTokoLengkap'] ?? []]);
            break;
        case 'get_all':
            echo json_encode(["status" => "success", "data" => $db]);
            break;
        default:
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        echo json_encode(["status" => "error", "message" => "Invalid payload"]);
        exit;
    }

    $db = readDB($db_file);

    switch ($action) {
        case 'save_stock':
            $db['stokData'] = $payload['data'];
            break;
        case 'save_log':
            $db['inputLogHistory'] = $payload['data'];
            break;
        case 'save_toko':
            $db['databaseTokoLengkap'] = $payload['data'];
            $db['databaseToko'] = [];
            foreach ($payload['data'] as $t) {
                if (isset($t['nama'])) {
                    $db['databaseToko'][strtolower($t['nama'])] = $t['alamat'] ?? '';
                }
            }
            break;
        case 'delete_log':
            $id = $payload['id'] ?? '';
            if ($id && isset($db['inputLogHistory'])) {
                $db['inputLogHistory'] = array_values(array_filter($db['inputLogHistory'], function($item) use ($id) {
                    return $item['id'] != $id;
                }));
            }
            break;
        case 'delete_stock_entries':
            $ids = $payload['ids'] ?? [];
            if (!empty($ids) && isset($db['stokData'])) {
                $db['stokData'] = array_values(array_filter($db['stokData'], function($item) use ($ids) {
                    return !in_array($item['id'], $ids);
                }));
            }
            break;
        case 'sync_batch':
            $allKeys = [
                'databaseToko', 'databaseTokoLengkap', 'printTextPositions', 
                'inputLogHistory', 'lastNomorSJ', 'printFontSize', 'printFontFamily', 
                'printFontWeight', 'printColumnSpacing', 'columnSpacingConfig',
                'stokData', 'aggregatedStock'
            ];
            foreach ($allKeys as $key) {
                if (isset($payload[$key])) {
                    $db[$key] = $payload[$key];
                }
            }
            // Auto update databaseToko map
            if (isset($payload['databaseTokoLengkap']) && !isset($payload['databaseToko'])) {
                $db['databaseToko'] = [];
                foreach ($payload['databaseTokoLengkap'] as $t) {
                    if (isset($t['nama'])) {
                        $db['databaseToko'][strtolower($t['nama'])] = $t['alamat'] ?? '';
                    }
                }
            }
            break;
        case 'clear_data':
            $target = $payload['target'] ?? '';
            if ($target === 'stock') $db['stokData'] = [];
            if ($target === 'logs') $db['inputLogHistory'] = [];
            if ($target === 'toko') {
                $db['databaseTokoLengkap'] = [];
                $db['databaseToko'] = (object)[];
            }
            break;
        default:
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid action"]);
            exit;
    }

    if (writeDB($db_file, $db)) {
        echo json_encode(["status" => "success"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to write database"]);
    }
}
?>
