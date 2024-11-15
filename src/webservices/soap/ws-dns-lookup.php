<?php

// Define a dedicated exception for command execution failures
class CommandExecutionException extends Exception {}
class ValidationException extends Exception {}
class LookupException extends Exception {}

// Pull in the NuSOAP library
require_once './lib/nusoap.php';

$lServerName = $_SERVER['SERVER_NAME'];

// Construct the full URL to the documentation
$lDocumentationURL = "http://{$lServerName}/webservices/soap/docs/soap-services.html";

// Create the SOAP server instance
$lSOAPWebService = new soap_server();

// Initialize WSDL support for the SOAP service
$lSOAPWebService->configureWSDL('commandinjwsdl', 'urn:commandinjwsdl');

// Register the lookupDNS method to expose as a SOAP service
$lSOAPWebService->register(
    'lookupDNS',                           // Method name
    array('targetHost' => 'xsd:string'),   // Input parameter
    array('return' => 'tns:LookupDNSResponse'),  // Output parameter defined as a complex type
    'urn:commandinjwsdl',                  // Namespace
    'urn:commandinjwsdl#lookupDNS',        // SOAP action
    'rpc',                                 // Style
    'encoded',                             // Use
    "Executes a DNS lookup for the specified host and returns the result."
);

// Define a complex type for the response
$lSOAPWebService->wsdl->addComplexType(
    'LookupDNSResponse',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'host' => array('name' => 'host', 'type' => 'xsd:string'),
        'securityLevel' => array('name' => 'securityLevel', 'type' => 'xsd:string'),
        'timestamp' => array('name' => 'timestamp', 'type' => 'xsd:string'),
        'output' => array('name' => 'output', 'type' => 'xsd:string')
    )
);

/**
 * Method: lookupDNS
 * Performs a DNS lookup for a given target host.
 * 
 * @param string $pTargetHost The host name or IP address to look up.
 * @return array An associative array containing the nslookup output, timestamp, security level, and host.
 */
function lookupDNS($pTargetHost) {

    // Include required constants and utility classes
    require_once '../../includes/constants.php';
    require_once '../../classes/LogHandler.php';
    require_once '../../classes/EncodingHandler.php';
    require_once '../../classes/SQLQueryHandler.php';

    try {
        // Initialize classes
        $SQLQueryHandler = new SQLQueryHandler(0);
        $lSecurityLevel = $SQLQueryHandler->getSecurityLevelFromDB();
        $LogHandler = new LogHandler($lSecurityLevel);
        $Encoder = new EncodingHandler();

        // Determine security level and protection settings
        switch ($lSecurityLevel) {
            default: // Insecure
            case "0": // Insecure
            case "1": // Insecure
                $lProtectAgainstCommandInjection = false;
                $lProtectAgainstXSS = false;
                break;
            case "2": // Moderate security
            case "3": // More secure
            case "4": // Secure
            case "5": // Fairly secure
                $lProtectAgainstCommandInjection = true;
                $lProtectAgainstXSS = true;
                break;
        }

        // Validate the target host to protect against command injection, if security is enabled
        if ($lProtectAgainstCommandInjection) {
            $lTargetHostValidated = preg_match(IPV4_REGEX_PATTERN, $pTargetHost) ||
                                    preg_match(DOMAIN_NAME_REGEX_PATTERN, $pTargetHost) ||
                                    preg_match(IPV6_REGEX_PATTERN, $pTargetHost);
            if (!$lTargetHostValidated) {
                throw new ValidationException("Invalid target host: " . $pTargetHost);
            }
        }

        // Protect against XSS by encoding the target host, if enabled
        $lTargetHost = $lProtectAgainstXSS
            ? $Encoder->encodeForHTML($pTargetHost)
            : $pTargetHost;

        // Construct the command
        $lCommand = $lProtectAgainstCommandInjection
            ? escapeshellcmd("nslookup " . escapeshellarg($lTargetHost))
            : "nslookup $lTargetHost";

        // Execute the command and capture output
        $lOutput = shell_exec($lCommand);
        if ($lOutput === null) {
            throw new CommandExecutionException("Command execution failed.");
        }

        // Get the current timestamp
        $lTimestamp = date('Y-m-d H:i:s');

        // Create a structured response as an associative array
        $response = array(
            'host' => $lTargetHost,
            'securityLevel' => $lSecurityLevel,
            'timestamp' => $lTimestamp,
            'output' => $lOutput
        );

        $LogHandler->writeToLog("Executed nslookup on: $lTargetHost");

        return $response; // Return as an array for NuSOAP to serialize

    } catch (Exception $e) {
        throw new LookupException("Error in method lookupDNS: " . $e->getMessage());
    }
}

try {
    // Process the incoming SOAP request
    $lSOAPWebService->service(file_get_contents("php://input"));
} catch (Exception $e) {
    // Send a fault response back to the client if an error occurs
    $lSOAPWebService->fault('Server', "SOAP Service Error: " . $e->getMessage());
}
?>
