<?php

namespace Drupal\application_block\Controller;

use Drupal\Core\Controller\ControllerBase;

use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\CarbonCopy;
use DocuSign\eSign\Model\Checkbox;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\ModelList;
use DocuSign\eSign\Model\Number;
use DocuSign\eSign\Model\Radio;
use DocuSign\eSign\Model\RadioGroup;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\Tabs;
use DocuSign\eSign\Model\Text;


use Drupal\application_block\Service\ClientService;
use Drupal\application_block\Service\JWTService;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;

require_once __DIR__ . '/../ds_config.php';

/**
 * Defines ApplicationBlockController class.
 */

class ApplicationBlockController extends ControllerBase {
    /**
     * Path for the directory with demo documents
     */
    public const DOCS_PATH = __DIR__ . '/../../documents/';
    
    /** ClientService */
    private $clientService;
    
    /** JSON Web Token Service */
    private $authService;

    /** Specific template arguments */
    private $args;
    
    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct() {
        $this->args = $this->getTemplateArgs();
        $this->clientService = new ClientService($this->args);
        $this->authService = new JWTService();

        $this->authService->login();
    }

    /**
     * Get specific template arguments
     *
     * @return array
     */
    private function getTemplateArgs(): array
    {
        #$signer_name  = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer_name' ]);
        #$signer_email = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['signer_email']);
        #$cc_name      = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['cc_name'     ]);
        #$cc_email     = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['cc_email'    ]);
        
        $signer_name = "BaiYu";
        $signer_email = "baiyuchiyan@gmail.com";
        $cc_name = "Yun";
        $cc_email = "yunchunnan@gmail.com";

        $envelope_args = [
            'signer_email' => $signer_email,
            'signer_name' => $signer_name,
            'cc_email' => $cc_email,
            'cc_name' => $cc_name,
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
        $envelope_definition = $this->make_envelope($args["envelope_args"]);
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

    /**
     * Creates envelope definition
     * Document 1: A Borrower Form PDF document.
     * DocuSign will convert all of the documents to the PDF format.
     * The recipients' field tags are placed using <b>anchor</b> strings.
     *
     * Parameters for the envelope: signer_email, signer_name, signer_client_id
     *
     * @param  $args array
     * @return EnvelopeDefinition -- returns an envelope definition
     */
    private function make_envelope(array $args): EnvelopeDefinition
    {
        #
        # The envelope has two recipients.
        # recipient 1 - signer
        # recipient 2 - cc
        # The envelope will be sent first to the signer.
        # After it is signed, a copy is sent to the cc person.
        #
        # create the envelope definition
        $envelope_definition = new EnvelopeDefinition([
           'email_subject' => 'Please sign this borrower form'
        ]);
        # read files 2 and 3 from a local directory
        # The reads could raise an exception if the file is not available!
        $content_bytes = file_get_contents(self::DOCS_PATH . "PPP-Borrower-Application-Form-1.pdf");
        $borrower_form_b64 = base64_encode($content_bytes);
        
        # Create the document models
        $document = new Document([  # create the DocuSign document object
            'document_base64' => $borrower_form_b64,
            'name' => 'PPP Borrower Application Form',  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
        ]);
        $radio_group = new RadioGroup(['document_id' => '1', 'group_name' => 'CompanyStruct', 
            'radios' => [
                new Radio(['page_number' => '1', 'x_position' => '10', 'y_position' => '30', 'value' => 'Sole Prope'])
            ]
        ]);
        
        $sign_here = new SignHere(['document_id' => "1", 'page_number' => "2",
        'x_position' => '40', 'y_position' => '670']);
        
        
        

        # Create the signer recipient model
        $signer = new Signer([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'role_name' => 'signer', 'recipient_id' => "1", 'routing_order' => "1"]);
        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.
        
        $signer->setTabs(new Tabs(['sign_here_tabs' => [$sign_here], 'radio_group_tabs' => [$radio_group]]));


        # create a cc recipient to receive a copy of the documents
        $cc = new CarbonCopy([
            'email' => $args['cc_email'], 'name' => $args['cc_name'],
            'recipient_id' => "2", 'routing_order' => "2"]);

        # Add the recipients to the envelope object
        $recipients = new Recipients([
            'signers' => [$signer], 'carbon_copies' => [$cc]]);
        $envelope_definition->setRecipients($recipients);

        # The order in the docs array determines the order in the envelope
        $envelope_definition->setDocuments([$document]);



        # Request that the envelope be sent by setting |status| to "sent".
        # To request that the envelope be created as a draft, set to "created"
        $envelope_definition->setStatus($args["status"]);

        return $envelope_definition;
    }
    # ***DS.snippet.0.end
    
    /**
     * Display the markup.
     */
    public function content() {
        
        
 
        $results = $this->worker($this->args);

        
        if ($results) {
            # results is an object that implements ArrayAccess. Convert to a regular array:
            $this->clientService->showDoneTemplate(
                "Send Envelope",
                "Send Envelope results",
                "Send Envelope",
                $results);
        }

        return [
             '#type' => 'markup',
             '#markup' => $this->t("Success"),
         ];
     }
}
