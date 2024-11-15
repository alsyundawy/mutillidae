<?php

class MethodExecutionException extends Exception {}

// Include the nusoap library
require_once './lib/nusoap.php';

$lServerName = $_SERVER['SERVER_NAME'];

// Construct the full URL to the documentation
$lDocumentationURL = "http://{$lServerName}/webservices/soap/docs/soap-services.html";

// Create the SOAP server instance
$lSOAPWebService = new soap_server();

// Initialize WSDL (Web Service Definition Language) support
$lSOAPWebService->configureWSDL('echowsdl', 'urn:echowsdl');

// Register the "echoMessage" method to expose it as a SOAP function
$lSOAPWebService->register(
    'echoMessage',                     // Method name
    array('message' => 'xsd:string'),  // Input parameter
    array('return' => 'tns:EchoMessageResponse'),   // Output parameter defined as a complex type
    'urn:echowsdl',                    // Namespace
    'urn:echowsdl#echoMessage',        // SOAP action
    'rpc',                             // Style
    'encoded',                         // Use
    "Echoes the provided message back to the caller. For detailed documentation, visit: {$lDocumentationURL}"
);

// Define a complex type for the response
$lSOAPWebService->wsdl->addComplexType(
    'EchoMessageResponse',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'message' => array('name' => 'message', 'type' => 'xsd:string'),
        'securityLevel' => array('name' => 'securityLevel', 'type' => 'xsd:string'),
        'timestamp' => array('name' => 'timestamp', 'type' => 'xsd:string'),
        'output' => array('name' => 'output', 'type' => 'xsd:string')
    )
);

// Define the "echoMessage" method
function echoMessage($pMessage) {

    try{
        // Include required constants and utility classes
        require_once '../../includes/constants.php';
        require_once '../../classes/EncodingHandler.php';
        require_once '../../classes/SQLQueryHandler.php';

        $SQLQueryHandler = new SQLQueryHandler(0);

        $lSecurityLevel = $SQLQueryHandler->getSecurityLevelFromDB();

        $Encoder = new EncodingHandler();

        switch ($lSecurityLevel){
            default: // Default case: This code is insecure
            case "0": // This code is insecure
            case "1": // This code is insecure
                $lProtectAgainstCommandInjection=false;
                $lProtectAgainstXSS = false;
            break;

            case "2":
            case "3":
            case "4":
            case "5": // This code is fairly secure
                $lProtectAgainstCommandInjection=true;
                $lProtectAgainstXSS = true;
            break;
        }// end switch
    
        // Apply XSS protection if enabled
        if ($lProtectAgainstXSS) {
            $lMessage = $Encoder->encodeForHTML($pMessage);
        } else {
            $lMessage = $pMessage;
        }

        // Handle command execution based on the protection flag
        if ($lProtectAgainstCommandInjection) {
            $lResult = $lMessage;
        } else {
            // Allow command injection vulnerability (insecure)
            $lResult = shell_exec("echo " . $lMessage);
        }

        // Get the current timestamp
        $lTimestamp = date('Y-m-d H:i:s');

        // Create a structured response as an associative array
        $lResponse = array(
            'message' => $lMessage,
            'securityLevel' => $lSecurityLevel,
            'timestamp' => $lTimestamp,
            'output' => $lResult
        );

        return $lResponse; // Return as an array for NuSOAP to serialize

    }catch(Exception $e){
        $lMessage = "Error executing method echoMessage in webservice ws-echo.php";
        throw new MethodExecutionException($lMessage);
    }// end try

} // end function echoMessage

// Handle the SOAP request with error handling
try {
    // Process the incoming SOAP request
    $lSOAPWebService->service(file_get_contents("php://input"));
} catch (Exception $e) {
    // Send a fault response back to the client
    $lSOAPWebService->fault('Server', "SOAP Service Error: " . $e->getMessage());
}

?>
