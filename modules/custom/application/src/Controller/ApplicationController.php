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

use Drupal\application\Service\ClientService;
use Drupal\application\Service\JWTService;

use Drupal\webform\Utility\WebformFormHelper;
use Drupal\Core\Form\FormStateInterface;

use SplFileObject;
use stdClass;
use NumberFormatter;

require_once __DIR__ . '/../ds_config.php';

/**
 * Defines ApplicationController class.
 */

//class ApplicationController extends ControllerBase {
class ApplicationController {
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
    public function sendBorrowerDocuSignForm(array &$form, FormStateInterface $form_state) {
        $result = $this->worker($this->args);

        if ($result && empty($result["envelope_id"])) {
            return;
        }

        $entity = $form_state->getFormObject()->getEntity();
        $data = $entity->getData();
        $data["borrower_envelope_status"] = "Sent";
        $data["borrower_envelope_id"] = $result["envelope_id"];
        $entity->setData($data);
        $entity->save();

        $this->elements["borrower_envelope_status"]["#value"] = "Sent";
        $this->elements["borrower_envelope_id"]["#value"] = $result["envelope_id"];
    }

    public function sendSBAFiles(array &$form, FormStateInterface $form_state) {
        $result = $this->sba_worker($this->args);

        if ($result && empty($result["envelope_id"])) {
            return;
        }

        $entity = $form_state->getFormObject()->getEntity();
        $data = $entity->getData();
        $data["sba_envelope_status"] = "Sent";
        $data["sba_envelope_id"] = $result["envelope_id"];
        $entity->setData($data);
        $entity->save();

        $this->elements["sba_envelope_status"]["#value"] = "Sent";
        $this->elements["sba_envelope_id"]["#value"] = $result["envelope_id"];
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
        
        $company_structure_text = new Text(['document_id' => "1", 'page_number' => "1",
            "x_position" => "120", "y_position" => "70",
            "font" => "Arial", "font_size" => "size12", "value" => $this->elements["company_structure"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"]);
        
        $business_classify_text = new Text(['document_id' => "1", 'page_number' => "1",
            "x_position" => "120", "y_position" => "90",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->elements["business_classify"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $business_name_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "100", "y_position" => "125",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["business_name"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        $business_address_1_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "100", "y_position" => "155",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->getBusinessAddress(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        
        $business_address_2_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "100", "y_position" => "175",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->getBusinessAddress2(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $ssn_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "380", "y_position" => "153",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["social_security_number"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $business_phone_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "480", "y_position" => "153",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["business_phone_number"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        // Primary Contact
        $primary_contact = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "380", "y_position" => "178",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->getPrintName(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        
        $email_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "480", "y_position" => "178",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->getBorrowerEmail(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $num_of_employees_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "540", "y_position" => "208",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["number_of_employees"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $average_monthly_payroll = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "170", "y_position" => "208",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->getAveragePayroll(),
            "height" => "20", "width" => "100", "required" => "false"
        ]);

        $loan_amount = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "364", "y_position" => "208",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->getLoanAmount(),
            "height" => "20", "width" => "100", "required" => "false"
        ]);

        $owner_name = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "325",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->getPrintName(),
            "height" => "14", "width" => "100", "required" => "false"
        ]);

        $owner_job_title = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "230", "y_position" => "325",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->getJobTitle(),
            "height" => "14", "width" => "100", "required" => "false"
        ]);

        $ownership = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "315", "y_position" => "325",
            "font" => "Arial", "font_size" => "size10",
            "value" => "100%",
            "height" => "14", "width" => "60", "required" => "false"
        ]);

        $owner_ssn = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "360", "y_position" => "325",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["social_security_number"]["#default_value"],
            "height" => "14", "width" => "60", "required" => "false"
        ]);

        $owner_address = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "425", "y_position" => "325",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->getBusinessAddress(),
            "height" => "14", "width" => "60", "required" => "false"
        ]);


        $another_business_name = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "410", "y_position" => "85",
            "font" => "Arial", "font_size" => "size16",
            "value" => $this->elements["another_business_name"]["#default_value"],
            "height" => "32", "width" => "160", "required" => "false"
        ]);

        $today = new Text([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "380", "y_position" => "670",
            "font" => "Arial", "font_size" => "size12",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "100", "required" => "false"
        ]);
        
        $print_name = new Text([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "40", "y_position" => "705",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getPrintName(),
            "height" => "20", "width" => "200", "required" => "false"
        ]);

        $job_title = new Text([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "380", "y_position" => "705",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getJobTitle(),
            "height" => "20", "width" => "200", "required" => "false"
        ]);
        
        
        $sign_here = new SignHere(['document_id' => "1", 'page_number' => "2",
        'x_position' => '40', 'y_position' => '650']);

        # Create the signer recipient model
        $signer = new Signer([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'role_name' => 'signer', 'recipient_id' => "1", 'routing_order' => "1"]);
        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.
        
        $radio_groups = $this->getRadioGroup();

        $purpose_list = $this->getPurposeList();

        $initial_here_list = $this->getInitialList();

        $signer->setTabs(new Tabs(['sign_here_tabs' => [$sign_here],
            'initial_here_tabs' => $initial_here_list,
            'radio_group_tabs' => $radio_groups,
            'checkbox_tabs' => $purpose_list,
            'text_tabs' => [
                $company_structure_text,
                $business_classify_text,
                $business_name_text,
                $business_address_1_text,
                $business_address_2_text,
                $ssn_text,
                $business_phone_text,
                $primary_contact,
                $email_text,
                $num_of_employees_text,
                $average_monthly_payroll,
                $loan_amount,
                $another_business_name,
                $today,
                $print_name,
                $job_title,
                $owner_name,
                $owner_job_title,
                $ownership,
                $owner_ssn,
                $owner_address,
            ]
        ]));

        # Add the recipients to the envelope object
        $recipients = new Recipients([
            'signers' => [$signer]
        ]);
        $envelope_definition->setRecipients($recipients);

        # The order in the docs array determines the order in the envelope
        $envelope_definition->setDocuments([$document]);

        # Request that the envelope be sent by setting |status| to "sent".
        # To request that the envelope be created as a draft, set to "created"
        $envelope_definition->setStatus($args["status"]);

        return $envelope_definition;
    }
    
    public function getBorrowerEmail() {
        $email = $this->elements["borrower_email"]["#default_value"];
        return $email;
    }

    private function getBusinessAddress() {
        $is_us_address = $this->elements["is_us_address"]["#default_value"];
        $address = "";
        if ($is_us_address === "1") {
            $address = $this->elements["business_address"]["#default_value"]["address"]
            . ", " . $this->elements["business_address"]["#default_value"]["address_2"];
        }
        else {
            $address = $this->elements["global_business_address"]["#default_value"]["address"]
            . ", " . $this->elements["global_business_address"]["#default_value"]["address_2"];
        }
        return $address;
    }

    private function getBusinessAddress2() {
        $is_us_address = $this->elements["is_us_address"]["#default_value"];
        $address2 = "";
        if ($is_us_address === "1") {
            $address2 = $this->elements["business_address"]["#default_value"]["city"]
            . ", " . $this->elements["business_address"]["#default_value"]["state_province"]
            . ", " . $this->elements["business_address"]["#default_value"]["postal_code"];

        }
        else {
            $address2 = $this->elements["global_business_address"]["#default_value"]["city"]
            . ", " . $this->elements["global_business_address"]["#default_value"]["state_province"]
            . ", " . $this->elements["global_business_address"]["#default_value"]["postal_code"];
        }
        return $address2;
    }

    private function getPrintName() {
        $first_name = $this->elements["first_name"]["#default_value"];
        $last_name = $this->elements["last_name"]["#default_value"];
        return $first_name . " " . $last_name;
    }

    private function getFirstName() {
        $first_name = $this->elements["first_name"]["#default_value"];
        return $first_name;
    }

    public function getInitialName() {
        $first_name = $this->elements["first_name"]["#default_value"];
        $last_name = $this->elements["last_name"]["#default_value"];
        
        $initial_first = strtoupper(substr($first_name, 0, 1));
        $initial_last = strtoupper(substr($last_name, 0, 1));
        return $initial_first . $initial_last;
    }

    private function getJobTitle() {
        $title = $this->elements["job_title"]["#default_value"];
        return $title;
    }

    private function getAveragePayroll() {
        $number_of_employees = $this->elements["number_of_employees"]["#default_value"];
        $net_earnings = $this->elements["net_earnings"]["#default_value"];
        $net_earnings = str_replace(",", "", $net_earnings);
        $net_earnings = floatval(substr($net_earnings, 2));
        $average_payroll = 0;
        
        if ($net_earnings > 100000) {
            $net_earnings = 100000;
        }
        else if ($net_earnings < 0) {
            $net_earnings = 0;
        }
        
        $total_payroll = $this->elements["total_salaries"]["#default_value"];
        $total_tax_paid = $this->elements["total_tax_paid"]["#default_value"];
        $total_payroll = str_replace(",", "", $total_payroll);
        $total_tax_paid = str_replace(",", "", $total_tax_paid);
        $total_payroll = floatval(substr($total_payroll, 2));
        $total_tax_paid = floatval(substr($total_tax_paid, 2));

        if ($total_payroll > ($number_of_employees * 100000)) {
            $total_payroll = $number_of_employees * 100000;
        }
        else if ($total_payroll < 0) {
            $total_payroll = 0;
        }

        if ($total_tax_paid > ($number_of_employees * 100000)) {
            $total_tax_paid = $number_of_employees * 100000;
        }
        else if ($total_tax_paid < 0) {
            $total_tax_paid = 0;
        }
        
        if ($number_of_employees <= 1) {
            $average_payroll = $net_earnings / 12;
        }
        else {
            $average_payroll = ($net_earnings + $total_payroll + $total_tax_paid) / 12;
        }

        return $average_payroll;
    }

    private function getLoanAmount() {
        $average_payroll = $this->getAveragePayroll();

        $loan_amount = 0;

        $used_EIDL_amount = $this->elements["used_loan_amount"]["#default_value"];
        $used_EIDL_amount = str_replace(",", "", $used_EIDL_amount);
        $used_EIDL_amount = floatval(substr($used_EIDL_amount, 2));

        if ($used_EIDL_amount < 0) {
            $used_EIDL_amount = 0;
        }

        $loan_amount = $average_payroll * 2.5 - $used_EIDL_amount;
        if ($loan_amount < 0) {
            $loan_amount = 0;
        }
        return number_format($loan_amount, 2);
    }

    private function getPurposeList() {
        $payroll_percentage = $this->elements["payroll_costs_"]["#default_value"];
        $less_percentage = $this->elements["less_mortgage_interest_"]["#default_value"];
        $utilities_percentage = $this->elements["utilities_"]["#default_value"];
        $other = $this->elements["other_costs"]["#default_value"];
        
        $selected = "false";
        if ($payroll_percentage > 0) {
            $selected = "true";
        }
        else {
            $selected = "false";
        }
        $payroll_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "162", 'y_position' => "263",
            'selected' => $selected
        ]);
        
        if ($less_percentage > 0) {
            $selected = "true";
        }
        else {
            $selected = "false";
        }
        $less_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "200", 'y_position' => "263",
            'selected' => $selected
        ]);

        if ($utilities_percentage > 0) {
            $selected = "true";
        }
        else {
            $selected = "false";
        }
        $utilities_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "300", 'y_position' => "263",
            'selected' => $selected
        ]);

        if ($other > 0) {
            $selected = "true";
        }
        else {
            $selected = "false";
        }
        $other_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "350", 'y_position' => "263",
            'selected' => $selected
        ]);

        $checkbox_list = [
            $payroll_checkbox,
            $less_checkbox,
            $utilities_checkbox,
            $other_checkbox
        ];

        return $checkbox_list;
    }


    private function getRadioGroup() {
        $selected = false;
        
        if ($this->elements["question_step_18_1"]["#default_value"] === "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q1_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q1_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "400",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "560", 'y_position' => "400",
                'value' => "No", 
                'selected' => $selected ? "false" : "true",
                'required' => "false"])
        ]]);


        if ($this->elements["question_18_2"]["#default_value"] === "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q2_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q2_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "440",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false",  
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "560", 'y_position' => "440",
                'value' => "No", 
                'selected' => $selected ? "false" : "true",
                'required' => "false"])
        ]]);

        if ($this->elements["question_18_3"]["#default_value"] === "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q3_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q3_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "480",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "560", 'y_position' => "480",
                'value' => "No", 
                'selected' => $selected ? "false" : "true",
                'required' => "false"])
        ]]);

        if ($this->elements["has_received_loan"]["#default_value"] === "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q4_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q4_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "510",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "560", 'y_position' => "510",
                'value' => "No", 
                'selected' => $selected ? "false" : "true",
                'required' => "false"])
        ]]);

        if ($this->elements["is_question_23_1"]["#default_value"] === "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q5_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q5_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "515", 'y_position' => "575",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "550", 'y_position' => "575",
                'value' => "No", 
                'selected' => $selected ? "false" : "true",
                'required' => "false"])
        ]]);

        if ($this->elements["is_convicted"]["#default_value"] === "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q6_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q6_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "515", 'y_position' => "630",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "550", 'y_position' => "630",
                'value' => "No", 
                'selected' => $selected ? "false" : "true",
                'required' => "false"])
        ]]);

        if ($this->elements["is_residence_"]["#default_value"] === "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q7_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q7_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "515", 'y_position' => "690",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "550", 'y_position' => "690",
                'value' => "No", 
                'selected' => $selected ? "false" : "true",
                'required' => "false"])
        ]]);

        if ($this->elements["is_franchise_listed_in"]["#default_value"] === "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q8_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q8_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "515", 'y_position' => "720",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "550", 'y_position' => "720",
                'value' => "No", 
                'selected' => $selected ? "false" : "true",
                'required' => "false"])
        ]]);


        $radio_groups = [
            $q1_radio_group, 
            $q2_radio_group,
            $q3_radio_group,
            $q4_radio_group,
            $q5_radio_group,
            $q6_radio_group,
            $q7_radio_group,
            $q8_radio_group
        ];

        return $radio_groups;
    }

    private function getInitialList() {
        $Initial_1 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "292", "y_position" => "590",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_2 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "328", "y_position" => "658",
            "height" => "12", "width" => "40", "required" => "false"
        ]);
        
        $Initial_3 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "40", "y_position" => "350",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_4 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "40", "y_position" => "380",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_5 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "40", "y_position" => "406",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_6 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "40", "y_position" => "445",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->getInitialName(),
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_7 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "40", "y_position" => "490",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->getInitialName(),
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_8 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "40", "y_position" => "524",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_9 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "40", "y_position" => "554",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        $Initial_10 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "40", "y_position" => "618",
            "height" => "12", "width" => "40", "required" => "false"
        ]);

        return [
            $Initial_1,
            $Initial_2,
            $Initial_3,
            $Initial_4,
            $Initial_5,
            $Initial_6,
            $Initial_7,
            $Initial_8,
            $Initial_9,
            $Initial_10,
        ];
    }

    private function getDownloadArgs($document_id = "1", $envelope_id): array
    {
        #$envelope_id= isset($_SESSION['envelope_id']) ? $_SESSION['envelope_id'] : false;
        #$envelope_documents = isset($_SESSION['envelope_documents']) ? $_SESSION['envelope_documents'] : false;
        #$document_id  = preg_replace('/([^\w \-\@\.\,])+/', '', $_POST['document_id' ]);
        
        #$envelope_id = isset($this->elements["envelope_id"]["#default_value"]) ?
        #        $this->elements["envelope_id"]["#default_value"] : false;   

        $args = [
            'account_id' => $_SESSION['ds_account_id'],
            'base_path' => $_SESSION['ds_base_path'],
            'ds_access_token' => $_SESSION['ds_access_token'],
            'envelope_id' => $envelope_id,
            'document_id' => $document_id,
            #'document_id' => "1",
        ];

        return $args;
    }

    private function download_worker(array $args): array
    {
        # 1. call API method
        # Exceptions will be caught by the calling function
        $envelope_api = $this->clientService->getEnvelopeApi();

        # An SplFileObject is returned. See http://php.net/manual/en/class.splfileobject.php

        $temp_file = $envelope_api->getDocument($args['account_id'],  $args['document_id'], $args['envelope_id']);
         # find the matching document information item
        $doc_item = false;
        /*
        foreach ($args['envelope_documents']['documents'] as $item) {
            if ($item['document_id'] ==  $args['document_id']) {
                $doc_item = $item;
                break;
            }
        }
        $doc_name = $doc_item['name'];
        $has_pdf_suffix = strtoupper(substr($doc_name, -4)) == '.PDF';
        $pdf_file = $has_pdf_suffix;
        # Add ".pdf" if it's a content or summary doc and doesn't already end in .pdf
        if ($doc_item["type"] == "content" || ($doc_item["type"] == "summary" && ! $has_pdf_suffix)) {
            $doc_name .= ".pdf";
            $pdf_file = true;
        }
        # Add .zip as appropriate
        if ($doc_item["type"] == "zip") {
            $doc_name .= ".zip";
        }
        # Return the file information
        if ($pdf_file) {
            $mimetype = 'application/pdf';
        } elseif ($doc_item["type"] == 'zip') {
            $mimetype = 'application/zip';
        } else {
            $mimetype = 'application/octet-stream';
        }
        */
        $mimetype = 'application/pdf';
        $doc_name = "test.pdf";
        return ['mimetype' => $mimetype, 'doc_name' => $doc_name, 'data' => $temp_file];
    }

    public function downloadBorrowerForm(array &$form, FormStateInterface &$form_state) {
        if (!empty($form["elements"]["loan_officer_page"]["borrower_application_form"]["#default_value"])) {
            return false;
        }
        if (!isset($this->elements["borrower_envelope_id"]["#default_value"])) {
            return false;
        }
        $envelope_id = isset($this->elements["borrower_envelope_id"]["#default_value"]) ?
            $this->elements["borrower_envelope_id"]["#default_value"] : false;   
        
        $args = $this->getDownloadArgs("1", $envelope_id);
        $result = null;
        try {
            $results = $this->download_worker($args);
        }
        catch (ApiException $e) {
            dpm($e);
            return false;
        }
        
        # See https://stackoverflow.com/a/27805443/64904
        #header("Content-Type: {$results['mimetype']}");
        #header("Content-Disposition: attachment; filename=\"{$results['doc_name']}\"");
        #$file_path = $results['data']->getPathname();
        #readfile($file_path);
        ob_clean();
        #ob_start();
        #flush();
        
        $submission_id = $form_state->getFormObject()->getEntity()->id();
        $submission_path = $this->getSubmissionPath($submission_id);

        $real_path = $this->getRealPath($submission_path);

        $file_name = "borrower_form_" . $submission_id . ".pdf";
        $file1 = new SplFileObject($real_path . "/" . $file_name, "w+");
        $file = $results["data"];
        clearstatcache();
        $handle = $file->openFile('r');
        $content = $handle->fread($file->getSize());
            
        $length = $file1->fwrite($content);
        
        $attachment_file = file_save_data($content, 
                $submission_path . "/" . $file_name,
                \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
        $attachment_id = intval($attachment_file->id());
        
        $form["elements"]["loan_officer_page"]["borrower_application_form"]["#default_value"] = [$attachment_id];

        $entity = $form_state->getFormObject()->getEntity();
        $data = $entity->getData();
        $data["borrower_application_form"] = $attachment_id;
        $entity->setData($data);
        $entity->save();

        return $attachment_id ? true : false;
    }

    public function downloadSBADocuments(array &$form, FormStateInterface &$form_state) {
        if (!empty($this->elements["sba_documents"]["#default_value"])) {
            return false;
        }
        if (!isset($this->elements["sba_envelope_id"]["#default_value"])) {
            return false;
        }
        $envelope_id = isset($this->elements["sba_envelope_id"]["#default_value"]) ?
            $this->elements["sba_envelope_id"]["#default_value"] : false;
        
        $args = $this->getDownloadArgs("1", $envelope_id);
        $result = null;
        try {
            $results = $this->download_worker($args);
        }
        catch (ApiException $e) {
            dpm($e);
            return false;
        }
        ob_clean();
        
        $submission_id = $form_state->getFormObject()->getEntity()->id();
        $submission_path = $this->getSubmissionPath($submission_id);

        $real_path = $this->getRealPath($submission_path);

        $errors_file_name = "sba_errors_and_omissions_" . $submission_id . ".pdf";
        $file1 = new SplFileObject($real_path . "/" . $errors_file_name, "w+");
        $file = $results["data"];
        clearstatcache();
        $handle = $file->openFile('r');
        $content = $handle->fread($file->getSize());
        $length = $file1->fwrite($content);
        
        $attachment_file = file_save_data($content, 
                $submission_path . "/" . $errors_file_name,
                \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
        $attachment_id = intval($attachment_file->id());

        $args = $this->getDownloadArgs("2", $envelope_id);
        $result = null;
        try {
            $results = $this->download_worker($args);
        }
        catch (ApiException $e) {
            dpm($e);
            return false;
        }
        ob_clean();
        $note_file_name = "sba_ppp_note_" . $submission_id . ".pdf";
        $file2 = new SplFileObject($real_path . "/" . $note_file_name, "w+");
        $file = $results["data"];
        clearstatcache();
        $handle = $file->openFile('r');
        $content = $handle->fread($file->getSize());
        $length = $file2->fwrite($content);
        
        $note_file = file_save_data($content, 
                $submission_path . "/" . $note_file_name,
                \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
        $note_id = intval($note_file->id());
        
        $form["elements"]["loan_officer_page"]["sba_documents"]["#default_value"] = [$attachment_id, $note_id];

        $entity = $form_state->getFormObject()->getEntity();
        $data = $entity->getData();
        $data["sba_documents"] = [$attachment_id, $note_id];
        $entity->setData($data);
        $entity->save();
        return true;
    }

    /**
     * Do the work of the example
     * 1. Create the envelope request object
     * 2. Send the envelope
     *
     * @param  $args array
     * @return array ['envelope_id']
     * @throws ApiException for API problems and perhaps file access \Exception too.
     */
    public function sba_worker($args): array
    {
        # 1. Create the envelope request object
        $envelope_definition = $this->make_sba_envelope($args["envelope_args"]);
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
     * Create sba envelope definition
     * Document 1: SBA Errors
     * Document 2: SBA Notes
     * DocuSign will convert all of the documents to the PDF format.
     * The recipients' field tags are placed using <b>anchor</b> strings.
     *
     * Parameters for the envelope: signer_email, signer_name, signer_client_id
     *
     * @param  $args array
     * @return EnvelopeDefinition -- returns an envelope definition
     */
    private function make_sba_envelope(array $args): EnvelopeDefinition
    {
        #
        # The envelope has only one recipient.
        # recipient 1 - signer
        # The envelope will be sent first to the signer.
        # After it is signed, a copy is sent to the cc person.
        #
        # create the envelope definition
        $envelope_definition = new EnvelopeDefinition([
            'email_subject' => 'Please sign SBA PPP Errors and Omissions Agreement and Note'
        ]);
        # read files 2 and 3 from a local directory
        # The reads could raise an exception if the file is not available!
        $content_bytes = file_get_contents(self::DOCS_PATH . "SBA PPP Errors and Omissions Agreement.pdf");
        $sba_form_b64 = base64_encode($content_bytes);
        
        # Create the document models
        $ppp_doc = new Document([  # create the DocuSign document object
            'document_base64' => $sba_form_b64,
            'name' => 'SBA PPP Errors and Omissions Agreement',  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
        ]);

        $note_bytes = file_get_contents(self::DOCS_PATH . "SBA PPP Note.pdf");
        $note_b64 = base64_encode($note_bytes);

        $note_doc = new Document([
            "document_base64" => $note_b64,
            "name" => "SBA PPP Note",
            "file_extension" => "pdf",
            "document_id" => "2"
        ]);

        $sign_here_1 = new SignHere([
            'document_id' => "1", 'page_number' => "1",
            'x_position' => '40', 'y_position' => '470'
        ]);

        $sign_here_2 = new SignHere([
            'document_id' => "2", 'page_number' => "7",
            'x_position' => '80', 'y_position' => '85'
        ]);

        # Create the signer recipient model
        $signer = new Signer([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'role_name' => 'signer', 'recipient_id' => "1", 'routing_order' => "1"]);
        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.
        
        $text_list = $this->build_sba_text_list();
        $text_list_2 = $this->build_note_text_list();
        foreach ($text_list_2 as $text) {
            $text_list[] = $text;
        }
        
        $signer->setTabs(new Tabs([
            'sign_here_tabs' => [$sign_here_1, $sign_here_2],
            'text_tabs' => $text_list,
        ]));

        # Add the recipients to the envelope object
        $recipients = new Recipients([
            'signers' => [$signer]
        ]);
        $envelope_definition->setRecipients($recipients);

        # The order in the docs array determines the order in the envelope
        $envelope_definition->setDocuments([$ppp_doc, $note_doc]);

        # Request that the envelope be sent by setting |status| to "sent".
        # To request that the envelope be created as a draft, set to "created"
        $envelope_definition->setStatus($args["status"]);

        return $envelope_definition;
    }

    private function build_sba_text_list() {
        $sba_loan_number = new Text([
            'document_id' => "1", 'page_number' => "1",
            "x_position" => "280", "y_position" => "130",
            "font" => "Arial", "font_size" => "size12", 
            "value" => "1234567890",
            "height" => "20", "width" => "140", "required" => "false"]);
        
        $date_1 = new Text([
            'document_id' => "1", 'page_number' => "1",
            "x_position" => "460", "y_position" => "130",
            "font" => "Arial", "font_size" => "size12",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $borrower_name_1 = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "186", "y_position" => "186",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["business_name"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        $borrower_name_2 = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "84", "y_position" => "460",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["business_name"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $date_2 = new Text([
            'document_id' => "1", 'page_number' => "1",
            "x_position" => "340", "y_position" => "500",
            "font" => "Arial", "font_size" => "size12",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        // Primary Contact
        $primary_contact = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "530",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getPrintName(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $job_title = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "340", "y_position" => "530",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getJobTitle(),
            "height" => "14", "width" => "100", "required" => "false"
        ]);
        
        
        
        $array = [
            $sba_loan_number,
            $date_1,
            $borrower_name_1,
            $borrower_name_2,
            $date_2,
            $primary_contact,
            $job_title
        ];
        return $array;
    }

    private function build_note_text_list() {
        $sba_loan_number = new Text([
            'document_id' => "2", 'page_number' => "1",
            "x_position" => "180", "y_position" => "156",
            "font" => "Arial", "font_size" => "size12", 
            "value" => "1234567890",
            "height" => "20", "width" => "140", "required" => "false"]);
        
        $date_1 = new Text([
            'document_id' => "2", 'page_number' => "1",
            "x_position" => "180", "y_position" => "230",
            "font" => "Arial", "font_size" => "size12",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        $loan_amount = new Text([
            'document_id' => "2", "page_number" => "1",
            "x_position" => "180", "y_position" => "260",
            "font" => "Arial", "font_size" => "size11",
            "value" => "$ " . $this->getLoanAmount(),
            "height" => "20", "width" => "100", "required" => "false"
        ]);
        
        // Primary Contact
        $primary_contact = new Text([
            'document_id' => "2", "page_number" => "1",
            "x_position" => "180", "y_position" => "320",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getPrintName(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        // Business Name
        $business_name_1 = new Text([
            'document_id' => "2", "page_number" => "1",
            "x_position" => "180", "y_position" => "365",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->elements["business_name"]["#default_value"],
            "height" => "20", "width" => "200", "required" => "false"
        ]);

        $amount_number = str_replace(",", "", $this->getLoanAmount());

        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        $spell_number = $f->format($amount_number);

        $spell_amount = new Text([
            'document_id' => "2", "page_number" => "1",
            "x_position" => "96", "y_position" => "502",
            "font" => "Arial", "font_size" => "size11",
            "value" => $spell_number,
            "height" => "20", "width" => "360", "required" => "false"
        ]);
        
        $date_2 = new Text([
            'document_id' => "2", 'page_number' => "7",
            "x_position" => "375", "y_position" => "100",
            "font" => "Arial", "font_size" => "size12",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        // Primary Contact
        $borrower_name = new Text([
            'document_id' => "2", "page_number" => "7",
            "x_position" => "80", "y_position" => "145",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getPrintName(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $job_title = new Text([
            'document_id' => "2", "page_number" => "7",
            "x_position" => "375", "y_position" => "145",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getJobTitle(),
            "height" => "14", "width" => "100", "required" => "false"
        ]);
        
        
        $array = [
            $sba_loan_number,
            $date_1,
            $loan_amount,
            $primary_contact,
            $business_name_1,
            $spell_amount,
            $date_2,
            $borrower_name,
            $job_title
        ];
        return $array;
    }

    private function getSubmissionPath($submission_id) {
        return "private://webform/apply_for_ppp_loan/" . $submission_id;
    }

    private function getRealPath($submission_path) {
        $real_path = \Drupal::service('file_system')->realpath($submission_path);
        if (!is_dir($real_path)) {
            mkdir($real_path);
        }
        return $real_path;
    }

}
