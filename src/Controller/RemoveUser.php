<?php

namespace Drupal\nodegrants\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * A method of removing a user's assigned permissions from a piece of
 * content. No confirmation dialogue yet. That is on the list.
 */
class RemoveUser extends ControllerBase {

  /**
   * Handler for permission by account removal.
   */
  public function handleRemoval($nid, $uid) {
    $db = \Drupal::database();

    $db->delete('nodegrants')
      ->condition('nid', $nid)
      ->condition('gid', $uid)
      ->condition('realm', 'nodegrants_uid')
      ->execute();

    $db->delete('node_access')
      ->condition('nid', $nid)
      ->condition('gid', $uid)
      ->condition('realm', 'nodegrants_uid')
      ->execute();

    \Drupal::messenger()->addMessage(t('The selected user was removed. No other data was saved.'));
    return new RedirectResponse('/node/' . $nid . '/edit'); 
  }
}
