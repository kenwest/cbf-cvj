<?php

class CRM_CivirulesActions_Case_AddListedSupervisor extends CRM_Civirules_Action {

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
      $triggerCase = $trigger->getEntityData('Case');
      if (!$triggerCase) {
        return; // there is no case
      }
      $contacts = $triggerCase['contacts'] ?? [];
      foreach ($contacts as $contact) {
        if (stristr($contact['role'], 'Supervisor') !== false) {
          return; // a light-weight test that the Case already has a Supervisor
        }
      }
      $cases = \Civi\Api4\CiviCase::get(false)
        ->addSelect('id', 'case_type_id.definition')
        ->addWhere('id', '=', $triggerCase['id'])
        ->setLimit(1)
        ->addChain('client', \Civi\Api4\CaseContact::get(false)
          ->addSelect('contact_id')
          ->addWhere('case_id', '=', '$id'))
        ->addChain('roles', \Civi\Api4\Relationship::get(false)
          ->addSelect(
            'contact_id_a',
            'contact_id_b',
            'relationship_type_id.label_a_b',
            'relationship_type_id.label_b_a')
          ->addWhere('case_id', '=', '$id')
          ->addWhere('is_active', '=', true))
        ->execute();
      foreach ($cases as $case) {
        $client = $case['client'][0]['contact_id'] ?? 0;
        if (!$client) {
          return; // unusual but there's no client for this case
        }
        foreach ($case['roles'] as $role) {
          if (stristr($role['relationship_type_id.label_a_b'], 'Supervisor') !== false) {
            return; // a heavy-weight test that the Case already has a Supervisor
          }
        }
        $caseRoles = $case['case_type_id.definition']['caseRoles'] ?? [];
        $groups = [];
        foreach ($caseRoles as $caseRole) {
          if (stristr($caseRole['name'], 'Supervisor') !== false) {
            $groups = $caseRole['groups'] ?? [];
            break; // found the groups limiting candidates for the Supervisor role
          }
        }
        if ($groups) {
          $groupContacts = \Civi\Api4\GroupContact::get(FALSE)
            ->addSelect('contact_id')
            ->addWhere('group_id:name', 'IN', $groups)
            ->addWhere('status', '=', 'Added')
            ->setLimit(100)
            ->execute();
          if ($groupContacts->count() > 0) {
            $groupContact = $groupContacts->itemAt(rand(0, $groupContacts->count() - 1));
            $results = \Civi\Api4\Relationship::create(false)
              ->addValue('contact_id_a', $groupContact['contact_id'])
              ->addValue('contact_id_b', $client)
              ->addValue('relationship_type_id:name', 'Case Supervisor is')
              ->addValue('case_id', $case['id'])
              ->addValue('start_date', date('Y-m-d'))
              ->addValue('is_active', true)
              ->addValue('description', "Created by Civirules Action 'Case add listed supervisor'")
              ->execute();
            foreach ($results as $result) {
              CRM_Core_Error::debug_var('Civirules Action: Case add listed supervisor: added role', $result);
            }
          }
        }
      }
    } catch (\API_Exception $e) {
      CRM_Core_Error::debug_var('Civirules Action: Case add listed supervisor: exception', $e);
    }
  }
}
