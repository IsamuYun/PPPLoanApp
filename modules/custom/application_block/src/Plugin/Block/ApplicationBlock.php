<?php

namespace Drupal\application_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\user\Entity\User;

/**
 * Provides a block with three options.
 * 
 * @Block(
 *   id = "application_block",
 *   admin_label = @Translation("Application Option Block"),
 * )
 */

class ApplicationBlock extends BlockBase {

    /**
     * {@inheritDoc}
     */
    public function build() {
        // Load the current user
        $user = User::load(\Drupal::currentUser()->id());

        if ($user == null) {
            return [
                "#type" => "markup",
                "#markup" => "Can't get user information",
            ];
        }

        $email = $user->get('mail')->value;
        $name = $user->get('name')->value;
        $uid = $user->get('uid')->value;

        $sids = $this->get_webform_submission_by_user_id("apply_for_ppp_loan", $uid);

        $sid_content = "";

        if (is_array($sids)) {
            foreach ($sids as $sid) {
                $sid_content .= ", " . $sid['sid'];
            }
        }

        return [
            "#type" => "markup",
            "#markup" => "Email: " . $email . "<br/>Name: " . $name . "<br/>UID: " . $uid . "<br />SID: " . $sid_content,
        ];
    }

    public function get_webform_submission_by_data_key($web_form_id, $data_key, $value):array {
        $database = \Drupal::service("database");
        $select = $database->select("webform_submission_data", "wsd")
            ->fields("wsd", array("sid"))
            ->condition("wsd.webform_id", $web_form_id, '=')
            ->condition("wsd.name", $data_key, '=')
            ->condition("wsd.value", $value, '=');
        $executed = $select->execute();
        // Get all the results.
        $results = $executed->fetchAll(\PDO::FETCH_ASSOC);
        if (count($results) == 1) {
            $results = reset($results);
        }
        return $results;
    }

    public function get_webform_submission_by_user_id($web_form_id, $user_id):array {
        $database = \Drupal::service("database");
        $select = $database->select("webform_submission", "ws")
            ->fields("ws", array("sid"))
            ->condition("ws.webform_id", $web_form_id, '=')
            ->condition("ws.uid", $user_id, '=');
        $executed = $select->execute();
        // Get all the results.
        $results = $executed->fetchAll(\PDO::FETCH_ASSOC);
        if (count($results) == 1) {
            $results = reset($results);
        }
        return $results;
    }






}