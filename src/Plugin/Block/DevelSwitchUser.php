<?php

/**
* @file
* Contains \Drupal\devel\Plugin\Block\DevelSwitchUser.
*/

namespace Drupal\devel\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Tests\Core\Session\AnonymousUserSessionTest;

/**
 * Provides a block for switching users.
 *
 * @Block(
 *   id = "devel_switch_user",
 *   admin_label = @Translation("Switch user"),
 *   category = @Translation("Forms")
 * )
 */
class DevelSwitchUser extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // By default, the block will contain 12 users.
    return array(
      'list_size' => 12,
      'include_anon' => TRUE,
      'show_form' => TRUE,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $anon = new AnonymousUserSession();
    $form['list_size'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of users to display in the list'),
      '#default_value' => $this->configuration['list_size'],
      '#size' => '3',
      '#maxlength' => '4',
    );
    $form['include_anon'] = array(
      '#type' => 'checkbox',
      '#title' => t('Include %anonymous', array('%anonymous' => $anon->getUsername())),
      '#default_value' => $this->configuration['include_anon'],
    );
    $form['show_form'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow entering any user name'),
      '#default_value' => $this->configuration['show_form'],
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['list_size'] = $form_state->getValue('list_size');
    $this->configuration['include_anon'] = $form_state->getValue('include_anon');
    $this->configuration['show_form'] = $form_state->getValue('show_form');
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return $account->hasPermission('switch users');
  }

  /**
   * {@inheritdoc}
   */
  public function isCacheable() {
    // Perhaps this can be improved. Low priority.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $links = $this->switchUserList();
    if (!empty($links)) {
      $build = array(
        'devel_links' => array('#theme' => 'links', '#links' => $links),
        '#attached' => array(
          'css' => array(
            drupal_get_path('module', 'devel') . '/css/devel.css')
          )
      );
      if ($this->configuration['show_form']) {
        $form_state = array();
        $build_info = array(
          'args' => array(),
          'callback' => array($this, 'switchForm'),
        );
        $form_state->setBuildInfo($build_info);
        $build['devel_form'] = drupal_build_form('devel_switch_user_form', $form_state);
      }
      return $build;
    }
  }

  /**
   * Provides the Switch user form.
   */
  public function switchForm() {
    $form['username'] = array(
      '#type' => 'textfield',
      '#description' => t('Enter username'),
      '#autocomplete_path' => 'user/autocomplete',
      '#maxlength' => USERNAME_MAX_LENGTH,
      '#size' => 16,
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Switch'),
      '#button_type' => 'primary',
    );
    $form['#attributes'] = array('class' => array('clearfix'));
    return $form;
  }

  /**
   * Provides the Switch user list.
   */
  public function switchUserList() {
    return devel_switch_user_list($this->configuration['list_size'], $this->configuration['include_anon']);
  }

}
