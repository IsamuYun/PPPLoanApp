<?php

namespace Drupal\application\Controller;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformFormHelper;

use Drupal\application\Controller\SecondLenderController;

use Drupal\file\FileInterface;

/**
 * Define Lender Form Template Builder
 */

class LenderController {
    public const DOC_PATH = __DIR__ . "/../../documents/";

    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct() {
    }

    public function createLenderFormPDF(array &$form, FormStateInterface $form_state) {
        $pdf = new Fpdi();

        $elements = WebformFormHelper::flattenElements($form);

        if ($elements["round"]["#default_value"] == "Yes") {
            $controller = new SecondLenderController();
            $controller->createLenderFormPDF($form, $form_state);
            return;
        }

        try {
            $pageCount = $pdf->setSourceFile(self::DOC_PATH . "PPP Lender Application Form-508.pdf");
            $templateId = $pdf->importPage(1);
            $pdf->AddPage();
            $pdf->useTemplate($templateId);
            
            $this->printLenderInformation($pdf);
            $this->printCompanyStructure($pdf, $elements);
            $this->printBorrowerInformation($pdf, $elements);

            $templateId = $pdf->importPage(2);
            $pdf->AddPage();
            $pdf->useTemplate($templateId);
            $this->printEligibility1($pdf, $elements);
            $this->printEligibility2($pdf, $elements);
            

            $templateId = $pdf->importPage(3);
            $pdf->AddPage();
            $pdf->useTemplate($templateId);
            $this->printLenderCertification($pdf, $elements);

            $submission_id = $form_state->getFormObject()->getEntity()->id();
            
            $submission_path = $this->getSubmissionPath($submission_id);
            
            $real_path = $this->getRealPath($submission_path);
            
            $filename = "lender_form_" . $submission_id .".pdf";
            $pdf->Output($real_path . '/' . $filename, "F");
            
            $data = file_get_contents($real_path . '/' . $filename);

            $attachment_file = file_save_data($data, 
                $submission_path . "/" . $filename,
                \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
            $attachment_id = intval($attachment_file->id());
            $form["elements"]["loan_officer_page"]["lender_form"]["#default_value"] = [$attachment_id];
            #$form["elements"]["loan_officer_page"]["lender_files"]["#value"]["fids"] = $attachment_id;
            #$file_handle = \Drupal\file\Entity\File::load($attachment_id);
            
            //$elements["lender_files"]["#files"] = $attachment_file;
            //dpm($elements["lender_files"]);
            #dpm($elements["1040_schedule_c"]);
            $entity = $form_state->getFormObject()->getEntity();
            $data = $entity->getData();
            #dpm($data["1040_schedule_c"]);

            $data["lender_form"] = $attachment_id;
            $entity->setData($data);
            $entity->save();

        }
        catch (PdfParserException $e) {
            #dpm($e);
        }
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

    private function printLenderInformation($pdf) {
        $email = \Drupal::currentUser()->getEmail();
        $officer_name = \Drupal::currentUser()->getAccountName();
        // Lender Name
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->SetXY(36, 67);
        $pdf->Write(0, 'American Lending Center');
        // Lender Location ID
        $pdf->SetXY(162, 67);
        $pdf->Write(0, "530223");
        // Lender Address
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(36, 72);
        $pdf->Write(0, "1 World Trade Center, Suite 1130");
        // City
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(98, 72);
        $pdf->Write(0, "Long Beach");
        // State
        $pdf->SetXY(140, 72);
        $pdf->Write(0, "CA");
        // Zip 
        $pdf->SetXY(170, 72);
        $pdf->Write(0, "90831");
        // Lender Contact
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(36, 78);
        $pdf->Write(0, $officer_name);
        // Phone
        $pdf->SetXY(93, 78);
        $pdf->Write(0, "562  449 0139");
        // Cell
        $pdf->SetXY(146, 78);
        $pdf->Write(0, "562  449 0139");
        // Contact Email
        $pdf->SetXY(36, 83);
        $pdf->Write(0, $email);
        // Title
        $pdf->SetXY(130, 83);
        $pdf->Write(0, "Admin");
    }

    private function printCompanyStructure($pdf, $elements) {
        $check = "3";
        $pdf->SetFont('ZapfDingbats','', 10);
        /*
        $pdf->SetXY(39, 91);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(64.5, 91);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(85, 91);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(102, 91);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(117, 91);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(128.5, 91);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(40, 94.5);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(79, 94.5);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(110, 94.5);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(40, 98);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(87.5, 98);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(120, 98);
        $pdf->Cell(10, 10, $check, 0, 0);
        $pdf->SetXY(143.5, 98);
        $pdf->Cell(10, 10, $check, 0, 0);
        */
        
        $company_structure = $elements["company_structure"]["#default_value"];
        if ($company_structure == "Sole Proprietorship") {
            $pdf->SetXY(39, 91);
        }
        else if ($company_structure == "General Partnership") {
            $pdf->SetXY(64.5, 91);
        }
        else if ($company_structure == "C Corporation") {
            $pdf->SetXY(85, 91);
        }
        else if ($company_structure == "S Corporation") {
            $pdf->SetXY(102, 91);
        }
        else if ($company_structure == "Limited Liability Company") {
            $pdf->SetXY(116, 91);
        }
        else if ($company_structure == "Independent Contractor") {
            $pdf->SetXY(128.5, 91);
        }
        else if ($company_structure == "Eligible Self-employed Individual") {
            $pdf->SetXY(40, 94.5);
        }
        else if ($company_structure == "501 (c)(3) nonprofit") {
            $pdf->SetXY(79, 94.5);
        }
        else if ($company_structure == "501 (c)(6) organization") {
            $pdf->SetXY(110, 94.5);
        }
        else if ($company_structure == "501 (c)(19) veterans organization") {
            $pdf->SetXY(40, 98);
        }
        else if ($company_structure == "Housing cooperative") {
            $pdf->SetXY(87.5, 98);
        }
        else if ($company_structure == "Tribal Business") {
            $pdf->SetXY(120, 98);
        }
        else if ($company_structure == "Other") {
            $pdf->SetXY(143.5, 98);
        }
        $pdf->Cell(10, 10, $check, 0, 0);
    }

    private function getLoanAmount($elements) {
        $amount = $elements["adjusted_loan_amount"]["#default_value"];
        $amount = str_replace(["$", ",", " "], "", $amount);
        return number_format($amount, 2);
    }

    private function getAveragePayroll($elements) {
        $amount = $elements["adjusted_average_payroll"]["#default_value"];
        $amount = str_replace(["$", ",", " "], "", $amount);
        $amount *= 2.5;
        return number_format($amount, 2);
    }

    private function getLoanTotal($elements) {
        $payroll = $elements["adjusted_average_payroll"]["#default_value"];
        $payroll = str_replace(["$", ",", " "], "", $payroll);
        $payroll *= 2.5;

        $used_EIDL_amount = $elements["used_loan_amount"]["#default_value"];
        if (empty($used_EIDL_amount)) {
            $used_EIDL_amount = 0;
        }
        $used_EIDL_amount = str_replace(["$", ",", " "], "", $used_EIDL_amount);
        $amount = 0;
        if ($payroll >= $used_EIDL_amount) {
            $amount = $payroll - $used_EIDL_amount;
        }
        return number_format($amount, 2);
    }

    private function getEIDLAmount($elements) {
        $used_EIDL_amount = $elements["used_loan_amount"]["#default_value"];
        if (empty($used_EIDL_amount)) {
            return "0.00";
        }
        $used_EIDL_amount = floatval(substr($used_EIDL_amount, 2));
        return number_format($used_EIDL_amount, 2);
    }

    private function printBorrowerInformation($pdf, $elements) {
        $applicant_legal_name = $elements["business_name"]["#default_value"];
        $dba_name = $elements["another_business_name"]["#default_value"];
        $naics_code = $elements["naics_code"]["#default_value"];
        $tax_id = $elements["tax_id_number"]["#default_value"];
        $year_of_establishment = "";
        if (!$elements["date_established"]["#value"]) {
            $year_of_establishment = $elements["date_established"]["#value"]["year"];
        }
        $number_of_employees = $elements["number_of_employees"]["#default_value"];
        $address = $this->getBusinessAddress($elements);
        $city_state_zip = $this->getCityStateZip($elements);
        $primary_contact = $this->getPrimaryName($elements);
        $phone_num = $elements["business_phone_number"]["#default_value"];
        $average_payroll = $this->getAveragePayroll($elements);
        $loan_amount = $this->getLoanAmount($elements);
        $eidl_amount = $this->getEIDLAmount($elements);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->setXY(50, 108.5);
        $pdf->Write(0, $applicant_legal_name);

        $pdf->setXY(140, 108.5);
        $pdf->Write(0, $naics_code);

        $pdf->setXY(66, 113.5);
        $pdf->Write(0, $dba_name);

        $pdf->SetXY(140, 113.5);
        $pdf->Write(0, $tax_id);

        $pdf->setXY(66, 118.5);
        $pdf->Write(0, $year_of_establishment);

        $pdf->setXY(140, 118.5);
        $pdf->Write(0, $number_of_employees);

        $check = "3";
        $pdf->SetFont('ZapfDingbats','', 10);
        $pdf->SetXY(71, 118);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(62, 136.5);
        $pdf->Write(0, $address);

        $pdf->SetXY(132, 136.5);
        $pdf->Write(0, $city_state_zip);

        $pdf->SetXY(54, 142.5);
        $pdf->Write(0, $primary_contact);

        $pdf->SetXY(120, 142.5);
        $pdf->Write(0, $phone_num);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(54, 157);
        $pdf->Write(0, $loan_amount);

        $pdf->SetXY(178, 181.5);
        $pdf->Write(0, $average_payroll);
        
        $pdf->SetXY(178, 188);
        $pdf->Write(0, $eidl_amount);
        
        $pdf->SetXY(178, 194);
        $pdf->Write(0, $this->getLoanTotal($elements));
    }

    private function printEligibility1($pdf, $elements) {
        $check = "n";
        $pdf->SetFont('ZapfDingbats','', 11);
        $pdf->SetXY(177, 24.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(177, 62);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(177, 96);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(177.5, 136);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(177.5, 140);
        $pdf->Cell(10, 10, $check, 0, 0);

        $f_code = $elements["sba_franchise_identifier_code"]["#default_value"];
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(88, 151);
        $pdf->Write(0, $f_code);
    }

    private function printEligibility2($pdf, $elements) {
        $check = "n";
        $pdf->SetFont('ZapfDingbats','', 11);
        

        $pdf->SetXY(177.5, 162);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(177.5, 178);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(173.5, 200.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(173.5, 212.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(174, 229.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(190, 245);
        $pdf->Cell(10, 10, $check, 0, 0);
    }

    private function printLenderCertification($pdf, $elements) {
        $h_img = fopen(self::DOC_PATH . "Stella's Signature.png", "rb");
        $img = fread($h_img, filesize(self::DOC_PATH . "Stella's Signature.png"));
        fclose($h_img);
        // prepare a base64 encoded "data url"
        $pic = 'data://text/plain;base64,' . base64_encode($img);
        // extract dimensions from image
        // $info = getimagesize($pic);

        $pdf->Image($pic, 72, 70, 30, 8, 'png');

        $pdf->SetFont('Helvetica', '', 12);
        $pdf->SetXY(156, 76);
        $pdf->Write(0, date("m-j-Y"));

        $pdf->SetXY(66, 86.5);
        $pdf->Write(0, "Stella Zhang");

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(156, 86.5);
        $pdf->Write(0, "Chief Operating Officer");

    }

    private function getBusinessAddress($elements) {
        $is_us_address = $elements["is_us_address"]["#default_value"];
        $address = "";
        if ($is_us_address == 1) {
            $address = $elements["business_address"]["#default_value"]["address"]
            . ", " . $elements["business_address"]["#default_value"]["address_2"];
        }
        else {
            $address = $elements["global_business_address"]["#default_value"]["address"]
            . ", " . $elements["global_business_address"]["#default_value"]["address_2"];
        }
        return $address;
    }

    private function getCityStateZip($elements) {
        $is_us_address = $elements["is_us_address"]["#default_value"];
        $address2 = "";
        if ($is_us_address == 1) {
            $address2 = $elements["business_address"]["#default_value"]["city"]
            . ", " . $elements["business_address"]["#default_value"]["state_province"]
            . ", " . $elements["business_address"]["#default_value"]["postal_code"];

        }
        else {
            $address2 = $elements["global_business_address"]["#default_value"]["city"]
            . ", " . $elements["global_business_address"]["#default_value"]["state_province"]
            . ", " . $elements["global_business_address"]["#default_value"]["postal_code"];
        }
        return $address2;
    }

    private function getPrimaryName($elements) {
        if (!empty($elements["first_name"][0]) && !empty($elements["last_name"][0])) {
            return $elements["first_name"][0]["#default_value"] . " " . $elements["last_name"][0]["#default_value"];
        }
        else {
            return "";
        }
    }

    
    
    

    
    
    
}