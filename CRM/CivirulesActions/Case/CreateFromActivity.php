<?php

class CRM_CivirulesActions_Case_CreateFromActivity extends CRM_Civirules_Action {

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
    try {
      CRM_Core_Error::debug_var('Civirules Action: Create Case from an Activity: triggered', $trigger);

      // Get data for case
      $triggerActivity = $trigger->getEntityData('Activity');
      $triggerActivityContact = $trigger->getEntityData('ActivityContact');
      if (!$triggerActivity) {
        CRM_Core_Error::debug_var('Civirules Action: Create Case from an Activity: triggered without an Activity', $trigger);
        return; // there is no activity
      }
      if (!$triggerActivityContact) {
        CRM_Core_Error::debug_var('Civirules Action: Create Case from an Activity: triggered without an Activity Contact', $trigger);
        return; // there is no activity contact to which to link the case
      }
      $creator = null;
      $activityContacts = \Civi\Api4\ActivityContact::get(FALSE)
        ->addWhere('activity_id', '=', $triggerActivity['id'])
        ->addWhere('record_type_id:name', '=', 'Activity Source')
        ->setLimit(25)
        ->execute();
      foreach ($activityContacts as $activityContact) {
        $creator = $activityContact['contact_id'];
      }
      if (!$creator) {
        CRM_Core_Error::debug_var('Civirules Action: Create Case from an Activity: Activity has no Added By contact', $trigger);
        return; // there is no activity contact to use as the creator of the case
      }

      // Create the case
      $results = \Civi\Api4\CiviCase::create(false)
        ->addValue('case_type_id:name', 'one_to_one_mobilising')
        ->addValue('subject', $triggerActivity['subject'])
        ->addValue('status_id:name', 'Open') // 'Ongoing' in UI
        ->addValue('creator_id', 16602) // Magic number for Do Not Reply individual contact
        ->addValue('contact_id', [ $triggerActivityContact['contact_id'], ])
        ->addValue('duration', 0)
        ->addValue('details', "This case was created by Civirules Action 'Create Case from an Activity'")
        ->execute();
      foreach ($results as $result) {
        CRM_Core_Error::debug_var('Civirules Action: Create Case from an Activity: created a case', $result);

        /*
         * Add the $triggerActivity to the new Case
         *
         * Since API4 doesn't do this for us I am directly calling
         * CRM_Activity_Page_AJAX::_convertToCaseActivity(). That function
         * is currently used to create a new revision of the Activity and
         * creates a CaseActivity for it.
         *
         * Unfortunately, creating the new revision makes the Activity
         * invisible in other contexts including reports on Activities. So what
         * I'm doing in the code that follows is calling the function in 'copy'
         * mode which that function doesn't recognise, so it doesn't modify the
         * original Activity, but adds a new Activity that is a CaseActivity.
         *
         * Note this runs the risk that code which is looking for the original
         * Activity also finds the copy.
         */
        $caseActivity = CRM_Activity_Page_AJAX::_convertToCaseActivity([
          'caseID' => $result['id'],
          'activityID' => $triggerActivity['id'],
          'assigneeContactIds' => '', // Preserve Activity's assignees
          'newSubject' => 'Copy of: ' . $triggerActivity['subject'],
          'targetContactIds' => $triggerActivityContact['contact_id'],
          'mode' => 'copy',
        ]);
        if (!$caseActivity['newId'] || $caseActivity['error_msg']) {
          CRM_Core_Error::debug_var('Civirules Action: Create Case from an Activity: failed to add triggering Activity to case', $caseActivity);
        }
        else {
          CRM_Core_Error::debug_var('Civirules Action: Create Case from an Activity: added copy of triggering Activity to case', $caseActivity);
        }
      }
    } catch (\API_Exception $e) {
      CRM_Core_Error::debug_var('Civirules Action: Create Case from an Activity: exception', $e);
    }
  }
}
