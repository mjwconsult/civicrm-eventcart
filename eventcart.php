<?php

require_once 'eventcart.civix.php';
// phpcs:disable
use CRM_Event_Cart_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function eventcart_civicrm_config(&$config) {
  if (isset(Civi::$statics[__FUNCTION__])) {
    return;
  }
  Civi::$statics[__FUNCTION__] = 1;
  // Since as a hidden extension it's always enabled, until this is a "real" extension you can turn off we need to check the legacy setting.
  if ((bool) Civi::settings()->get('enable_cart')) {
    Civi::dispatcher()->addListener('hook_civicrm_pageRun', 'CRM_Event_Cart_PageCallback::run');
  }

  _eventcart_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function eventcart_civicrm_install() {
  _eventcart_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function eventcart_civicrm_enable() {
  _eventcart_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_navigationMenu().
 */
function eventcart_civicrm_navigationMenu(&$menu) {
  _eventcart_civix_insert_navigation_menu($menu, 'Administer/CiviEvent', array(
    'label' => E::ts('Event Cart Settings'),
    'name' => 'eventcart_settings',
    'url' => 'civicrm/admin/setting/eventcart',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _eventcart_civix_navigationMenu($menu);
}
