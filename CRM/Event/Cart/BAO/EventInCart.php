<?php

use CRM_Event_Cart_ExtensionUtil as E;

/**
 * Class CRM_Event_Cart_BAO_EventInCart
 */
class CRM_Event_Cart_BAO_EventInCart extends CRM_Event_Cart_DAO_EventInCart implements ArrayAccess {
  public $assocations_loaded = FALSE;
  public $event;
  public $event_cart;
  public $location = NULL;

  /**
   * Array of CRM_Event_Cart_BAO_MerParticipant indexed by participant ID
   *
   * @var array
   */
  public $participants = [];

  /**
   * Class constructor.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Add participant to cart.
   *
   * @param array $participantParams
   */
  public function add_participant($participantParams) {
    $participantParams['cart_id'] = $participantParams['cart_id'] ?? $this->event_cart_id;
    $participantParams['event_id'] = $participantParams['event_id'] ?? $this->event_id;

    $merParticipantObject = CRM_Event_Cart_BAO_MerParticipant::create($participantParams);
    $this->participants[$merParticipantObject->id] = $merParticipantObject;
  }

  /**
   * @param array $params
   *
   * @return object $this|CRM_Event_Cart_BAO_EventInCart
   * @throws Exception
   */
  public static function create(&$params) {
    $transaction = new CRM_Core_Transaction();
    $event_in_cart = new CRM_Event_Cart_BAO_EventInCart();
    $event_in_cart->copyValues($params);
    $event_in_cart = $event_in_cart->save();

    if (is_a($event_in_cart, 'CRM_Core_Error')) {
      $transaction->rollback();
      throw new CRM_Core_Exception(ts('There was an error creating an event_in_cart'));
    }

    $transaction->commit();

    return $event_in_cart;
  }

  /**
   * @param bool $useWhere
   *
   * @return mixed|void
   */
  public function delete($useWhere = FALSE) {
    $this->load_associations();
    $contacts_to_delete = [];
    foreach ($this->participants as $participant) {
      $defaults = [];
      $params = ['id' => $participant->contact_id];
      $temporary_contact = CRM_Contact_BAO_Contact::retrieve($params, $defaults);

      if ($temporary_contact->is_deleted) {
        $contacts_to_delete[$temporary_contact->id] = 1;
      }
      $participant->delete();
    }
    foreach (array_keys($contacts_to_delete) as $contact_id) {
      CRM_Contact_BAO_Contact::deleteContact($contact_id);
    }
    return parent::delete();
  }

  /**
   * @param int $event_cart_id
   *
   * @return array
   */
  public static function find_all_by_event_cart_id($event_cart_id) {
    return self::find_all_by_params(['event_cart_id' => $event_cart_id]);
  }

  /**
   * @param array $params
   *
   * @return array
   */
  public static function find_all_by_params($params) {
    $event_in_cart = new CRM_Event_Cart_BAO_EventInCart();
    $event_in_cart->copyValues($params);
    $result = [];
    if ($event_in_cart->find()) {
      while ($event_in_cart->fetch()) {
        $result[$event_in_cart->event_id] = clone($event_in_cart);
      }
    }
    return $result;
  }

  /**
   * @param int $id
   *
   * @return bool|CRM_Event_Cart_BAO_EventInCart
   */
  public static function find_by_id($id) {
    return self::find_by_params(['id' => $id]);
  }

