<?php

namespace Drupal\application\Controller;


use Drupal\application\Service\ClientService;
use Drupal\application\Service\JWTService;

require_once __DIR__ . '/../ds_config.php';

/**
 * Define Lender Form Template Builder
 */

class LenderController {
    /** ClientService */
    private $clientService;
    
    /** JSON Web Token Service */
    private $authService;

    /** Specific template arguments */
    private $args;

    private $elements;

    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct(&$elements) {
        $this->elements = $elements;
    }

    
    
}