<?php

namespace Drupal\application\Controller;

use GuzzleHttp\Exception\ClientException;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformFormHelper;

use stdClass;

class SBALoanController {
    const SBA_SANDBOX_HEADERS = [
        'Authorization' => 'Token 7f4d183d617b693c3ad355006c2d7381745e49c6',
        'Vendor-Key' => 'de512795-a13f-4812-8d47-ed41adaa6d32'
    ];

    const SBA_PRODUCTION_HEADERS = [
        'Authorization' => 'Token 7152fb356b47624e89ff81dd06afe44b4e345999',
        'Vendor-Key' => '360be2e5-cc2c-4a90-837c-32394087efb3'
    ];

    const SBA_HEADERS = self::SBA_SANDBOX_HEADERS;
    #const SBA_HEADERS = self::SBA_PRODUCTION_HEADERS;
    const SBA_SANDBOX_HOST = "https://sandbox.forgiveness.sba.gov/";
    const SBA_PRODUCTION_HOST = "https://forgiveness.sba.gov/";

    const SBA_HOST = self::SBA_SANDBOX_HOST;
    #const SBA_HOST = self::SBA_PRODUCTION_HOST;
    
    private $elements;

    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct() {
    }

    public function sendLoanRequest(array &$form, FormStateInterface $form_state) {
        $this->elements = WebformFormHelper::flattenElements($form);
        try {
            $client = \Drupal::httpClient();

            $headers = self::SBA_HEADERS;
            $headers['Content-Type'] = "application/json";
            $request_data = $this->createRequestData();
            
            $url = self::SBA_HOST . "api/origination/";
    
            $response = $client->request('POST', $url, [
                'headers' => $headers,
                'body' => $request_data,
            ]);
            $body = json_decode($response->getBody());
            
            if (!empty($body->{"slug"})) {
                $slug = $body->{"slug"};
                $confirmation_id = $body->{"submission_confirmation_id"};
                $status = $body->{"status"};

                $entity = $form_state->getFormObject()->getEntity();
                $data = $entity->getData();
                $data["sba_slug"] = $slug;
                $data["sba_request_status"] = $status;
                $data["sba_confirmation_id"] = $confirmation_id;
                $entity->setData($data);
                $entity->save();
                $form["elements"]["loan_officer_page"]["sba_slug"]["#value"] = $slug;
                $form["elements"]["loan_officer_page"]["sba_slug"]["#default_value"] = $slug;
                $form["elements"]["loan_officer_page"]["sba_confirmation_id"]["#value"] = $confirmation_id;
                $form["elements"]["loan_officer_page"]["sba_confirmation_id"]["#default_value"] = $confirmation_id;
                $form["elements"]["loan_officer_page"]["sba_request_status"]["#value"] = $status;
                $form["elements"]["loan_officer_page"]["sba_request_status"]["#default_value"] = $status;

                $form["elements"]["loan_officer_page"]["sba_response"]["#value"] = "SBA Loan Application successfully submitted. \nConfirmation ID: " . $confirmation_id;
                $form["elements"]["loan_officer_page"]["sba_response"]["#default_value"] = "SBA Loan Application successfully submitted. \nConfirmation ID: " . $confirmation_id;

                
            }
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["loan_officer_page"]["sba_response"]["#value"] = $response;
                $form["elements"]["loan_officer_page"]["sba_response"]["#default_value"] = $response;
            }
        }
    }

    private function createRequestData() {
        $request = new stdClass();
        $request->business = $this->createBusinessData();
        $request->average_monthly_payroll = $this->getAverageMonthlyPayroll();
        $request->loan_amount = $this->getLoanAmount();
        $request->received_eidl = $this->receivedEIDL();
        //if ($request->received_eidl) {
            $request->refinance_of_eidl_amount = 0;
            $request->refinance_of_eidl_loan_number = "";
        //}
        $request->number_of_employees = $this->getNumOfEmployees();
        
        $request->period_1_quarter = $this->getP1Quarter();
        $request->period_1_revenue = $this->getP1Revenue();
        $request->period_2_quarter = $this->getP2Quarter();
        $request->period_2_revenue = $this->getP2Revenue();
        
        $request->purpose_of_loan_payroll = $this->getPurpose(1);
        $request->purpose_of_loan_mortgage = $this->getPurpose(2);
        $request->purpose_of_loan_utilities = $this->getPurpose(3);
        $request->purpose_of_loan_covered_operations_expenditures = $this->getPurpose(4);
        $request->purpose_of_loan_covered_property_damage = $this->getPurpose(5);
        $request->purpose_of_loan_covered_supplier_costs = $this->getPurpose(6);
        $request->purpose_of_loan_covered_worker_protection_expenditure = $this->getPurpose(7);
        $request->purpose_of_loan_other = $this->getPurpose(8);
        $request->purpose_of_loan_other_info = $this->getOtherPurposeReason();
        $request->ineligible_general = $this->getRadios(1);
        $request->ineligible_bad_loan = $this->getRadios(2);
        $request->has_other_businesses = $this->getRadios(3);
        $request->received_eidl = $this->getRadios(4);
        $request->ineligible_criminal_charges = $this->getRadios(5);
        $request->ineligible_felony = $this->getRadios(6);
        $request->all_employees_residency = $this->getRadios(7);
        
        $request->applicant_is_eligible = true;
        $request->applicant_meets_revenue_test_and_size_standard = true;
        $request->applicant_no_shuttered_venue_grant = true;
        $request->applicant_meets_size_standard = 1;
        $request->second_draw_ppp_loan = $this->isSecondDraw();
        if ($request->second_draw_ppp_loan) {
            $request->ppp_first_draw_sba_loan_number = $this->getFirstDrawLoanNumber();
            $request->ppp_first_draw_loan_amount = $this->getFirstDrawLoanAmount();
            $request->applicant_has_reduction_in_gross_receipts = true;
            $request->applicant_wont_receive_another_second_draw = true;
        }
        $request->lender_contracted_third_party = $this->contactedThirdParty();
        $request->loan_request_is_necessary = true;
        $request->lender_application_number = $this->getLenderApplicationNumber();
        
        return json_encode($request);
    }

    private function createBusinessData() {
        $business = new stdClass();
        $owners = [];
        $owners[] = $this->createPrimayOwnerData();
        $other_owner_count = $this->getOwnerCount();
        $business->business_type = $this->getBusinessType();
        if ($business->business_type != 1 && 
            $business->business_type != 16 && 
            $business->business_type != 17) {
            for ($i = 1; $i < $other_owner_count; $i++) {
                $other_owner = $this->createOwnerData($i);
                if ($other_owner->first_name != "") {
                    $owners[] = $other_owner;
                }
            }
        }
        
        $business->owners = $owners;
        $business->naics_code = $this->getNAICSCode();
        
        $business->dba_tradename = $this->getDBATradeName();
        if ($business->business_type == 1 || 
            $business->business_type == 16 || 
            $business->business_type == 17) {
            $business->first_name = $this->getFirstName(0);
            $business->last_name = $this->getLastName(0);
            
        }
        else {
            $business->legal_name = $this->getBusinessName();
            
        }
        $business->tin_type = 0;
        $business->tin = $this->getTIN();
        $business->address_line_1 = $this->getAddressLine1();
        $business->address_line_2 = $this->getAddressLine2();
        $business->city = $this->getCity();
        $business->state = $this->getState();
        $business->zip_code = $this->getZipCode();
        $business->zip_code_plus4 = "";
        
        $business->phone_number = $this->getPhoneNumber();
        $business->primary_contact = $this->getFirstName(0) . " " . $this->getLastName(0);
        $business->primary_contact_email = $this->getPrimaryContactEmail();
        $business->is_franchise = $this->isFranchise();
        $business->is_sba_listed_franchise = $this->isFranchise();
        $business->franchise_code = $this->getFranchiseCode();
        $business->date_of_establishment = $this->getDateEstablishment();
        return $business;
    }

    private function createPrimayOwnerData() {
        $owner = new stdClass();
        $owner->owner_type = $this->getPrimaryOwnerType(); // 1 = PERSON 2 = BUSINESS
        if ($owner->owner_type == 1) {
            $owner->first_name = $this->getFirstName(0);
            $owner->last_name = $this->getLastName(0);
            $owner->tin_type = 1;
            $owner->tin = $this->getSSN();
        }
        else {
            $owner->business_name = $this->getBusinessName();
            $owner->business_type = $this->getBusinessType();
            $owner->tin_type = $this->getTINType();
            if ($owner->tin_type == 1) {
                $owner->tin = $this->getSSN();
            }
            else {
                $owner->tin = $this->getTIN();
            }
        }
        
        $owner->title = $this->getJobTitle();
        
        $owner->ownership_percentage = $this->getOwnerShipPercentage();
        $owner->address_line_1 = $this->getAddressLine1();
        $owner->address_line_2 = $this->getAddressLine2();
        $owner->city = $this->getCity();
        $owner->state = $this->getState();
        $owner->zip_code = $this->getZipCode();
        return $owner;
    }

    private function createOwnerData($num) {
        $owner = new stdClass();
        $owner->owner_type = 1;
        $owner->first_name = $this->getFirstName($num);
        $owner->last_name = $this->getLastName($num);
        $owner->tin_type = 1;
        $owner->tin = $this->getOwnerSSN($num);
        $owner->ownership_percentage = $this->getProperty("ownership_perc", $num - 1);
        $owner->address_line_1 = $this->getOwnerAddress1($num);
        $owner->address_line_2 = "";
        $owner->city = $this->getOwnerCity($num);
        $owner->state = $this->getOwnerState($num);
        $owner->zip_code = $this->getOwnerZipCode($num);
        return $owner;
    }

    private function getBusinessName() {
        return $this->elements["business_name"]["#default_value"];
    }

    private function getPrimaryOwnerType() {
        return $this->elements["primary_owner_type"]["#default_value"];
    }

    private function getDBATradeName() {
        return $this->elements["another_business_name"]["#default_value"];
    }

    private function getPhoneNumber() {
        $phone_num = $this->elements["business_phone_number"]["#default_value"];
        return str_replace(["(", ")", "-", " "], "", $phone_num);
    }

    private function getJobTitle() {
        return $this->elements["job_title"]["#default_value"];
    }

    private function getFirstName($num) {
        return $this->getProperty("first_name", $num);
    }

    private function getLastName($num) {
        return $this->getProperty("last_name", $num);
    }

    private function getProperty($field, $num) {
        if ($num < 0 || empty($field)) {
            return "";
        }
        if (empty($this->elements[$field])) {
            return "";
        }
        if (empty($this->elements[$field][$num])) {
            return "";
        }
        if (count($this->elements[$field]) <= $num) {
            return "";
        }
        
        return $this->elements[$field][$num]["#value"];
    }

    private function getOwnerShipPercentage() {
        return $this->elements["percentage_of_business"]["#value"];
    }

    private function getTIN() {
        $tin = $this->elements["tax_id_number"]["#default_value"];
        return str_replace(["(", ")", "-", " "], "", $tin);
    }

    private function getTINType() {
        $business_type = $this->getBusinessType();
        if ($business_type == 1 || $business_type == 16 || $business_type == 17) {
            return 1;
        }
        return 0;
    }

    private function getSSN() {
        $ssn = $this->elements["social_security_number"]["#default_value"];
        $ssn = str_replace(["(", ")", "-", " "], "", $ssn);
        return $ssn;
    }

    private function getAddressLine1() {
        $is_us_address = $this->elements["is_us_address"]["#default_value"];
        if ($is_us_address == 1) {
            return $this->elements["business_address"]["#default_value"]["address"];
        }
        return $this->elements["global_business_address"]["#default_value"]["address"];
    }

    private function getAddressLine2() {
        $is_us_address = $this->elements["is_us_address"]["#default_value"];
        if ($is_us_address == 1) {
            return $this->elements["business_address"]["#default_value"]["address_2"];
        }
        return $this->elements["global_business_address"]["#default_value"]["address_2"];
    }

    private function getCity() {
        $is_us_address = $this->elements["is_us_address"]["#default_value"];
        if ($is_us_address == 1) {
            return $this->elements["business_address"]["#default_value"]["city"];
        }
        return $this->elements["global_business_address"]["#default_value"]["city"];
    }

    private function getState() {
        $is_us_address = $this->elements["is_us_address"]["#default_value"];
        if ($is_us_address == 1) {
            $state_name = $this->elements["business_address"]["#default_value"]["state_province"];
            return $this->convertState($state_name);
        }
        return $this->elements["global_business_address"]["#default_value"]["state_province"];
    }

    private function getZipCode() {
        $is_us_address = $this->elements["is_us_address"]["#default_value"];
        if ($is_us_address == 1) {
            return $this->elements["business_address"]["#default_value"]["postal_code"];
        }
        return $this->elements["global_business_address"]["#default_value"]["postal_code"];
    }

    private function getOwnerCount() {
        if (empty($this->elements["first_name"])) {
            return 0;
        }
        return count($this->elements["first_name"]);
    }

    private function getOwnerSSN($num) {
        if ($num <= 0) {
            return "";
        }
        return $this->getProperty("ssn", $num - 1);
    }

    private function getOwnerAddress1($num) {
        if ($num <= 0) {
            return "";
        }
        return $this->getProperty("address", $num + 2);
    }

    private function getOwnerCity($num) {
        if ($num <= 0) {
            return "";
        }
        return $this->getProperty("city", $num + 2);
    }

    private function getOwnerState($num) {
        if ($num <= 0) {
            return "";
        }
        $state = $this->getProperty("state", $num - 1);
        return $this->convertState($state);
    }

    private function getOwnerZipCode($num) {
        if ($num <= 0) {
            return "";
        }
        return $this->getProperty("zip_code", $num - 1);
    }

    private function getNAICSCode() {
        return $this->elements["naics_code"]["#default_value"];
    }

    private function getBusinessType() {
        $company_structure = $this->elements["company_structure"]["#default_value"];
        $business_type = 1;
        if ($company_structure == "General Partnership") {
            return 2;
        }
        else if ($company_structure == "C Corporation") {
            return 3;
        }
        else if ($company_structure == "S Corporation") {
            return 10;
        }
        else if ($company_structure == "Limited Liability Company") {
            return 4;
        }
        else if ($company_structure == "Independent Contractor") {
            return 17;
        }
        else if ($company_structure == "Eligible Self-employed Individual") {
            return 16;
        }
        else if ($company_structure == "501 (c)(3) nonprofit") {
            return 21;
        }
        else if ($company_structure == "501 (c)(6) organization") {
            return 22;
        }
        else if ($company_structure == "501 (c)(19) veterans organization") {
            return 23;
        }
        else if ($company_structure == "Housing cooperative") {
            return 25;
        }
        else if ($company_structure == "Tribal Business") {
            return 24;
        }
        
        return $business_type;
    }

    private function isFranchise() {
        if ($this->elements["is_franchise_listed_in"]["#default_value"] === "Yes") {
            return true;
        }
        return false;
    }

    private function getFranchiseCode() {
        if (empty($this->elements["sba_franchise_identifier_code"]["#default_value"])) {
            return "";
        }
        return $this->elements["sba_franchise_identifier_code"]["#default_value"];
    }

    private function getDateEstablishment() {
        $month = $this->elements["date_established"]["#value"]["month"];
        $day = $this->elements["date_established"]["#value"]["day"];
        $year = $this->elements["date_established"]["#value"]["year"];
        return $year . "-" . $month . "-" . $day;
    }

    public function getPrimaryContactEmail() {
        return $this->elements["borrower_email"]["#default_value"];
    }

    public function getAverageMonthlyPayroll() {
        $amount = $this->elements["adjusted_average_payroll"]["#default_value"];
        $amount = str_replace(["$", ",", " "], "", $amount);
        return (float)$amount;
    }

    public function getLoanAmount() {
        $amount = $this->elements["adjusted_loan_amount"]["#default_value"];
        $amount = str_replace(["$", ",", " "], "", $amount);
        return (float)$amount;
    }

    public function getNumOfEmployees() {
        return $this->elements["number_of_employees"]["#default_value"];
    }

    public function receivedEIDL() {
        if ($this->elements["has_received_loan"]["#default_value"] == "Yes") {
            return true;
        }
        return false;
    }
    
    public function getEIDLAmount() {
        $amount = $this->elements["received_loan_amount"]["#default_value"];
        return str_replace(["$", ",", " "], "", $amount);
    }

    public function getP1Quarter() {
        $value = $this->elements["2020_quarter"]["#value"];
        return $this->getMappedValue($value);
    }

    public function getP2Quarter() {
        $value = $this->elements["reference_2019_quarter"]["#value"];
        return $this->getMappedValue($value);
    }

    private function getMappedValue($value) {
        if (empty($value)) {
            return 1;
        }
        if ($value == "Q1") {
            return 1;
        }
        if ($value == "Q2") {
            return 2;
        }
        if ($value == "Q3") {
            return 3;
        }
        if ($value == "Q4") {
            return 4;
        }
        return 1;
    }

    public function getP1Revenue() {
        if (empty($this->elements["2020_gross_receipts"]["#value"])) {
            return 0;
        }
        else {
            return (float)$this->elements["2020_gross_receipts"]["#value"];
        }
    }

    public function getP2Revenue() {
        if (empty($this->elements["2019_gross_receipts"]["#value"])) {
            return 0;
        }
        else {
            return (float)$this->elements["2019_gross_receipts"]["#value"];
        }
    }

    public function getPurpose($field) {
        if (empty($field) || $field <= 0) {
            return false;
        }
        if ($field == 1 && $this->elements["payroll_costs"]["#default_value"]) {
            return true;
        }
        if ($field == 2 && $this->elements["less_mortgage_interest"]["#default_value"]) {
            return true;
        }
        if ($field == 3 && $this->elements["utilities"]["#default_value"]) {
            return true;
        }
        if ($field == 4 && $this->elements["covered_operations_expenditures"]["#default_value"]) {
            return true;
        }
        if ($field == 5 && $this->elements["covered_property_damage"]["#default_value"]) {
            return true;
        }
        if ($field == 6 && $this->elements["covered_supplier_costs"]["#default_value"]) {
            return true;
        }
        if ($field == 7 && $this->elements["covered_worker_protection_expenditures"]["#default_value"]) {
            return true;
        }
        if ($field == 8 && $this->elements["other_cost"]["#default_value"]) {
            return true;
        }
        return false;
    }

    public function getOtherPurposeReason() {
        return $this->elements["purpose_of_loan_other"]["#default_value"];
    }

    public function isSecondDraw() {
        if ($this->elements["round"]["#default_value"] == "Yes") {
            return true;
        }
        return false;
    }

    public function getLenderApplicationNumber() {
        return $this->elements["alc_loan_serial"]["#value"];
    }

    public function getRadios($num) {
        if (empty($num) || $num <= 0) {
            return false;
        }
        
        if ($num == 1) {
            if ($this->elements["question_step_18_1"]["#default_value"] == "Yes") {
                return false;
            }
            else {
                return true;
            }
        }
        else if ($num == 2) {
            if ($this->elements["question_18_2"]["#default_value"] == "Yes") {
                return false;
            }
            else {
                return true;
            }
        }
        else if ($num == 3) {
            if ($this->elements["question_18_3"]["#default_value"] === "Yes") {
                return true;
            }
            else {
                return false;
            }
        }
        else if ($num == 4) {
            if ($this->elements["has_received_loan"]["#default_value"] === "Yes") {
                return true;
            }
            else {
                return false;
            }
        }
        else if ($num == 5) {
            // ineligible_criminal_charges
            if ($this->elements["is_question_23_1"]["#default_value"] === "Yes") {
                return false;
            }
            else {
                return true;
            }
        }
        else if ($num == 6) {
            // ineligible_felony
            if ($this->elements["is_convicted"]["#default_value"] === "Yes") {
                return false;
            }
            else {
                return true;
            }
        }
        else if ($num == 7) {
            // all_employees_residency
            if ($this->elements["is_residence_"]["#default_value"] === "Yes") {
                return true;
            }
            else {
                return false;
            }
        }
        else if ($num == 8) {
            // is_franchise
            if ($this->elements["is_franchise_listed_in"]["#default_value"] === "Yes") {
                return true;
            }
            else {
                return false;
            }
        }

        return false;
    }

    public function hasReductionGrossReceipts() {
        if ($this->elements["have_you_experienced_a_revenue_reduction_25perc"]["#default_value"] === "Yes") {
            return true;
        }
        return false;
    }

    public function getFirstDrawLoanNumber() {
        return (int)$this->elements["first_draw_sba_loan_number"]["#default_value"];
    }

    public function getFirstDrawLoanAmount() {
        $amount = $this->elements["first_draw_sba_loan_amount"]["#default_value"];
        return str_replace(["$", ",", " "], "", $amount);
    }

    private function contactedThirdParty() {
        if ($this->elements["is_someone_help"]["#default_value"] === "Yes") {
            return true;
        }
        return false;
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