<?php

namespace Drupal\application\Controller;

use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\Envelope;

use Drupal\application\Service\ClientService;
use Drupal\application\Service\JWTService;

/*
 * Envelope Info Controller
 */ 
class EnvelopeInfoController {
    /** Envelope Id */
    private $envelope_id;

    /** Specific template arguments */
    private $args;

    /** JSON Web Token Service */
    private $authService;

    /** ClientService */
    private $clientService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct($envelope_id)
    {
        $this->envelope_id = $envelope_id;
        $this->args = $this->getStatusArgs();
        $this->clientService = new ClientService($this->args);
        $this->authService = new JWTService();
    }

    /**
     * Get specific template arguments
     *
     * @return array
     */
    private function getStatusArgs(): array
    {
        $args = [
            'account_id' => $_SESSION['ds_account_id'],
            'base_path' => $_SESSION['ds_base_path'],
            'ds_access_token' => $_SESSION['ds_access_token'],
            'envelope_id' => $this->envelope_id
        ];

        return $args;
    }

    public function login() {
        $this->authService->login();
    }

    
}