<?php

namespace Drupal\application\Controller;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformFormHelper;

use Drupal\file\FileInterface;

/**
 * Define Lender Form Template Builder
 */

class SecondLenderController {
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

        try {
            $pageCount = $pdf->setSourceFile(self::DOC_PATH . "PPP Second Draw Lender Application Form-508.pdf");
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

            $templateId = $pdf->importPage(3);
            $pdf->AddPage();
            $pdf->useTemplate($templateId);
            $this->printEligibility2($pdf, $elements);
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
        $pdf->SetXY(36, 71);
        $pdf->Write(0, 'American Lending Center');
        // Lender Location ID
        $pdf->SetXY(162, 71);
        $pdf->Write(0, "530223");
        // Lender Address
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(36, 77);
        $pdf->Write(0, "1 World Trade Center, Suite 1130");
        // City
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(98, 77);
        $pdf->Write(0, "Long Beach");
        // State
        $pdf->SetXY(140, 77);
        $pdf->Write(0, "CA");
        // Zip 
        $pdf->SetXY(170, 77);
        $pdf->Write(0, "90831");
        // Lender Contact
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(36, 82);
        $pdf->Write(0, $officer_name);
        // Phone
        $pdf->SetXY(93, 82);
        $pdf->Write(0, "562  449 0139");
        // Cell
        $pdf->SetXY(146, 82);
        $pdf->Write(0, "562  449 0139");
        // Contact Email
        $pdf->SetXY(36, 87);
        $pdf->Write(0, $email);
        // Title
        $pdf->SetXY(130, 87);
        $pdf->Write(0, "Admin");
    }

    private function printCompanyStructure($pdf, $elements) {
        $check = "3";
        $pdf->SetFont('ZapfDingbats','', 10);
        
        $company_structure = $elements["company_structure"]["#default_value"];
        if ($company_structure == "Sole Proprietorship") {
            $pdf->SetXY(39.5, 95);
        }
        else if ($company_structure == "General Partnership") {
            $pdf->SetXY(65, 95);
        }
        else if ($company_structure == "C Corporation") {
            $pdf->SetXY(86, 95);
        }
        else if ($company_structure == "S Corporation") {
            $pdf->SetXY(101, 95);
        }
        else if ($company_structure == "Limited Liability Company") {
            $pdf->SetXY(116, 95);
        }
        else if ($company_structure == "Independent Contractor") {
            $pdf->SetXY(128.5, 95);
        }
        else if ($company_structure == "Eligible Self-employed Individual") {
            $pdf->SetXY(39.5, 98.5);
        }
        else if ($company_structure == "501 (c)(3) nonprofit") {
            $pdf->SetXY(78, 98.5);
        }
        else if ($company_structure == "501 (c)(6) organization") {
            $pdf->SetXY(108, 98.5);
        }
        else if ($company_structure == "501 (c)(19) veterans organization") {
            $pdf->SetXY(39.5, 98.5);
        }
        else if ($company_structure == "Housing cooperative") {
            $pdf->SetXY(39.5, 102);
        }
        else if ($company_structure == "Tribal Business") {
            $pdf->SetXY(86, 102);
        }
        else if ($company_structure == "Other") {
            $pdf->SetXY(141.5, 102);
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
        /*
        $used_EIDL_amount = $elements["used_loan_amount"]["#default_value"];
        if (empty($used_EIDL_amount)) {
            $used_EIDL_amount = 0;
        }
        $used_EIDL_amount = str_replace(["$", ",", " "], "", $used_EIDL_amount);
        */
        $used_EIDL_amount = 0;
        $amount = 0;
        
        if ($payroll >= $used_EIDL_amount) {
            $amount = $payroll - $used_EIDL_amount;
        }
        return number_format($amount, 2);
    }

    private function getFirstDrawLoanNumber($elements) {
        return $elements["first_draw_sba_loan_number"]["#default_value"];
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
        $year_of_establishment = $elements["date_established"]["#value"]["year"];
        $number_of_employees = $elements["number_of_employees"]["#default_value"];
        $address = $this->getBusinessAddress($elements);
        $city_state_zip = $this->getCityStateZip($elements);
        $primary_contact = $this->getPrimaryName($elements);
        $phone_num = $elements["business_phone_number"]["#default_value"];
        $average_payroll = $this->getAveragePayroll($elements);
        $loan_amount = $this->getLoanAmount($elements);
        
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->setXY(50, 111.5);
        $pdf->Write(0, $applicant_legal_name);

        $pdf->setXY(142, 111.5);
        $pdf->Write(0, $naics_code);

        $pdf->setXY(66, 116);
        $pdf->Write(0, $dba_name);

        $pdf->SetXY(142, 116);
        $pdf->Write(0, $tax_id);

        $pdf->setXY(66, 120);
        $pdf->Write(0, $year_of_establishment);

        $pdf->setXY(142, 120);
        $pdf->Write(0, $number_of_employees);
        
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(60, 126);
        $pdf->Write(0, $address);

        $pdf->SetXY(134, 126);
        $pdf->Write(0, $city_state_zip);

        $pdf->SetXY(54, 131.5);
        $pdf->Write(0, $primary_contact);

        $pdf->SetXY(122, 131.5);
        $pdf->Write(0, $phone_num);

        // First Draw Loan Number 
        $pdf->SetXY(78, 135.5);
        $pdf->Write(0, $this->getFirstDrawLoanNumber($elements));

        $pdf->SetXY(92, 140.5);
        $pdf->Write(0, $elements["2020_quarter"]["#default_value"]);

        $pdf->SetXY(158, 140.5);
        $pdf->Write(0, $elements["reference_2019_quarter"]["#default_value"]);

        $pdf->SetXY(92, 154);
        $pdf->Write(0, $elements["2020_gross_receipts"]["#default_value"]);

        $pdf->SetXY(158, 154);
        $pdf->Write(0, $elements["2019_gross_receipts"]["#default_value"]);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(52, 173);
        $pdf->Write(0, $loan_amount);

        $pdf->SetXY(156, 197.5);
        $pdf->Write(0, $average_payroll);
        
        $pdf->SetXY(156, 202.5);
        $pdf->Write(0, $this->getLoanTotal($elements));
    }

    private function printEligibility1($pdf, $elements) {
        $check = "n";
        $pdf->SetFont('ZapfDingbats','', 11);
        $pdf->SetXY(178.5, 24);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(178.5, 56.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(178.5, 81.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(178.5, 121);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(178.5, 155.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(176.5, 184.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(176.5, 188.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $f_code = $elements["sba_franchise_identifier_code"]["#default_value"];
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(88, 199);
        $pdf->Write(0, $f_code);

        $pdf->SetFont('ZapfDingbats','', 11);
        $pdf->SetXY(177.5, 209);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(177.5, 224.5);
        $pdf->Cell(10, 10, $check, 0, 0);
    }

    private function printEligibility2($pdf, $elements) {
        $check = "n";
        $pdf->SetFont('ZapfDingbats','', 11);
        
        $pdf->SetXY(173.5, 13);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(173.5, 25.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(173.5, 42.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(192.5, 59);
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

        $pdf->Image($pic, 72, 143, 30, 8, 'png');

        $pdf->SetFont('Helvetica', '', 12);
        $pdf->SetXY(156, 148.5);
        $pdf->Write(0, date("m-j-Y"));

        $pdf->SetXY(66, 159.5);
        $pdf->Write(0, "Stella Zhang");

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(156, 159.5);
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