<?php

namespace Drupal\application\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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
            '#markup' => $this->t('Please enter your Business Legal Name and email to fetch your saved application'),
        ];

        $form['legel_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Business Legal Name'),
            '#required' => TRUE,
        ];

        $form['email'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Email'),
            '#required' => TRUE,
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
        $title = $form_state->getValue('legel_name');
        if (strlen($title) < 5) {
            // Set an error for the form element with a key of "title".
            $form_state->setErrorByName('legel_name', $this->t('The title must be at least 5 characters long.'));
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
        /*
         * This would normally be replaced by code that actually does something
         * with the title.
         */
        $title = $form_state->getValue('legel_name');
        $this->messenger()->addMessage($this->t('Sorry, cannot find any saved application of %title. Please check it.', ['%title' => $title]));
    }

}
