<?php

namespace Drupal\application\Controller;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformFormHelper;

use Drupal\file\FileInterface;

use stdClass;

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

        try {
            $pageCount = $pdf->setSourceFile(self::DOC_PATH . "PPP Lender Application Form-508.pdf");
            $templateId = $pdf->importPage(1);
            $pdf->AddPage();
            $pdf->useTemplate($templateId);
            
            $this->printLenderInformation($pdf);
            $this->printCompanyStructure($pdf, $elements);
            $this->printBorrowerInformation($pdf, $elements);

            $this->printEligibility1($pdf, $elements);
            
            $templateId = $pdf->importPage(2);
            $pdf->AddPage();
            $pdf->useTemplate($templateId);
            $this->printEligibility2($pdf, $elements);
            $this->printLenderCertification($pdf, $elements);

            $templateId = $pdf->importPage(3);
            $pdf->AddPage();
            $pdf->useTemplate($templateId);

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
            dpm($e);
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
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(36, 73);
        $pdf->Write(0, "1 World Trade Center, Suite 1130");
        // City
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->SetXY(98, 73);
        $pdf->Write(0, "Long Beach");
        // State
        $pdf->SetXY(140, 73);
        $pdf->Write(0, "CA");
        // Zip 
        $pdf->SetXY(170, 73);
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
        $pdf->SetXY(124, 83);
        $pdf->Write(0, "Admin");
    }

    private function printCompanyStructure($pdf, $elements) {
        $check = "3";
        $pdf->SetFont('ZapfDingbats','', 10);
        
        $company_structure = $elements["company_structure"]["#default_value"];
        if ($company_structure == "Sole Proprietorship") {
            $pdf->SetXY(39, 91);
        }
        else if ($company_structure == "General Partnership") {
            $pdf->SetXY(64.5, 91);
        }
        else if ($company_structure == "Limited Liability Company") {
            $pdf->SetXY(116, 91);
        }
        else if ($company_structure == "S Corporation") {
            $pdf->SetXY(85, 91);
        }
        else if ($company_structure == "C Corporation") {
            $pdf->SetXY(85, 91);
        }
        else if ($company_structure == "Other") {
            $pdf->SetXY(117, 98);
        }
        else if ($company_structure == "501 (c)(3) nonprofit") {
            $pdf->SetXY(88, 94.5);
        }
        else if ($company_structure == "501 (c)(19) veterans organization") {
            $pdf->SetXY(119, 94.5);
        }
        else if ($company_structure == "Tribal business (sec. 31 (b)(2)(c) of Small Business Act)") {
            $pdf->SetXY(39, 98);
        }
        else if ($company_structure == "Independent contractor") {
            $pdf->SetXY(128, 91);
        }
        else if ($company_structure == "Eligible self-employed individual") {
            $pdf->SetXY(39, 94.5);
        }
        $pdf->Cell(10, 10, $check, 0, 0);
    }

    private function printBorrowerInformation($pdf, $elements) {
        $applicant_legal_name = $elements["business_name"]["#default_value"];
        $dba_name = $elements["another_business_name"]["#default_value"];
        $tax_id = $elements["tax_id_number"]["#default_value"];
        $address = $this->getBusinessAddress($elements);
        $city_state_zip = $this->getCityStateZip($elements);
        $primary_contact = $this->getPrimaryName($elements);
        $phone_num = $elements["business_phone_number"]["#default_value"];
        $average_payroll = number_format($this->getAveragePayroll($elements) * 2.5, 2);
        $loan_amount = $this->getLoanAmount($elements);
        $eidl_amount = $this->getEIDLAmount($elements);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(66, 113);
        $pdf->Write(0, $applicant_legal_name);

        $pdf->SetXY(66, 118.5);
        $pdf->Write(0, $dba_name);

        $pdf->SetXY(160, 118.5);
        $pdf->Write(0, $tax_id);

        $pdf->SetXY(66, 124.5);
        $pdf->Write(0, $address);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(144, 124.5);
        $pdf->Write(0, $city_state_zip);

        $pdf->SetXY(66, 131);
        $pdf->Write(0, $primary_contact);

        $pdf->SetXY(154, 131);
        $pdf->Write(0, $phone_num);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(50, 141);
        $pdf->Write(0, $loan_amount);

        $pdf->SetXY(155, 166);
        $pdf->Write(0, $average_payroll);
        
        $pdf->SetXY(155, 172);
        $pdf->Write(0, $eidl_amount);
        
        $pdf->SetXY(155, 178);
        $pdf->Write(0, $loan_amount);
    }

    private function printEligibility1($pdf, $elements) {
        $check = "n";
        $pdf->SetFont('ZapfDingbats','', 11);
        $pdf->SetXY(177, 196);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(177, 214);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(188, 230);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(170, 241);
        $pdf->Cell(10, 10, $check, 0, 0);

    }

    private function printEligibility2($pdf, $elements) {
        $check = "n";
        $pdf->SetFont('ZapfDingbats','', 11);
        $pdf->SetXY(177, 15.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(177, 33);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(173, 56.5);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(173, 72);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(173.5, 91);
        $pdf->Cell(10, 10, $check, 0, 0);

        $pdf->SetXY(192, 107);
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

        $pdf->Image($pic, 66, 177, 36, 10, 'png');

        $pdf->SetFont('Helvetica', '', 14);
        $pdf->SetXY(156, 184);
        $pdf->Write(0, date("m-j-Y"));

        $pdf->SetXY(66, 195);
        $pdf->Write(0, "Stella Zhang");

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(156, 196);
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
        return $elements["first_name"]["#default_value"] . " " . $elements["last_name"]["#default_value"];
    }

    private function getAveragePayroll($elements) {
        $number_of_employees = $elements["number_of_employees"]["#default_value"];
        $net_earnings = $elements["net_earnings"]["#default_value"];
        $net_earnings = str_replace(",", "", $net_earnings);
        $net_earnings = floatval(substr($net_earnings, 2));
        $average_payroll = 0;
        
        if ($net_earnings > 100000) {
            $net_earnings = 100000;
        }
        else if ($net_earnings < 0) {
            $net_earnings = 0;
        }
        
        $total_payroll = $elements["total_salaries"]["#default_value"];
        $total_tax_paid = $elements["total_tax_paid"]["#default_value"];
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

    private function getLoanAmount($elements) {
        $average_payroll = $this->getAveragePayroll($elements);

        $loan_amount = 0;

        $used_EIDL_amount = $elements["used_loan_amount"]["#default_value"];
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

    private function getEIDLAmount($elements) {
        $used_EIDL_amount = $elements["used_loan_amount"]["#default_value"];
        if (empty($used_EIDL_amount)) {
            return "0.00";
        }
        $used_EIDL_amount = floatval(substr($used_EIDL_amount, 2));
        return number_format($used_EIDL_amount, 2);
    }

    
    
    
}