<?php

namespace Drupal\content_moderation_actions\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The moderation_state change deriver.
 */
class StateChangeDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Moderation state change deriver constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(TranslationInterface $string_translation, ModerationInformationInterface $moderation_information, EntityTypeManagerInterface $entity_type_manager) {
    $this->stringTranslation = $string_translation;
    $this->moderationInfo = $moderation_information;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('string_translation'),
      $container->get('content_moderation.moderation_information'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Reset the discovered definitions.
    $this->derivatives = [];
    $entity_types = [];
    $workflows = Workflow::loadMultipleByType('content_moderation');
    // Collect all the entity types ID which has workflow attached to them.
    foreach ($workflows as $workflow) {
      $entity_types = array_unique(array_merge($entity_types, $workflow->getTypePlugin()->getEntityTypes()));
    }
    // Create the derivatives for each entity.
    foreach ($entity_types as $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $plugin['type'] = $entity_type_id;
      $plugin['label'] = $this->t('Change Status of @entity_type', ['@entity_type' => $entity_type->getLabel()]);
      $plugin['config_dependencies']['module'] = [
        $entity_type->getProvider(),
      ];
      $this->derivatives[$entity_type_id] = $plugin + $base_plugin_definition;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
