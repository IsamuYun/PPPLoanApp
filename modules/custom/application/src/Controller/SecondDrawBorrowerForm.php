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
    private $elements;
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
    public function make_envelope(array $args, ApplicationController $controller, &$elements): EnvelopeDefinition
    {
        $this->elements = $elements;
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
         
        $business_name = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "80", "y_position" => "124",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["business_name"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
        
        $another_business_name = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "360", "y_position" => "85",
            "font" => "Arial", "font_size" => "size11",
            "value" => $this->elements["another_business_name"]["#default_value"],
            "height" => "24", "width" => "160", "required" => "false"
        ]);

        $date_established = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "480", "y_position" => "85",
            "font" => "Arial", "font_size" => "size11",
            "value" => $controller->getDateEstablished(),
            "height" => "24", "width" => "160", "required" => "false"
        ]);
        
        $naics_code = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "360", "y_position" => "124",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["naics_code"]["#default_value"],
            "height" => "20", "width" => "200", "required" => "false"
        ]);
         
        $business_address_1 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "80", "y_position" => "158",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getBusinessAddress(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
         
        $business_address_2 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "80", "y_position" => "175",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getBusinessAddress2(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
 
        $ssn = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "360", "y_position" => "155",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getTIN(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
 
        $business_phone = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "450", "y_position" => "155",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["business_phone_number"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);
         
        $primary_contact = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "360", "y_position" => "182",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getPrintName(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);
         
        $email = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "450", "y_position" => "182",
            "font" => "Arial", "font_size" => "size7",
            "value" => $controller->getBorrowerEmail(),
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $average_monthly_payroll = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "140", "y_position" => "214",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getAmount($controller->getAdjustedAveragePayrollAmount()),
            "height" => "20", "width" => "100", "required" => "false"
        ]);

        $loan_amount = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "333", "y_position" => "214",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getAmount($controller->getAdjustedLoanAmount()),
            "height" => "20", "width" => "100", "required" => "false"
        ]);
 
        $num_of_employees = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "536", "y_position" => "214",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["number_of_employees"]["#default_value"],
            "height" => "20", "width" => "140", "required" => "false"
        ]);

        $other_purpose = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "472", "y_position" => "290",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOtherPurpose(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);

        $sba_loan_num = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "136", "y_position" => "310",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["first_draw_sba_loan_number"]["#default_value"],
            "height" => "20", "width" => "200", "required" => "false"
        ]);

        $quarter_2020 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "300", "y_position" => "335",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["2020_quarter"]["#value"] . " 2020",
            "height" => "20", "width" => "200", "required" => "false"
        ]);

        $quarter_2019 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "485", "y_position" => "335",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["reference_2019_quarter"]["#value"] . " 2019",
            "height" => "20", "width" => "200", "required" => "false"
        ]);

        $gross_receipts_2020 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "300", "y_position" => "360",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getAmount($this->elements["2020_gross_receipts"]["#value"]),
            "height" => "20", "width" => "200", "required" => "false"
        ]);

        $gross_receipts_2019 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "485", "y_position" => "360",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getAmount($this->elements["2019_gross_receipts"]["#value"]),
            "height" => "20", "width" => "200", "required" => "false"
        ]);

        $owner_name_0 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "430",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getPrintName(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);
        
        $owner_name_1 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "445",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerName1(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);

        $owner_name_2 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "455",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerName2(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);

        $owner_job_title_0 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "235", "y_position" => "430",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getJobTitle(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);
 
        $owner_job_title_1 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "235", "y_position" => "445",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerJobTitle1(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);

        $owner_job_title_2 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "235", "y_position" => "455",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerJobTitle2(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);

        $ownership_0 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "325", "y_position" => "430",
            "font" => "Arial", "font_size" => "size8",
            "value" => $this->elements["percentage_of_business"]["#value"],
            "height" => "10", "width" => "60", "required" => "false"
        ]);
 
        $ownership_1 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "325", "y_position" => "445",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnership1(),
            "height" => "10", "width" => "60", "required" => "false"
        ]);

        $ownership_2 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "325", "y_position" => "455",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnership2(),
            "height" => "10", "width" => "60", "required" => "false"
        ]);

        $owner_tin_0 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "365", "y_position" => "430",
            "font" => "Arial", "font_size" => "size8",
            "value" => $this->elements["social_security_number"]["#default_value"],
            "height" => "10", "width" => "60", "required" => "false"
        ]);
 
        $owner_tin_1 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "365", "y_position" => "445",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerTIN1(),
            "height" => "10", "width" => "60", "required" => "false"
        ]);
        
        $owner_tin_2 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "365", "y_position" => "455",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerTIN2(),
            "height" => "10", "width" => "60", "required" => "false"
        ]);
        
        $owner_address_0 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "425", "y_position" => "430",
            "font" => "Arial", "font_size" => "size7",
            "value" => $controller->getFullBusinessAddress(),
            "height" => "10", "width" => "120", "required" => "false"
        ]);
 
        $owner_address_1 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "425", "y_position" => "445",
            "font" => "Arial", "font_size" => "size7",
            "value" => $controller->getOwnerAddress1(),
            "height" => "10", "width" => "120", "required" => "false"
        ]);

        $owner_address_2 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "425", "y_position" => "455",
            "font" => "Arial", "font_size" => "size7",
            "value" => $controller->getOwnerAddress2(),
            "height" => "10", "width" => "120", "required" => "false"
        ]);
        
        $sba_franchise_identifier_code = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "440", "y_position" => "711",
            "font" => "Arial", "font_size" => "size8",
            "value" => $this->elements["sba_franchise_identifier_code"]["#default_value"],
            "height" => "10", "width" => "80", "required" => "false"
        ]);
 
        $today = new Text([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "380", "y_position" => "341",
            "font" => "Arial", "font_size" => "size11",
            "value" => date("m-j-Y"),
            "height" => "20", "width" => "100", "required" => "false"
        ]);
         
        $print_name = new Text([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "40", "y_position" => "381",
            "font" => "Arial", "font_size" => "size11",
            "value" => $controller->getPrintName(),
            "height" => "20", "width" => "200", "required" => "false"
        ]);
 
        $job_title = new Text([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "380", "y_position" => "381",
            "font" => "Arial", "font_size" => "size11",
            "value" => $controller->getJobTitle(),
            "height" => "20", "width" => "200", "required" => "false"
        ]);
         
         
        $sign_here = new SignHere([
            "document_id" => "1", "page_number" => "3",
            "x_position" => "40", "y_position" => "320"]);
 
        # Create the signer recipient model
        $signer = new Signer([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'role_name' => 'signer', 'recipient_id' => "1", 'routing_order' => "1"]);
        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.
         
        $radio_groups = $this->getRadioGroup();
 
        $checkbox_list = $this->getCheckboxList();
 
        $initial_here_list = $this->getInitialList();
         
        $signer->setTabs(new Tabs([
            'sign_here_tabs' => [$sign_here],
            'initial_here_tabs' => $initial_here_list,
            'radio_group_tabs' => $radio_groups,
            'checkbox_tabs' => $checkbox_list,
            'text_tabs' => [
                $another_business_name,
                $date_established,
                $business_name,
                $naics_code,
                $business_address_1,
                $business_address_2,
                $ssn,
                $business_phone,
                $primary_contact,
                $email,
                $average_monthly_payroll,
                $loan_amount,
                $num_of_employees,
                $other_purpose,
                $sba_loan_num,
                $quarter_2020,
                $quarter_2019,
                $gross_receipts_2020,
                $gross_receipts_2019,
                $owner_name_0,
                $owner_name_1,
                $owner_name_2,
                $owner_job_title_0,
                $owner_job_title_1,
                $owner_job_title_2,
                $ownership_0,
                $ownership_1,
                $ownership_2,
                $owner_tin_0,
                $owner_tin_1,
                $owner_tin_2,
                $owner_address_0,
                $owner_address_1,
                $owner_address_2,
                $sba_franchise_identifier_code,
                $today,
                $print_name,
                $job_title,
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

    private function getCompanyStructurePosition() {
        $company_structure = $this->elements["company_structure"]["#default_value"];
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

    public function getCheckboxList() {
        $cs_position = $this->getCompanyStructurePosition();
        $company_structure = new Checkbox([
            'document_id' => "1", 'page_number' => "1",
            "x_position" => $cs_position["x"], "y_position" => $cs_position["y"],
            "selected" => "true"]);
        
        $payroll_costs = $this->elements["payroll_costs"]["#default_value"];
        $less_mortgage_interest = $this->elements["less_mortgage_interest"]["#default_value"];
        $utilities = $this->elements["utilities"]["#default_value"];
        $covered_operations_expenditures = $this->elements["covered_operations_expenditures"]["#default_value"];
        $covered_property_damage = $this->elements["covered_property_damage"]["#default_value"];
        $covered_supplier_costs = $this->elements["covered_supplier_costs"]["#default_value"];
        $covered_worker_protection_expenditures = $this->elements["covered_worker_protection_expenditures"]["#default_value"];
        $other_cost = $this->elements["other_cost"]["#default_value"];
        
        $payroll_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "141", 'y_position' => "250",
            'selected' => $payroll_costs ? "true" : "false",
        ]);
        
        $less_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "235", 'y_position' => "250",
            'selected' => $less_mortgage_interest ? "true" : "false",
        ]);
        
        $utilities_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "361", 'y_position' => "250",
            'selected' => $utilities ? "true" : "false",
        ]);

        $cb_covered_1 = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "470", 'y_position' => "245",
            'selected' => $covered_operations_expenditures ? "true" : "false",
        ]);

        $cb_covered_2 = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "141", 'y_position' => "276",
            'selected' => $covered_property_damage ? "true" : "false",
        ]);

        $cb_covered_3 = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "235", 'y_position' => "281",
            'selected' => $covered_supplier_costs ? "true" : "false",
        ]);

        $cb_covered_4 = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "361", 'y_position' => "276",
            'selected' => $covered_worker_protection_expenditures ? "true" : "false",
        ]);

        $other_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "470", 'y_position' => "276",
            'selected' => $other_cost ? "true" : "false",
        ]);
        
        $checkbox_list = [
            $company_structure,
            $payroll_checkbox,
            $less_checkbox,
            $utilities_checkbox,
            $cb_covered_1,
            $cb_covered_2,
            $cb_covered_3,
            $cb_covered_4,
            $other_checkbox,
        ];

        return $checkbox_list;
    }

    private function getRadioGroup() {
        $selected = false;
        
        if ($this->elements["question_step_18_1"]["#default_value"] == "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q1_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q1_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "500",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false",
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "565", 'y_position' => "500",
                'value' => "No", 
                'selected' => $selected ? "false" : "true",
                'required' => "false"])
        ]]);


        if ($this->elements["question_18_2"]["#default_value"] == "Yes") {
            $selected = true;
        }
        else {
            $selected = false;
        }
        $q2_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q2_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "540",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false",  
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "565", 'y_position' => "540",
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
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "575",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "565", 'y_position' => "575",
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
        $q4_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q5_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "620",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "565", 'y_position' => "620",
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
        $q5_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q6_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "665",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "565", 'y_position' => "665",
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
        $q6_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q7_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "690",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "565", 'y_position' => "690",
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
        $q7_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q8_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "702",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "565", 'y_position' => "702",
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
        $q8_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q9_radio",
        'radios' => [
            new Radio(['page_number' => "1", 'x_position' => "540", 'y_position' => "715",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "1", 'x_position' => "565", 'y_position' => "715",
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
            "x_position" => "310", "y_position" => "598",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_2 = new InitialHere([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "310", "y_position" => "650",
            "height" => "10", "width" => "40", "required" => "false"
        ]);
        
        $Initial_3 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "30", "y_position" => "365",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_4 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "30", "y_position" => "408",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_5 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "30", "y_position" => "428",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_6 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "30", "y_position" => "486",
            "font" => "Arial", "font_size" => "size10",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_7 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "30", "y_position" => "526",
            "font" => "Arial", "font_size" => "size10",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_8 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "30", "y_position" => "580",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_9 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "30", "y_position" => "643",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_10 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "30", "y_position" => "680",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_11 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "30", "y_position" => "700",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_12 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "30", "y_position" => "24",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_13 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "30", "y_position" => "56",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_14 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "30", "y_position" => "120",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_15 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "30", "y_position" => "150",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_16 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "30", "y_position" => "190",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $Initial_17 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "30", "y_position" => "250",
            "height" => "10", "width" => "40", "required" => "false"
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
            $Initial_11,
            $Initial_12,
            $Initial_13,
            $Initial_14,
            $Initial_15,
            $Initial_16,
            $Initial_17,
        ];
    }

}