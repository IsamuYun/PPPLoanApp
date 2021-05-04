<?php

namespace Drupal\application\Controller;

use Drupal\Core\Controller\ControllerBase;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use GuzzleHttp\Exception\ClientException;

class OnfidoListener extends ControllerBase {
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

    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct() {    
    }

    /**
    * Enable or disable debugging.
    *
    * @var bool
    */
    protected $debug = FALSE;
    
    /**
    * Capture the payload.
    *
    * @return Symfony\Component\HttpFoundation\Response
    *   A simple string and 200 response.
    */
    public function capture(Request $request) {
        // Keep things fast.
        // Don't load a themed site for the response.
        // Most Webhook providers just want a 200 response.
        $response = new Response();

        // Capture the payload.
        // Option 2: $payload = file_get_contents("php://input");.
        $payload = $request->getContent();

        // Check if it is empty.
        if (empty($payload)) {
            $message = 'The payload was empty.';
            \Drupal::logger("OnfidoWebhook")->notice($message);
            $response->setContent($message);
            return $response;
        }
        #\Drupal::logger("OnfidoWebhook")->notice("Payload: " . $payload);
        $event_result = json_decode($payload);
        $report_id = "";
        $report_url = "";
        if (!empty($event_result->{"object"})) {
            $report_obj = $event_result->{"object"};
            if (!empty($report_obj->{"id"})) {
                $report_id = $report_obj->{"id"};
                $report_url = $report_obj->{"href"};
            }
        }
        \Drupal::logger("OnfidoWebhook")->notice("Report ID: " . $report_id);
        $this->updateReportResult($report_id, $report_url);

        return $response;
    }

    private function updateReportResult($report_id, $report_url) {
        if (empty($report_id) || empty($report_url)) {
            return false;
        }

        $database = \Drupal::database();
        $query = $database->select("webform_submission_data", "wsd");
        $query->condition("wsd.webform_id", "apply_for_ppp_loan", '=');
        $query->condition("wsd.value", $report_id, '=');
        $query->addField("wsd", "sid");
        $query->addField("wsd", "name");

        $result = $query->execute()->fetchAll();
        if (empty($result)) {
            return false;
        }
        $sid = $result[0]->sid;
        $name = $result[0]->name;

        $index = intval(substr($name, strlen($name) - 2));
        \Drupal::logger("OnfidoWebhook")->notice("Submission ID: " . $sid . ", Index: " . $index);
        if ($this->retrieveReport($report_id, $report_url, $index, $sid)) {
            return true;
        } 
        else {
            return false;
        }
    }

    private function retrieveReport($report_id, $report_url, $index, $sid) {
        try {
            $client = \Drupal::httpClient();
            $header = self::VERIFY_HEADER;
            
            $response = $client->request('GET', $report_url, [
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
                } 
                
                \Drupal::logger("OnfidoWebhook")->notice("Submission ID: " . $sid . ", Index: " . $index . ", Report Result = ". $result_message);

                $update_query = \Drupal::database()->update('webform_submission_data');
                $update_query->fields([
                    'value' => $result_message,
                ]);
                $update_query->condition("sid", $sid);
                $update_query->condition("name", "onfido_report_" . $index);
                $update_result = $update_query->execute();
                if ($update_result > 0) {

                }

                $update_query = \Drupal::database()->update('webform_submission_data');
                $update_query->fields([
                    'value' => $result,
                ]);
                $update_query->condition("sid", $sid);
                $update_query->condition("name", "onfido_report_result_" . $index);
                $update_result = $update_query->execute();

                return true;
            }
            return false;
        }
        catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                $update_query = \Drupal::database()->update('webform_submission_data');
                $update_query->fields([
                    'value' => $response,
                ]);
                $update_query->condition("sid", $sid);
                $update_query->condition("name", "verify_result");
                $update_query->execute();
            }
            return false;
        }
    }
}