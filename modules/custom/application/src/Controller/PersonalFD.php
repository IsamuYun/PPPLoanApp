<?php

namespace Drupal\application\Controller;

use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\DateSigned;
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

class PersonalFD {
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
        $content_bytes = file_get_contents(self::DOCS_PATH . "PPP First Draw Borrower Application Form 2483 C.pdf");
        $borrower_form_b64 = base64_encode($content_bytes);
         
        # Create the document models
        $document = new Document([  # create the DocuSign document object
            'document_base64' => $borrower_form_b64,
            'name' => 'PPP First Draw Borrower Application Form 2483 C',  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
        ]);

        $another_business_name = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "300", "y_position" => "120",
            "font" => "Arial", "font_size" => "size8",
            "value" => $this->elements["another_business_name"]["#default_value"],
            "height" => "16", "width" => "160", "required" => "false"
        ]);

        $date_established = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "440", "y_position" => "120",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getDateEstablished(),
            "height" => "16", "width" => "160", "required" => "false"
        ]);
         
        $business_name_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "50", "y_position" => "180",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["business_name"]["#default_value"],
            "height" => "20", "width" => "240", "required" => "false"
        ]);
         
        $business_address_1_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "50", "y_position" => "248",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getBusinessAddress(),
            "height" => "20", "width" => "200", "required" => "false"
        ]);
         
        $business_address_2_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "50", "y_position" => "260",
            "font" => "Arial", "font_size" => "size10",
            "value" => $controller->getBusinessAddress2(),
            "height" => "20", "width" => "200", "required" => "false"
        ]);
        
        $naics_code = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "300", "y_position" => "180",
            "font" => "Arial", "font_size" => "size10",
            "value" => $this->elements["naics_code"]["#default_value"],
            "height" => "20", "width" => "200", "required" => "false"
        ]);

        $ssn_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "300", "y_position" => "240",
            "font" => "Arial", "font_size" => "size9",
            "value" => $controller->getTIN(),
            "height" => "16", "width" => "140", "required" => "false"
        ]);
 
        $business_phone_text = new Text(['document_id' => "1", "page_number" => "1",
            "x_position" => "430", "y_position" => "240",
            "font" => "Arial", "font_size" => "size9",
            "value" => $this->elements["business_phone_number"]["#default_value"],
            "height" => "16", "width" => "140", "required" => "false"
        ]);

        // Primary Contact
        $primary_contact = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "300", "y_position" => "268",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getPrintName(),
            "height" => "16", "width" => "140", "required" => "false"
        ]);
         
        $email_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "430", "y_position" => "268",
            "font" => "Arial", "font_size" => "size7",
            "value" => $controller->getBorrowerEmail(),
            "height" => "16", "width" => "200", "required" => "false"
        ]);
        
        $gross_income_1 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "50", "y_position" => "306",
            "font" => "Arial", "font_size" => "size9",
            "value" => $controller->getAmount($this->elements["net_earnings"]["#default_value"]),
            "height" => "18", "width" => "100", "required" => "false"
        ]);
        
        $num_of_employees_text = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "480", "y_position" => "306",
            "font" => "Arial", "font_size" => "size9",
            "value" => $this->elements["number_of_employees"]["#default_value"],
            "height" => "18", "width" => "100", "required" => "false"
        ]);
        if ($this->isSoleProprietor() && $this->hasEmployee()) {
            $box_a = new Text([
                'document_id' => "1", "page_number" => "1",
                "x_position" => "164", "y_position" => "441",
                "font" => "Arial", "font_size" => "size9",
                "value" => $controller->getAmount($this->elements["table_b_a"]["#default_value"]),
                "height" => "18", "width" => "100", "required" => "false"
            ]);

            $box_b = new Text([
                'document_id' => "1", "page_number" => "1",
                "x_position" => "344", "y_position" => "441",
                "font" => "Arial", "font_size" => "size9",
                "value" => $controller->getAmount($this->elements["table_b_b"]["#default_value"]),
                "height" => "18", "width" => "100", "required" => "false"
            ]);

            $box_c = new Text([
                'document_id' => "1", "page_number" => "1",
                "x_position" => "516", "y_position" => "445",
                "font" => "Arial", "font_size" => "size9",
                "value" => $controller->getAmount($this->elements["table_b_c"]["#default_value"]),
                "height" => "18", "width" => "100", "required" => "false"
            ]);
            
            $average_payroll = new Text([
                'document_id' => "1", "page_number" => "1",
                "x_position" => "164", "y_position" => "485",
                "font" => "Arial", "font_size" => "size9",
                "value" => $controller->getAmount($controller->getAdjustedAveragePayrollAmount()),
                "height" => "18", "width" => "100", "required" => "false"
            ]);

            $loan_amount = new Text([
                'document_id' => "1", "page_number" => "1",
                "x_position" => "470", "y_position" => "485",
                "font" => "Arial", "font_size" => "size9",
                "value" => $controller->getAmount($controller->getAdjustedLoanAmount()),
                "height" => "18", "width" => "100", "required" => "false"
            ]);

        }
        else {
            $gross_income_2 = new Text([
                'document_id' => "1", "page_number" => "1",
                "x_position" => "164", "y_position" => "366",
                "font" => "Arial", "font_size" => "size9",
                "value" => $controller->getAmount($this->elements["net_earnings"]["#default_value"]),
                "height" => "18", "width" => "100", "required" => "false"
            ]);
            
            $monthly_gross = new Text([
                'document_id' => "1", "page_number" => "1",
                "x_position" => "344", "y_position" => "366",
                "font" => "Arial", "font_size" => "size9",
                "value" => $controller->getMonthlyGross(),
                "height" => "18", "width" => "100", "required" => "false"
            ]);
    
            $loan_amount = new Text([
                'document_id' => "1", "page_number" => "1",
                "x_position" => "514", "y_position" => "369",
                "font" => "Arial", "font_size" => "size9",
                "value" => $controller->getAmount($controller->getAdjustedLoanAmount()),
                "height" => "18", "width" => "100", "required" => "false"
            ]);
        }

        $other_purpose = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "472", "y_position" => "576",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOtherPurpose(),
            "height" => "12", "width" => "100", "required" => "false"
        ]);

        $owner_name_0 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "650",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getPrintName(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);
        
        $owner_name_1 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "660",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerName1(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);

        $owner_name_2 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "670",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerName2(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);

        $owner_job_title_0 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "235", "y_position" => "650",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getJobTitle(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);
 
        $owner_job_title_1 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "235", "y_position" => "660",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerJobTitle1(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);

        $owner_job_title_2 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "235", "y_position" => "670",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerJobTitle2(),
            "height" => "10", "width" => "100", "required" => "false"
        ]);

        $ownership_0 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "325", "y_position" => "650",
            "font" => "Arial", "font_size" => "size8",
            "value" => $this->elements["percentage_of_business"]["#value"],
            "height" => "10", "width" => "60", "required" => "false"
        ]);
 
        $ownership_1 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "325", "y_position" => "660",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnership1(),
            "height" => "10", "width" => "60", "required" => "false"
        ]);

        $ownership_2 = new Text([
            "document_id" => "1", "page_number" => "1",
            "x_position" => "325", "y_position" => "670",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnership2(),
            "height" => "10", "width" => "60", "required" => "false"
        ]);

        $owner_tin_0 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "363", "y_position" => "650",
            "font" => "Arial", "font_size" => "size8",
            "value" => $this->elements["social_security_number"]["#default_value"],
            "height" => "10", "width" => "60", "required" => "false"
        ]);
 
        $owner_tin_1 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "363", "y_position" => "660",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerTIN1(),
            "height" => "10", "width" => "60", "required" => "false"
        ]);
        
        $owner_tin_2 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "363", "y_position" => "670",
            "font" => "Arial", "font_size" => "size8",
            "value" => $controller->getOwnerTIN2(),
            "height" => "10", "width" => "60", "required" => "false"
        ]);
        
        $owner_address_0 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "425", "y_position" => "650",
            "font" => "Arial", "font_size" => "size7",
            "value" => $controller->getFullBusinessAddress(),
            "height" => "10", "width" => "120", "required" => "false"
        ]);
 
        $owner_address_1 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "425", "y_position" => "660",
            "font" => "Arial", "font_size" => "size7",
            "value" => $controller->getOwnerAddress1(),
            "height" => "10", "width" => "120", "required" => "false"
        ]);

        $owner_address_2 = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "425", "y_position" => "670",
            "font" => "Arial", "font_size" => "size7",
            "value" => $controller->getOwnerAddress2(),
            "height" => "10", "width" => "120", "required" => "false"
        ]);
        
        $sba_franchise_identifier_code = new Text([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "440", "y_position" => "574",
            "font" => "Arial", "font_size" => "size8",
            "value" => $this->elements["sba_franchise_identifier_code"]["#default_value"],
            "height" => "10", "width" => "80", "required" => "false"
        ]);
 
        $today = new Text([
             'document_id' => "1", "page_number" => "3",
             "x_position" => "380", "y_position" => "683",
             "font" => "Arial", "font_size" => "size10",
             "value" => date("m-j-Y"),
             "height" => "20", "width" => "100", "required" => "false"
        ]);
         
        $print_name = new Text([
             'document_id' => "1", "page_number" => "3",
             "x_position" => "40", "y_position" => "706",
             "font" => "Arial", "font_size" => "size10",
             "value" => $controller->getPrintName(),
             "height" => "20", "width" => "200", "required" => "false"
        ]);
 
        $job_title = new Text([
             'document_id' => "1", "page_number" => "3",
             "x_position" => "380", "y_position" => "706",
             "font" => "Arial", "font_size" => "size10",
             "value" => $controller->getJobTitle(),
             "height" => "20", "width" => "200", "required" => "false"
        ]);
         
        $sign_here = new SignHere([
            'document_id' => "1", 'page_number' => "3",
            'x_position' => '40', 'y_position' => '664']);
 
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
        
        $text_list = [
            $another_business_name,
            $date_established,
            $business_name_text,
            $business_address_1_text,
            $business_address_2_text,
            $naics_code,
            $ssn_text,
            $business_phone_text,
            $primary_contact,
            $email_text,
            $gross_income_1,
            $num_of_employees_text,
            $gross_income_2,
            $monthly_gross,
            $loan_amount,
            $other_purpose,
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
        ];

        if ($this->isSoleProprietor() && $this->hasEmployee()) {
            $text_list = [
                $another_business_name,
                $date_established,
                $business_name_text,
                $business_address_1_text,
                $business_address_2_text,
                $naics_code,
                $ssn_text,
                $business_phone_text,
                $primary_contact,
                $email_text,
                $gross_income_1,
                $num_of_employees_text,
                $box_a,
                $box_b,
                $box_c,
                $average_payroll,
                $loan_amount,
                $other_purpose,
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
            ];
        }
        
         
        $signer->setTabs(new Tabs(['sign_here_tabs' => [$sign_here],
            'initial_here_tabs' => $initial_here_list,
            'radio_group_tabs' => $radio_groups,
            'checkbox_tabs' => $checkbox_list,
            'text_tabs' => $text_list,
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

    private function isSoleProprietor() {
        if ($this->elements["company_structure"]["#default_value"] == "Sole Proprietorship") {
            return true;
        }
        return false;
    }

    private function hasEmployee() {
        if ($this->elements["number_of_employees"]["#default_value"] > 1) {
            return true;
        }
        return false;
    }

    private function getCompanyStructurePosition() {
        $company_structure = $this->elements["company_structure"]["#default_value"];
        $position = [];
        
        if ($company_structure == "Sole Proprietorship") {
            $position["x"] = 107;
            $position["y"] = 100;
        }
        else if ($company_structure == "Independent Contractor") {
            $position["x"] = 107;
            $position["y"] = 110;
        }
        else if ($company_structure == "Eligible Self-employed Individual") {
            $position["x"] = 107;
            $position["y"] = 120;
        }
        
        
        return $position;
    }

    public function getCheckboxList() {
        $cs_position = $this->getCompanyStructurePosition();
        $company_structure = new Checkbox([
            'document_id' => "1", 'page_number' => "1",
            "x_position" => $cs_position["x"], "y_position" => $cs_position["y"],
            "selected" => "true"]);

        // Need to append a employees number checkbox
        $cb_employees_num = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "420", 'y_position' => "165",
            'selected' => "true",
        ]);

        // Tax Year checkbox
        $tax_year = $this->elements["tax_year_used_for_gross_income"]["#default_value"];
        if ($tax_year == "2019") {
            $position_x = 294;
            $position_y = 302;
        }
        else {
            $position_x = 294;
            $position_y = 312;
        }
        $tax_year_cb = new Checkbox([
            'document_id' => "1", 'page_number' => "1",
            "x_position" => $position_x, "y_position" => $position_y,
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
            'x_position' => "143", 'y_position' => "520",
            'selected' => $payroll_costs ? "true" : "false",
        ]);
        
        $less_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "282", 'y_position' => "525",
            'selected' => $less_mortgage_interest ? "true" : "false",
        ]);
        
        $utilities_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "355", 'y_position' => "534",
            'selected' => $utilities ? "true" : "false",
        ]);

        $cb_covered_1 = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "467", 'y_position' => "530",
            'selected' => $covered_operations_expenditures ? "true" : "false",
        ]);

        $cb_covered_2 = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "143", 'y_position' => "567",
            'selected' => $covered_property_damage ? "true" : "false",
        ]);

        $cb_covered_3 = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "282", 'y_position' => "568",
            'selected' => $covered_supplier_costs ? "true" : "false",
        ]);

        $cb_covered_4 = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "355", 'y_position' => "568",
            'selected' => $covered_worker_protection_expenditures ? "true" : "false",
        ]);

        $other_checkbox = new Checkbox([
            'document_id' => '1', 'page_number' => '1',
            'x_position' => "467", 'y_position' => "567",
            'selected' => $other_cost ? "true" : "false",
        ]);

        $checkbox_list = [
            $company_structure,
            $tax_year_cb,
            $payroll_checkbox,
            $less_checkbox,
            $utilities_checkbox,
            $cb_covered_1,
            $cb_covered_2,
            $cb_covered_3,
            $cb_covered_4,
            $other_checkbox,
            $cb_employees_num,
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
            new Radio(['page_number' => "2", 'x_position' => "540", 'y_position' => "344",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false",
                'required' => "false"]),
            new Radio(['page_number' => "2", 'x_position' => "565", 'y_position' => "344",
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
            new Radio(['page_number' => "2", 'x_position' => "540", 'y_position' => "378",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false",  
                'required' => "false"]),
            new Radio(['page_number' => "2", 'x_position' => "565", 'y_position' => "378",
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
            new Radio(['page_number' => "2", 'x_position' => "540", 'y_position' => "417",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "2", 'x_position' => "565", 'y_position' => "417",
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
            new Radio(['page_number' => "2", 'x_position' => "540", 'y_position' => "445",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "2", 'x_position' => "565", 'y_position' => "445",
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
            new Radio(['page_number' => "2", 'x_position' => "540", 'y_position' => "471",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "2", 'x_position' => "565", 'y_position' => "471",
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
            new Radio(['page_number' => "2", 'x_position' => "540", 'y_position' => "514",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "2", 'x_position' => "565", 'y_position' => "514",
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
            new Radio(['page_number' => "2", 'x_position' => "540", 'y_position' => "546",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "2", 'x_position' => "565", 'y_position' => "546",
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
            new Radio(['page_number' => "2", 'x_position' => "540", 'y_position' => "563",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "2", 'x_position' => "565", 'y_position' => "563",
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
        $q9_radio_group = new RadioGroup(['document_id' => "1", 'group_name' => "q9_radio",
        'radios' => [
            new Radio(['page_number' => "2", 'x_position' => "540", 'y_position' => "575",
                'value' => "Yes",
                'selected' => $selected ? "true" : "false", 
                'required' => "false"]),
            new Radio(['page_number' => "2", 'x_position' => "565", 'y_position' => "575",
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
            $q8_radio_group,
            $q9_radio_group
        ];

        return $radio_groups;
    }
    
    private function getInitialList() {
        $initial_1 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "300", "y_position" => "464",
            "height" => "10", "width" => "40", "required" => "false"
        ]);
        
        $initial_2 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "300", "y_position" => "508",
            "height" => "10", "width" => "40", "required" => "false"
        ]);
        
        $initial_3 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "308",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_4 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "340",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_5 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "362",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_6 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "410",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_7 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "456",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_8 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "484",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_9 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "512",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_10 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "550",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_11 = new InitialHere([
            "document_id" => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "572",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_12 = new InitialHere([
            'document_id' => "1", "page_number" => "3",
            "x_position" => "25", "y_position" => "618",
            "height" => "10", "width" => "40", "required" => "false"
        ]);
        
        return [
            $initial_1, 
            $initial_2, 
            $initial_3, $initial_4,
            $initial_5, $initial_6, $initial_7, $initial_8,
            $initial_9, $initial_10, $initial_11, $initial_12,
        ];
    }
}