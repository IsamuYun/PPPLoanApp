<?php

namespace Drupal\application_block\Controller;

use Drupal\Core\Controller\ControllerBase;

use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\CarbonCopy;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\Tabs;

use DocuSign\eSign\Api\EnvelopesApi\ListStatusChangesOptions;
use DocuSign\eSign\Model\EnvelopesInformation;

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
     * Get specific template arguments
     *
     * @return array
     */
    private function getEnvelopeListTemplateArgs(): array
    {
        $args = [
            'account_id' => $_SESSION['ds_account_id'],
            'base_path' => $_SESSION['ds_base_path'],
            'ds_access_token' => $_SESSION['ds_access_token'],
        ];

        return $args;
    }


    /**
     * Do the work of the example
     * 1. List the envelopes that have changed in the last 10 days
     *
     * @param  $args array
     * @return EnvelopesInformation
     * @throws ApiException for API problems and perhaps file access \Exception too.
     */
    # ***DS.snippet.0.start
    private function envelopes_worker(array $args): EnvelopesInformation
    {
        # 1. call API method
        # Exceptions will be caught by the calling function
        # The Envelopes::listStatusChanges method has many options
        # See https://developers.docusign.com/esign-rest-api/reference/Envelopes/Envelopes/listStatusChanges
        # The list status changes call requires at least a from_date OR
        # a set of envelope_ids. Here we filter using a from_date.
        # Here we set the from_date to filter envelopes for the last 10 days
        # Use ISO 8601 date format
        $envelope_api = $this->clientService->getEnvelopeApi();
        $from_date = date("c", (time() - (10 * 24 * 60 * 60)));
        $options = new ListStatusChangesOptions();
        $options->setFromDate($from_date);
        try {
            $results = $envelope_api->listStatusChanges($args['account_id'], $options);
        } catch (ApiException $e) {
            $this->clientService->showErrorTemplate($e);
            exit;
        }

        return $results;
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
     * Document 1: An HTML document.
     * Document 2: A Word .docx document.
     * Document 3: A PDF document.
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
        # document 1 (html) has sign here anchor tag **signature_1**
        # document 2 (docx) has sign here anchor tag /sn1/
        # document 3 (pdf)  has sign here anchor tag /sn1/
        #
        # The envelope has two recipients.
        # recipient 1 - signer
        # recipient 2 - cc
        # The envelope will be sent first to the signer.
        # After it is signed, a copy is sent to the cc person.
        #
        # create the envelope definition
        $envelope_definition = new EnvelopeDefinition([
           'email_subject' => 'Please sign this document set'
        ]);
        $doc1_b64 = base64_encode($this->clientService->createDocumentForEnvelope($args));
        # read files 2 and 3 from a local directory
        # The reads could raise an exception if the file is not available!
        $content_bytes = file_get_contents(self::DOCS_PATH . $GLOBALS['DS_CONFIG']['doc_docx']);
        $doc2_b64 = base64_encode($content_bytes);
        $content_bytes = file_get_contents(self::DOCS_PATH . $GLOBALS['DS_CONFIG']['doc_pdf']);
        $doc3_b64 = base64_encode($content_bytes);

        # Create the document models
        $document1 = new Document([  # create the DocuSign document object
            'document_base64' => $doc1_b64,
            'name' => 'Order acknowledgement',  # can be different from actual file name
            'file_extension' => 'html',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
        ]);
        $document2 = new Document([  # create the DocuSign document object
            'document_base64' => $doc2_b64,
            'name' => 'Battle Plan',  # can be different from actual file name
            'file_extension' => 'docx',  # many different document types are accepted
            'document_id' => '2'  # a label used to reference the doc
        ]);
        $document3 = new Document([  # create the DocuSign document object
            'document_base64' => $doc3_b64,
            'name' => 'Lorem Ipsum',  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '3'  # a label used to reference the doc
        ]);
        # The order in the docs array determines the order in the envelope
        $envelope_definition->setDocuments([$document1, $document2, $document3]);


        # Create the signer recipient model
        $signer1 = new Signer([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'recipient_id' => "1", 'routing_order' => "1"]);
        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.

        # create a cc recipient to receive a copy of the documents
        $cc1 = new CarbonCopy([
            'email' => $args['cc_email'], 'name' => $args['cc_name'],
            'recipient_id' => "2", 'routing_order' => "2"]);

        # Create signHere fields (also known as tabs) on the documents,
        # We're using anchor (autoPlace) positioning
        #
        # The DocuSign platform searches throughout your envelope's
        # documents for matching anchor strings. So the
        # signHere2 tab will be used in both document 2 and 3 since they
        #  use the same anchor string for their "signer 1" tabs.
        $sign_here1 = new SignHere([
            'anchor_string' => '**signature_1**', 'anchor_units' => 'pixels',
            'anchor_y_offset' => '10', 'anchor_x_offset' => '20']);
        $sign_here2 = new SignHere([
            'anchor_string' => '/sn1/', 'anchor_units' =>  'pixels',
            'anchor_y_offset' => '10', 'anchor_x_offset' => '20']);

        # Add the tabs model (including the sign_here tabs) to the signer
        # The Tabs object wants arrays of the different field/tab types
        $signer1->setTabs(new Tabs([
            'sign_here_tabs' => [$sign_here1, $sign_here2]]));

        # Add the recipients to the envelope object
        $recipients = new Recipients([
            'signers' => [$signer1], 'carbon_copies' => [$cc1]]);
        $envelope_definition->setRecipients($recipients);

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
        
        
 
        //$results = $this->worker($this->args);

        $envelope_id = 0;

        $envelopes_arg = $this->getEnvelopeListTemplateArgs();

        $results = $this->envelopes_worker($envelopes_arg);

        if ($results) {
            # results is an object that implements ArrayAccess. Convert to a regular array:
            $this->clientService->showDoneTemplate(
                "Envelope list",
                "List envelopes results",
                "Results from the Envelopes::listStatusChanges method:",
                $results);
        }
        
        
        return [
             '#type' => 'markup',
             '#markup' => $this->t("Success"),
         ];
     }
}
