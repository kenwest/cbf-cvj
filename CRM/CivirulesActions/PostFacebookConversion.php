<?php

class CRM_CivirulesActions_PostFacebookConversion extends CRM_Civirules_Action {

  /**
   * Method to return the url for additional form processing for action
   * and return false if none is needed
   *
   * @param int $ruleActionId
   * @return bool
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId)
  {
    return false;
  }

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   *
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $trigger)
  {
    $fbEvent = [
      'action_source' => 'website',
      'event_time' => time(),
      'event_source_url' => url(current_path(), ['absolute' => true]),
      'user_data' => [
        'client_ip_address' => $_SERVER['REMOTE_ADDR'],
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'],
      ],
    ];

    // Get data from the trigger
    $contactId = $trigger->getContactId();
    $participant = $trigger->getEntityData('Participant');
    if (!$participant) {
      return;
    }
    $fbEvent['event_id'] = "{$participant['event_id']}.{$participant['id']}";
    $fbEvent['event_name'] = 'TestEvent';

    // Get data from the form
    $pixel = variable_get('cbf_cvj_fb_pixel');

    try {
      $emails = \Civi\Api4\Email::get(false)
        ->addSelect('email')
        ->addWhere('contact_id', '=', $contactId)
        ->addWhere('is_primary', '=', true)
        ->setLimit(1)
        ->execute();
      foreach ($emails as $email) {
        $fbEvent['user_data']['em'] = hash('sha256', $email['email'], false);
      }
      
    } catch (\API_Exception $e) {
      CRM_Core_Error::debug_var('Civirules Action: Post Facebook conversion: exception', $e);
    }

    $url = url(
      "https://graph.facebook.com/v12.0/$pixel/events",
      [
        'query' => [
          'data' => json_encode([$fbEvent]),
          'test_event_code' => 'TEST10228',
          'access_token' => variable_get('cbf_cvj_fb_access_token'),
        ],
      ]);
    $httpResult = drupal_http_request(
      $url,
      [
        'method' => 'POST',
      ]);
    $httpResponse = json_decode($httpResult->data);
  }
}
