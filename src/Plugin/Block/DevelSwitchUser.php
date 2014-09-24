<?php

/**
* @file
* Contains \Drupal\devel\Plugin\Block\DevelSwitchUser.
*/

namespace Drupal\devel\Plugin\Block;

use Drupal\Component\Utility\String;
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
//        $form_state = array();
//        $build_info = array(
//          'args' => array(),
//          'callback' => array($this, 'switchForm'),
//        );
//        $form_state->setBuildInfo($build_info);
        $build['devel_form'] = \Drupal::formBuilder()->getForm('\Drupal\devel\Form\SwitchUserForm');
      }
      return $build;
    }
  }

  /**
   * Provides the Switch user list.
   */
  public function switchUserList() {
    $list_size = $this->configuration['list_size'];
    $include_anon = $this->configuration['include_anon'];
    $anon = new AnonymousUserSession();
    $links = array();
    if (\Drupal::currentUser()->hasPermission('switch users')) {
      if ($include_anon) {
        --$list_size;
      }
      $dest = drupal_get_destination();
      // Try to find at least $list_size users that can switch.
      // Inactive users are omitted from all of the following db selects.
      $roles = user_roles(TRUE, 'switch users');
      $query = db_select('users', 'u');
      $query->join('users_field_data', 'ufd');
      $query->addField('u', 'uid');
      $query->addField('ufd', 'access');
      $query->distinct();
      $query->condition('u.uid', 0, '>');
      $query->condition('ufd.status', 0, '>');
      $query->orderBy('ufd.access', 'DESC');
      $query->range(0, $list_size);

      if (!isset($roles[DRUPAL_AUTHENTICATED_RID])) {
        $query->leftJoin('users_roles', 'r', 'u.uid = r.uid');
        $or_condition = db_or();
        $or_condition->condition('u.uid', 1);
        if (!empty($roles)) {
          $or_condition->condition('r.rid', array_keys($roles), 'IN');
        }
        $query->condition($or_condition);
      }

      $uids = $query->execute()->fetchCol();
      $accounts = user_load_multiple($uids);

      foreach ($accounts as $account) {
        $path = 'devel/switch/' . $account->name->value;
        $links[$account->id()] = array(
          'title' => String::placeholder(user_format_name($account)),
          'href' => $path,
          'query' => $dest + array('token' => \Drupal::csrfToken()->get($path)),
          'attributes' => array('title' => t('This user can switch back.')),
          'html' => TRUE,
          'last_access' => $account->access->value,
        );
      }
      $num_links = count($links);
      if ($num_links < $list_size) {
        // If we don't have enough, add distinct uids until we hit $list_size.
        $uids = db_query_range('SELECT u.uid FROM {users} u INNER JOIN {users_field_data} ufd WHERE u.uid > 0 AND u.uid NOT IN (:uids) AND ufd.status > 0 ORDER BY ufd.access DESC', 0, $list_size - $num_links, array(':uids' => array_keys($links)))->fetchCol();
        $accounts = user_load_multiple($uids);
        foreach ($accounts as $account) {
          $path = 'devel/switch/' . $account->name->value;
          $links[$account->id()] = array(
            'title' => user_format_name($account),
            'href' => $path,
            'query' => $dest + array('token' => \Drupal::csrfToken()->get($path)),
            'attributes' => array('title' => t('Caution: this user will be unable to switch back.')),
            'last_access' => $account->access->value,
          );
        }
        uasort($links, '_devel_switch_user_list_cmp');
      }
      if ($include_anon) {
        $path = 'devel/switch';
        $link = array(
          'title' => $anon->getUsername(),
          'href' => $path,
          'query' => $dest + array('token' => \Drupal::csrfToken()->get($path)),
          'attributes' => array('title' => t('Caution: the anonymous user will be unable to switch back.')),
        );
        if (\Drupal::currentUser()->hasPermission('switch users')) {
          $link['title'] = String::placeholder($link['title']);
          $link['attributes'] = array('title' => t('This user can switch back.'));
          $link['html'] = TRUE;
        }
        $links[] = $link;
      }
    }
    if (array_key_exists($uid = \Drupal::currentUser()->id(), $links)) {
      $links[$uid]['title'] = '<strong>' . $links[$uid]['title'] . '</strong>';
    }
    return $links;
  }
}
