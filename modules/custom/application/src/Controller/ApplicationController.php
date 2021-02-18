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

use Drupal\application\Controller\FirstDrawBorrowerForm;
use Drupal\application\Controller\SecondDrawBorrowerForm;

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
        $signer_name = $this->getPrintName();
        $signer_email = $this->getBorrowerEmail();
        #$signer_name = "Isamu";
        #$signer_email = "yunforreg@gmail.com";
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

    public function sendSBANote(array &$form, FormStateInterface $form_state) {
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
        $envelope_definition = null;
        if ($this->elements["round"]["#default_value"] == "Yes") {
            $borrower_form = new SecondDrawBorrowerForm();
        }
        else {
            $borrower_form = new FirstDrawBorrowerForm();
        }
        $envelope_definition = $borrower_form->make_envelope($args["envelope_args"], $this, $this->elements);
        
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

    public function getBorrowerEmail() {
        $email = $this->elements["borrower_email"]["#default_value"];
        return $email;
    }

    public function getBusinessAddress() {
        $is_us_address = $this->elements["is_us_address"]["#default_value"];
        $address = "";
        if ($is_us_address == 1) {
            $address = $this->elements["business_address"]["#default_value"]["address"]
            . ", " . $this->elements["business_address"]["#default_value"]["address_2"];
        }
        else {
            $address = $this->elements["global_business_address"]["#default_value"]["address"]
            . ", " . $this->elements["global_business_address"]["#default_value"]["address_2"];
        }
        return $address;
    }

    public function getTIN() {
        $tax_id_number = $this->elements["tax_id_number"]["#default_value"];
        return $tax_id_number;
    }

    public function getBusinessAddress2() {
        $is_us_address = $this->elements["is_us_address"]["#default_value"];
        $address2 = "";
        if ($is_us_address == 1) {
            $state = $this->elements["business_address"]["#default_value"]["state_province"];
            $state_abbr = $this->convertState($state);
            $address2 = $this->elements["business_address"]["#default_value"]["city"] . ", ";
            if ($state_abbr) {
                $address2 .= $state_abbr . ", ";
            }
            if ($this->elements["business_address"]["#default_value"]["postal_code"]) {
                $address2 .= $this->elements["business_address"]["#default_value"]["postal_code"];
            }
        }
        else {
            $address2 = $this->elements["global_business_address"]["#default_value"]["city"]
            . ", " . $this->elements["global_business_address"]["#default_value"]["state_province"]
            . ", " . $this->elements["global_business_address"]["#default_value"]["postal_code"];
        }
        return $address2;
    }

    public function getFullBusinessAddress() {
        return $this->getBusinessAddress() . ", " . $this->getBusinessAddress2();
    }

    public function getDateEstablished() {
        $month = $this->elements["date_established"]["#value"]["month"];
        $day = $this->elements["date_established"]["#value"]["day"];
        $year = $this->elements["date_established"]["#value"]["year"];
        return $month . "/" . $day . "/" . $year;
    }

    public function getPrintName() {
        return $this->getFirstName(0) . " " . $this->getLastName(0);
    }

    public function getFirstName($num) {
        if ($num < 0) {
            return "";
        }
        if (empty($this->elements["first_name"])) {
            return "";
        }
        if (count($this->elements["first_name"]) <= $num) {
            return "";
        }
        if (empty($this->elements["first_name"][$num])) {
            return "";
        }

        return $this->elements["first_name"][$num]["#default_value"];
    }

    public function getLastName($num) {
        if ($num < 0) {
            return "";
        }
        if (empty($this->elements["last_name"])) {
            return "";
        }
        if (count($this->elements["last_name"]) <= $num) {
            return "";
        }
        if (empty($this->elements["last_name"][$num])) {
            return "";
        }
        return $this->elements["last_name"][$num]["#default_value"];
    }

    public function getOwnerName1() {
        
        return $this->getFirstName(1) . " " . $this->getLastName(1);
    }
    
    public function getOwnerName2() {
        return $this->getFirstName(2) . " " . $this->getLastName(2);
    }

    public function getOwnerJobTitle1() {
        return $this->getOwnerProperty("title", 0);
    }

    public function getOwnerJobTitle2() {
        return $this->getOwnerProperty("title", 1);
    }
    
    public function getOwnership($num) {
        if ($num < 0) {
            return "";
        }
        if (empty($this->elements["ownership_perc"])) {
            return "";
        }
        if (count($this->elements["ownership_perc"]) <= $num) {
            return "";
        }

        return $this->elements["ownership_perc"][$num]["#value"];
    }

    public function getOwnership1() {
        return $this->getOwnership(0);
    }

    public function getOwnership2() {
        return $this->getOwnership(1);
    }

    public function getOwnerTIN1() {
        return $this->getOwnerProperty("ssn", 0);
    }

    public function getOwnerTIN2() {
        return $this->getOwnerProperty("ssn", 1);
    }

    public function getOwnerAddress1() {
        $address = $this->getOwnerProperty("address", 3);
        $city = $this->getOwnerProperty("city", 3);
        $state = $this->getOwnerProperty("state", 0);
        $state = $this->convertState($state);
        $zip_code = $this->getOwnerProperty("zip_code", 0);
        if (empty($address)) {
            return "";
        }

        return $address . ", " . $city . ", " . $state . ", " . $zip_code;
    }
    
    public function getOwnerAddress2() {
        
        $address = $this->getOwnerProperty("address", 4);
        $city = $this->getOwnerProperty("city", 4);
        $state = $this->getOwnerProperty("state", 1);
        $state = $this->convertState($state);
        $zip_code = $this->getOwnerProperty("zip_code", 1);
        if (empty($address)) {
            return "";
        }
        return $address . ", " . $city . ", " . $state . ", " . $zip_code;
    }

    public function getOwnerProperty($field, $num) {
        if ($num < 0 || empty($field)) {
            return "";
        }
        if (empty($this->elements[$field])) {
            return "";
        }
        if (count($this->elements[$field]) <= $num) {
            return "";
        }
        return $this->elements[$field][$num]["#value"];
    }

    public function getOtherPurpose() {
        return $this->elements["purpose_of_loan_other"]["#default_value"];
    }
    
    public function getJobTitle() {
        $title = $this->elements["job_title"]["#default_value"];
        return $title;
    }

    public function getSBALoanNumber() {
        return $this->elements["sba_loan_number"]["#default_value"];
    }

    public function getSBALoanAmount() {
        $amount = $this->elements["sba_loan_amount"]["#default_value"];
        $amount = str_replace(",", "", $amount);
        return number_format($amount, 2);
    }

    public function getAveragePayroll() {
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

    public function getAmount($num) {
        $float_num = str_replace(",", "", $num);
        return number_format($float_num, 2);
    }

    public function getAveragePayrollAmount() {
        return number_format($this->getAveragePayroll(), 2);
    }

    public function getLoanAmount() {
        $average_payroll = $this->getAveragePayroll();
        
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

    public function getAdjustedLoanAmount() {
        return $this->elements["adjusted_loan_amount"]["#default_value"];
    }

    public function getAdjustedAveragePayrollAmount() {
        return $this->elements["adjusted_average_payroll"]["#default_value"];
    }

    public function getCompanyStructure() {
        return $this->elements["company_structure"]["#default_value"];
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
        if ($this->elements["borrower_envelope_status"]["#default_value"] != "completed") {
            return false;
        }
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
        
        if ($this->elements["sba_envelope_status"]["#default_value"] != "completed") {
            return false;
        }
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
            return false;
        }
        ob_clean();
        
        $submission_id = $form_state->getFormObject()->getEntity()->id();
        $submission_path = $this->getSubmissionPath($submission_id);

        $real_path = $this->getRealPath($submission_path);

        $errors_file_name = "sba_note_and_eo_" . $submission_id . ".pdf";
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
        /*
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
        */
        $form["elements"]["loan_officer_page"]["sba_documents"]["#default_value"] = [$attachment_id];

        $entity = $form_state->getFormObject()->getEntity();
        $data = $entity->getData();
        $data["sba_documents"] = $attachment_id;
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
            'email_subject' => 'Please sign SBA PPP Note and Errors and Omissions Agreement'
        ]);
        # read files 2 and 3 from a local directory
        # The reads could raise an exception if the file is not available!
        $content_bytes = file_get_contents(self::DOCS_PATH . "SBA PPP Note And EO.pdf");
        $sba_form_b64 = base64_encode($content_bytes);
        
        # Create the document models
        $ppp_doc = new Document([  # create the DocuSign document object
            'document_base64' => $sba_form_b64,
            'name' => 'SBA PPP Note and Errors and Omissions Agreement',  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
        ]);
        
        $sign_here_1 = new SignHere([
            'document_id' => "1", 'page_number' => "7",
            'x_position' => '340', 'y_position' => '123'
        ]);

        $sign_here_2 = new SignHere([
            'document_id' => "1", 'page_number' => "8",
            'x_position' => '80', 'y_position' => '546'
        ]);

        # Create the signer recipient model
        $signer = new Signer([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'role_name' => 'signer', 'recipient_id' => "1", 'routing_order' => "1"]);
        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.
        
        $text_list = $this->build_sba_text_list();
        
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
        $envelope_definition->setDocuments([$ppp_doc]);

        # Request that the envelope be sent by setting |status| to "sent".
        # To request that the envelope be created as a draft, set to "created"
        $envelope_definition->setStatus($args["status"]);

        return $envelope_definition;
    }

    private function build_sba_text_list() {
        $sba_loan_number_1 = new Text([
            'document_id' => "1", 'page_number' => "1",
            "x_position" => "180", "y_position" => "156",
            "font" => "Arial", "font_size" => "size12", 
            "value" => $this->getSBALoanNumber(),
            "height" => "20", "width" => "140", "required" => "false"]);
        
        $date_1 = new Text([
            'document_id' => "1", 'page_number' => "1",
            "x_position" => "180", "y_position" => "230",
            "font" => "Arial", "font_size" => "size12",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        $loan_amount = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "180", "y_position" => "260",
            "font" => "Arial", "font_size" => "size11",
            "value" => "$ " . $this->getSBALoanAmount(),
            "height" => "20", "width" => "100", "required" => "false"
        ]);
        
        // Primary Contact
        $primary_contact_1 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "180", "y_position" => "320",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getPrintName(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        // Business Name
        $business_name_1 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "180", "y_position" => "365",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->elements["business_name"]["#default_value"],
            "height" => "20", "width" => "200", "required" => "false"
        ]);

        $amount_number = str_replace(",", "", $this->getSBALoanAmount());

        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        $spell_number = $f->format($amount_number);
        $spell_number = strtoupper($spell_number);

        $spell_amount = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "96", "y_position" => "506",
            "font" => "Arial", "font_size" => "size11",
            "value" => $spell_number,
            "height" => "20", "width" => "480", "required" => "false"
        ]);
        
        // Primary Contact
        $borrower_name_1 = new Text([
            'document_id' => "1", "page_number" => "7",
            "x_position" => "96", "y_position" => "136",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getPrintName(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        $sba_loan_number_2 = new Text([
            'document_id' => "1", 'page_number' => "8",
            "x_position" => "300", "y_position" => "130",
            "font" => "Arial", "font_size" => "size12", 
            "value" => $this->getSBALoanNumber(),
            "height" => "20", "width" => "140", "required" => "false"]);
        
        $date_2 = new Text([
            'document_id' => "1", 'page_number' => "8",
            "x_position" => "460", "y_position" => "130",
            "font" => "Arial", "font_size" => "size12",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $business_name_2 = new Text(['document_id' => "1", "page_number" => "8",
            "x_position" => "250", "y_position" => "177",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["business_name"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        
        // Primary Contact
        $primary_contact_2 = new Text([
            'document_id' => "1", "page_number" => "8",
            "x_position" => "80", "y_position" => "605",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getPrintName(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $job_title = new Text([
            'document_id' => "1", "page_number" => "8",
            "x_position" => "80", "y_position" => "645",
            "font" => "Arial", "font_size" => "size12",
            "value" => $this->getJobTitle(),
            "height" => "14", "width" => "100", "required" => "false"
        ]);

        $date_3 = new Text([
            'document_id' => "1", 'page_number' => "8",
            "x_position" => "80", "y_position" => "684",
            "font" => "Arial", "font_size" => "size12",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        $array = [
            $sba_loan_number_1,
            $date_1,
            $loan_amount,
            $primary_contact_1,
            $business_name_1,
            $spell_amount,
            $borrower_name_1,
            $sba_loan_number_2,
            $date_2,
            $business_name_2,
            $primary_contact_2,
            $job_title,
            $date_3,
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

    /* -----------------------------------
    * CONVERT STATE NAMES!
    * Goes both ways. e.g.
    * $name = 'Orgegon' -> returns "OR"
    * $name = 'OR' -> returns "Oregon"
    * ----------------------------------- */
    public function convertState($name) {
        $states = array(
            array('name'=>'Alabama', 'abbr'=>'AL'),
            array('name'=>'Alaska', 'abbr'=>'AK'),
            array('name'=>'Arizona', 'abbr'=>'AZ'),
            array('name'=>'Arkansas', 'abbr'=>'AR'),
            array('name'=>'California', 'abbr'=>'CA'),
            array('name'=>'Colorado', 'abbr'=>'CO'),
            array('name'=>'Connecticut', 'abbr'=>'CT'),
            array('name'=>'Delaware', 'abbr'=>'DE'),
            array('name'=>'Florida', 'abbr'=>'FL'),
            array('name'=>'Georgia', 'abbr'=>'GA'),
            array('name'=>'Hawaii', 'abbr'=>'HI'),
            array('name'=>'Idaho', 'abbr'=>'ID'),
            array('name'=>'Illinois', 'abbr'=>'IL'),
            array('name'=>'Indiana', 'abbr'=>'IN'),
            array('name'=>'Iowa', 'abbr'=>'IA'),
            array('name'=>'Kansas', 'abbr'=>'KS'),
            array('name'=>'Kentucky', 'abbr'=>'KY'),
            array('name'=>'Louisiana', 'abbr'=>'LA'),
            array('name'=>'Maine', 'abbr'=>'ME'),
            array('name'=>'Maryland', 'abbr'=>'MD'),
            array('name'=>'Massachusetts', 'abbr'=>'MA'),
            array('name'=>'Michigan', 'abbr'=>'MI'),
            array('name'=>'Minnesota', 'abbr'=>'MN'),
            array('name'=>'Mississippi', 'abbr'=>'MS'),
            array('name'=>'Missouri', 'abbr'=>'MO'),
            array('name'=>'Montana', 'abbr'=>'MT'),
            array('name'=>'Nebraska', 'abbr'=>'NE'),
            array('name'=>'Nevada', 'abbr'=>'NV'),
            array('name'=>'New Hampshire', 'abbr'=>'NH'),
            array('name'=>'New Jersey', 'abbr'=>'NJ'),
            array('name'=>'New Mexico', 'abbr'=>'NM'),
            array('name'=>'New York', 'abbr'=>'NY'),
            array('name'=>'North Carolina', 'abbr'=>'NC'),
            array('name'=>'North Dakota', 'abbr'=>'ND'),
            array('name'=>'Ohio', 'abbr'=>'OH'),
            array('name'=>'Oklahoma', 'abbr'=>'OK'),
            array('name'=>'Oregon', 'abbr'=>'OR'),
            array('name'=>'Pennsylvania', 'abbr'=>'PA'),
            array('name'=>'Rhode Island', 'abbr'=>'RI'),
            array('name'=>'South Carolina', 'abbr'=>'SC'),
            array('name'=>'South Dakota', 'abbr'=>'SD'),
            array('name'=>'Tennessee', 'abbr'=>'TN'),
            array('name'=>'Texas', 'abbr'=>'TX'),
            array('name'=>'Utah', 'abbr'=>'UT'),
            array('name'=>'Vermont', 'abbr'=>'VT'),
            array('name'=>'Virginia', 'abbr'=>'VA'),
            array('name'=>'Washington', 'abbr'=>'WA'),
            array('name'=>'West Virginia', 'abbr'=>'WV'),
            array('name'=>'Wisconsin', 'abbr'=>'WI'),
            array('name'=>'Wyoming', 'abbr'=>'WY'),
            array('name'=>'Virgin Islands', 'abbr'=>'V.I.'),
            array('name'=>'Guam', 'abbr'=>'GU'),
            array('name'=>'Puerto Rico', 'abbr'=>'PR')
        );
    
        $return_name = $name;   
        $strlen = strlen($name);
        if (strlen($name) <= 2) {
            return $return_name;
        }

        foreach ($states as $state) {
            //else if ($strlen == 2) {
            //    if (strtolower($state['abbr']) == strtolower($name)) {
            //        $return = $state['name'];
            //        break;
            //    }   
            //} 
            if (strtolower($state['name']) == strtolower($name)) {
                $return_name = strtoupper($state['abbr']);
                break;
            }         
        };
    
        return $return_name;
    } // end function convertState()

}
