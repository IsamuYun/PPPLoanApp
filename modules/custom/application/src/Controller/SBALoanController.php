<?php

namespace Drupal\application\Controller;

use GuzzleHttp\Exception\ClientException;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformFormHelper;

use stdClass;

class SBALoanController {
    const SBA_SANDBOX_HEADERS = [
        'Authorization' => 'Token 7f4d183d617b693c3ad355006c2d7381745e49c6',
        'Vendor-Key' => 'de512795-a13f-4812-8d47-ed41adaa6d32'
    ];

    const SBA_PRODUCTION_HEADERS = [
        'Authorization' => 'Token 7152fb356b47624e89ff81dd06afe44b4e345999',
        'Vendor-Key' => '360be2e5-cc2c-4a90-837c-32394087efb3'
    ];

    #const SBA_HEADERS = self::SBA_SANDBOX_HEADERS;
    const SBA_HEADERS = self::SBA_PRODUCTION_HEADERS;
    const SBA_SANDBOX_HOST = "https://sandbox.forgiveness.sba.gov/";
    const SBA_PRODUCTION_HOST = "https://forgiveness.sba.gov/";

    #const SBA_HOST = self::SBA_SANDBOX_HOST;
    const SBA_HOST = self::SBA_PRODUCTION_HOST;
    
    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct() {
    }

    public function sendLoanRequest(array &$form, FormStateInterface $form_state) {
        
    }
}