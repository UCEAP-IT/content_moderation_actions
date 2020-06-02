<?php

/**
 * @file
 * Hooks provided by the Content Moderation actions module.
 */

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\workflows\Entity\Workflow;

/**
 * Alters access to a state_change action plugin access.
 *
 * @param \Drupal\Core\Access\AccessResultInterface $result
 *   The access result.
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The user object to perform the access check operation on.
 * @param \Drupal\Core\Entity\ContentEntityInterface $object
 *   The entity object to perform the access check operation on.
 * @param \Drupal\workflows\Entity\Workflow $workflow
 *   The workflow object to limit the access check operation on.
 */
function hook_cma_state_change_access_alter(AccessResultInterface $result, AccountInterface $account, ContentEntityInterface $object, Workflow $workflow) {

  if ($workflow->id() === "cma_example_action_workflow_test") {
    // Do actions on state_change action plugin access result: $result.
    $result = AccessResult::forbidden('Do use this example as a workflow machine name!');
    $result->addCacheableDependency($object);
  }

}
