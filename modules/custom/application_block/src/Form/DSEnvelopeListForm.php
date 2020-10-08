<?php

namespace Drupal\application_block\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class DSEnvelopeListForm extends FormBase {
    /**
     * Returns a unique string identifying the form.
     * 
     * The returned ID should be a unique string that can be a valid PHP function name, 
     * since it's used in hook implementation names such as 
     * hook_form_FORM_id_alter().
     * 
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId() {
        return "ds_envelope_list_form";
    }

    /**
     * Form constructor.
     * 
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * 
     * @return array
     *   The form structure.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['description'] = [
            '#type' => 'item',
            '#markup' => $this->t('Please enter the account id'),
        ];

        $form['account_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Account ID'),
            '#description' => $this->t("Enter the account id"),
            '#required' => TRUE,
        ];

        $form['accept'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t("I accept the terms of use of the site"),
            '#description' => $this->t("Please read and accept the terms of use"),
        );

        // Group submit handlers in an actions element with a key of "actions" so
        // that it gets styled correctly, and so that other modules may add actions
        // to the form. This is not required, but is convention.
        $form['actions'] = [
            '#type' => 'actions',
        ];

        // Add a submit button that handles the submission of the form.
        $form["actions"]["submit"] = [
            "#type" => "submit",
            "#value" => $this->t("Submit"),
        ];

        return $form;
    }

    /**
     * Validate the title and the checkbox of the form
     * 
     * @param array $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);

        $account_id = $form_state->getValue("account_id");
        $accept = $form_state->getValue("accept");

        if (strlen($account_id) < 10) {
            // Set an error for the form element with a key of "account_id".
            $form_state->setErrorByName("account_id", $this->t("The account_id must be at least 10 characters long."));
        }

        if (empty($accept)) {
            // Set an error for the form element with a key of "accept".
            $form_state->setErrorByName("accept", $this->t("You must accept the terms of use to continue"));
        }
    }

    /**
     * Form submission handler.
     * 
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Display the results.
        // Call the Static Service Container wrapper
        // We should inject the messenger service, but its beyone the scope of this example.
        $messenger = \Drupal::messenger();
        $messenger->addMessage("Account ID: " . $form_state->getValue("account_id"));
        $messenger->addMessage("Accept: " . $form_state->getValue("accept"));

        // Redirect to home
        $form_state->setRedirect("<front>");
    }
}