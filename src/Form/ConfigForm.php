<?php

namespace Drupal\nodegrants\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;

/**
 * Configuration form for Nodegrants.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['nodegrants.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nodegrants_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('nodegrants.settings');
    $role_map = $config->get('role_map');

    $form['info'] = [
      '#markup' => '<p>' . $this->t('Nodegrants default grants are configured per content type on each <a href=":url">content type edit page</a>. Role visibility in per-node grants is controlled by the <em>Include in Nodegrants grants</em> permission.', [
        ':url' => '/admin/structure/types',
      ]) . '</p>',
    ];

    $form['rebuild'] = [
      '#type' => 'details',
      '#title' => $this->t('Rebuild role map'),
      '#description' => $this->t('If roles have been added or removed outside of normal operation, you can rebuild the role-to-grant-ID mapping here.'),
      '#open' => FALSE,
    ];

    $form['rebuild']['role_map_display'] = [
      '#type' => 'table',
      '#header' => [$this->t('Role'), $this->t('Grant ID')],
    ];

    $roles = Role::loadMultiple();
    if (!empty($role_map)) {
      foreach ($role_map as $role_id => $gid) {
        $role = $roles[$role_id] ?? NULL;
        $form['rebuild']['role_map_display'][$role_id]['role'] = [
          '#markup' => $role ? $role->label() : $role_id . ' (' . $this->t('missing') . ')',
        ];
        $form['rebuild']['role_map_display'][$role_id]['gid'] = [
          '#markup' => (string) $gid,
        ];
      }
    }

    $form['rebuild']['rebuild_map'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rebuild role map on save'),
      '#default_value' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('rebuild_map')) {
      $config = $this->config('nodegrants.settings');
      $roles = Role::loadMultiple();
      $i = 0;
      $roles_gids = [];
      foreach ($roles as $role_id => $role) {
        $roles_gids[$role_id] = $i;
        $i++;
      }
      $config->set('role_map', $roles_gids);
      $config->save();
      $this->messenger()->addStatus($this->t('The role map has been rebuilt.'));
    }

    parent::submitForm($form, $form_state);
  }

}
