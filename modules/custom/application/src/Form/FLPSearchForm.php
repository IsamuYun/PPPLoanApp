<?php

namespace Drupal\application\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;

define('FLP_WEBFORM_ID', 'apply_for_flp_loan');

/**
 * Implements the FLPSearchForm form controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class FLPSearchForm extends FormBase {


    /**
     * Build the FLP Search form.
     *
     * A build form method constructs an array that defines how markup and
     * other form elements are included in an HTML form.
     *
     * @param array $form
     *   Default form array structure.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   Object containing current form state.
     *
     * @return array
     *   The render array defining the elements of the form.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['description'] = [
            '#type' => 'item',
            '#markup' => $this->t('Please enter your Primary Contact / Business TIN (EIN, SSN) / ALC Loan Number to fetch your saved application.'),
        ];

        $form['primary_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Primary Contact'),
        ];

        $form['ein'] = [
            '#type' => 'textfield',
            '#required' => false,
            '#title' => $this->t('Business TIN (EIN, SSN)'),
        ];

        $form['loan_number'] = [
            '#type' => 'textfield',
            '#required' => false,
            '#title' => $this->t('ALC Loan Number'),
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];

        // Add a submit button that handles the submission of the form.
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Search'),
        ];

        return $form;
    }

    /**
     * Getter method for Form ID.
     *
     * The form ID is used in implementations of hook_form_alter() to allow other
     * modules to alter the render array built by this form controller. It must be
     * unique site wide. It normally starts with the providing module's name.
     *
     * @return string
     *   The unique ID of the form defined by this class.
     */
    public function getFormId() {
        return 'flp_search_form';
    }

    /**
     * Implements form validation.
     *
     * The validateForm method is the default method called to validate input on
     * a form.
     *
     * @param array $form
     *   The render array of the currently built form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   Object describing the current state of the form.
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $primary_name = trim($form_state->getValue('primary_name'));
        $ein = trim($form_state->getValue('ein'));
        $loan_number = trim($form_state->getValue('loan_number'));

        if (empty($ein) && empty($primary_name) && empty($loan_number)) {
            $form_state->setErrorByName('primary_name', $this->t('Please enter your Primary Contact to fetch your saved application.'));
            $form_state->setErrorByName('ein', $this->t('Please enter your Business TIN (EIN, SSN) to fetch your saved application.'));
            $form_state->setErrorByName('loan_number', $this->t('Please enter your Loan Number to fetch your saved application.'));
        }
    }

    /**
     * Implements a form submit handler.
     *
     * The submitForm method is the default method called for any submit elements.
     *
     * @param array $form
     *   The render array of the currently built form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   Object describing the current state of the form.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $primary_name = $form_state->getValue('primary_name');
        $ein = $form_state->getValue('ein');
        $loan_number = $form_state->getValue('loan_number');
        $title = empty($primary_name) ? $ein : $primary_name;
        $data = $this->searchFlpResult($primary_name, $ein, $loan_number);
        if (empty($data)) {
            $this->messenger()->addMessage($this->t('Sorry, cannot find any saved application of %title. Please check it.', ['%title' => $title]));
        }
        else {
            $draft_token = '';
            $ret = $this->checkFlpSubmission($data->loan_number);

            if ($ret['saved']) {
                if (empty($ret['token'])) {
                    $this->messenger()->addMessage($this->t('Sorry, you have submitted the application of %title. All submissions must be unique. Please try another one.', ['%title' => $title]));
                    return;
                }
                $draft_token = $ret['token'];
            } 
            else {
                $draft_token = $this->createFlpWebformDraft($data);
            }

            if (!empty($draft_token)) {
                $response = new RedirectResponse("flp?token=$draft_token");
                $response->send();
                return;
            }
            else {
                $title = empty($data->primary_name) ? $data->ein : $data->primary_name;
                $this->messenger()->addMessage($this->t('Sorry, cannot find any saved application of %title. Please check it!', ['%title' => $title]));
            }

        }
    }

    /**
     * Search flp result from database.
     *
     * @param string $primary_name
     *   The string value of Primary Contact.
     * @param string $ein
     *   The string value of Business TIN (EIN, SSN).
     */
    public function searchFlpResult($primary_name = '', $ein = '', $loan_number = '') {
        $database = \Drupal::database();
        $condition = " WHERE ";
        $params = [];
        if (!empty($paimary_name)) {
            $condition .= "LOWER(primary_name) = :primary_name";
            $params[':primary_name'] = strtolower($primary_name);
        }
        if (!empty($ein)) {
            if (!empty($params)) {
                $condition .= " OR ";
            }
            $condition .= "ein = :ein";
            $params[":ein"] = $ein;
        }
        if (!empty($loan_number)) {
            if (!empty($params)) {
                $condition .= " OR ";
            }
            $condition .= "loan_number = :loan_number";
            $params[":loan_number"] = $loan_number;
        }
        if (empty($params)) {
            return "";
        }
        $query = $database->query("SELECT * FROM {lfa_data} " . $condition, $params);
        $result = $query->fetch();
        return $result;
    }


    /**
     * check flp result from webform submissions.
     *
     * @param string $primary_name
     *   The string value of Primary Contact.
     * @param string $ein
     *   The string value of Business TIN (EIN, SSN).
     */
    public function checkFlpSubmission($loan_number = '') :Array {
        $ret = ['saved' => false, 'token' => ''];
        $database = \Drupal::database();
        $select = $database->select('webform_submission_data', 'wsd')
            ->fields('wsd', array('sid'))
            ->condition('wsd.webform_id', FLP_WEBFORM_ID, '=')
            ->condition('wsd.name', 'lender_ppp_loan_number', '=')
            ->condition('wsd.value', $loan_number, '=');
        $executed = $select->execute();
        // Get all the results.
        $results = $executed->fetchAll();

        if (count($results) == 1) {
            $result = reset($results);

            $webform_submission = WebformSubmission::load($result->sid);
            $current_user = $this->currentUser();
            $uid = $current_user->id();
            $ret = ['saved' => true, 'token' => ''];
            if ($webform_submission->isDraft() && $uid == $webform_submission->getOwnerId()) {
                $ret['token'] = $webform_submission->getToken();
            }

        }
        return $ret;
    }




    /**
     * Create draft FLP webform submission based flp result from database.
     *
     * @param object $data
     *   Object describing the current user from database.
     */
    public function createFlpWebformDraft($data = []) :String {
        $ret = '';
        if ($data) {
            $current_user = $this->currentUser();
            $uid = $current_user->id();
            // Get submission values and data.
            $value_data = [
                'business_street_address' => $data->address_1,
                'city_state_zip' => $data->address_2,
                'email_address' => $data->primary_email,
                'business_legal_name_borrower' => $data->entity_name,
                'business_tin_ein_ssn_' => $data->ein,
                'primary_contact' => $data->primary_name,
                'phone_number' => $data->phone_number,
                'sba_ppp_loan_number' => $data->sba_number,
                'lender_ppp_loan_number' => $data->loan_number,
                'ppp_loan_amount' => $data->bank_notional_amount,
                'ppp_loan_disbursement_date' => $data->funding_date,
                'employees_at_time_of_loan_application' => $data->forgive_fte_at_loan_application,
                'eidl_application_number_if_applicable' => $data->forgive_eidl_application_number,
                'eidl_advance_amount_if_applicable_' => $data->forgive_eidl_amount,
                'forgiveness_calculation' => $data->bank_notional_amount,
                'forgive_covered_period_from' => $data->covered_period_from,
                'forgive_covered_period_to' => $data->covered_period_to,
                'naics_code' => $data->naics_code,
                'forgive_payroll' => $data->bank_notional_amount,
                'employees_at_time_of_forgiveness_application' => $data->forgive_fte_at_forgiveness_application,
                'loan_offer' => $data->loan_officer,
                'sba_loan_draw' => $data->draw,
            ];
            
            $values = [
                'webform_id' => FLP_WEBFORM_ID,
                'entity_type' => NULL,
                'entity_id' => NULL,
                'in_draft' => TRUE,
                'uid' => $uid,
                'uri' => '/flp',
                'remote_addr' => '',
                'data' => $value_data
            ];

            // Check webform is open.
            $webform = Webform::load($values['webform_id']);
            $is_open = WebformSubmissionForm::isOpen($webform);
            if ($is_open === TRUE) {
                // Submit values and get submission ID.
                $webform_submission = WebformSubmissionForm::submitFormValues($values);
                $web_submission_token = $webform_submission->getToken();
                $ret = $web_submission_token;

            }
        }
        return $ret;
    }

}
