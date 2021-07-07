<?php

namespace Drupal\application\Controller;

use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\CarbonCopy;
use DocuSign\eSign\Model\Checkbox;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\InitialHere;
use DocuSign\eSign\Model\SealSign;
use DocuSign\eSign\Model\Radio;
use DocuSign\eSign\Model\RadioGroup;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\Tabs;
use DocuSign\eSign\Model\Text;
use DocuSign\eSign\Model\DateSigned;

use Drupal\application\Service\ClientService;
use Drupal\application\Service\JWTService;

use Drupal\application\Controller\SForm;

use Drupal\webform\Utility\WebformFormHelper;
use Drupal\Core\Form\FormStateInterface;

use SplFileObject;
use stdClass;
use NumberFormatter;

require_once __DIR__ . '/../ds_config.php';

/**
 * Defines Forgivness DocuSign Form class.
 */

class ForgivenessForm {
    /**
     * Path for the directory with documents
    */
    public const DOCS_PATH = __DIR__ . '/../../documents/';
        
    /** ClientService */
    private $clientService;
        
    /** JSON Web Token Service */
    private $authService;
    
    /** Specific template arguments */
    private $args;
    
    public $elements;

    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct(&$elements) {
        $this->elements = $elements;
        $this->args = $this->getTemplateArgs();
        $this->clientService = new ClientService($this->args);
        $this->authService = new JWTService();
    }

    public function login() {
        $this->authService->login();
    }
    
    /**
     * Get specific template arguments
     *
     * @return array
     */
    private function getTemplateArgs(): array
    {
        #$signer_name = $this->getPrintName();
        #$signer_email = $this->getBorrowerEmail();
        $signer_name = "Isamu";
        $signer_email = "yunforreg@gmail.com";
        #$cc_name = "";
        #$cc_email = "";

        $envelope_args = [
            'signer_email' => $signer_email,
            'signer_name' => $signer_name,
            #'cc_email' => $cc_email,
            #'cc_name' => $cc_name,
            'status' => 'sent'
        ];
        $args = [
            'account_id' => $_SESSION['ds_account_id'],
            'base_path' => $_SESSION['ds_base_path'],
            'ds_access_token' => $_SESSION['ds_access_token'],
            'envelope_args' => $envelope_args
        ];

        return $args;
    }

    /**
     * Display the markup.
     */
    public function sendForm(array &$form, FormStateInterface $form_state) {
        $result = $this->worker($this->args);

        if ($result && empty($result["envelope_id"])) {
            return;
        }

        $entity = $form_state->getFormObject()->getEntity();
        $data = $entity->getData();
        $data["forgiveness_form_status"] = "Sent";
        $data["forgiveness_form_id"] = $result["envelope_id"];
        $entity->setData($data);
        $entity->save();

        $this->elements["forgiveness_form_status"]["#value"] = "Sent";
        $this->elements["forgiveness_form_status"]["#default_value"] = "Sent";
        $this->elements["forgiveness_form_id"]["#value"] = $result["envelope_id"];
        $this->elements["forgiveness_form_id"]["#default_value"] = $result["envelope_id"];
    }

    /**
     * Do the work of the example
     * 1. Create the envelope request object
     * 2. Send the envelope
     *
     * @param  $args array
     * @return array ['redirect_url']
     * @throws ApiException for API problems and perhaps file access \Exception too.
     */
    # ***DS.snippet.0.start
    public function worker($args): array
    {
        # 1. Create the envelope request object
        $envelope_definition = null;
        $forgiveness_form = new SForm();

        
        
        $envelope_definition = $forgiveness_form->make_envelope($args["envelope_args"], $this, $this->elements);
        
        $envelope_api = $this->clientService->getEnvelopeApi();
       
        # 2. call Envelopes::create API method
        # Exceptions will be caught by the calling function
        try {
            $results = $envelope_api->createEnvelope($args['account_id'], $envelope_definition);
        } catch (ApiException $e) {
            $this->clientService->showErrorTemplate($e);
            exit;
        }
        
        return ['envelope_id' => $results->getEnvelopeId()];
    }

}