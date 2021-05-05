<?php

namespace Drupal\application\Controller;

use GuzzleHttp\Exception\ClientException;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformFormHelper;

use stdClass;

class VerifyController {
    const VERIFY_SANDBOX_HEADER = [
        'Authorization' => 'Token token=api_sandbox_us.CA0CqeDo6Rz._VBaEBEtYbYZduvG0JoeY0cQEwjq3ABB',
        'Content-Type' => 'application/json',
    ];

    const VERIFY_LIVE_HEADER = [
        'Authorization' => 'Token token=api_sandbox_us.CA0CqeDo6Rz._VBaEBEtYbYZduvG0JoeY0cQEwjq3ABB',
        'Content-Type' => 'application/json',
    ];

    const VERIFY_HEADER = self::VERIFY_SANDBOX_HEADER;
    #const VERIFY_HEADER = self::VERIFY_LIVE_HEADER;

    const VERIFY_HOST = 'https://api.us.onfido.com/v3.1/';

    private $elements;
    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct() {
        $this->elements = [];
    }

    public function setElements(array &$form) {
        $this->elements = WebformFormHelper::flattenElements($form);
    }

    public function verifyApplicant(array &$form, FormStateInterface $form_state) {
        $this->setElements($form);

        // If applicant has not existed
        if (empty($this->elements["onfido_applicant_id"]["#default_value"])) {
            $this->createApplicant($form, $form_state);

            $this->uploadPhoto($form, $form_state);

            $this->uploadLivePhoto($form, $form_state);
        }
        $this->checkPhotos($form, $form_state);
    }

