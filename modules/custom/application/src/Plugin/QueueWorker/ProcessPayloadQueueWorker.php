<?php

namespace Drupal\application\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Component\Serialization\Json;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process the JSON payload provided by the webhook.
 *
 * @QueueWorker(
 *   id = "process_payload_queue_worker",
 *   title = @Translation("Docusign Listener Process Payload Queue Worker"),
 *   cron = {"time" = 5}
 * )
 */
class ProcessPayloadQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
    /**
    * Drupal\Core\Entity\EntityTypeManager definition.
    *
    * @var \Drupal\Core\Entity\EntityTypeManager
    */
    protected $entityTypeManager;

    /**
    * Constructor.
    *
    * @param array $configuration
    * @param string $plugin_id
    * @param mixed $plugin_definition
    * @param \Drupal\Core\Entity\EntityTypeManager $entity_field_manager
    */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->entityTypeManager = $entity_type_manager;
    }

    /**
    * Implementation of the container interface to allow dependency injection.
    *
    * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
    * @param array $configuration
    * @param string $plugin_id
    * @param mixed $plugin_definition
    *
    * @return static
    */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            empty($configuration) ? [] : $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager')
        );
    }

    /**
    * {@inheritdoc}
    */
    public function processItem($data) {
        $EnvelopeID = "";
        if (isset($data["EnvelopeStatus"]) && isset($data["EnvelopeStatus"]["EnvelopeID"])) {
            $EnvelopeID = $data["EnvelopeStatus"]["EnvelopeID"];
        }

        $database = \Drupal::database();
        $query = $database->select("webform_submission_data", "wsd");
        $query->condition("wsd.name", "envelope_id", '=');
        $query->condition("wsd.value", $EnvelopeID, '=');
        $query->addField("wsd", "sid");

        $result = $query->execute()->fetchAll();
        $sid = 0;
        if (!empty($result)) {
            $sid = $result[0]->sid;
        }
        else {
            return;
        }

        \Drupal::logger("ProcessPayloadQueueWorker")->notice("Submission ID: " . $sid . ", Envelope ID: " . $EnvelopeID);

        $update_query = \Drupal::database()->update('webform_submission_data');
        $update_query->fields([
            'value' => "completed"
        ]);
        $update_query->condition("sid", $sid);
        $update_query->condition("name", "envelope_status");
        $update_query->execute();
        \Drupal::logger("ProcessPayloadQueueWorker")->notice("Submission ID: " . $sid . " Envelope status has been updated.");


        // Decode the JSON that was captured.
        // $decode = Json::decode($data);

        // Pull out applicable values.
        // You may want to do more validation!
        //$nodeValues = [
        //    'type' => 'machine_name_here',
        //    'status' => 1,
        //    'title' => $decode['title'],
        //    'field_custom_field' => $decode['something'],
        //];

        // Create a node.
        //$storage = $this->entityTypeManager->getStorage('node');
        //$node = $storage->create($nodeValues);
        //$node->save();
    }

}