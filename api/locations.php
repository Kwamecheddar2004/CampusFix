<?php
/**
 * CampusFix - Locations AJAX API Endpoint
 * Returns JSON list of campus locations filtered by building.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Allow authenticated requests
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$buildingCode = trim($_GET['building_code'] ?? '');

try {
    $pdo = get_db_connection();

    if (!empty($buildingCode)) {
        $stmt = $pdo->prepare("
            SELECT id, building_name, building_code, floor, room_number, area_description 
            FROM campus_locations 
            WHERE building_code = ? AND is_active = 1
            ORDER BY floor ASC, room_number ASC
        ");
        $stmt->execute([$buildingCode]);
    } else {
        $stmt = $pdo->query("
            SELECT id, building_name, building_code, floor, room_number, area_description 
            FROM campus_locations 
            WHERE is_active = 1
            ORDER BY building_name ASC, floor ASC
        ");
    }

    $locations = $stmt->fetchAll();
    echo json_encode(['success' => true, 'locations' => $locations]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to query locations: ' . $e->getMessage()]);
}

