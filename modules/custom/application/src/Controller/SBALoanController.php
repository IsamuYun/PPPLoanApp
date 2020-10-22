<?php

namespace Drupal\application\Controller;




class SBALoanController {
    private $elements;

    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct(&$elements) {
        $this->elements = $elements;
    }

    public function sendToSBA() {

    }
}