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
    protected $debug = TRUE;


    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct(LoggerChannelFactory $logger, QueueInterface $queue) {
        $this->logger = $logger->get('dslistener');
        $this->queue = $queue;
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
        $arrOutput = json_decode($objJsonDocument, TRUE);
        // Use temporarily to inspect payload.
        if ($this->debug) {
            
            $this->logger->debug('<pre>@payload</pre>', ['@payload' => $arrOutput]);
        }

        // Add the $payload to our defined queue.
        $this->queue->createItem($arrOutput);

        $response->setContent('Success!');
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
}