<?php

namespace Drupal\application\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DSListenerController extends ControllerBase {
    /**
    * Drupal\Core\Logger\LoggerChannelFactory definition.
    *
    * @var \Drupal\Core\Logger\LoggerChannelFactory
    */
    protected $logger;
    
    /**
    * Drupal\Core\Queue\QueueFactory definition.
    *
    * @var \Drupal\Core\Queue\QueueInterface
    */
    protected $queue;

    /**
    * Enable or disable debugging.
    *
    * @var bool
    */
    protected $debug = FALSE;


    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct(LoggerChannelFactory $logger, QueueInterface $queue) {
        $this->logger = $logger->get('dslistener');
        //$this->queue = $queue;
    }

    /**
    * {@inheritdoc}
    */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('logger.factory'),
            $container->get('queue')->get('process_payload_queue_worker')
        );
    }

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
            $this->logger->error($message);
            $response->setContent($message);
            return $response;
        }
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
        
        return $response;
  }

    /**
    * Simple authorization using a token.
    *
    * @param string $token
    *    A random token only your webhook knows about.
    *
    * @return AccessResult
    *   AccessResult allowed or forbidden.
    */
    public function authorize($token) {
        //if ($token === $this->secret) {
        //    return AccessResult::allowed();
        //}
        //return AccessResult::forbidden();
        return AccessResult::allowed();
    }

    private function checkFLPEnvelopeStatus($EnvelopeID) {
        if (empty($EnvelopeID)) {
            return false;
        }

        $database = \Drupal::database();
        $query = $database->select("webform_submission_data", "wsd");
        $query->condition("wsd.name", "envelope_id", '=');
        $query->condition("wsd.value", $EnvelopeID, '=');
        $query->addField("wsd", "sid");

        $result = $query->execute()->fetchAll();
        if (empty($result)) {
            return false;
        }
        $sid = $result[0]->sid;

        \Drupal::logger("ProcessPayload")->notice("FLP Submission ID: " . $sid . ", Envelope ID: " . $EnvelopeID);

        $update_query = \Drupal::database()->update('webform_submission_data');
        $update_query->fields([
            'value' => "completed"
        ]);
        $update_query->condition("sid", $sid);
        $update_query->condition("name", "envelope_status");
        $update_query->execute();
        \Drupal::logger("ProcessPayload")->notice("Submission ID: " . $sid . " Envelope status has been updated.");

        return true;
    }

    private function checkPPPBorrowerEnvelopeStatus($EnvelopeID) {
        if (empty($EnvelopeID)) {
            return false;
        }

        $database = \Drupal::database();
        $query = $database->select("webform_submission_data", "wsd");
        $query->condition("wsd.name", "borrower_envelope_id", '=');
        $query->condition("wsd.value", $EnvelopeID, '=');
        $query->addField("wsd", "sid");

        $result = $query->execute()->fetchAll();
        if (empty($result)) {
            return false;
        }
        $sid = $result[0]->sid;

        \Drupal::logger("ProcessPayload")->notice("PPP Submission ID: " . $sid . ", Borrower Envelope ID: " . $EnvelopeID);

        $update_query = \Drupal::database()->update('webform_submission_data');
        $update_query->fields([
            'value' => "completed"
        ]);
        $update_query->condition("sid", $sid);
        $update_query->condition("name", "borrower_envelope_status");
        $update_query->execute();
        \Drupal::logger("ProcessPayload")->notice("Submission ID: " . $sid . " Borrower Envelope status has been updated.");
        return true;
    }

    private function checkPPPSBAEnvelopeStatus($EnvelopeID) {
        if (empty($EnvelopeID)) {
            return false;
        }

        $database = \Drupal::database();
        $query = $database->select("webform_submission_data", "wsd");
        $query->condition("wsd.name", "sba_envelope_id", '=');
        $query->condition("wsd.value", $EnvelopeID, '=');
        $query->addField("wsd", "sid");

        $result = $query->execute()->fetchAll();
        if (empty($result)) {
            return false;
        }
        $sid = $result[0]->sid;
        
        \Drupal::logger("ProcessPayload")->notice("PPP Submission ID: " . $sid . ", SBA Envelope ID: " . $EnvelopeID);

        $update_query = \Drupal::database()->update('webform_submission_data');
        $update_query->fields([
            'value' => "completed"
        ]);
        $update_query->condition("sid", $sid);
        $update_query->condition("name", "sba_envelope_status");
        $update_query->execute();
        \Drupal::logger("ProcessPayload")->notice("Submission ID: " . $sid . " SBA Envelope status has been updated.");
        return true;
    }
}