  /**
   * @param array $params
   *
   * @return bool|CRM_Event_Cart_BAO_EventInCart
   */
  public static function find_by_params($params) {
    $event_in_cart = new CRM_Event_Cart_BAO_EventInCart();
    $event_in_cart->copyValues($params);
    if ($event_in_cart->find(TRUE)) {
      return $event_in_cart;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @param int $contact_id
   */
  public function remove_participant_by_contact_id($contact_id) {
    $to_remove = [];
    foreach ($this->participants as $participant) {
      if ($participant->contact_id == $contact_id) {
        $to_remove[$participant->id] = 1;
        $participant->delete();
      }
    }
    $this->participants = array_diff_key($this->participants, $to_remove);
  }

  /**
   * @param int $participant_id
   *
   * @return mixed
   */
  public function get_participant_by_id($participant_id) {
    return $this->participants[$participant_id];
  }

  /**
   * @param int $participant_id
   */
  public function remove_participant_by_id($participant_id) {
    $this->get_participant_by_id($participant_id)->delete();
    unset($this->participants[$participant_id]);
  }

  /**
   * @param $participant
   *
   * @return mixed
   */
  public static function part_key($participant) {
    return $participant->id;
  }

  /**
   * @param CRM_Event_Cart_BAO_Cart $event_cart
   */
  public function load_associations($event_cart = NULL) {
    if ($this->assocations_loaded) {
      return;
    }
    $this->assocations_loaded = TRUE;
    $params = ['id' => $this->event_id];
    $defaults = [];
    $this->event = CRM_Event_BAO_Event::retrieve($params, $defaults);

    if ($event_cart !== NULL) {
      $this->event_cart = $event_cart;
      $this->event_cart_id = $event_cart->id;
    }
    else {
      $this->event_cart = CRM_Event_Cart_BAO_Cart::find_by_id($this->event_cart_id);
    }

    $this->participants = CRM_Event_Cart_BAO_MerParticipant::find_all_by_event_and_cart_id($this->event_id, $this->event_cart->id);
    if (empty($this->participants)) {
      $participantParams = ['event_id' => $this->event_id, 'cart_id' => $this->event_cart->id];
      if ($this->event_cart->defaultParticipantContactID) {
        $participantParams['contact_id'] = $this->event_cart->defaultParticipantContactID;
      }
      $newParticipant = CRM_Event_Cart_BAO_MerParticipant::create($participantParams);
      $this->event_cart->defaultParticipantContactID = $newParticipant->contact_id;
      $this->participants[$newParticipant->id] = $newParticipant;
    }
    /** @var \CRM_Event_BAO_Participant $participant */
    foreach ($this->participants as $participant) {
      $participant->load_associations();
    }
  }

  public function load_location() {
    if ($this->location == NULL) {
      $location_params = ['entity_id' => $this->event_id, 'entity_table' => 'civicrm_event'];
      $this->location = CRM_Core_BAO_Location::getValues($location_params, TRUE);
    }
  }

  /**
   * @return array
   */
  public function not_waiting_participants() {
    $result = [];
    foreach ($this->participants as $participant) {
      if (!$participant->must_wait) {
        $result[] = $participant;
      }
    }
    return $result;
  }

  /**
   * @return int
   */
  public function num_not_waiting_participants() {
    return count($this->not_waiting_participants());
  }

  /**
   * @return int
   */
  public function num_waiting_participants() {
    return count($this->waiting_participants());
  }

  /**
   * @param mixed $offset
   *
   * @return bool
   */
  public function offsetExists($offset) {
    return array_key_exists(array_merge($this->fields(), ['main_conference_event_id']), $offset);
  }

  /**
   * @param mixed $offset
   *
   * @return int
   */
  public function offsetGet($offset) {
    if ($offset == 'event') {
      return $this->event->toArray();
    }
    if ($offset == 'id') {
      return $this->id;
    }
    if ($offset == 'main_conference_event_id') {
      return $this->main_conference_event_id ?? NULL;
    }
    $fields = &$this->fields();
    return $fields[$offset];
  }

  /**
   * @param mixed $offset
   * @param mixed $value
   */
  public function offsetSet($offset, $value) {
  }

  /**
   * @param mixed $offset
   */
  public function offsetUnset($offset) {
  }

  /**
   * @return array
   */
  public function waiting_participants() {
    $result = [];
    foreach ($this->participants as $participant) {
      if ($participant->must_wait) {
        $result[] = $participant;
      }
    }
    return $result;
  }

  /**
   * @param int $event_id
   *
   * @return array
   */
  public static function get_registration_link($event_id) {
    $cart = CRM_Event_Cart_BAO_Cart::find_or_create_for_current_session();
    $event_in_cart = $cart->get_event_in_cart_by_event_id($event_id);

    if ($event_in_cart) {
      return [
        'label' => ts("Remove from Cart"),
        'path' => 'civicrm/event/remove_from_cart',
        'query' => "reset=1&id={$event_id}",
      ];
    }
    else {
      return [
        'label' => ts("Add to Cart"),
        'path' => 'civicrm/event/add_to_cart',
        'query' => "reset=1&id={$event_id}&cid={$cart->defaultParticipantContactID}",
      ];
    }
  }

  /**
   * @return bool
   */
  public function is_parent_event() {
    return (NULL !== (CRM_Event_BAO_Event::get_sub_events($this->event_id)));
  }

  /**
   * @param int $parent_event_id
   *
   * @return bool
   */
  public function is_child_event($parent_event_id) {
    return ($this->event->parent_event_id === $parent_event_id);
  }

}
