<?php

namespace Drupal\application\Controller;

use Drupal\Core\Controller\ControllerBase;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OnfidoListener extends ControllerBase {
    
    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct() {    
    }

    /**
    * Enable or disable debugging.
    *
    * @var bool
    */
    protected $debug = FALSE;
    
    /**
    * Capture the payload.
    *
    * @return Symfony\Component\HttpFoundation\Response
    *   A simple string and 200 response.
    */
    public function capture(Request $request) {
        // Keep things fast.
        // Don't load a themed site for the response.
        // Most Webhook providers just want a 200 response.
        $response = new Response();

        // Capture the payload.
        // Option 2: $payload = file_get_contents("php://input");.
        $payload = $request->getContent();

        // Check if it is empty.
        if (empty($payload)) {
            $message = 'The payload was empty.';
            \Drupal::logger("OnfidoWebhook")->notice($message);
            $response->setContent($message);
            return $response;
        }
        \Drupal::logger("OnfidoWebhook")->notice("Payload: " . $payload);

        /*
        libxml_use_internal_errors(TRUE);
        
        $objXmlDocument = simplexml_load_string($payload);

        if ($objXmlDocument === FALSE) {
            $message = "There were errors parsing the XML file.\n";
            foreach (libxml_get_errors() as $error) {
                $message .= $error->message . "\n";
            }
            $this->logger->error($message);
            $response->setContent($message);
            return $response;
        }
        $objJsonDocument = json_encode($objXmlDocument);
        $payload = json_decode($objJsonDocument, TRUE);
        
        #$payload_str = print_r($payload, true);
        // Use temporarily to inspect payload.
        if ($this->debug) {
            $this->logger->debug('<pre>@payload</pre>', ['@payload' => print_r($payload, true)]);
        }

        // Add the $payload to our defined queue.
        //$this->queue->createItem($arrOutput);
        $EnvelopeID = "";
        if (isset($payload["EnvelopeStatus"]) && isset($payload["EnvelopeStatus"]["EnvelopeID"])) {
            $EnvelopeID = $payload["EnvelopeStatus"]["EnvelopeID"];
        }
        $update_status = false;
        $updated_status = $this->checkFLPEnvelopeStatus($EnvelopeID);
        if ($update_status) {
            $response->setContent('FLP Success!');
            return $response;
        }

        $update_status = $this->checkPPPBorrowerEnvelopeStatus($EnvelopeID);
        if ($update_status) {
            $response->setContent('PPP Borrower Success!');
            return $response;
        }

        $update_status = $this->checkPPPSBAEnvelopeStatus($EnvelopeID);
        if ($update_status) {
            $response->setContent('PPP SBA Success!');
            return $response;
        }
        */
        return $response;
  }
}