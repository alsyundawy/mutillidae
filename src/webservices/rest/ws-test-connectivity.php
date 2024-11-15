<?php

# -------------------------------
# Documentation:
# - Domain: mutillidae.localhost
# - Description: This is a GET request to test connectivity to the API
# - Endpoint: /
# - CORS Headers:
#   - Access-Control-Allow-Origin: * or specific domains
#   - Access-Control-Allow-Methods: GET, OPTIONS
#   - Access-Control-Allow-Headers: Content-Type, Authorization
#   - Access-Control-Max-Age: 600 (10 minutes)
#   - Access-Control-Allow-Credentials: true (if enabled)
# - Expected Response:
#   - Status: 200 OK with JSON message {"message": "Connection succeeded"}
#   - If method not allowed: 405 Method Not Allowed with allowed methods in response header
# -------------------------------

/* Example Request: Make sure to copy the new line characters as well

GET /webservices/rest/ws-test-connectivity.php HTTP/1.1
Host: mutillidae.localhost
Origin: http://mutillidae.localhost
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36
Content-Type: application/json
Authorization: Bearer <bearer_token>
Accept: application/json

*/

// Include required constants and utility classes
require_once '../../includes/constants.php';
require_once '../../classes/SQLQueryHandler.php';

// Initialize SQL query handler with security level 0
$SQLQueryHandler = new SQLQueryHandler(0);

// Get the current security level from database instead of session
$lSecurityLevel = $SQLQueryHandler->getSecurityLevelFromDB();

// Get the origin of the request
$lOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';

header('Access-Control-Allow-Origin: ' . $lOrigin); // Allow requests from any origin domain
header('Access-Control-Allow-Methods: GET, OPTIONS'); // Allowed methods
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Specify allowed headers
header('Access-Control-Expose-Headers: Authorization'); // Expose headers if needed
header('Access-Control-Allow-Credentials: true'); // Allow credentials (if required)
header('Content-Type: application/json');

// Handle preflight requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Max-Age: 600'); // Cache the preflight response for 600 seconds (10 minutes)
    http_response_code(204); // No Content
    exit();
}

// Handle the GET request to test connectivity
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Return a success message with 200 OK status
        http_response_code(200); // OK
        echo json_encode(["code" => 200, "status" => "OK", "message" => "Connection succeeded...", 'security-level' => $lSecurityLevel, "timestamp" => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
    } else {
        // If the request method is not allowed, return 405 status
        http_response_code(405); // Method Not Allowed
        header('Allow: GET, OPTIONS'); // Inform allowed methods
        echo json_encode(["error" => "Method not allowed. Use GET or OPTIONS request."]);
    }
} catch (Exception $e) {
    // Handle any exceptions with a 500 Internal Server Error response
    http_response_code(500); // Internal Server Error
    echo json_encode([
        "error" => "Unable to process the request",
        "details" => $e->getMessage()
    ]);
}
?>
