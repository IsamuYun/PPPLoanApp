<?php

namespace Drupal\application\Controller;

use GuzzleHttp\Exception\ClientException;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformFormHelper;

use stdClass;

class SBAForgivenessRequestController {
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

    public function sendForgivenessRequest(array &$elements, array &$form, FormStateInterface $form_state) {
        $results = [];
        try {
            $client = \Drupal::httpClient();

            $headers = self::SBA_HEADERS;
            $headers['Content-Type'] = "application/json";
            $request_data = $this->createForgivenessData($elements);

            $url = self::SBA_HOST . "api/ppp_loan_forgiveness_requests/";
    
            $response = $client->request('POST', $url, [
                'headers' => $headers,
                'body' => $request_data,
            ]);
            $body = json_decode($response->getBody());
            
            dpm($body->{"etran_loan"}->{"slug"});

            $slug = false;
            $request_slug = false;

            if (!empty($body->{"etran_loan"}->{"slug"})) {
                $request_slug = $body->{"slug"};
                $slug = $body->{"etran_loan"}->{"slug"};
            }
            if ($slug && $request_slug) {
                $current_user_account_name = \Drupal::currentUser()->getAccountName();
                $entity = $form_state->getFormObject()->getEntity();
                $data = $entity->getData();
                $data["sba_etran_loan_uuid"] = $slug;
                $data["sba_slug"] = $request_slug;
                $data["sba_request_status"] = "Pending Validation";
                $data["loan_offer"] = $current_user_account_name;
                $entity->setData($data);
                $entity->save();
                $form["elements"]["lender_confirmation"]["loan_offer"]["#value"] = $current_user_account_name;
                $form["elements"]["lender_confirmation"]["loan_offer"]["#default_value"] = $current_user_account_name;
                $form["elements"]["lender_confirmation"]["sba_slug"]["#value"] = $request_slug;
                $form["elements"]["lender_confirmation"]["sba_slug"]["#default_value"] = $request_slug;
                $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"]["#value"] = $slug;
                $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"]["#default_value"] = $slug;
                $form["elements"]["lender_confirmation"]["sba_request_status"]["#value"] = "Pending Validation";
                $form["elements"]["lender_confirmation"]["sba_request_status"]["#default_value"] = "Pending Validation";

                $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = "SBA Forgiveness Request successfully created. \nEtran Loan UUID: " . $slug;
                $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = "SBA Forgiveness Request successfully created. \nEtran Loan UUID: " . $slug;

                $this->uploadDocument($elements, $form, $form_state);
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
        $forgive_amount = $bank_notional_amount;
        #$forgive_amount = str_replace($order, "", $elements["forgiveness_calculation"]["#default_value"]);
        
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
            #$etran_loan->forgive_eidl_application_number = intval($eidl_application_number);
            $etran_loan->forgive_eidl_application_number = null;
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
        $etran_loan->naics_code = $elements["naics_code"]["#default_value"];
        $etran_loan->ppp_loan_draw = 1;
        $etran_loan->s_form = true;
        $etran_loan->forgive_2_million = false;
        $etran_loan->forgive_payroll = str_replace($order, "", $elements["forgive_payroll"]["#default_value"]);
        $request_data->etran_loan = $etran_loan;
    
        return json_encode($request_data);
    }

    public function uploadDocument(array &$elements, array &$form, FormStateInterface $form_state) {
        try {
            $client = \Drupal::httpClient();
            $headers = self::SBA_HEADERS;

            $etran_loan_uuid = $elements["sba_etran_loan_uuid"]["#default_value"];
            if (empty($etran_loan_uuid)) {
                return;
            }
            $sba_upload_status = $elements["sba_upload_status"]["#default_value"];
            if ($sba_upload_status == "uploaded") {
                return;
            }
            
            $file_url = $elements["form_file_name"]["#default_value"];
            $file_name_array = explode('/', $file_url);
        
            if (empty($file_url) || empty($file_url)) {
                return;
            }
            
            $file_name = $file_name_array[count($file_name_array) - 1];
            $real_path = \Drupal::service('file_system')->realpath('private://webform/3508s_form/' . $file_name);
        
            $url = self::SBA_HOST . "api/ppp_loan_documents/";
            
            $response = $client->request('POST', $url, [
                'headers' => $headers,
                'multipart' => [
                    [
                        "name" => "name",
                        "contents" => "SBA_PPP_Loan_Forgiveness_Application_Form_3508S.pdf",
                    ],
                    [
                        "name" => "document_type",
                        "contents" => 35
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
            $entity = $form_state->getFormObject()->getEntity();
            $data = $entity->getData();
            $data["sba_upload_status"] = "uploaded";
            $entity->setData($data);
            $entity->save();
            $sba_response = $form["elements"]["lender_confirmation"]["sba_response"]["#value"];
            $sba_response .= "\n3508S Form is successfully uploaded.\n";
            $sba_response .= $this->uploadAnotherDocuments($form, $form_state, $etran_loan_uuid);
            $form["elements"]["lender_confirmation"]["sba_upload_status"]["#value"] = "uploaded";
            $form["elements"]["lender_confirmation"]["sba_upload_status"]["#default_value"] = "uploaded";
            $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = $sba_response; 
            $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = $sba_response;
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = $response;
                $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = $response;
            }
        }
    }

    private function uploadAnotherDocuments(array &$form, FormStateInterface $form_state, $etran_loan_uuid) :string {
        try {
            $client = \Drupal::httpClient();
            $headers = self::SBA_HEADERS;
            $file_list = $form["elements"]["supporting_documents"]["supporting_documents_title"]["supporting_documents_files"]["#default_value"];
            $upload_message = "";
            foreach ($file_list as $file) {
                $file_id = $file["file"];
                $file_handle = \Drupal\file\Entity\File::load($file_id);
                $file_uri = $file_handle->getFileUri();
                $file_name = $file_handle->getFilename();
                $file_type = intval($file["file_type"]);
                $real_path = \Drupal::service('file_system')->realpath($file_uri);
                $url = self::SBA_HOST . "api/ppp_loan_documents/";
            
                $response = $client->request('POST', $url, [
                    'headers' => $headers,
                    'multipart' => [
                        [
                            "name" => "name",
                            "contents" => $file_name,
                        ],
                        [
                            "name" => "document_type",
                            "contents" => $file_type
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
                #$body = json_decode($response->getBody());
                $upload_message .= $file_name . " is uploaded.\n";
            }
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = $response;
                $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = $response;
            }
        }
        return $upload_message;
    }

    public function getRequestStatus(array &$elements, array &$form, FormStateInterface $form_state) {
        try {
            $client = \Drupal::httpClient();
            $headers = self::SBA_HEADERS;
            $headers['Content-Type'] = "application/json";
            $sba_number = $elements["sba_ppp_loan_number"]["#default_value"];
            if (empty($sba_number)) {
                return;
            }
            
            $url = self::SBA_HOST . "api/ppp_loan_forgiveness_requests/?sba_number=" . $sba_number;
            
            $response = $client->request('GET', $url, [
                'headers' => $headers,
            ]);
            $body = json_decode($response->getBody());
            $sba_slug = "";
            $sba_etran_loan_uuid = "";
            $status = "";
            $upload_status = "";
            if (!empty($body->{"results"})) {
                $result = $body->{"results"}[0];
                $sba_slug = $result->{"slug"};
                $sba_etran_loan_uuid = $result->{"etran_loan"}->{"slug"};
                $status = $result->{"etran_loan"}->{"status"};
                $documents = $result->{"etran_loan"}->{"documents"};
                if (!empty($documents)) {
                    $upload_status = count($documents) . " documents has been uploaded";
                }
            }
            else {
                $upload_status = "";
            }
            
            $entity = $form_state->getFormObject()->getEntity();
            $data = $entity->getData();
            $data["sba_etran_loan_uuid"] = $sba_etran_loan_uuid;
            $data["sba_slug"] = $sba_slug;
            $data["sba_request_status"] = $status;
            $data["sba_response"] = "";
            $data["sba_upload_status"] = $upload_status;
            $entity->setData($data);
            $entity->save();
            $form["elements"]["lender_confirmation"]["sba_slug"]["#value"] = $sba_slug;
            $form["elements"]["lender_confirmation"]["sba_slug"]["#default_value"] = $sba_slug;
            $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"]["#value"] = $sba_etran_loan_uuid;
            $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"]["#default_value"] = $sba_etran_loan_uuid;
            $form["elements"]["lender_confirmation"]["sba_request_status"]["#value"] = $status;
            $form["elements"]["lender_confirmation"]["sba_request_status"]["#default_value"] = $status;
            $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = "";
            $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = "";
            $form["elements"]["lender_confirmation"]["sba_upload_status"]["#value"] = $upload_status;
            $form["elements"]["lender_confirmation"]["sba_upload_status"]["#default_value"] = $upload_status;
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["lender_confirmation"]["sba_request_status"]["#value"] = $response;
                $form["elements"]["lender_confirmation"]["sba_request_status"]["#default_value"] = $response;
            }
        }
    }

    public function deleteRequest(array &$form, FormStateInterface $form_state) {
        $elements = WebformFormHelper::flattenElements($form);
        $client = \Drupal::httpClient();
        $headers = self::SBA_HEADERS;
        try {
            $sba_slug = $elements["sba_slug"]["#default_value"];
            if (empty($sba_slug)) {
                return;
            }
            $status = $elements["sba_request_status"]["#default_value"];
            
            $url = self::SBA_HOST . "api/ppp_loan_forgiveness_requests/" . $sba_slug . "/";
            $response = $client->request("DELETE", $url, [
                'headers' => $headers,
            ]);
            $entity = $form_state->getFormObject()->getEntity();
            $data = $entity->getData();
            $data["sba_etran_loan_uuid"] = "";
            $data["sba_slug"] = "";
            $data["sba_request_status"] = "";
            $data["sba_upload_status"] = "";
            $data["sba_response"] = "Successfully deleted request";
            $entity->setData($data);
            $entity->save();
            $form["elements"]["lender_confirmation"]["sba_upload_status"]["#value"] = "";
            $form["elements"]["lender_confirmation"]["sba_upload_status"]["#default_value"] = "";
            $form["elements"]["lender_confirmation"]["sba_request_status"]["#value"] = "";
            $form["elements"]["lender_confirmation"]["sba_request_status"]["#default_value"] = "";
            $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"]["#value"] = "";
            $form["elements"]["lender_confirmation"]["sba_etran_loan_uuid"]["#default_value"] = "";
            $form["elements"]["lender_confirmation"]["sba_slug"]["#value"] = "";
            $form["elements"]["lender_confirmation"]["sba_slug"]["#default_value"] = "";
            $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = "Successfully deleted request";
            $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = "Successfully deleted request";
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["lender_confirmation"]["sba_response"]["#value"] = $response;
                $form["elements"]["lender_confirmation"]["sba_response"]["#default_value"] = $response;
            }
        }
    }
}