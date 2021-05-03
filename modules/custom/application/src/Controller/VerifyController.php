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

    public function createApplicant(array &$form, FormStateInterface $form_state) {
        $this->elements = WebformFormHelper::flattenElements($form);
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
                $form["elements"]["verify_id_page"]["verify_result"]["#value"] = $response;
                $form["elements"]["verify_id_page"]["verify_result"]["#default_value"] = $response;
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
            $document_type = $this->elements["document_type"]["#default_value"];
            $entity = $form_state->getFormObject()->getEntity();
            $data = $entity->getData();
            foreach ($file_list as $file) {
                $file_id = $file["government_issued_id_file"];
                if (empty($file_id)) {
                    break;
                }
                $file_handle = \Drupal\file\Entity\File::load($file_id);
                $file_uri = $file_handle->getFileUri();
                $file_name = $file_handle->getFilename();
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
        $this->elements = WebformFormHelper::flattenElements($form);

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
        $this->elements = WebformFormHelper::flattenElements($form);
        
        try {
            $client = \Drupal::httpClient();
            $header = self::VERIFY_HEADER;
            $url = self::VERIFY_HOST . "checks";
            
            $request_data = $this->getCheckData();

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
                foreach ($report_ids as $report_id) {
                    $data["report_id"][] = $report_id;
                    $this->elements["report_id"]["#value"][] = $report_id;
                    $this->elements["report_id"]["#default_value"][] = $report_id;
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
        
        $check_data = new stdClass();
        $check_data->applicant_id = $applicant_id;
        $check_data->report_names = [
                                        "document",
                                        "facial_similarity_photo",
        ];
        $document_ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $document_id = $this->elements["onfido_document_id_" . $i]["#default_value"];
            if (!empty($document_id)) {
                $document_ids[] = $document_id;
            }
        }
        #$check_data->document_ids = $document_ids;

        $webhook_ids = [];
        $webhook_ids[] = "19dc8881-9754-4db5-b681-4ef70bf74fc0";
        $check_data->webhook_ids = $webhook_ids;

        return json_encode($check_data);
    }

    

}