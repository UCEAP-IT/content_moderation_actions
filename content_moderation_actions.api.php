<?php

/**
 * @file
 * Hooks provided by the Content Moderation actions module.
 */

use Drupal\Core\Access\AccessResultInterface;

/**
 * Alters access to a state_change action plugin access.
 *
 * @param \Drupal\Core\Access\AccessResultInterface $result
 *   The access result.
 * @param array $data
 *   (optional) An additional variable that is passed by reference.
 *
 * @option \Drupal\Core\Session\AccountInterface 'account'
 *   The user object to perform the access check operation on.
 * @option \Drupal\Core\Entity\ContentEntityInterface  "object"
 *   The entity object to perform the access check operation on.
 * @option \Drupal\workflows\Entity\Workflow "workflow"
 *   The workflow object to limit the access check operation on.
 * @option string "from_state"
 *   The workflow state the object is attempting to transition from.
 * @option string  "to_state"
 *   The workflow state the object is attempting to transition to.
 */
function hook_cma_state_change_access_alter(AccessResultInterface &$result, array $data = []) {

  if ($data['workflow']->id() === "cma_example_action_workflow_test") {
    // Do actions on state_change action plugin access result: $result.
    $result = AccessResult::forbidden('Do use this example as a workflow machine name!');
    $result->addCacheableDependency($data['object']);
  }

}
