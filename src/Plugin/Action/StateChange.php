<?php

namespace Drupal\content_moderation_actions\Plugin\Action;

use Drupal\Component\Datetime\Time;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Changes moderation_state of an entity.
 *
 * @Action(
 *   id = "state_change",
 *   deriver = "\Drupal\content_moderation_actions\Plugin\Deriver\StateChangeDeriver"
 * )
 */
class StateChange extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use DependencyTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The moderation info service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The logger channel used for logging messages.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Drupal time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Moderation state change constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation info service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\Time $time
   *   The Drupal time service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModerationInformationInterface $moderation_info, LoggerChannelFactoryInterface $loggerFactory, EntityTypeManagerInterface $entityTypeManager, Time $time, AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moderationInfo = $moderation_info;
    $this->logger = $loggerFactory->get('content_moderation_actions');
    $this->entityTypeManager = $entityTypeManager;
    $this->time = $time;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('content_moderation.moderation_information'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'workflow' => NULL,
      'state' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $workflow_options = [];
    $workflows = Workflow::loadMultipleByType('content_moderation');

    foreach ($workflows as $workflow) {
      if (in_array($this->pluginDefinition['type'], $workflow->getTypePlugin()->getEntityTypes(), TRUE)) {
        $workflow_options[$workflow->id()] = $workflow->label();
      }
    }

    if (!$default_workflow = $form_state->getValue('workflow')) {
      if (!empty($this->configuration['workflow'])) {
        $default_workflow = $this->configuration['workflow'];
      }
      else {
        $default_workflow = key($workflow_options);
      }
    }

    $form['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow'),
      '#options' => $workflow_options,
      '#default_value' => $default_workflow,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [static::class, 'configurationFormAjax'],
        'wrapper' => 'edit-state-wrapper',
      ],
    ];

    $form['workflow_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Change workflow'),
      '#limit_validation_errors' => [['workflow']],
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#submit' => [[static::class, 'configurationFormAjaxSubmit']],
    ];
    // Only show state select when we have workflow attached to the entity type
    // and when we have a valid workflow selected.
    if (!empty($workflow_options) && $default_workflow) {
      $state_options = [];
      foreach ($workflows[$default_workflow]->getTypePlugin()->getStates() as $state) {
        $state_options[$state->id()] = $this->t('Change moderation state to @state', ['@state' => $state->label()]);
      }

      $form['state-wrapper'] = [
        '#type' => 'container',
        '#id' => 'edit-state-wrapper',
      ];

      $form['state-wrapper']['state'] = [
        '#type' => 'select',
        '#title' => $this->t('State'),
        '#options' => $state_options,
        '#default_value' => $this->configuration['state'],
        '#required' => TRUE,
      ];
    }

    return $form;

  }

  /**
   * Ajax callback for the configuration form.
   *
   * @see static::buildConfigurationForm()
   */
  public static function configurationFormAjax($form, FormStateInterface $form_state) {
    return $form['state-wrapper'];
  }

  /**
   * Submit configuration for the non-JS case.
   *
   * @see static::buildConfigurationForm()
   */
  public static function configurationFormAjaxSubmit($form, FormStateInterface $form_state) {
    // Rebuild the form.
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['workflow'] = $form_state->getValue('workflow');
    $this->configuration['state'] = $form_state->getValue('state');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    if (!empty($this->configuration['workflow'])) {
      $this->addDependency('config', 'workflows.workflow.' . $this->configuration['workflow']);
    }
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    // Create a new revision if entity is revisionable to fix some edge cases
    // with performing moderation states which publishes the entity.
    if ($entity->getEntityType()->isRevisionable()) {
      $entity = $this->loadLatestRevision($entity);
      $entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())
        ->createRevision($entity, $entity->isDefaultRevision());
      if ($entity instanceof RevisionLogInterface) {
        $entity->setRevisionCreationTime($this->time->getRequestTime());
        $entity->setRevisionUserId($this->currentUser->id());
      }
    }
    // Set changed time of the entity when finalize it.
    if ($entity instanceof EntityChangedInterface) {
      $entity->setChangedTime($this->time->getRequestTime());
    }
    $entity->moderation_state->value = $this->configuration['state'];
    $entity->save();
  }

  /**
   * Loads the latest revision of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The latest revision of content entity.
   */
  protected function loadLatestRevision(ContentEntityInterface $entity) {
    return $this->moderationInfo->getLatestRevision($entity->getEntityTypeId(), $entity->id());
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object || !$object instanceof ContentEntityInterface) {
      $result = AccessResult::forbidden();
      $this->logger->error('Tried to change moderation state on non content entity');
      return $return_as_object ? $result : $result->isAllowed();
    }
    if ($workflow = $this->moderationInfo->getWorkflowForEntity($object)) {
      if ($workflow->id() !== $this->configuration['workflow']) {
        $result = AccessResult::forbidden($this->getAccessErrorMessage('It is not possible to apply selected transition', $object));
        $this->logger->error('Tried to change moderation state on an entity which doesn\'t use the selected workflow.');
        $result->addCacheableDependency($workflow);
        return $return_as_object ? $result : $result->isAllowed();
      }
    }
    else {
      $result = AccessResult::forbidden($this->getAccessErrorMessage('It is not possible to apply selected transition', $object));
      $this->logger->error('Tried to change moderation state on an entity which doesn\'t use workflows.');
      return $return_as_object ? $result : $result->isAllowed();
    }
    $object = $this->loadLatestRevision($object);
    // Let content moderation do its job. See content_moderation_entity_access()
    // for more details.
    $access = $object->access('update', $account, TRUE);

    $to_state_id = $this->configuration['state'];
    $from_state = $workflow->getTypePlugin()->getState($object->moderation_state->value);
    // Make sure we can make the transition.
    if ($from_state->canTransitionTo($to_state_id)) {
      $transition = $from_state->getTransitionTo($to_state_id);
      $result = AccessResult::allowedIfHasPermission($account, 'use ' . $workflow->id() . ' transition ' . $transition->id())
        ->andIf($access);
    }
    else {
      $result = AccessResult::forbidden($this->getAccessErrorMessage('It is not possible to apply selected transition', $object));
      $this->logger->error('Tried to change moderation state on an entity which doesn\'t use have the selected transition: From @from to @to.', [
        '@from' => $object->moderation_state->value,
        '@to' => $to_state_id,
      ]);
    }
    // Allow modules to alter state_change action access result.
    $data = [
      'object' => $object,
      'account' => $account,
      'workflow' => $workflow,
      'from_state' => $from_state,
      'to_state' => $to_state_id,
    ];
    \Drupal::moduleHandler()->alter('cma_state_change_access', $result, $data);
    $result->addCacheableDependency($workflow);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Generates an access error message based on a given reason.
   *
   * @param string $reason
   *   The error reason.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to generate the error message for.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Error message Translatable Markup.
   */
  protected function getAccessErrorMessage($reason, $entity) {
    return (string) $this->t('@reason for @entity_label.', [
      '@reason' => $reason,
      '@entity_label' => $entity->label(),
    ]);
  }

}
