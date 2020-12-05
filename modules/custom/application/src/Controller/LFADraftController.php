<?php

namespace Drupal\application\Controller;

use Drupal\Core\Controller\ControllerBase;

use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionForm;

class LFADraftController extends ControllerBase {
    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct() {
    
    }

    /**
     * Display the markup.
     */
    public function content() {

        // Get submission values and data.
        $values = [
            'webform_id' => 'apply_for_flp_loan',
            'entity_type' => NULL,
            'entity_id' => NULL,
            'in_draft' => TRUE,
            'uid' => '4',
            'langcode' => 'en',
            'token' => 'pgmJREX2l4geg2RGFp0p78Qdfm1ksLxe6IlZ-mN9GZI',
            'uri' => '/flp',
            'remote_addr' => '',
            'data' => [
                'business_street_address' => 'Don\'t come to me when you are lonely tonight.',
                'city_state_zip' => '91803',
                'email_address' => 'myemail@mydomain.com',
                'business_legal_name_borrower' => "Qin",
                'business_tin_ein_ssn_' => 3345678,
                'primary_contact' => "路人甲乙丙",
                'phone_number' => "8008820",
                'sba_ppp_loan_number' => 12345678,
                'lender_ppp_loan_number' => 1000,
                'ppp_loan_amount' => "$5.00",
                'ppp_loan_disbursement_date' => '2000-01-01',
                'employees_at_time_of_loan_application' => 10,
                'employees_at_time_of_forgiveness_application' => 20,
            ],
        ];
  
        // Check webform is open.
        $webform = Webform::load($values['webform_id']);
        $is_open = WebformSubmissionForm::isOpen($webform);
        $web_submission_id = 0;
        if ($is_open === TRUE) {
            // Validate submission.
            $errors = WebformSubmissionForm::validateFormValues($values);
  
            // Check there are no validation errors.
            if (!empty($errors)) {
                print($errors);
            }
            else {
                // Submit values and get submission ID.
                $webform_submission = WebformSubmissionForm::submitFormValues($values);
                $web_submission_id = $webform_submission->id();
            }
        }

        return [
             '#type' => 'markup',
             '#markup' => $web_submission_id,
        ];
    }

}