<?php

/**
 * Class CRM_Event_Cart_Form_Cart
 */
class CRM_Event_Cart_Form_Cart extends CRM_Core_Form {

  /**
   * @var \CRM_Event_Cart_BAO_Cart
   */
  public $cart;

  public $contact;
  public $event_cart_id = NULL;
  public $participants;

  public function preProcess() {
    $this->_action = CRM_Utils_Request::retrieveValue('action', 'String');
    $this->loadCart();

    $this->checkWaitingList();

    $this->assignBillingType();

    $event_titles = [];
    foreach ($this->cart->get_main_events_in_carts() as $event_in_cart) {
      $event_titles[] = $event_in_cart->event->title;
    }
    $this->description = ts("Online Registration for %1", [1 => implode(", ", $event_titles)]);
    if (!isset($this->discounts)) {
      $this->discounts = [];
    }
  }

  public function loadCart() {
    if ($this->event_cart_id == NULL) {
      $this->cart = CRM_Event_Cart_BAO_Cart::find_or_create_for_current_session();
    }
    else {
      $this->cart = CRM_Event_Cart_BAO_Cart::find_by_id($this->event_cart_id);
    }
    $this->cart->load_associations();
    $this->stub_out_and_inherit();
  }

  public function stub_out_and_inherit() {
    $transaction = new CRM_Core_Transaction();

    /** @var CRM_Event_Cart_BAO_EventInCart $event_in_cart */
    foreach ($this->cart->get_main_events_in_carts() as $event_in_cart) {
      if (empty($event_in_cart->participants)) {
        $event_in_cart->load_associations();
      }
      $event_in_cart->save();
    }
    $transaction->commit();
  }

  public function checkWaitingList() {
    foreach ($this->cart->events_in_carts as $event_in_cart) {
      $empty_seats = $this->checkEventCapacity($event_in_cart->event_id);
      if ($empty_seats === NULL) {
        continue;
      }
      foreach ($event_in_cart->participants as $participant) {
        $participant->must_wait = ($empty_seats <= 0);
        $empty_seats--;
      }
    }
  }

  /**
   * @param int $event_id
   *
   * @return bool|int|null|string
   */
  public function checkEventCapacity($event_id) {
    $empty_seats = CRM_Event_BAO_Participant::eventFull($event_id, TRUE);
    if (is_numeric($empty_seats)) {
      return $empty_seats;
    }
    if (is_string($empty_seats)) {
      return 0;
    }
    else {
      return NULL;
    }
  }

  /**
   * @param $fields
   *
   * @return mixed|null
   */
  public static function find_contact($fields) {
    return CRM_Contact_BAO_Contact::getFirstDuplicateContact($fields, 'Individual', 'Unsupervised', [], FALSE);
  }

  /**
   * @param array $fields
   * @param int $contactID
   *
   * @return int|mixed|null
   */
  public static function find_or_create_contact($fields = [], $contactID = NULL) {
    if (!$contactID) {
      $contactID = self::find_contact($fields);
    }

    $contact_params = [
      'email-Primary' => $fields['email'] ?? NULL,
      'first_name' => $fields['first_name'] ?? NULL,
      'last_name' => $fields['last_name'] ?? NULL,
      'is_deleted' => $contactID ? FALSE : TRUE,
    ];
    $no_fields = [];
    $contactID = CRM_Contact_BAO_Contact::createProfileContact($contact_params, $no_fields, $contactID);
    if (!$contactID) {
      CRM_Core_Session::setStatus(ts("Could not create or match a contact with that email address. Please contact the webmaster."), '', 'error');
    }
    return $contactID;
  }

  /**
   * @param string $page_name
   *
   * @return mixed
   */
  public function getValuesForPage($page_name) {
    $container = $this->controller->container();
    return $container['values'][$page_name];
  }

}
