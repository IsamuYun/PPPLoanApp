<?php

namespace Drupal\application\Controller;

use Drupal\application\Service\ClientService;
use Drupal\application\Service\JWTService;

use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\Envelope;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\InitialHere;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\Tabs;
use DocuSign\eSign\Model\Text;

use SplFileObject;

require_once __DIR__ . '/../ds_config.php';

// SBA Loan Forgiveness Application Controller

class SBALFAController {
    /**
     * Path for the directory with documents
     */
    public const FORM_PATH = __DIR__ . '/../../documents/' . "PPP Loan Forgiveness Application Form 3508S.pdf";

    /** ClientService */
    private $clientService;

    /** JSON Web Token Service */
    private $authService;

    /** Specific template arguments */
    private $args;

    /** elements of submission */
    private $elements;

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

    /**
     * Get specific template arguments
     *
     * @return array
     */
    private function getTemplateArgs(): array
    {
        $envelope_args = [
            #'signer_email' => $this->getBorrowerEmail(),
            'signer_email' => "ppp@americanlendingcenter.com",
            'signer_name' => $this->getBorrowerName(),
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

    public function login() {
        $this->authService->login();
    }

    /**
     * Get specific template arguments
     *
     * @return array
     */
    private function getStatusArgs(): array
    {
        $envelope_id = isset($this->elements["envelope_id"]["#default_value"]) ?
                        $this->elements["envelope_id"]["#default_value"] : false;
        $args = [
            'account_id' => $_SESSION['ds_account_id'],
            'base_path' => $_SESSION['ds_base_path'],
            'ds_access_token' => $_SESSION['ds_access_token'],
            'envelope_id' => $envelope_id
        ];

        return $args;
    }

    /**
     * Get the envelope's data
     *
     * @param  $args array
     * @return Envelope
     * @throws ApiException for API problems and perhaps file access \Exception too.
     */
    private function statusWorker(array $args): Envelope
    {
        # 1. call API method
        # Exceptions will be caught by the calling function
        $envelope_api = $this->clientService->getEnvelopeApi();
        try {
            $results = $envelope_api->getEnvelope($args['account_id'], $args['envelope_id']);
        } catch (ApiException $e) {
            $this->clientService->showErrorTemplate($e);
            exit;
        }

        return $results;
    }

    public function getEnvelopeStatus() {
        $statusArgs = $this->getStatusArgs();
        if (!empty($statusArgs["envelope_id"])) {
            $result = $this->statusWorker($statusArgs);
        }
        else {
            $result = "No Envelope ID";
        }
        
        return $result;
    }

    private function getDownloadDocumentArgs(): array
    {
        #$envelope_id= isset($_SESSION['envelope_id']) ? $_SESSION['envelope_id'] : false;
        #$envelope_documents = isset($_SESSION['envelope_documents']) ? $_SESSION['envelope_documents'] : false;
        #$document_id  = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['document_id' ]);
        
        $envelope_id = isset($this->elements["envelope_id"]["#default_value"]) ?
                $this->elements["envelope_id"]["#default_value"] : false;   

        $args = [
            'account_id' => $_SESSION['ds_account_id'],
            'base_path' => $_SESSION['ds_base_path'],
            'ds_access_token' => $_SESSION['ds_access_token'],
            'envelope_id' => $envelope_id,
            'document_id' => "combined",
            #'document_id' => "1",
        ];

        return $args;
    }

    private function downloadWorker(array $args): array
    {
        # 1. call API method
        # Exceptions will be caught by the calling function
        $envelope_api = $this->clientService->getEnvelopeApi();

        # An SplFileObject is returned. See http://php.net/manual/en/class.splfileobject.php

        $temp_file = $envelope_api->getDocument($args['account_id'],  $args['document_id'], $args['envelope_id']);
         # find the matching document information item
        $doc_item = false;
        
        $mimetype = 'application/pdf';
        $doc_name = "3508S Form.pdf";
        return ['mimetype' => $mimetype, 'doc_name' => $doc_name, 'data' => $temp_file];
    }

    public function downloadForgivenessForm() {
        $args = $this->getDownloadDocumentArgs();
        $results = $this->downloadWorker($args);
        if ($results) {
            # See https://stackoverflow.com/a/27805443/64904
            #header("Content-Type: {$results['mimetype']}");
            #header("Content-Disposition: attachment; filename=\"{$results['doc_name']}\"");
            #ob_clean();
            #flush();
            #$file_path = $results['data']->getPathname();
            #readfile($file_path);
            
            #flush();
            $submission_id = 0;
            ob_clean();
            ob_start();
            $absolute_path = \Drupal::service('file_system')->realpath('private://webform/apply_for_flp_loan/');
            $file_name = "3508S_Form_" . time() . ".pdf";
            $absolute_path .= '/' . $file_name;
            $file1 = new SplFileObject($absolute_path, "w+");
            $file = $results["data"];
            clearstatcache();
            
            $handle = $file->openFile('r');
            $contents = $handle->fread($file->getSize());
            
            $length = $file1->fwrite($contents);
            
            return $length ? $file_name : "???";
        }
        
        return $results;
    }

    private function getBorrowerName() {
        return $this->elements["primary_contact"]["#default_value"];
    }

    private function getBorrowerEmail() {
        return $this->elements["email_address"]["#default_value"];
    }

    public function sendForm() {
        $result = $this->worker($this->args);
        return $result;
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
           'email_subject' => 'Please sign this SBA PPP Loan Forgiveness Application Form 3508S'
        ]);
        # read files 2 and 3 from a local directory
        # The reads could raise an exception if the file is not available!
        $content_bytes = file_get_contents(self::FORM_PATH);
        $forgiveness_form_b64 = base64_encode($content_bytes);
        
        # Create the document models
        $document = new Document([  # create the DocuSign document object
            'document_base64' => $forgiveness_form_b64,
            'name' => 'SBA PPP Loan Forgiveness Application Form 3508S',  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
        ]);
        
        // 001 - entity_name
        $entity_name_text = new Text(['document_id' => "1", 'page_number' => "1",
            "x_position" => "40", "y_position" => "84",
            "font" => "Arial", "font_size" => "size9", 
            "value" => $this->elements["business_legal_name_borrower"]["#default_value"],
            "height" => "20", "width" => "240", "required" => "false"]);
        // 002 - dba_name
        $dba_name_text = new Text(['document_id' => "1", 'page_number' => "1",
            "x_position" => "330", "y_position" => "84",
            "font" => "Arial", "font_size" => "size9",
            "value" => $this->elements["dba_or_trade_name_if_applicable"]["#default_value"],
            "height" => "20", "width" => "160", "required" => "false"
        ]);
        // 003 - address1
        $address1_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "106",
            "font" => "Arial", "font_size" => "size9",
            "value" => $this->elements["business_street_address"]["#default_value"],
            "height" => "20", "width" => "200", "required" => "false"
        ]);
        // 004 - ein
        $ein_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "330", "y_position" => "106",
            "font" => "Arial", "font_size" => "size9",
            "value" => $this->elements["business_tin_ein_ssn_"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // 005 - phone_number
        $phone_number_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "464", "y_position" => "106",
            "font" => "Arial", "font_size" => "size9",
            "value" => $this->elements["phone_number"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // address_2
        $address2_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "131",
            "font" => "Arial", "font_size" => "size9",
            "value" => $this->elements["city_state_zip"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // 007 - primary_name
        $primary_name_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "330", "y_position" => "131",
            "font" => "Arial", "font_size" => "size9",
            "value" => $this->elements["primary_contact"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // 008 - primary_email
        $primary_email_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "462", "y_position" => "131",
            "font" => "Arial", "font_size" => "size9",
            "value" => $this->elements["email_address"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // 009 - sba_number
        $sba_number_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "150", "y_position" => "146",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["sba_ppp_loan_number"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // 010 - loan_number
        $loan_number_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "414", "y_position" => "146",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["lender_ppp_loan_number"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // 011 - bank_notional_amount
        $bank_notional_amount_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "127", "y_position" => "169",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["ppp_loan_amount"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // 012 - funding_date
        $funding_date_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "424", "y_position" => "169",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["ppp_loan_disbursement_date"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // 013 - forgive_fte_at_loan_application
        $forgive_fte_at_loan_application_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "216", "y_position" => "191",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["employees_at_time_of_loan_application"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        // 014 - forgive_fte_at_forgiveness_application
        $forgive_fte_at_forgiveness_application_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "497", "y_position" => "191",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["employees_at_time_of_forgiveness_application"]["#default_value"],
            "height" => "20", "width" => "100", "required" => "false"
        ]);
        // 015 - forgive_eidl_amount
        $forgive_eidl_amount_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "147", "y_position" => "212",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["eidl_advance_amount_if_applicable_"]["#default_value"],
            "height" => "20", "width" => "100", "required" => "false"
        ]);
        // 016 - forgive_eidl_application_number
        $forgive_eidl_application_number_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "414", "y_position" => "212",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["eidl_application_number_if_applicable"]["#default_value"],
            "height" => "14", "width" => "100", "required" => "false"
        ]);
        // 033 - forgive_amount
        $forgive_amount_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "131", "y_position" => "232",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->elements["forgive_amount"]["#default_value"],
            "height" => "14", "width" => "100", "required" => "false"
        ]);
        // forgive_date
        $forgive_date_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "400", "y_position" => "703",
            "font" => "Arial", "font_size" => "size12",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "100", "required" => "false"
        ]);

        // print_name
        $print_name_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "735",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["primary_contact"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        // title
        $title_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "400", "y_position" => "735",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["borrow_title"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $sign_here = new SignHere(['document_id' => "1", 'page_number' => "1",
        'x_position' => '40', 'y_position' => '692']);

        # Create the signer recipient model
        $signer = new Signer([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'role_name' => 'signer', 'recipient_id' => "1", 'routing_order' => "1"]);
        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.
        
        $initial_here_list = $this->getInitialList();
        
        $signer->setTabs(new Tabs(['sign_here_tabs' => [$sign_here],
            'initial_here_tabs' => $initial_here_list,
            'text_tabs' => [
                $entity_name_text,
                $dba_name_text,
                $ein_text,
                $address1_text,
                $address2_text,
                $phone_number_text,
                $primary_name_text,
                $primary_email_text,
                $sba_number_text,
                $loan_number_text,
                $bank_notional_amount_text,
                $funding_date_text,
                $forgive_fte_at_loan_application_text,
                $forgive_fte_at_forgiveness_application_text,
                $forgive_eidl_application_number_text,
                $forgive_eidl_amount_text,
                $forgive_amount_text,
                $forgive_date_text,
                $print_name_text,
                $title_text
            ]
        ]));

        # Add the recipients to the envelope object
        $recipients = new Recipients([
            'signers' => [$signer],
        ]);
        $envelope_definition->setRecipients($recipients);

        # The order in the docs array determines the order in the envelope
        $envelope_definition->setDocuments([$document]);

        # Request that the envelope be sent by setting |status| to "sent".
        # To request that the envelope be created as a draft, set to "created"
        $envelope_definition->setStatus($args["status"]);

        return $envelope_definition;
    }

    private function getInitialList() {
        $Initial_1 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "32", "y_position" => "270",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_2 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "32", "y_position" => "370",
            "height" => "12", "width" => "40", "required" => "false"
        ]);
        
        $Initial_3 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "32", "y_position" => "400",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_4 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "32", "y_position" => "428",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_5 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "32", "y_position" => "470",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_6 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "32", "y_position" => "545",
            "font" => "Arial", "font_size" => "size10",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_7 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "32", "y_position" => "596",
            "font" => "Arial", "font_size" => "size10",
            "height" => "12", "width" => "40", "required" => "false"
        ]);
        
        return [
            $Initial_1,
            $Initial_2,
            $Initial_3,
            $Initial_4,
            $Initial_5,
            $Initial_6,
            $Initial_7
        ];
    }

    public function sendToSBA() {

    }
}