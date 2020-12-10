<?php

namespace Drupal\application\Controller;

use Drupal\Core\Controller\ControllerBase;

class DSListenerController extends ControllerBase {
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
        

        return [
             '#type' => 'markup',
             '#markup' => $this->t("DocuSign Listener is working"),
        ];
    }
}