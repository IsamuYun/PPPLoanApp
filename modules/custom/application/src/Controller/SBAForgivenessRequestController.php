<?php

namespace Drupal\application\Controller;

use GuzzleHttp\Exception\ClientException;

use Drupal\Core\Form\FormStateInterface;

use stdClass;

class SBAForgivenessRequestController {
    const SBA_HEADERS = [
        'Authorization' => 'Token 7f4d183d617b693c3ad355006c2d7381745e49c6',
        'Vendor-Key' => 'de512795-a13f-4812-8d47-ed41adaa6d32'
    ];

    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct() {
        //$this->elements = $elements;
    }

    public function sendForgivenessRequest(array &$elements, array &$form, FormStateInterface $form_state) {
        $results = [];
        try {
            $client = \Drupal::httpClient();

            $headers = self::SBA_HEADERS;
            $headers['Content-Type'] = "application/json";
            $request_data = $this->createForgivenessData($elements);

            $url = "https://sandbox.forgiveness.sba.gov/api/ppp_loan_forgiveness_requests/";
    
            $response = $client->request('POST', $url, [
                'headers' => $headers,
                'body' => $request_data,
            ]);
            $body = json_decode($response->getBody());
            dpm($body);

            dpm($body->{"etran_loan"}->{"slug"});

            $slug = false;

            if (!empty($body->{"etran_loan"}->{"slug"})) {
                $slug = $body->{"etran_loan"}->{"slug"};
            }
            if ($slug) {
                $entity = $form_state->getFormObject()->getEntity();
                $data = $entity->getData();
                $data["sba_etran_loan_uuid"] = $slug;
                $entity->setData($data);
                $entity->save();
                $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"]["#value"] = $slug;
                $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"]["#default_value"] = $slug;

                $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = "SBA Forgiveness Request successfully created. \nEtran Loan UUID: " . $slug;
                $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = "SBA Forgiveness Request successfully created. \nEtran Loan UUID: " . $slug;

                dpm($form["elements"]["lender_confirmation"]["sba_response"]);
                //$this->uploadDocument($elements, $form, $form_state);
            }
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = $response;
                $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = $response;
            }
        }
    }

    private function createForgivenessData(array &$elements) {
        $request_data = new stdClass();
        $etran_loan = new stdClass();
        $order = array("$", " ", ",");
    
        $bank_notional_amount = str_replace($order, "", $elements["ppp_loan_amount"]["#default_value"]);
        $forgive_amount = str_replace($order, "", $elements["forgive_amount"]["#default_value"]);
        
        $time = strtotime($elements["ppp_loan_disbursement_date"]["#default_value"]);
        $funding_date = date('Y-m-d', $time);
    
        $etran_loan->bank_notional_amount = $bank_notional_amount;
        $etran_loan->sba_number = $elements["sba_ppp_loan_number"]["#default_value"];
        $etran_loan->loan_number = $elements["lender_ppp_loan_number"]["#default_value"];
        $etran_loan->entity_name = $elements["business_legal_name_borrower"]["#default_value"];
        $etran_loan->ein = $elements["business_tin_ein_ssn_"]["#default_value"];
        $etran_loan->funding_date = $funding_date;
        $etran_loan->forgive_eidl_amount = str_replace($order, "", $elements["eidl_advance_amount_if_applicable_"]["#default_value"]);
        $eidl_application_number = $elements["eidl_application_number_if_applicable"]["#default_value"];
        if (empty($eidl_application_number) || $eidl_application_number == 0) {
            $etran_loan->forgive_eidl_application_number = null;
        }
        else {
            $etran_loan->forgive_eidl_application_number = $eidl_application_number;
        }
        
        $etran_loan->address1 = $elements["business_street_address"]["#default_value"];
        $etran_loan->address2 = $elements["city_state_zip"]["#default_value"];
        $etran_loan->dba_name = $elements["dba_or_trade_name_if_applicable"]["#default_value"];
        $etran_loan->phone_number = $elements["phone_number"]["#default_value"];
        $etran_loan->forgive_fte_at_loan_application = $elements["employees_at_time_of_loan_application"]["#default_value"];
        $etran_loan->forgive_amount = $forgive_amount;
        $etran_loan->forgive_fte_at_forgiveness_application = $elements["employees_at_time_of_forgiveness_application"]["#default_value"];
        $etran_loan->primary_email = $elements["email_address"]["#default_value"];
        $etran_loan->primary_name = $elements["primary_contact"]["#default_value"];
        $etran_loan->ez_form = false;
        $etran_loan->forgive_lender_confirmation = true;
        
        $decision_value = $elements["decision_regarding_forgiveness_of_this_ppp_loan"]["#default_value"];
        $decision = 0;
    
        if ($decision_value == "approved_in_part") {
            $decision = 1;
        }
        else if ($decision_value == "denied") {
            $decision = 2;
        }
        
        $etran_loan->forgive_lender_decision = $decision;
        $etran_loan->s_form = true;
        $request_data->etran_loan = $etran_loan;
    
        return json_encode($request_data);
    }

    public function uploadDocument(array &$elements, array &$form, FormStateInterface $form_state) {
        try {
            $client = \Drupal::httpClient();
            $headers = self::SBA_HEADERS;
            $etran_loan_uuid = $elements["sba_etran_loan_uuid"]["#default_value"];
            if (empty($etran_loan_uuid)) {
                return $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"];
            }
        
            $file_url = $elements["form_file_name"]["#default_value"];
            $file_name_array = explode('/', $file_url);
        
            if (empty($file_url) || empty($file_url)) {
                return $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"];
            }
        
            $file_name = $file_name_array[count($file_name_array) - 1];
            $real_path = \Drupal::service('file_system')->realpath('private://webform/apply_for_flp_loan/' . $file_name);
        
            $url = "https://sandbox.forgiveness.sba.gov/api/ppp_loan_documents/";
            
            $response = $client->request('POST', $url, [
                'headers' => $headers,
                'multipart' => [
                    [
                        "name" => "name",
                        "contents" => "SBA_PPP_Loan_Forgiveness_Application_Form_3508S.pdf",
                    ],
                    [
                        "name" => "document_type",
                        "contents" => 1
                    ],
                    [
                        "name" => "etran_loan",
                        "contents" => $etran_loan_uuid,
                    ],
                    [
                        "name" => "document",
                        "contents" => fopen($real_path, 'r')
                    ]
                ]
            ]);
            $body = json_decode($response->getBody());
            dpm($body);

            $sba_response = $form["elements"]["lender_confirmation"]["sba_response"]["#value"];
            $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = $sba_response . "\n" . "3508S Form is successfully uploaded.";
            $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = $sba_response . "\n" . "3508S Form is successfully uploaded.";
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = $response;
                $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = $response;
            }
        }
    }

    public function getRequestStatus(array &$elements, array &$form, FormStateInterface $form_state) {
        try {
            $client = \Drupal::httpClient();
            $headers = self::SBA_HEADERS;
            $headers['Content-Type'] = "application/json";
            $etran_loan_uuid = $elements["sba_etran_loan_uuid"]["#default_value"];
            if (empty($etran_loan_uuid)) {
                return;
            }
            
            $url = "https://sandbox.forgiveness.sba.gov/api/ppp_loan_forgiveness_requests/" . $etran_loan_uuid . "/";
            
            $response = $client->request('GET', $url, [
                'headers' => $headers,
            ]);
            $body = json_decode($response->getBody());
            dpm($body);

            $form["elements"]["lender_confirmation"]["sba_request_status"]["#value"] = $response->getBody();
            $form["elements"]["lender_confirmation"]["sba_request_status"]["#default_value"] = $response->getBody();
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["lender_confirmation"]["sba_request_status"]["#value"] = $response;
                $form["elements"]["lender_confirmation"]["sba_request_status"]["#default_value"] = $response;
            }
        }
    }
}