    public function createApplicant(array &$form, FormStateInterface $form_state) {
        
        
        try {
            $client = \Drupal::httpClient();
            $headers = self::VERIFY_HEADER;

            $url = self::VERIFY_HOST . "applicants/";
            $request_data = $this->getApplicantData();

            $response = $client->request('POST', $url, [
                'headers' => $headers,
                'body' => $request_data,
            ]);
            $body = json_decode($response->getBody());

            if (!empty($body->{"id"})) {
                $applicant_id = $body->{"id"};
                $entity = $form_state->getFormObject()->getEntity();
                $data = $entity->getData();

                $data["onfido_applicant_id"] = $applicant_id;
                $entity->setData($data);
                $entity->save();
                $this->elements["onfido_applicant_id"]["#value"] = $applicant_id;
                $this->elements["onfido_applicant_id"]["#default_value"] = $applicant_id;
            }

        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $this->elements["verify_result"]["#value"] = $response;
                $this->elements["verify_result"]["#default_value"] = $response;
            }
        }
    }

    public function getApplicantData() {
        $applicant = new stdClass();
        $applicant->first_name = $this->getApplicantName("first_name", 0);
        $applicant->last_name = $this->getApplicantName("last_name", 0);
        $applicant->email = $this->elements["borrower_email"]["#default_value"];
        return json_encode($applicant);
    }

    public function getApplicantName($property_name, $index) {
        if ($index < 0 && empty($property_name)) {
            return "";
        }
        if (empty($this->elements[$property_name])) {
            return "";
        }
        if (count($this->elements[$property_name]) <= $index) {
            return "";
        }
        if (empty($this->elements[$property_name][$index])) {
            return "";
        }

        return $this->elements[$property_name][$index]["#default_value"];
    }

    public function uploadPhoto(array &$form, FormStateInterface $form_state) {
        $this->elements = WebformFormHelper::flattenElements($form);

        $applicant_id = $this->elements["onfido_applicant_id"]["#default_value"];
        if (empty($applicant_id)) {
            return;
        }
        $client = \Drupal::httpClient();
        $header = self::VERIFY_HEADER;
        unset($header["Content-Type"]);
        $url = self::VERIFY_HOST . "documents";
        
        try {
            $file_list = $this->elements["government_issued_id"]["#default_value"];
            $index = 1;
            //$document_type = $this->elements["document_type"]["#default_value"];
            $entity = $form_state->getFormObject()->getEntity();
            $data = $entity->getData();
            foreach ($file_list as $file) {
                $file_id = $file["government_issued_id_file"];
                if (empty($file_id)) {
                    break;
                }
                $file_handle = \Drupal\file\Entity\File::load($file_id);
                $file_uri = $file_handle->getFileUri();
                #$file_name = $file_handle->getFilename();
                $real_path = \Drupal::service('file_system')->realpath($file_uri);

                $response = $client->request('POST', $url, [
                    'headers' => $header,
                    'multipart' => [
                        [
                            "name" => "type",
                            "contents" => 'unknown',
                        ],
                        [
                            "name" => "applicant_id",
                            "contents" => $applicant_id,
                        ],
                        [
                            "name" => "file",
                            "contents" => fopen($real_path, 'r')
                        ]
                    ]
                ]);
                $body = json_decode($response->getBody());
                if (!empty($body->{"id"})) {
                    $document_id = $body->{"id"};
                    $data["onfido_document_id_" . $index] = $document_id;
                    
                    $this->elements["onfido_document_id_" . $index]["#value"] = $document_id;
                    $this->elements["onfido_document_id_" . $index]["#default_value"] = $document_id;
                }
                
                $index++;
            }
            $entity->setData($data);
            $entity->save();
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["verify_id_page"]["verify_result"]["#value"] = $response;
                $form["elements"]["verify_id_page"]["verify_result"]["#default_value"] = $response;
            }
        }
    }

    public function uploadLivePhoto(array &$form, FormStateInterface $form_state) {
        #$this->elements = WebformFormHelper::flattenElements($form);

        $applicant_id = $this->elements["onfido_applicant_id"]["#default_value"];
        if (empty($applicant_id)) {
            return;
        }
        $client = \Drupal::httpClient();
        $header = self::VERIFY_HEADER;
        unset($header["Content-Type"]);
        $url = self::VERIFY_HOST . "live_photos/";
        
        try {
            $file_list = $this->elements["id_selfie"]["#default_value"];
            $entity = $form_state->getFormObject()->getEntity();
            $data = $entity->getData();
            $file_id = 0;
            if (!empty($file_list)) {
                $file_id = $file_list[0];
            }
            $file_handle = \Drupal\file\Entity\File::load($file_id);
            $file_uri = $file_handle->getFileUri();
            $file_name = $file_handle->getFilename();
            $real_path = \Drupal::service('file_system')->realpath($file_uri);

            $response = $client->request('POST', $url, [
                'headers' => $header,
                'multipart' => [
                    [
                        "name" => "applicant_id",
                        "contents" => $applicant_id,
                    ],
                    [
                        "name" => "file",
                        "contents" => fopen($real_path, 'r'),
                    ],
                    [
                        "name" => "advanced_validation",
                        "contents" => "false",
                    ]
                ]
            ]);
            $body = json_decode($response->getBody());
            if (!empty($body->{"id"})) {
                $document_id = $body->{"id"};
                $data["onfido_livephoto_id"] = $document_id;
                
                $this->elements["onfido_livephoto_id"]["#value"] = $document_id;
                $this->elements["onfido_livephoto_id"]["#default_value"] = $document_id;
            }
            
            $entity->setData($data);
            $entity->save();
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["verify_id_page"]["verify_result"]["#value"] = $response;
                $form["elements"]["verify_id_page"]["verify_result"]["#default_value"] = $response;
            }
        }
    }

    public function checkPhotos(array &$form, FormStateInterface $form_state) {
        try {
            $client = \Drupal::httpClient();
            $header = self::VERIFY_HEADER;
            $url = self::VERIFY_HOST . "checks";
            
            $request_data = $this->getCheckData();
            if (empty($request_data)) {
                return;
            }
            $response = $client->request('POST', $url, [
                'headers' => $header,
                'body' => $request_data,
            ]);
            $body = json_decode($response->getBody());
            
            
            if (!empty($body->{"id"})) {
                $applicant_id = $body->{"id"};
                $report_ids = $body->{"report_ids"};
                $entity = $form_state->getFormObject()->getEntity();
                $data = $entity->getData();

                $data["onfido_check_id"] = $applicant_id;
                $report_index = 1;
                foreach ($report_ids as $report_id) {
                    if (!empty($this->elements["onfido_report_id_" . $report_index])) {
                        $data["onfido_report_id_" . $report_index] = $report_id;
                        $this->elements["onfido_report_id_" . $report_index]["#value"] = $report_id;
                        $this->elements["onfido_report_id_" . $report_index]["#default_value"] = $report_id;
                    }
                    $report_index++;
                }
                $entity->setData($data);
                $entity->save();
                $this->elements["onfido_check_id"]["#value"] = $applicant_id;
                $this->elements["onfido_check_id"]["#default_value"] = $applicant_id;
            }

        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $form["elements"]["verify_id_page"]["verify_result"]["#value"] = $response;
                $form["elements"]["verify_id_page"]["verify_result"]["#default_value"] = $response;
            }
        }
    }

    public function getCheckData() {
        $applicant_id = $this->elements["onfido_applicant_id"]["#default_value"];
        if (empty($applicant_id)) {
            return "";
        }

        $check_data = new stdClass();
        $check_data->applicant_id = $applicant_id;
        
        $document_ids = [];
        for ($i = 1; $i <= 2; $i++) {
            if (!empty($this->elements["onfido_document_id_" . $i]["#default_value"])) {
                $document_ids[] = $this->elements["onfido_document_id_" . $i]["#default_value"];
            }
        }
        $check_data->document_ids = $document_ids;
        if (!empty($document_ids)) {
            $check_data->report_names[] = "document";
        }
        $livephoto_id = $this->elements["onfido_livephoto_id"]["#default_value"];
        if (!empty($livephoto_id)) {
            $check_data->report_names[] = "facial_similarity_photo";
        }

        $webhook_ids = [];
        $webhook_ids[] = "19dc8881-9754-4db5-b681-4ef70bf74fc0";
        $check_data->webhook_ids = $webhook_ids;

        return json_encode($check_data);
    }

    public function retrieveReports(array &$form, FormStateInterface $form_state) {
        $this->elements = WebformFormHelper::flattenElements($form);
        if (empty($this->elements["onfido_report_id_1"]["#default_value"])
            && empty($this->elements["onfido_report_id_2"]["#default_value"]))
        {
            return;
        }
        $report_id_1 = $this->elements["onfido_report_id_1"]["#default_value"];
        if (!empty($report_id_1) && empty($this->elements["onfido_report_1"]["#default_value"])) {
            $this->retrieveReport(1, $report_id_1, $form_state);
        } 

        $report_id_2 = $this->elements["onfido_report_id_2"]["#default_value"];
        if (!empty($report_id_2) && empty($this->elements["onfido_report_2"]["#default_value"])) {
            $this->retrieveReport(2, $report_id_2, $form_state);
        }
    }

    public function retrieveReport($index, $report_id, FormStateInterface $form_state) {
        try {
            $client = \Drupal::httpClient();
            $header = self::VERIFY_HEADER;
            $url = self::VERIFY_HOST . "reports/" . $report_id;
            
            $response = $client->request('GET', $url, [
                'headers' => $header,
            ]);
            $body = json_decode($response->getBody());
            
            if (!empty($body->{"result"})) {
                $name = $body->{"name"};
                $result_message = "Report Name: " . $name;
                $result = $body->{"result"};
                $sub_result = $body->{"sub_result"};
                $result_message .= " - Result: " . $result;
                if (!empty($sub_result)) {
                    $result_message .= " - Sub Result: " . $sub_result;
                    if ($sub_result != "clear") {
                        $data["borrower_envelope_status"] = "99999";
                        $data["loan_status"] = "Declined";
                        $this->elements["borrower_envelope_status"]["#value"] = "99999";
                        $this->elements["borrower_envelope_status"]["#default_value"] = "99999";
                        $this->elements["loan_status"]["#value"] = "Declined";
                        $this->elements["loan_status"]["#default_value"] = "Declined";
                    }
                } 
                $entity = $form_state->getFormObject()->getEntity();
                $data = $entity->getData();
                $data["onfido_report_result_" . $index] = $result;
                $data["onfido_report_" . $index] = $result_message;
                $entity->setData($data);
                $entity->save();
                $this->elements["onfido_report_result_" . $index]["#value"] = $result;
                $this->elements["onfido_report_result_" . $index]["#default_value"] = $result;
                $this->elements["onfido_report_" . $index]["#value"] = $result_message;
                $this->elements["onfido_report_" . $index]["#default_value"] = $result_message;
            }

        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $this->elements["verify_result"]["#value"] = $response;
                $this->elements["verify_result"]["#default_value"] = $response;
            }
        }
    }

    

}