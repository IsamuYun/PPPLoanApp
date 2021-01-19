<?php

namespace Drupal\application\Controller;

use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\Checkbox;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\InitialHere;
use DocuSign\eSign\Model\Radio;
use DocuSign\eSign\Model\RadioGroup;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\Tabs;
use DocuSign\eSign\Model\Text;

use Drupal\application\Controller\ApplicationController;

use Drupal\webform\Utility\WebformFormHelper;
use Drupal\Core\Form\FormStateInterface;

use SplFileObject;
use stdClass;
use NumberFormatter;

class SecondDrawBorrowerForm {
    public const DOCS_PATH = __DIR__ . '/../../documents/';

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
    public function make_envelope(array $args, ApplicationController $controller): EnvelopeDefinition
    {
        $this->elements = $controller->getElements();
        #
        # The envelope has two recipients.
        # recipient 1 - signer
        # recipient 2 - cc
        # The envelope will be sent first to the signer.
        # After it is signed, a copy is sent to the cc person.
        #
        # create the envelope definition
        $envelope_definition = new EnvelopeDefinition([
            'email_subject' => 'Please sign this Borrower Application Form'
         ]);
        # read files 2 and 3 from a local directory
        # The reads could raise an exception if the file is not available!
        $content_bytes = file_get_contents(self::DOCS_PATH . "PPP Second Draw Borrower Application Form.pdf");
        $borrower_form_b64 = base64_encode($content_bytes);
         
        # Create the document models
        $document = new Document([  # create the DocuSign document object
            'document_base64' => $borrower_form_b64,
            'name' => 'PPP Borrower Application Form',  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
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
             "value" => $controller->getBusinessAddress(),
             "height" => "20", "width" => "140", "required" => "false"
         ]);
 
         
         $business_address_2_text = new Text(['document_id' => "1", "page_number" => "1",
             "x_position" => "100", "y_position" => "175",
             "font" => "Arial", "font_size" => "size11",
             "value" => $controller->getBusinessAddress2(),
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

    private function getCompanyStructurePosition($company_structure) {
        $position = [];
        $position["x"] = 105;
        $position["y"] = 59;
        if ($company_structure == "General Partnership") {
            $position["x"] = 175;
            $position["y"] = 59;
        }
        else if ($company_structure == "C Corporation") {
            $position["x"] = 230;
            $position["y"] = 59;
        }
        else if ($company_structure == "S Corporation") {
            $position["x"] = 269;
            $position["y"] = 59;
        }
        else if ($company_structure == "Limited Liability Company") {
            $position["x"] = 309;
            $position["y"] = 59;
        }
        else if ($company_structure == "Independent Contractor") {
            $position["x"] = 105;
            $position["y"] = 70;
        }
        else if ($company_structure == "Eligible Self-employed Individual") {
            $position["x"] = 206;
            $position["y"] = 70;
        }
        else if ($company_structure == "501 (c)(3) nonprofit") {
            $position["x"] = 105;
            $position["y"] = 80;
        }
        else if ($company_structure == "501 (c)(6) organization") {
            $position["x"] = 190;
            $position["y"] = 80;
        }
        else if ($company_structure == "501 (c)(19) veterans organization") {
            $position["x"] = 105;
            $position["y"] = 90;
        }
        else if ($company_structure == "Housing cooperative") {
            $position["x"] = 238;
            $position["y"] = 90;
        }
        else if ($company_structure == "Tribal Business") {
            $position["x"] = 105;
            $position["y"] = 101;
        }
        else if ($company_structure == "Other") {
            $position["x"] = 177;
            $position["y"] = 101;
        }
        
        return $position;
    }

    public function getCheckboxList(ApplicationController $controller) {
        $cs_position = $this->getCompanyStructurePosition($controller->getCompanyStructure());
        $company_structure = new Checkbox([
            'document_id' => "1", 'page_number' => "1",
            "x_position" => $cs_position["x"], "y_position" => $cs_position["y"],
            "selected" => "true"]);
        
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
            $company_structure,
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

}