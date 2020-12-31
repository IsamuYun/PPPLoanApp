<?php

namespace Drupal\application\Validate;

use Drupal\Core\Field\FieldException;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form API callback. Validate element value.
 */
class ApplicationValidate {
  /**
   * Validates given element.
   *
   * @param array              $element      The form element to process.
   * @param FormStateInterface $formState    The form state.
   * @param array              $form The complete form structure.
   */
  public static function birthdayValidate(array &$element, FormStateInterface $formState, array &$form) {
    $webformKey = $element['#webform_key'];
    $value = $formState->getValue($webformKey);

    // Skip empty unique fields or arrays (aka #multiple).
    if ($value === '' || is_array($value)) {
      return;
    }


    $birth_date = date_create($value);
    $now_date = date_create("now");
    $interval = date_diff($now_date, $birth_date);


    if ($interval->invert == 1 || $interval->y < 16 || $interval->y >= 100) {
      if (isset($element['#title'])) {
        $formState->setError(
          $element,
          t('%year years old is not allowed for this application. Please use a different one.', ['%year' =>$interval->y ])
        );
      } else {
        $formState->setError($element);
      }
    }
  }
}