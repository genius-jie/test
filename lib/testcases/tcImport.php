<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * Scope: control test specification import
 *
 * @filesource  tcImport.php
 * @package     TestLink
 * @copyright   2007-2014, TestLink community 
 * @link        http://testlink.sourceforge.net/ 
 * 
 * @internal revisions
 * @since 1.9.10
 *
 */
require('../../config.inc.php');
require_once('common.php');
require_once('csv.inc.php');
require_once('xml.inc.php');
include('../../Excel/reader.php');
testlinkInitPage($db);


$templateCfg = templateConfiguration();
$pcheck_fn=null;
$args = init_args();
$gui = initializeGui($db,$args);
if ($args->do_upload)
{
  
  // check the uploaded file
  $source = isset($_FILES['uploadedFile']['tmp_name']) ? $_FILES['uploadedFile']['tmp_name'] : null;
  
  tLog('Uploaded file: '.$source);
  $doIt = false;
  $gui->file_check = null;
  if (($source != 'none') && ($source != ''))
  { 
    // ATTENTION:
    // MAX_FILE_SIZE hidden input is defined on form, but anyway we do not get error at least using
    // Firefox and Chrome.
    if( !($doIt = $_FILES['uploadedFile']['size'] <= $gui->importLimitBytes) )
    {
      $gui->file_check['status_ok'] = 0;
      $gui->file_check['msg'] = sprintf(lang_get('file_size_exceeded'),$_FILES['uploadedFile']['size'],$gui->importLimitBytes);
    }
  }
  if($doIt)
  { 
    $gui->file_check['status_ok'] = 1;
    if (move_uploaded_file($source, $gui->dest))
    {
      tLog('Renamed uploaded file: ' . $source);
      switch($args->importType)
      {
        case 'XML':
          $pcheck_fn = "check_xml_tc_tsuite";
          $pimport_fn = "importTestCaseDataFromXML";
          break;
      case 'XLS':
$pcheck_fn = null;
$pimport_fn = "importTestCaseDataFromSpreadsheet";
break;
      case 'XMIND':
          $pcheck_fn = null;
          $pimport_fn = "importTestCaseDataFromXmind";
          break;
	}
      if(!is_null($pcheck_fn))
      {
        $gui->file_check = $pcheck_fn($gui->dest,$args->useRecursion);
      }
    }
    if($gui->file_check['status_ok'] && $pimport_fn)
    {
      tLog('Check is Ok.');
      $opt = array();
      $opt['useRecursion'] = $args->useRecursion;
      $opt['importIntoProject'] = $args->bIntoProject;
      $opt['duplicateLogic'] = array('hitCriteria' => $args->hit_criteria,
                                     'actionOnHit' => $args->action_on_duplicated_name);
      $gui->resultMap = $pimport_fn($db,$gui->dest,intval($args->container_id),
                                    intval($args->tproject_id),intval($args->userID),$opt);
    }
  }
  else if(is_null($gui->file_check))
  {
    
    tLog('Missing upload file','WARNING');
    $gui->file_check = array('status_ok' => 0, 'msg' => lang_get('please_choose_file_to_import'));
    $args->importType = null;
  }
}

if($args->useRecursion)
{
  $obj_mgr = new testsuite($db);
  $gui->actionOptions=array('update_last_version' => lang_get('update_last_testcase_version'),
                            'generate_new' => lang_get('generate_new_testcase'),
                            'create_new_version' => lang_get('create_new_testcase_version'));
  
  $gui->hitOptions=array('name' => lang_get('same_name'),
                         'internalID' => lang_get('same_internalID'),
                         'externalID' => lang_get('same_externalID'));
}
else
{
  $obj_mgr = new testcase($db);
  $gui->actionOptions=array('update_last_version' => lang_get('update_last_testcase_version'),
                            'generate_new' => lang_get('generate_new_testcase'),
                            'create_new_version' => lang_get('create_new_testcase_version'));

  $gui->hitOptions=array('name' => lang_get('same_name'),
                         'internalID' => lang_get('same_internalID'),
                         'externalID' => lang_get('same_externalID'));

}

$gui->testprojectName = $_SESSION['testprojectName'];
$gui->importTypes = $obj_mgr->get_import_file_types();
$gui->action_on_duplicated_name=$args->action_on_duplicated_name;


$smarty = new TLSmarty();
$smarty->assign('gui',$gui);  
$smarty->display($templateCfg->template_dir . $templateCfg->default_template);


// --------------------------------------------------------------------------------------
/*
  function: importTestCaseDataFromXML
  args :
  returns: 
*/
function importTestCaseDataFromXML(&$db,$fileName,$parentID,$tproject_id,$userID,$options=null)
{
  tLog('importTestCaseDataFromXML called for file: '. $fileName);
  $xmlTCs = null;
  $resultMap  = null;
  $my = array();
  $my['options'] = array('useRecursion' => false, 'importIntoProject' => 0,
                         'duplicateLogic' => array('hitCriteria' => 'name', 'actionOnHit' => null)); 
  $my['options'] = array_merge($my['options'], (array)$options);
  foreach($my['options'] as $varname => $value)
  {
    $$varname = $value;
  }
  
  if (file_exists($fileName))
  {
    $xml = @simplexml_load_file_wrapper($fileName);
    if($xml !== FALSE)
    {
      $xmlKeywords = $xml->xpath('//keywords');
      $kwMap = null;
      if ($xmlKeywords)
      {
        $tproject = new testproject($db);
        $loop2do = sizeof($xmlKeywords);
        for($idx = 0; $idx < $loop2do ;$idx++)
        {
          $tproject->importKeywordsFromSimpleXML($tproject_id,$xmlKeywords[$idx]);
        }
        $kwMap = $tproject->get_keywords_map($tproject_id);
        $kwMap = is_null($kwMap) ? null : array_flip($kwMap);
      }

      if (!$useRecursion &&  ($xml->getName() == 'testcases') )
      {
        $resultMap = importTestCasesFromSimpleXML($db,$xml,$parentID,$tproject_id,$userID,$kwMap,$duplicateLogic);
      }
      
      if ($useRecursion && ($xml->getName() == 'testsuite'))
      {
        $resultMap = importTestSuitesFromSimpleXML($db,$xml,intval($parentID),intval($tproject_id),$userID,
                                                   $kwMap,$importIntoProject,$duplicateLogic);
      }

    }
  }
  return $resultMap;
}


// --------------------------------------------------------------------------------------
/*
  function: saveImportedTCData
  args :
  returns: 
*/
function saveImportedTCData(&$db,$tcData,$tproject_id,$container_id,
                            $userID,$kwMap,$duplicatedLogic = array('hitCriteria' => 'name', 'actionOnHit' => null))
{
  static $messages;
  static $fieldSizeCfg;
  static $feedbackMsg;
  static $tcase_mgr;
  static $tproject_mgr;
  static $req_spec_mgr;
  static $req_mgr;
  static $safeSizeCfg;
  static $linkedCustomFields;
  static $tprojectHas;
  static $reqSpecSet;
  static $getVersionOpt;
  static $userObj;
  static $tcasePrefix;
  static $glueChar;
  static $userRights;

  $ret = null;
  
  if (!$tcData)
  {
    return;
  }
  
  // $tprojectHas = array('customFields' => false, 'reqSpec' => false);
  $hasCustomFieldsInfo = false;
  $hasRequirements = false;
  $hasAttachments = false;

  if(is_null($messages))
  {
    $feedbackMsg = array();
    $messages = array();
    $fieldSizeCfg = config_get('field_size');

    $tcase_mgr = new testcase($db);
    $tproject_mgr = new testproject($db);
    $req_spec_mgr = new requirement_spec_mgr($db);
    $req_mgr = new requirement_mgr($db);
    $userObj = new tlUser($userID);
    $userObj->readFromDB($db,tlUser::TLOBJ_O_SEARCH_BY_ID);
    $userRights['can_edit_executed'] = 
      $userObj->hasRight($db,'testproject_edit_executed_testcases',$tproject_id);
	$userRights['can_link_to_req'] = 
	  $userObj->hasRight($db,'req_tcase_link_management',$tproject_id);
	$userRights['can_assign_keywords'] = 
      $userObj->hasRight($db,'keyword_assignment',$tproject_id);
    $k2l = array('already_exists_updated','original_name','testcase_name_too_long','already_exists_not_updated',
                 'start_warning','end_warning','testlink_warning','hit_with_same_external_ID',
				 'keywords_assignment_skipped_during_import','req_assignment_skipped_during_import');

    foreach($k2l as $k)
    {
      $messages[$k] = lang_get($k);
    }

    $messages['start_feedback'] = $messages['start_warning'] . "\n" . $messages['testlink_warning'] . "\n";
    $messages['cf_warning'] = lang_get('no_cf_defined_can_not_import');
    $messages['reqspec_warning'] = lang_get('no_reqspec_defined_can_not_import');
    
    
    
    $feedbackMsg['cfield']=lang_get('cf_value_not_imported_missing_cf_on_testproject');
    $feedbackMsg['tcase'] = lang_get('testcase');
    $feedbackMsg['req'] = lang_get('req_not_in_req_spec_on_tcimport');
    $feedbackMsg['req_spec'] = lang_get('req_spec_ko_on_tcimport');
    $feedbackMsg['reqNotInDB'] = lang_get('req_not_in_DB_on_tcimport');
    $feedbackMsg['attachment'] = lang_get('attachment_skipped_during_import');


    // because name can be changed automatically during item creation
    // to avoid name conflict adding a suffix automatically generated,
    // is better to use a max size < max allowed size 
    $safeSizeCfg = new stdClass();
    $safeSizeCfg->testcase_name=($fieldSizeCfg->testcase_name) * 0.8;


    // Get CF with scope design time and allowed for test cases linked to this test project
    $linkedCustomFields = $tcase_mgr->cfield_mgr->get_linked_cfields_at_design($tproject_id,1,null,'testcase',null,'name');
    $tprojectHas['customFields']=!is_null($linkedCustomFields);                   

    $reqSpecSet = getReqSpecSet($db,$tproject_id);

    $tprojectHas['reqSpec'] = (!is_null($reqSpecSet) && count($reqSpecSet) > 0);

    $getVersionOpt = array('output' => 'minimun');
    $tcasePrefix = $tproject_mgr->getTestCasePrefix($tproject_id);
    $glueChar = config_get('testcase_cfg')->glue_character;
  }
  
  $resultMap = array();
  $tc_qty = sizeof($tcData);
  $userIDCache = array();
  
  for($idx = 0; $idx <$tc_qty ; $idx++)
  {
    $tc = $tcData[$idx];
    $name = $tc['name'];
    $summary = $tc['summary'];
    $steps = $tc['steps'];

    // I've changed value to use when order has not been provided 
    // from testcase:DEFAULT_ORDER to a counter, because with original solution
    // an issue arise with 'save execution and go next'
    // if use has not provided order I think is OK TestLink make any choice.
    $node_order = isset($tc['node_order']) ? intval($tc['node_order']) : ($idx+1);
    $internalid = $tc['internalid'];
    $preconditions = $tc['preconditions'];
    $exec_type = isset($tc['execution_type']) ? $tc['execution_type'] : TESTCASE_EXECUTION_TYPE_MANUAL;
    $importance = isset($tc['importance']) ? $tc['importance'] : MEDIUM;   

    $attr = null;
    if(isset($tc['estimated_exec_duration']) && !is_null($tc['estimated_exec_duration']))
    {
      $attr['estimatedExecDuration'] = trim($tc['estimated_exec_duration']);
      $attr['estimatedExecDuration'] = $attr['estimatedExecDuration']=='' ? null : floatval($attr['estimatedExecDuration']);
    }  

    if(isset($tc['is_open']))
    {
      $attr['is_open'] = trim($tc['is_open']);
    }  
	
	if(isset($tc['active']))
    {
      $attr['active'] = trim($tc['active']);
    }  
	
    if(isset($tc['status']))
    {
      $attr['status'] = trim($tc['status']);
    }  

    $externalid = $tc['externalid'];
    if( intval($externalid) <= 0 )
    {
      $externalid = null;
    }
    
    $personID = $userID;
    if( !is_null($tc['author_login']) )
    {
      if( isset($userIDCache[$tc['author_login']]) )
      {
        $personID = $userIDCache[$tc['author_login']];
      }
      else
      {
        $userObj->login = $tc['author_login'];
        if( $userObj->readFromDB($db,tlUser::USER_O_SEARCH_BYLOGIN) == tl::OK )
        {
          $personID = $userObj->dbID;
        }
        
        // I will put always a valid userID on this cache,
        // this way if author_login does not exit, and is used multiple times
        // i will do check for existence JUST ONCE.
        $userIDCache[$tc['author_login']] = $personID;
      }
    }
      
    $name_len = tlStringLen($name);  
    if($name_len > $fieldSizeCfg->testcase_name)
    {
      // Will put original name inside summary
      $xx = $messages['start_feedback'];
      $xx .= sprintf($messages['testcase_name_too_long'],$name_len, $fieldSizeCfg->testcase_name) . "\n";
      $xx .= $messages['original_name'] . "\n" . $name. "\n" . $messages['end_warning'] . "\n";
	  $tcCfg = getWebEditorCfg('design');
	  $tcType = $tcCfg['type'];
	  if ($tcType == 'none'){
		$summary = $xx . $summary ;
      }
	  else{
		$summary = nl2br($xx) . $summary ;
	  }
	  $name = tlSubStr($name, 0, $safeSizeCfg->testcase_name);      
    }
        
    
    $kwIDs = null;
    if (isset($tc['keywords']) && $tc['keywords'])
    {
	  if(!$userRights['can_assign_keywords']){
		$resultMap[] = array($name,$messages['keywords_assignment_skipped_during_import']);
	  }
	  else{
		$kwIDs = implode(",",buildKeywordList($kwMap,$tc['keywords']));
	  }
    }  
    
    $doCreate=true;
    if( $duplicatedLogic['actionOnHit'] == 'update_last_version' )
    {
      $updOpt['blockIfExecuted'] = !$userRights['can_edit_executed'];
      switch($duplicatedLogic['hitCriteria'])
      {
        case 'name':
          $info = $tcase_mgr->getDuplicatesByName($name,$container_id);
        break;
        
        case 'internalID':
          $dummy = $tcase_mgr->tree_manager->get_node_hierarchy_info($internalid,$container_id);
          if( !is_null($dummy) )
          {
            $info = null;  // TICKET 4925
            $info[$internalid] = $dummy;
          }
        break;
    
        case 'externalID':
          $info = $tcase_mgr->get_by_external($externalid,$container_id);
        break;
    
        
      }

      if( !is_null($info) )
      {
        $tcase_qty = count($info);

        switch($tcase_qty)
        {
           case 1:
             $doCreate=false;
             $tcase_id = key($info); 
             $last_version = $tcase_mgr->get_last_version_info($tcase_id,$getVersionOpt);
             $tcversion_id = $last_version['id'];
             $ret = $tcase_mgr->update($tcase_id,$tcversion_id,$name,$summary,
                                       $preconditions,$steps,$personID,$kwIDs,
                                       $node_order,$exec_type,$importance,$attr,$updOpt);

             $ret['id'] = $tcase_id;
             $ret['tcversion_id'] = $tcversion_id;
             if( $ret['status_ok'] )
             { 
               $resultMap[] = array($name,$messages['already_exists_updated']);
             }
             else
             {
               if($ret['reason'] == '')
               {
                 $resultMap[] = array($name, sprintf($messages['already_exists_not_updated'], 
                                                     $tcasePrefix . $glueChar . $externalid,
                                                     $tcasePrefix . $glueChar . $ret['hit_on']['tc_external_id']));
               }
               else
               {
                 $resultMap[] = array($name,$ret['msg']);
               } 
            } 
           break;
           
           case 0:
             $doCreate=true; 
           break;
           
           default:
               $doCreate=false; 
           break;
       }
      }
    }
    
    if( $doCreate )
    {           
      // Want to block creation of with existent EXTERNAL ID, if containers ARE DIFFERENT.
      $item_id = intval($tcase_mgr->getInternalID($externalid, array('tproject_id' => $tproject_id)));   
      if( $item_id > 0)
      {
        // who is his parent ?
        $owner = $tcase_mgr->getTestSuite($item_id);  
        if( $owner != $container_id)
        { 
          // Get full path of existent Test Cases
          $stain = $tcase_mgr->tree_manager->get_path($item_id,null, 'name');
          $n = count($stain);         
          $stain[$n-1] = $tcasePrefix . config_get('testcase_cfg')->glue_character . $externalid . ':' . $stain[$n-1];
          $stain = implode('/',$stain);
          
          $resultMap[] = array($name,$messages['hit_with_same_external_ID'] . $stain);
          $doCreate = false;
        }
      }        
    }
    if( $doCreate )
    {     
        $createOptions = array('check_duplicate_name' => testcase::CHECK_DUPLICATE_NAME, 
                               'action_on_duplicate_name' => $duplicatedLogic['actionOnHit'],
                               'external_id' => $externalid, 'importLogic' => $duplicatedLogic);

        if(!is_null($attr) )
        {
          $createOptions += $attr;
        }  

        if ($ret = $tcase_mgr->create($container_id,$name,$summary,$preconditions,$steps,
                                      $personID,$kwIDs,$node_order,testcase::AUTOMATIC_ID,
                                      $exec_type,$importance,$createOptions))
        {
          $resultMap[] = array($name,$ret['msg']);
        }  
    }
      
    // Custom Fields Management
    // Check if CF with this name and that can be used on Test Cases is defined in current Test Project.
    // If Check fails => give message to user.
    // Else Import CF data
    //   
    $hasCustomFieldsInfo = (isset($tc['customfields']) && !is_null($tc['customfields']));
    if($hasCustomFieldsInfo &&  !is_null($ret))
    {                
      if($tprojectHas['customFields'])
      {                         
        $msg = processCustomFields($tcase_mgr,$name,$ret['id'],$ret['tcversion_id'],$tc['customfields'],
                                   $linkedCustomFields,$feedbackMsg);
        if( !is_null($msg) )
        {
            $resultMap = array_merge($resultMap,$msg);
        }
      }
      else
      {
        // Can not import Custom Fields Values, give feedback
        $msg[]=array($name,$messages['cf_warning']);
        $resultMap = array_merge($resultMap,$msg);          
      }
    }
    
    $hasRequirements=(isset($tc['requirements']) && !is_null($tc['requirements']));
    if($hasRequirements)
    {
      if( $tprojectHas['reqSpec'] )
      {
		if(!$userRights['can_link_to_req']){
			$msg[]=array($name,$messages['req_assignment_skipped_during_import']);
		}
		else{
			// appel
			$msg = processRequirements($db,$req_mgr,$name,$ret['id'],$tc['requirements'],
                                   $reqSpecSet,$feedbackMsg,$userID);
		}
		if( !is_null($msg) )
        {
          $resultMap = array_merge($resultMap,$msg);
        }
      }
      else
      {
        $msg[]=array($name,$messages['reqspec_warning']);
        $resultMap = array_merge($resultMap,$msg);          
      }
    }

	$hasAttachments=(isset($tc['attachments']) && !is_null($tc['attachments']));
	if($hasAttachments)
	{
	  $fk_id = $doCreate ? $ret['id'] : $internalid;
	  if ($internalid == "" && $item_id>0){ // internalid is optionnal in XML schema, real internalid may has been retrieved based on externalID before
		$internalid = $item_id;
	  }
	  $msg = processAttachments( $db, $name, $internalid, $fk_id, $tc['attachments'], $feedbackMsg );
	  if( !is_null($msg) )
	  {
		$resultMap = array_merge($resultMap,$msg);
	  }      
	}
  }
  return $resultMap;
}


// --------------------------------------------------------------------------------------
/*
  function: buildKeywordList
  args :
  returns: 
*/
function buildKeywordList($kwMap,$keywords)
{
  $items = array();
  $loop2do = sizeof($keywords);
  for($jdx = 0; $jdx <$loop2do ; $jdx++)
  {
    $items[] = $kwMap[trim($keywords[$jdx]['name'])]; 
  }
  return $items;
}


// --------------------------------------------------------------------------------------

// --------------------------------------------------------------------------------------

/*
  function: Check if at least the file starts seems OK
*/
function check_xml_tc_tsuite($fileName,$recursiveMode)
{
  $xml = @simplexml_load_file_wrapper($fileName);
  $file_check = array('status_ok' => 0, 'msg' => 'xml_load_ko');          
  if($xml !== FALSE)
  {
    $file_check = array('status_ok' => 1, 'msg' => 'ok');          
    $elementName = $xml->getName();
    if($recursiveMode)
    {
      if($elementName != 'testsuite')
      {
        $file_check=array('status_ok' => 0, 'msg' => lang_get('wrong_xml_tsuite_file'));
      }  
    }
    else
    {
      if($elementName != 'testcases' && $elementName != 'testcase')
        {
        $file_check=array('status_ok' => 0, 'msg' => lang_get('wrong_xml_tcase_file'));
      }  
    }
  }
  return $file_check;
}



/* contribution by mirosvad - 
   Convert new line characters from XLS to HTML 
*/
function nl2p($str)  
{
  return str_replace('<p></p>', '', '<p>' . preg_replace('#\n|\r#', '</p>$0<p>', $str) . '</p>'); //MS
}


/*
  function: 
  
  args :
  
  returns: 
  
*/
function init_args()
{
  $args = new stdClass();
  $_REQUEST = strings_stripSlashes($_REQUEST);

  $key='action_on_duplicated_name';
  $args->$key = isset($_REQUEST[$key]) ? $_REQUEST[$key] : 'generate_new';

  $key='hit_criteria';
  $args->$key = isset($_REQUEST[$key]) ? $_REQUEST[$key] : 'name';
       
        
  $args->importType = isset($_REQUEST['importType']) ? $_REQUEST['importType'] : null;
  $args->useRecursion = isset($_REQUEST['useRecursion']) ? $_REQUEST['useRecursion'] : 0;
  $args->location = isset($_REQUEST['location']) ? $_REQUEST['location'] : null; 
  $args->container_id = isset($_REQUEST['containerID']) ? intval($_REQUEST['containerID']) : 0;
  $args->bIntoProject = isset($_REQUEST['bIntoProject']) ? intval($_REQUEST['bIntoProject']) : 0;
    
  $args->containerType = isset($_REQUEST['containerType']) ? intval($_REQUEST['containerType']) : 0;
  $args->do_upload = isset($_REQUEST['UploadFile']) ? 1 : 0;
    
  $args->userID = $_SESSION['userID'];
  $args->tproject_id = $_SESSION['testprojectID'];
  
  return $args;
}


/**
 * processCustomFields
 *
 * Analise custom field info related to test case being imported.
 * If everything OK, assign to test case.
 * Else return an array of messages.
 *
 *
 * @internal revisions
 * 20100905 - franciscom - BUGID 3431 - Custom Field values at Test Case VERSION Level
 */
function processCustomFields(&$tcaseMgr,$tcaseName,$tcaseId,$tcversionId,$cfValues,$cfDefinition,$messages)
{
    static $missingCfMsg;
    $cf2insert=null;
    $resultMsg=null;
      
    foreach($cfValues as $value)
    {
       if( isset($cfDefinition[$value['name']]) )
       {
           $cf2insert[$cfDefinition[$value['name']]['id']]=array('type_id' => $cfDefinition[$value['name']]['type'],
                                                                 'cf_value' => $value['value']);         
       }
       else
       {
           if( !isset($missingCfMsg[$value['name']]) )
           {
               $missingCfMsg[$value['name']] = sprintf($messages['cfield'],$value['name'],$messages['tcase']);
           }
           $resultMsg[] = array($tcaseName,$missingCfMsg[$value['name']]); 
       }
    }  
    
    $tcaseMgr->cfield_mgr->design_values_to_db($cf2insert,$tcversionId,null,'simple');
    return $resultMsg;
}

/**
 * processRequirements
 *
 * Analise requirements info related to test case being imported.
 * If everything OK, assign to test case.
 * Else return an array of messages.
 *
 */
function processRequirements(&$dbHandler,&$reqMgr,$tcaseName,$tcaseId,$tcReq,$reqSpecSet,$messages,$userID)
{
  static $missingReqMsg;
  static $missingReqSpecMsg;
  static $missingReqInDBMsg;
  static $cachedReqSpec;
  $resultMsg=null;
  $tables = tlObjectWithDB::getDBTables(array('requirements'));


  foreach($tcReq as $ydx => $value)
  {
    $cachedReqSpec=array();
    $doit=false;

    // Look for req doc id we get from file, inside Req Spec Set
    // we got from DB
    if( ($doit=isset($reqSpecSet[$value['doc_id']])) )
    {
      if( !(isset($cachedReqSpec[$value['req_spec_title']])) )
      {
        // $cachedReqSpec
        // key: Requirement Specification Title get from file
        // value: map with follogin keys
        //        id => requirement specification id from DB
        //        req => map with key: requirement document id
        $cachedReqSpec[$value['req_spec_title']]['id']=$reqSpecSet[$value['doc_id']]['id'];
        $cachedReqSpec[$value['req_spec_title']]['req']=null;
      }
    }
    
    if($doit)
    {
      $useit=false;
      $req_spec_id=$cachedReqSpec[$value['req_spec_title']]['id'];
    
      // Check if requirement with desired document id exists on requirement specification on DB.
      // If not => create message for user feedback.
      if( !($useit=isset($cachedReqSpec[$value['req_spec_title']]['req'][$value['doc_id']])) )
      {
        $sql = " SELECT REQ.id from {$tables['requirements']} REQ " .
               " WHERE REQ.req_doc_id='{$dbHandler->prepare_string($value['doc_id'])}' " .
               " AND REQ.srs_id={$req_spec_id} ";     
                   
        $rsx=$dbHandler->get_recordset($sql);
        if( $useit=((!is_null($rsx) && count($rsx) > 0) ? true : false) )
        {
          $cachedReqSpec[$value['req_spec_title']]['req'][$value['doc_id']]=$rsx[0]['id'];
        }  
      }
          
          
      if($useit)
      {

        $reqMgr->assign_to_tcase($cachedReqSpec[$value['req_spec_title']]['req'][$value['doc_id']],$tcaseId,$userID);
      }
      else
      {
        if( !isset($missingReqMsg[$value['doc_id']]) )
        {
          $missingReqMsg[$value['doc_id']]=sprintf($messages['req'],
                                                   $value['doc_id'],$value['req_spec_title']);  
        }
        $resultMsg[] = array($tcaseName,$missingReqMsg[$value['doc_id']]); 
      }
    } 
    else
    {
      // We didnt find Req Doc ID in Req Spec Set got from DB
      if( !isset($missingReqInDBMsg[$value['doc_id']]) )
      {
        $missingReqInDBMsg[$value['doc_id']]=sprintf($messages['reqNotInDB'],
                                                     $value['doc_id'],'');  
      }
      $resultMsg[] = array($tcaseName,$missingReqInDBMsg[$value['doc_id']]); 
    }
      
  } //foreach
     
  return $resultMsg;
}

/**
 * processAttachments
 *
 * Analyze attachments info related to testcase or testsuite to define if the the attachment has to be saved.
 * If attachment format is OK and attachment is not already in database for the target, save the attachment.
 * Else return an array of messages.
 *
 */
function processAttachments( &$dbHandler, $tcaseName, $xmlInternalID, $fk_Id, $tcAtt, $messages )
{  
	static $duplicateAttachment;
	$resultMsg=null;	
	$tables = tlObjectWithDB::getDBTables(array('nodes_hierarchy','attachments'));
	
	foreach( $tcAtt as $ydx => $value )
	{	
		$addAttachment = false;
		
		// Is it a CREATION or an UPDATE?
		if( $xmlInternalID == $fk_Id ) // internalID matches, seems to be an update
		{
			// try to bypass the importation of already known attachments.
			// Check in database if the attachment with the same ID is linked to the testcase/testsuite with the same internal ID
			// The couple attachment ID + InternalID is used as a kind of signature to avoid duplicates. 
			// If signature is not precise enough, could add the use of attachment timestamp (date_added in XML file).
			$sql = " SELECT ATT.id from {$tables['attachments']} ATT " .
               " WHERE ATT.id='{$dbHandler->prepare_string($value['id'])}' " .
               " AND ATT.fk_id={$fk_Id} ";     
                   
			$rsx=$dbHandler->get_recordset($sql);
			// allow attachment import only if no record with the same signature have been found in database
			$addAttachment = ( is_null($rsx) || count($rsx) < 1 );
			if( $addAttachment === false ){ // inform user that the attachment has been skipped
			  if( !isset($duplicateAttachment[$value['id']]) )
			  {
				$duplicateAttachment[$value['id']]=sprintf($messages['attachment'],$value['name']);  
			  }
			  $resultMsg[] = array($tcaseName,$duplicateAttachment[$value['id']]); 
			}
			
		}else{
			// Creation
			$addAttachment = true;
		}
		
		if( $addAttachment )
		{
			$attachRepo = tlAttachmentRepository::create($dbHandler);
				
			$fileInfo = $attachRepo->createAttachmentTempFile( $value['content'] );	
			$fileInfo['name'] = $value['name'];
			$fileInfo['type'] = $value['file_type'];

			$attachRepo->insertAttachment( $fk_Id, $tables['nodes_hierarchy'], $value['title'], $fileInfo);
		}
	} //foreach
	 
	return $resultMsg;
}



/**
 * 
 *
 */
function importTestCasesFromSimpleXML(&$db,&$simpleXMLObj,$parentID,$tproject_id,$userID,$kwMap,$duplicateLogic)
{
  $resultMap = null;
  $xmlTCs = $simpleXMLObj->xpath('//testcase');
  $tcData = getTestCaseSetFromSimpleXMLObj($xmlTCs);
  if ($tcData)
  {
    $resultMap = saveImportedTCData($db,$tcData,$tproject_id,$parentID,$userID,$kwMap,$duplicateLogic);
  }  
  return $resultMap;
}

/**
 * 
 *
 * @internal revisions
 */
function getTestCaseSetFromSimpleXMLObj($xmlTCs)
{
  $tcSet = null;
  if (!$xmlTCs)
  {
    return $tcSet;
  }
    
  $jdx = 0;
  $loops2do=sizeof($xmlTCs);
  $tcaseSet = array();
  
  // $tcXML['elements'] = array('string' => array("summary","preconditions"),
    //             'integer' => array("node_order","externalid","execution_type","importance"));
  // $tcXML['attributes'] = array('string' => array("name"), 'integer' =>array('internalid'));

  // TICKET 4963: Test case / Tes suite XML format, new element to set author
  $tcXML['elements'] = array('string' => array("summary" => null,"preconditions" => null,
                                               "author_login" => null,"estimated_exec_duration" => null),
                             'integer' => array("node_order" => null,"externalid" => null,"is_open" => null,"active" => null,"status" => null,
                                                "execution_type" => null ,"importance" => null));
  $tcXML['attributes'] = array('string' => array("name" => 'trim'), 
                               'integer' =>array('internalid' => null));

  for($idx = 0; $idx < $loops2do; $idx++)
  {
    $dummy = getItemsFromSimpleXMLObj(array($xmlTCs[$idx]),$tcXML);
    $tc = $dummy[0]; 
        
    if ($tc)
    {
      // Test Case Steps
      $steps = getStepsFromSimpleXMLObj($xmlTCs[$idx]->steps->step);
      $tc['steps'] = $steps;

      $keywords = getKeywordsFromSimpleXMLObj($xmlTCs[$idx]->keywords->keyword);
      if ($keywords)
      {
        $tc['keywords'] = $keywords;
      }

      $cf = getCustomFieldsFromSimpleXMLObj($xmlTCs[$idx]->custom_fields->custom_field);
      if($cf)
      {
          $tc['customfields'] = $cf;  
      } 

      $requirements = getRequirementsFromSimpleXMLObj($xmlTCs[$idx]->requirements->requirement);
      if($requirements)
      {
          $tc['requirements'] = $requirements;  
      } 

	  $attachments = getAttachmentsFromSimpleXMLObj($xmlTCs[$idx]->attachments->attachment);
	  if($attachments)
	  {
		$tc['attachments'] = $attachments;  
	  }
	}
	$tcaseSet[$jdx++] = $tc;    
  }
  return $tcaseSet;
}


/**
 * 
 *
 * @internal revisions
 */
function getStepsFromSimpleXMLObj($simpleXMLItems)
{
  $itemStructure['elements'] = array('string' => array("actions"=>null,"expectedresults" => null),
                                     'integer' => array("step_number" => null,"execution_type" => null));
                               
  // 20110205 - franciscom - seems key 'transformations' is not managed on
  // getItemsFromSimpleXMLObj(), then ??? is useless???                               
  $itemStructure['transformations'] = array("expectedresults" => "expected_results");
                               
  $items = getItemsFromSimpleXMLObj($simpleXMLItems,$itemStructure);

    // need to do this due to (maybe) a wrong name choice for XML element
  if( !is_null($items) )
  {
    $loop2do = count($items);
    for($idx=0; $idx < $loop2do; $idx++)
    {
      $items[$idx]['expected_results'] = '';
      if( isset($items[$idx]['expectedresults']) )
      {
        $items[$idx]['expected_results'] = $items[$idx]['expectedresults'];
        unset($items[$idx]['expectedresults']);
      }
    }
  }
  return $items;
}

function getCustomFieldsFromSimpleXMLObj($simpleXMLItems)
{
  $itemStructure['elements'] = array('string' => array("name" => 'trim',"value" => 'trim'));
  $items = getItemsFromSimpleXMLObj($simpleXMLItems,$itemStructure);
  return $items;

}

function getRequirementsFromSimpleXMLObj($simpleXMLItems)
{
  $itemStructure['elements'] = array('string' => array("req_spec_title" => 'trim',
                                                       "doc_id" => 'trim' ,"title" => 'trim' ));
  $items = getItemsFromSimpleXMLObj($simpleXMLItems,$itemStructure);
  return $items;
}

function getAttachmentsFromSimpleXMLObj($simpleXMLItems)
{
  $itemStructure['elements'] = array('string' => array("id" => 'trim', "name" => 'trim',
                                                       "file_type" => 'trim' ,"title" => 'trim',
                                                       "date_added" => 'trim' ,"content" => 'trim' ));
  $items = getItemsFromSimpleXMLObj($simpleXMLItems,$itemStructure);
  return $items;
}

function getKeywordsFromSimpleXMLObj($simpleXMLItems)
{
  $itemStructure['elements'] = array('string' => array("notes" => null));
  $itemStructure['attributes'] = array('string' => array("name" => 'trim'));
  $items = getItemsFromSimpleXMLObj($simpleXMLItems,$itemStructure);
  return $items;
}


/*
  function: importTestSuite
  args :
  returns: 
  
  @internal revisions
  20120623 - franciscom - TICKET 5070 - test suite custom fields import
  
*/
function importTestSuitesFromSimpleXML(&$dbHandler,&$xml,$parentID,$tproject_id,
                     $userID,$kwMap,$importIntoProject = 0,$duplicateLogic)
{
  static $tsuiteXML;
  static $tsuiteMgr;
  static $myself;
  static $callCounter = 0;
  static $cfSpec;
  static $doCF;
  static $feedbackMsg;
  
  $feedbackMsg['attachment'] = lang_get('attachment_skipped_during_import');
  
  $resultMap = array();
  if(is_null($tsuiteXML) )
  {
    $myself = __FUNCTION__;
    $tsuiteXML = array();
    $tsuiteXML['elements'] = array('string' => array("details" => null),
                                 'integer' => array("node_order" => null));
	$tsuiteXML['attributes'] = array('string' => array("name" => 'trim'), 
									 'integer' =>array('id' => null));

    $tsuiteMgr = new testsuite($dbHandler);
    $doCF = !is_null(($cfSpec = $tsuiteMgr->get_linked_cfields_at_design(null,null,null,
                                       $tproject_id,'name')));
  }
  
  if($xml->getName() == 'testsuite')
  {
            

    // getItemsFromSimpleXMLObj() first argument must be an array
    $dummy = getItemsFromSimpleXMLObj(array($xml),$tsuiteXML);
    $tsuite = current($dummy);
	$tsuiteXMLID = $dummy[0]['id'];	
    $tsuiteID = $parentID;  // hmmm, not clear

    if ($tsuite['name'] != "")
    {
      // Check if Test Suite with this name exists on this container
      // if yes -> update instead of create
      $info = $tsuiteMgr->get_by_name($tsuite['name'],$parentID);
      if( is_null($info) )
      {
        $ret = $tsuiteMgr->create($parentID,$tsuite['name'],$tsuite['details'],$tsuite['node_order']);
        $tsuite['id'] = $ret['id'];
      }
      else
      {
        $ret = $tsuiteMgr->update(($tsuite['id'] = $info[0]['id']),$tsuite['name'],$tsuite['details'],
                                  null,$tsuite['node_order']);
        
      }
      unset($dummy);

      $tsuiteID = $tsuite['id'];  // $tsuiteID is needed on more code pieces => DO NOT REMOVE
      if (!$tsuite['id'])
      {
        return null;
      }  

      if($doCF)
      {
        $cf = getCustomFieldsFromSimpleXMLObj($xml->custom_fields->custom_field);
        if(!is_null($cf))
        {  
          processTestSuiteCF($tsuiteMgr,$xml,$cfSpec,$cf,$tsuite,$tproject_id);
        }  
      }

      if( $keywords = getKeywordsFromSimpleXMLObj($xml->keywords->keyword) )
      {
        $kwIDs = buildKeywordList($kwMap,$keywords);
        $tsuiteMgr->addKeywords($tsuite['id'],$kwIDs);
      }
			
	  if( $attachments = getAttachmentsFromSimpleXMLObj($xml->attachments->attachment) )
	  {
		if(!is_null($attachments))
		{  
			if ($tsuiteXMLID == "" && $info[0]['id']>0){ // testsuite id is optionnal in XML schema, id may has been retrieved from name during update
				$tsuiteXMLID = $info[0]['id'];
			}
		  $msg = processAttachments( $dbHandler, $tsuite['name'], $tsuiteXMLID, $tsuite['id'], $attachments, $feedbackMsg );
		  if( !is_null($msg) )
		  {
			$resultMap = array_merge($resultMap,$msg);
		  } 
		}  
	  }

      unset($tsuite);
    }
    else if($importIntoProject)
    {
      $tsuiteID = intval($tproject_id);
    }

    $childrenNodes = $xml->children();  
    $loop2do = sizeof($childrenNodes);
    
    for($idx = 0; $idx < $loop2do; $idx++)
    {
      $target = $childrenNodes[$idx];
      switch($target->getName())
      {
        case 'testcase':
          // getTestCaseSetFromSimpleXMLObj() first argument must be an array
          $tcData = getTestCaseSetFromSimpleXMLObj(array($target));
          $resultMap = array_merge($resultMap,
                       saveImportedTCData($dbHandler,$tcData,$tproject_id,
                                          $tsuiteID,$userID,$kwMap,$duplicateLogic));
          unset($tcData);
        break;

        case 'testsuite':
          $resultMap = array_merge($resultMap,
                       $myself($dbHandler,$target,$tsuiteID,$tproject_id,
                            $userID,$kwMap,$importIntoProject,$duplicateLogic));
        break;


        // Important Development Notice
        // Due to XML file structure, while looping
        // we will find also this children:
        // node_order,keywords,custom_fields,details
        //
        // It's processing to get and save values is done
        // on other pieces of this code.
        //
        // Form a logical point of view seems the better 
        // to consider and process here testcase and testsuite as children.
        //
      }      
    }
  }
  return $resultMap;
}


/**
 * 
 *
 * 
 **/
function initializeGui(&$dbHandler,&$argsObj)
{
  $guiObj = new stdClass();
  $guiObj->importLimitBytes = config_get('import_file_max_size_bytes');
  $guiObj->importLimitKB = ($guiObj->importLimitBytes / 1024);
  $guiObj->hitCriteria = $argsObj->hit_criteria;
  $guiObj->useRecursion = $argsObj->useRecursion;
  $guiObj->containerID = $argsObj->container_id;
  $guiObj->bImport = tlStringLen($argsObj->importType);
  $guiObj->bIntoProject = $argsObj->bIntoProject;
  $guiObj->resultMap = null;
  $guiObj->container_name = '';


  $dest_common = TL_TEMP_PATH . session_id(). "-importtcs";
  //$dest_files = array('XML' => $dest_common . ".xml");
$dest_files = array('XML' => $dest_common . ".xml",
    'XLS' => $dest_common . ".xls",'XMIND' => $dest_common . ".xmind");
  
$guiObj->dest = $dest_files['XML'];
  if(!is_null($argsObj->importType))
  {
    $guiObj->dest = $dest_files[$argsObj->importType];
  }
  
  $guiObj->file_check = array('status_ok' => 1, 'msg' => 'ok');
  
  if($argsObj->useRecursion)
  {
    $guiObj->import_title = lang_get('title_tsuite_import_to');  
    $guiObj->container_description = lang_get('test_suite');
  }
  else
  {
    $guiObj->import_title = lang_get('title_tc_import_to');
    $guiObj->container_description = lang_get('test_case');
  }

  if($argsObj->container_id)
  {
    $tree_mgr = new tree($dbHandler);
    $node_info = $tree_mgr->get_node_hierarchy_info($argsObj->container_id);
    unset($tree_mgr);    
    $guiObj->container_name = $node_info['name'];
    if($argsObj->container_id == $argsObj->tproject_id)
    {
      $guiObj->container_description = lang_get('testproject');
    }  
  }

  return $guiObj;
} 

/**
 * 
 *
 * @internal revisions
 * @since 1.9.4
 * 
 **/
function processTestSuiteCF(&$tsuiteMgr,$xmlObj,&$cfDefinition,&$cfValues,$tsuite,$tproject_id)
{

  static $messages;
    static $missingCfMsg;

  if(is_null($messages))
  {
      $messages = array();
      $messages['cf_warning'] = lang_get('no_cf_defined_can_not_import');
        $messages['start_warning'] = lang_get('start_warning');
      $messages['end_warning'] = lang_get('end_warning');
      $messages['testlink_warning'] = lang_get('testlink_warning');
      $messages['start_feedback'] = $messages['start_warning'] . "\n" . $messages['testlink_warning'] . "\n";
      $messages['cfield'] = lang_get('cf_value_not_imported_missing_cf_on_testproject');
      $messages['tsuite'] = lang_get('testsuite');
  }    

    $cf2insert=null;
    $resultMsg=null;
    foreach($cfValues as $value)
    {
       if( isset($cfDefinition[$value['name']]) )
       {
           $cf2insert[$cfDefinition[$value['name']]['id']]=array('type_id' => $cfDefinition[$value['name']]['type'],
                                                                 'cf_value' => $value['value']);         
       }
       else
       {
           if( !isset($missingCfMsg[$value['name']]) )
           {
               $missingCfMsg[$value['name']] = sprintf($messages['cfield'],$value['name'],$messages['tsuite']);
           }
           $resultMsg[] = array($tsuite['name'],$missingCfMsg[$value['name']]); 
       }
    }  
    $tsuiteMgr->cfield_mgr->design_values_to_db($cf2insert,$tsuite['id'],null,'simple');
    return $resultMsg;
}

/*
 function: importTestCaseDataFromSpreadsheet
convert a XLS file to XML, and call importTestCaseDataFromXML() to do import.

args: db [reference]: db object
fileName: XLS file name
parentID: testcases parent node (container)
tproject_id: testproject where to import testcases
userID: who is doing import.
bRecursive: 1 -> recursive, used when importing testsuites
[importIntoProject]: default 0
 
 
returns: map

rev:
Original code by lightbulb.
Refactoring by franciscom
*/
function importTestCaseDataFromSpreadsheet(&$db,$fileName,$parentID,$tproject_id,
		$userID,$bRecursive,$importIntoProject = 0)
{
	$xmlTCs = null;
	$resultMap  = null;
	$xml_filename=$fileName . '.xml';
	global $args;
// 		print_r($args);
	if($args->useRecursion)
	{
// 		echo "<script>alert('".$args->useRecursion."提示内容')</script>";
		create_xml_tsspec_from_xls($fileName,$xml_filename);
	}
	else
	{
		create_xml_tcspec_from_xls($fileName,$xml_filename);
	}
	$resultMap=importTestCaseDataFromXML($db,$xml_filename,$parentID,$tproject_id,$userID,
			$bRecursive,$importIntoProject);
	unlink($fileName);
	unlink($xml_filename);
	 
	return $resultMap;
}

// --------------------------------------------------------------------------------------
/*
 function: create_xml_tcspec_from_xls
 Using an XSL file, that contains testcase specifications
 creates an XML testlink test specification file.
  
 XLS format:
 Column       Description
 1          test case name
 2          summary
 3          steps
 4          expectedresults
  
 First row contains header:  name,summary,steps,expectedresults
 and must be skipped.
  
 args: xls_filename
 xml_filename
  
 returns:
 */
function create_xml_tcspec_from_xls($xls_filename, $xml_filename)
{
	//echo $xls_filename;
	
	define('FIRST_DATA_ROW',2);
	define('IDX_COL_NAME',3);
	define('IDX_COL_SUMMARY',4);
	define('IDX_COL_PRECONDITIONS',5);
	define('IDX_COL_STEPS',6);
	define('IDX_COL_EXPRESULTS',7);
	define('IDX_COL_IMPORTANCE',8);
	 
	$xls_handle = new Spreadsheet_Excel_Reader();
	 
	$xls_handle->setOutputEncoding('UTF-8');
	$xls_handle->read($xls_filename);
	$xls_rows = $xls_handle->sheets[0]['cells'];//获得每一行的数据合成数组
	$xls_row_qty = sizeof($xls_rows);
	//echo '$xls_row_qty:'.$xls_row_qty;
	 
	if($xls_row_qty < FIRST_DATA_ROW)
	{
		return;  // >>>----> bye!
	}

	$xmlFileHandle = fopen($xml_filename, 'w') or die("can't open file");
	fwrite($xmlFileHandle,"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
	fwrite($xmlFileHandle,"<testcases>\n");
	

	
	
	for($idx = FIRST_DATA_ROW;$idx <= $xls_row_qty; $idx++ )
	{
		$iStepNum = 1;
		//echo $idx;
		//$name = htmlspecialchars(iconv("CP1252","UTF-8",$xls_rows[$idx][IDX_COL_NAME]));
		$name = str_replace('?',"...",$xls_rows[$idx][IDX_COL_NAME]);//将用例名称格式化
		If ($name <> "")
		{
			$iStepNum = 1;
			if ($idx != FIRST_DATA_ROW)
			{
				fwrite($xmlFileHandle,"</steps>\n");
				fwrite($xmlFileHandle,"</testcase>\n");
			}
			fwrite($xmlFileHandle,"<testcase internalid=\"1\" name=" . '"' . $name. '"'.">\n");
			 
			// $summary = htmlspecialchars(iconv("CP1252","UTF-8",$xls_rows[$idx][IDX_COL_SUMMARY]));
			// 20090117 - contribution - BUGID 1992
			$summary = str_replace('?',"...",$xls_rows[$idx][IDX_COL_SUMMARY]);
			$summary = nl2p($summary);
			//$summary = nl2p(htmlspecialchars(iconv("CP1252","UTF-8", $summary)));
			fwrite($xmlFileHandle,"<summary><![CDATA[".$summary."]]></summary>\n");
			$preConditions = str_replace('?',"...",$xls_rows[$idx][IDX_COL_PRECONDITIONS]);
			$preConditions = nl2p($preConditions);
			//$preConditions = nl2p(htmlspecialchars(iconv("CP1252","UTF-8", $preConditions)));
			fwrite($xmlFileHandle,"<preconditions><![CDATA[" . $preConditions . "]]></preconditions>\n");
			$importance = str_replace('?',"...",$xls_rows[$idx][IDX_COL_IMPORTANCE]);
			$importance = nl2p($importance);
			$importance=str_replace(array("<p>","</p>"),"",$importance);
			switch ($importance)
			{
				case "高":
					$importance="3";
					break;
				case "中":
					$importance="2";
					break;
				case "低":
					$importance="1";
			}
			//$importance = nl2p(htmlspecialchars(iconv("CP1252","UTF-8", $importance)));
			fwrite($xmlFileHandle,"<importance><![CDATA[".$importance."]]></importance>\n");
			fwrite($xmlFileHandle,"<steps>\n");
			fwrite($xmlFileHandle,"<step>\n");
			fwrite($xmlFileHandle,"<step_number><![CDATA[".$iStepNum."]]></step_number>\n");
			$step = str_replace('?',"...",$xls_rows[$idx][IDX_COL_STEPS]);
			$step = nl2p($step);
			//$step = nl2p(htmlspecialchars(iconv("CP1252","UTF-8",$steps)));
			fwrite($xmlFileHandle,"<actions><![CDATA[".$step."]]></actions>\n");
			$expresults = str_replace('?',"...",$xls_rows[$idx][IDX_COL_EXPRESULTS]);
			$expresults = nl2p(htmlspecialchars($expresults));
			//$expresults = nl2p(htmlspecialchars(iconv("CP1252","UTF-8",$expresults)));
			fwrite($xmlFileHandle,"<expectedresults><![CDATA[".$expresults."]]></expectedresults>\n");
			fwrite($xmlFileHandle,"</step>\n");
		}
		else
		{
			echo "111111111111111111111"	;
			echo "<script>alert('"."提示内容')</script>";
			fwrite($xmlFileHandle,"<step>\n");
			$iStepNum++;
			fwrite($xmlFileHandle,"<step_number><![CDATA[".$iStepNum."]]></step_number>\n");
			$step = str_replace('?',"...",$xls_rows[$idx][IDX_COL_STEPS]);
			$step = nl2p($step);
			//$step = nl2p(htmlspecialchars(iconv("CP1252","UTF-8",$steps)));
			fwrite($xmlFileHandle,"<actions><![CDATA[".$step."]]></actions>\n");
			$expresults = str_replace('?',"...",$xls_rows[$idx][IDX_COL_EXPRESULTS]);
			$expresults = nl2p(htmlspecialchars($expresults));
			//$expresults = nl2p(htmlspecialchars(iconv("CP1252","UTF-8",$expresults)));
			fwrite($xmlFileHandle,"<expectedresults><![CDATA[".$expresults."]]></expectedresults>\n");
			fwrite($xmlFileHandle,"</step>\n");
		
			
		}
	}
	fwrite($xmlFileHandle,"</steps>\n");
	fwrite($xmlFileHandle,"</testcase>\n");
	fwrite($xmlFileHandle,"</testcases>\n");
	fclose($xmlFileHandle);

}

// --------------------------------------------------------------------------------------
/*
 function: create_xml_tsspec_from_xls
 Using an XSL file, that contains more than one sheet, with each sheet a testsuite
 creates an XML testlink test specification file.
  
 XLS format:
 First row contains header:  name,summary,steps,expectedresults
 and must be skipped.
  
 args: xls_filename
 xml_filename
  
 returns:
 */
function create_xml_tsspec_from_xls($xls_filename, $xml_filename)
{
	//echo $xls_filename;
	define('FIRST_DATA_ROW',2);
	define('IDX_COL_TESTSUITES',1);
	define('IDX_COL_NAME',3);
	define('IDX_COL_SUMMARY',4);
	define('IDX_COL_PRECONDITIONS',5);
	define('IDX_COL_STEPS',6);
	define('IDX_COL_EXPRESULTS',7);
	define('IDX_COL_IMPORTANCE',8);
	 
	$xls_handle = new Spreadsheet_Excel_Reader();
	 
	$xls_handle->setOutputEncoding('UTF-8');
	$xls_handle->read($xls_filename);
	$xmlFileHandle = fopen($xml_filename, 'w') or die("can't open file");
	fwrite($xmlFileHandle,"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
	fwrite($xmlFileHandle,"<testsuite name=\"\" >\n");

	//读取多张sheet
	for($sheet=0;$sheet<count($xls_handle->sheets);$sheet++)
	{
		//echo '<br>'.count($xls_handle->sheets);
		//echo '<br>'.$sheet;
		//if($xls_handle->sheets[$sheet] == SPREADSHEET_EXCEL_READER_TYPE_EOF)
		if($xls_handle->sheets[$sheet]['numRows'] <2)
			{
			
			continue;//return结束方法，break结束for循环，continue结束本次循环，判断sheet页的行数小于1
	}
			$xls_rows = $xls_handle->sheets[$sheet]['cells'];//获得每一行的数据，合成数组
			$xls_row_qty = sizeof($xls_rows);//获取行数
			$idx = FIRST_DATA_ROW;
			$suite = str_replace('?',"...",$xls_rows[$idx][IDX_COL_TESTSUITES]);
			$suiteArray = explode("/", $suite);
			$nodeNum = count($suiteArray);
			
			for($iSuite = 0; $iSuite < $nodeNum; $iSuite++)
			{
			fwrite($xmlFileHandle,"<testsuite name=" . '"' . $suiteArray[$iSuite]. '"'.">\n");
	}
	//fwrite($xmlFileHandle,"<testcases>\n");
	$iStepNum = 1;
	$float=0;
	//开始判断每一行了

	for($idx = FIRST_DATA_ROW;$idx <= $xls_row_qty; $idx++ )
	{
	$suite = str_replace('?',"...",$xls_rows[$idx][IDX_COL_TESTSUITES]);

	if($suite <> ""and $idx != FIRST_DATA_ROW)
	{
		if($float!=1){
		fwrite($xmlFileHandle,"</steps>\n");
		fwrite($xmlFileHandle,"</testcase>\n");
		}
	//fwrite($xmlFileHandle,"</testcases>\n");
		for($iSuite = 0; $iSuite < $nodeNum; $iSuite++)
		{
		fwrite($xmlFileHandle,"</testsuite>\n");
		}
		$suiteArray = explode("/", $suite);
		$nodeNum = count($suiteArray);
	
	
		for($iSuite = 0; $iSuite < $nodeNum; $iSuite++)
		{
		fwrite($xmlFileHandle,"<testsuite name=" . '"' . $suiteArray[$iSuite]. '"'.">\n");
		}
//fwrite($xmlFileHandle,"<testcases>\n");
	}elseif ($suite == ""and $idx == FIRST_DATA_ROW){
		continue;
	}
	//$name = htmlspecialchars(iconv("CP1252","UTF-8",$xls_rows[$idx][IDX_COL_NAME]));
	$name = str_replace('?',"...",$xls_rows[$idx][IDX_COL_NAME]);
	$name = htmlspecialchars($name);
	



	If ($name <> "")
	{
		$iStepNum = 1;
		
		//echo '<br>$idx:'.$idx;
		if ($idx != FIRST_DATA_ROW and $suite == "")
		
		{
		fwrite($xmlFileHandle,"</steps>\n");
		fwrite($xmlFileHandle,"</testcase>\n");
		}
		// fwrite($xmlFileHandle,"<testcase internalid=\"\" name=" . '"' . $name. '"'.">\n");
		fwrite($xmlFileHandle,"<testcase internalid=\"\" name=" . '"' . $name. '"'.">\n");
		// $summary = htmlspecialchars(iconv("CP1252","UTF-8",$xls_rows[$idx][IDX_COL_SUMMARY]));
		// 20090117 - contribution - BUGID 1992
		//$summary = $xls_rows[$idx][IDX_COL_SUMMARY];
		$summary = str_replace('?',"...",$xls_rows[$idx][IDX_COL_SUMMARY]);
		$summary = nl2p(htmlspecialchars($summary));
		fwrite($xmlFileHandle,"<summary><![CDATA[" . $summary . "]]></summary>\n");

		$externalid = str_replace('?',"...",$xls_rows[$idx][FIRST_DATA_ROW]);
		$externalid = nl2p(htmlspecialchars($externalid));
		$externalid=str_replace(array("<p>","</p>"),"",$externalid);
		fwrite($xmlFileHandle,"<externalid><![CDATA[" . $externalid . "]]></externalid>\n");


		$preConditions = str_replace('?',"...",$xls_rows[$idx][IDX_COL_PRECONDITIONS]);
		//$preConditions = nl2p(htmlspecialchars(iconv("CP1252","UTF-8", $preConditions)));
		$preConditions = nl2p(htmlspecialchars($preConditions));
		fwrite($xmlFileHandle,"<preconditions><![CDATA[" . $preConditions . "]]></preconditions>\n");
		$importance = str_replace('?',"...",$xls_rows[$idx][IDX_COL_IMPORTANCE]);
		//$importance = nl2p(htmlspecialchars(iconv("CP1252","UTF-8", $importance)));
		$importance = nl2p($importance);
		$importance=str_replace(array("<p>","</p>"),"",$importance);
			switch ($importance)
		{
		case "高":
		  $importance="3";
		  break;  
		case "中":
		  $importance="2";
		  break;
		case "低":
		  $importance="1";
		}
		
		fwrite($xmlFileHandle,"<importance><![CDATA[".$importance."]]></importance>\n");
		fwrite($xmlFileHandle,"<steps>\n");
		fwrite($xmlFileHandle,"<step>\n");
		fwrite($xmlFileHandle,"<step_number><![CDATA[".$iStepNum."]]></step_number>\n");
		$step = str_replace('?',"...",$xls_rows[$idx][IDX_COL_STEPS]);
		$step = nl2p(htmlspecialchars($step));
		//$step = nl2p(htmlspecialchars(iconv("CP1252","UTF-8",$step)));
		fwrite($xmlFileHandle,"<actions><![CDATA[".$step."]]></actions>\n");
		$expresults = str_replace('?',"...",$xls_rows[$idx][IDX_COL_EXPRESULTS]);
		//$expresults = nl2p(htmlspecialchars(iconv("CP1252","UTF-8",$expresults)));
		$expresults = nl2p(htmlspecialchars($expresults));
		fwrite($xmlFileHandle,"<expectedresults><![CDATA[".$expresults."]]></expectedresults>\n");
		fwrite($xmlFileHandle,"</step>\n");
		$float=0;
	}
 else
 	{
 	$step = str_replace('?',"...",$xls_rows[$idx][IDX_COL_STEPS]);
 	$step = nl2p($step);
 			if ($step<>""){
			 fwrite($xmlFileHandle,"<step>\n");
			$iStepNum++;
			 fwrite($xmlFileHandle,"<step_number><![CDATA[".$iStepNum."]]></step_number>\n");
			
			//$step = nl2p(htmlspecialchars(iconv("CP1252","UTF-8",$step)));
			 fwrite($xmlFileHandle,"<actions><![CDATA[".$step."]]></actions>\n");
			$expresults = str_replace('?',"...",$xls_rows[$idx][IDX_COL_EXPRESULTS]);
			$expresults = nl2p($expresults);
			//$expresults = nl2p(htmlspecialchars(iconv("CP1252","UTF-8",$expresults)));
			 fwrite($xmlFileHandle,"<expectedresults><![CDATA[".$expresults."]]></expectedresults>\n");
			fwrite($xmlFileHandle,"</step>\n");
 			}else{
 				if ($suite=="") {
 					$xls_row_qty++;
 					continue;
 				}
 					$float=1;
 			}
   	}
 }

 if($float==0){
 fwrite($xmlFileHandle,"</steps>\n");
 fwrite($xmlFileHandle,"</testcase>\n");
 }
 		//fwrite($xmlFileHandle,"</testcases>\n");
 for($iSuite = 0; $iSuite < $nodeNum; $iSuite++)
 		{
 		fwrite($xmlFileHandle,"</testsuite>\n");
}
}
fwrite($xmlFileHandle,"</testsuite>\n");
    fclose($xmlFileHandle);

	}
	
	

	
//xmind导入从这里开始
function importTestCaseDataFromXmind(&$db,$fileName,$parentID,$tproject_id,
	    $userID,$bRecursive,$importIntoProject = 0)
	{   sleep(10);
	    $xmlTCs = null;
	    $resultMap  = null;
	    //对传入的文件先修改后缀，再进行解压，最后删除无用的文件
	    //1.修改后缀
	    $temp = substr($fileName, strrpos($fileName, '.')+1); //获取后缀格式
	    if ($temp == "xmind")
	    {
	        $pos = strripos($fileName,'.'); //获取到文件名的位置
	        $filename_z = substr($fileName,0,$pos); //获取文件名
	        rename($fileName,$filename_z.'.zip'); //替换为zip后缀格式。
	        $file_name_zz=$filename_z.'.zip';
	    }else{
	        die("文件不是xmind后缀");
	    }
	    //2.解压zip文件
	    $zip_filename = array_key_exists('zip', $_GET) && $_GET['zip'] ? $_GET['zip'] : $file_name_zz;
// 	    $zip_filepath = str_replace('\\', '/', dirname(__FILE__)) . '/' . $zip_filename;
	    $zip_filepath = str_replace('\\', '/', $zip_filename);
// 	    echo $zip_filepath."1111";
	    if(!is_file($zip_filepath))
	    {
	        die("文件".$zip_filepath."不存在!");
	    }
	    $zip = new ZipArchive();
	    $rs = $zip->open($zip_filepath);
	    if($rs !== TRUE)
	    {
	        die("解压失败!导入的文件可能不是xmind后缀！Error Code:". $rs);
	    }else{
// 	        die("解压成功！");
	    }
	    $zip->extractTo("./");
	    $zip->close();
// 	    echo $zip_filename."解压成功!";
// 	    $zip_filepath0 = preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $zip_filepath);
// 	    echo $zip_filepath0."22222";
	    //3.删除无用文件
	    /*
	    unlink($zip_filepath0."/meta.xml");
	    unlink($zip_filepath0."/styles.xml");
	    deldir($zip_filepath0."/Revisions");
	    deldir($zip_filepath0."/Thumbnails");
	    deldir($zip_filepath0."/META-INF");
	    deldir($fileName);
	    unlink($zip_filepath);
	    */
	    unlink("meta.xml");
	    unlink("styles.xml");
	    deldir("Revisions");
	    deldir("Thumbnails");
	    deldir("META-INF");
	    
	    unlink($zip_filepath);
	    
	    //content.xml作为原始文件进行转换
// 	    $contentfile="content.xml";
	    	    
	    $xml_filename=$fileName . '.xml';
	    
	    $domnew = new DOMDocument('1.0','UTF-8');
	    $domnew->formatOutput=true;//格式化输出
	    
	    //xml 文件中去掉部分内容后才能被识别加载
	    $data = file_get_contents('content.xml');
	    $data_new = str_replace('xmlns="urn:xmind:xmap:xmlns:content:2.0"', '', $data);
	    file_put_contents('content.xml', $data_new);
	   // sleep(15);
	    // 首先要建一个DOMDocument对象
	    $dom=new DOMDocument();
	    // 加载Xml文件
	    $dom->load('content.xml');
	   // sleep(15);
	    //通过xpath方式解析xml文档
	    $xp=new DOMXPath($dom);
	   // sleep(2);
	    
	    /*
	    //这段为调试的代码
	    $testsuite_id00='sheet/topic/title';//找到第一层目录所在路径
	    $rst700=$xp->query($testsuite_id00);
	    $testsuite_val00= $rst700->item(0)->nodeValue;//获取第一层目录值
	    echo $testsuite_val00."<br />";
	    */
	    
	    global $args;
	    if($args->useRecursion)
	    {
	        create_xml_tsspec_from_xmind($xp,$domnew,$xml_filename);
	        $domnew->save($xml_filename);
	    }
	    else
	    {
	        create_xml_tcspec_from_xmind($xp,$domnew,$xml_filename);
	        $domnew->save($xml_filename);
	    }
	    $resultMap=importTestCaseDataFromXML($db,$xml_filename,$parentID,$tproject_id,$userID,
	        $bRecursive,$importIntoProject);
	     	    unlink('content.xml');
// 	    unlink('content.xml');
	    unlink($xml_filename);
	    
	    return $resultMap;
	}
	
	//删除文件夹的方法
	function deldir($dir){
	    $current_dir = opendir($dir);
	    while($entryname = readdir($current_dir)){
	        if(is_dir("$dir/$entryname") and ($entryname != "." and $entryname!="..")){
	            deldir("${dir}/${entryname}");
	        }elseif($entryname != "." and $entryname!=".."){
	            unlink("${dir}/${entryname}");
	        }
	    }
	    closedir($current_dir);
	    rmdir($dir);
	}
	
	
	//xmind转换为用于导入用例集的xml的方法
	function create_xml_tsspec_from_xmind($xp,$domnew,$xml_filename){
// 	    echo '方法2';
	    // 	    $title_path ="//marker-refs/preceding-sibling::title";
	    // 	    $rst2_1=$xp->query($title_path);
	    $title_patha="//marker-ref[@marker-id]";
	    $rst2_1a=$xp->query($title_patha);
	    
	    $arr_path = Array();//创建数组用于保存用例的上一层目录testsuite的路径
	    $arr_suite=Array();//创建数组用于保存suite路径和对应的testsuite
// 	    echo "方法2的".($rst2_1a->length);
	    for($i=0;$i<($rst2_1a->length);$i++){
// 	        echo '方法2的for循环';
	        //获取到每个用例的上一层目录的路径，如下testsuite_num
	        $refspath=$rst2_1a->item($i)->getNodePath();
	        $refspath_absolute_jq0 = preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $refspath);
	        $refspath_absolute_jq1 = preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $refspath_absolute_jq0);
	        $refspath_absolute_jq2 = preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $refspath_absolute_jq1);
	        $refspath_absolute_jq3 = preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $refspath_absolute_jq2);
	        $refspath_absolute_jq4 = preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $refspath_absolute_jq3);
	        $testsuite_num=$refspath_absolute_jq4."/title";
	        $rst8=$xp->query($testsuite_num);
	        //获取testsuite值
	        $testsuite_v = $rst8->item(0)->nodeValue;
            //创建一个用例testcase
            $testcase=createTestcase($i,$refspath_absolute_jq1,$xp,$domnew,$xml_filename);
// 	        echo "方法2的testsuite_v".$testsuite_v;
	        //如果目录不存在，则创建目录;判断目录是否存在的依据是testsuite_num是否在数组中存在
	        if(!(in_array($testsuite_num, $arr_path))){
// 	            echo "方法2的if语句";
	            //创建testsuite节点和name属性，并将name的属性值赋给name
	            $testsuite=$domnew->createElement('testsuite');
	            $testsuite_name=$domnew->createAttribute('name');
	            $testsuite_value=$domnew->createTextNode($testsuite_v);
	            $testsuite_name->appendChild($testsuite_value);
	            $testsuite->appendChild($testsuite_name);
	            
	            $arr_path[$i]=$testsuite_num;
                //将用例加入到所属最近的testsuite节点下
                $testsuite->appendChild($testcase);
                $domnew->appendChild($testsuite);
                $arr_suite[$testsuite_num]=$testsuite;
	            
	        }else{
                $arr_suite[$testsuite_num]->appendChild($testcase);
                $domnew->appendChild($testsuite);
            }
// 	        var_dump($arr_path);
	        //将用例加入到所属最近的testsuite节点下
	 //       $testcase=createTestcase($i,$refspath_absolute_jq1,$xp,$domnew,$xml_filename);
	 //       $testsuite->appendChild($testcase);
	 //       $arr_suite[$testsuite_num]=$testsuite;
	        
	    }
	    putParentTestsuite($arr_suite,$xp,$domnew,$xml_filename);//生成所有的各级父testsuite
	    $domnew->save($xml_filename);
// 	    echo 'putTestsuite的保存';
	}
	//xmind转换为用于导入用例的xml的方法
	function create_xml_tcspec_from_xmind($xp,$domnew,$xml_filename){
// 	    echo '方法3';
	    //获取用例标题并存入
	    $refs_patha="//marker-refs";
	    $rst2_a=$xp->query($refs_patha);
	    //创建testcases的elements
	    $testcases=$domnew->createElement('testcases');
	    $domnew->appendChild($testcases);
	    
	    for($i=0;$i<($rst2_a->length);$i++){
	        //用下面的方法获取用例标题
	        $refs_1=$rst2_a->item($i)->getNodePath();
	        $refs_2=preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $refs_1);
	        $refs_3=$refs_2."/title";
	        $title_query=$xp->query($refs_3);
	        //获取用例标题
	        $name_v=$title_query->item(0)->nodeValue;
	        //创建testcase的element
	        $testcase=$domnew->createElement('testcase');
	        //创建testcase的属性name
	        $name = $domnew->createAttribute('name');
	        //定义name的属性值，name的属性值为用例标题
	        $name_value=$domnew->createTextNode($name_v);
	        //将来属性值赋给name
	        $name->appendChild($name_value);
	        //name为testcase的子节点
	        $testcase->appendChild($name);
	        
	        
	        //获取每个用例的路径
	        $testcase_path=$title_query->item(0)->getNodePath();
	        $steps=getSteps($i,$testcase_path,$xp,$domnew,$xml_filename);
	        $testcase->appendChild($steps);
	        //获取用例优先级
	        $testimportance = createImportance($i,$xp,$domnew,$xml_filename);
	        $testcase->appendChild($testimportance);
	        //获取用例的前置条件
	        $testpreconditions=createPreconditions($i,$refs_2,$xp,$domnew,$xml_filename);
	        $testcase->appendChild($testpreconditions);
	        //将testcase加入到testcases节点下
	        $testcases->appendChild($testcase);
	    }
	    $domnew->appendChild($testcases);
	   // echo 'putTestcase的最后调用';
	}
	
    
	//创建测试用例，包括用例标题，用例优先级，前置条件，步骤
	function createTestcase($i,$refspath_absolute_jq1,$xp,$domnew,$xml_filename){
// 	    echo '方法5';
	    //获取用例标题并存入
// 	    $title_path ="//marker-refs/preceding-sibling::title";
	    $title_path= $refspath_absolute_jq1."/title";
	    $rst2=$xp->query($title_path);
	    
	    //     for($i=0;$i<($rst2->length);$i++){
	    //获取用例标题
	    $name_v=$rst2->item(0)->nodeValue;
	    //创建testcase的element
	    $testcase=$domnew->createElement('testcase');
	    //创建testcase的属性name
	    $name = $domnew->createAttribute('name');
	    //定义name的属性值，name的属性值为用例标题
	    $name_value=$domnew->createTextNode($name_v);
	    //将来属性值赋给name
	    $name->appendChild($name_value);
	    //name为testcase的子节点
	    $testcase->appendChild($name);
	    
	    
	    //获取每个用例的路径
	    $testcase_path=$rst2->item(0)->getNodePath();
	    $steps=getSteps($i,$testcase_path,$xp,$domnew,$xml_filename);
	    $testcase->appendChild($steps);
	    //获取用例优先级
	    $testimportance = createImportance($i,$xp,$domnew,$xml_filename);
	    $testcase->appendChild($testimportance);
	    //获取用例的前置条件
	    $testpreconditions=createPreconditions($i,$refspath_absolute_jq1,$xp,$domnew,$xml_filename);
	    $testcase->appendChild($testpreconditions);
// 	    $domnew->save($xml_filename);
	    return $testcase;
	    //     }
	}
	
	//获取steps(根据topic数量)
	function getSteps($i,$testcase_path,$xp,$domnew,$xml_filename){
// 	    echo '方法6';
	    //截取topic路径
	    $topic_path = preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $testcase_path);
	    //拼装children下的topic路径
	    $steps_num= $topic_path."/children/topics/topic";
	    //查询每个用例的用例步骤数
	    $rst4=$xp->query($steps_num);
	    //创建每个用例的步骤
	    $steps=$domnew->createElement('steps');
	    
	    for($j=0;$j<($rst4->length);$j++){
	        $step=$domnew->createElement('step');//创建用例的step的element
	        //重要：
	        $steps->appendChild($step);//step为steps的子节点
	        //创建step_number的element,并赋值给step_number
	        $step_number=$domnew->createElement('step_number');
	        $step_number_value=$domnew->createTextNode($j+1);
	        $step_number->appendChild($step_number_value);
	        $step->appendChild($step_number);
	        //获取每个用例每条步骤的步骤和结果
	        $array_step=getstep_action_result($j,$steps_num,$xp,$domnew,$xml_filename);
	        //创建action的element并将来值赋给该节点
	        $actions=$domnew->createElement('actions');
	        $actions_value=$domnew->createCDATASection(current($array_step));
	        $actions->appendChild($actions_value);
	        $step->appendChild($actions);
	        //创建expectedresults的element并将值赋给该节点
	        
	        $expectedresults=$domnew->createElement('expectedresults');
	        $expectedresults_value=$domnew->createCDATASection(end($array_step));
	        $expectedresults->appendChild($expectedresults_value);
	        $step->appendChild($expectedresults);
	        /*结合steps需要优化
	         if(end($array_step)=="0"){
	         $expectedresults=$domnew->createElement('expectedresults');
	         $expectedresults_value=$domnew->createCDATASection('');
	         $expectedresults->appendChild($expectedresults_value);
	         $step->appendChild($expectedresults);
	         }else{
	         $expectedresults=$domnew->createElement('expectedresults');
	         $expectedresults_value=$domnew->createCDATASection(end($array_step));
	         $expectedresults->appendChild($expectedresults_value);
	         $step->appendChild($expectedresults);
	         }*/
	        
	    }
// 	    $domnew->save($xml_filename);
	    return $steps;
	    
	}
	
	//作用：返回每个用例每步的步骤和结果
	function getstep_action_result($j,$steps_num,$xp,$domnew,$xml_filename){
// 	    echo '方法7';
	    $array_step=Array();//定义数组，存放每个用例每步的步骤和结果
	    
	    $step_atcion_num=$steps_num.'/title';//每个用例的步骤所在路径
//	    $step_result_num=$steps_num.'/children/topics/topic/title';//每个用例的结果所在路径

            //20180613
            $step_result_num2 = preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $steps_num);
            $step_result_num3=$step_result_num2.'/topic['.($j+1).']/children/topics/topic/title';
            $step_result_num4=$step_result_num2.'/topic['.($j+1).']/children/topics/topic';

	    $rst5=$xp->query($step_atcion_num);//计算每个用例的所有步骤的步骤数
	    $step_action=$rst5->item($j)->nodeValue;//获取每个用例的每一步的步骤部分
	    $array_step[0]=$step_action;
//	    $rst6=$xp->query($step_result_num);//计算每个用例的所有步骤的结果数
//	    $step_result=$rst6->item($j)->nodeValue;//获取每个用例的每一步的结果部分

            //20180613
        $rst61=$xp->query($step_result_num3);//计算每个用例的每一个步骤的结果总数
        $step_result_all='';//存放每条步骤的一个或多个结果
        if(($rst61->length)==1){
            $step_result_num5=$step_result_num4.'[1]/title';
            $rst7=$xp->query($step_result_num5);
            $step_result20=$rst7->item(0)->nodeValue;
            $step_result_all=$step_result20;
        }else if(($rst61->length)>1){
            for($i=0;$i<($rst61->length);$i++){
                $step_result_num5=$step_result_num4.'['.($i+1).']/title';
                $rst7=$xp->query($step_result_num5);
                $step_result20=$rst7->item(0)->nodeValue;
                $step_result_all=$step_result_all.$step_result20.';'.'<br>';
            }
        }else{
            $step_result_all=$array_step[0];//如果只有步骤没有结果，那么将步骤的值赋给结果(易货嘀需求)
            }
	    /*这段代码需要调试
	     if(($array_step[0] != null)&& $rst6->length==1 &&($rst6->item($j)->nodeValue)==null){
	     $array_step[1]="0";
	     }else if($rst6->length==2 && ($rst6->item($j)->nodeValue)!=null){
	     $step_result=$rst6->item($j)->nodeValue;//获取每个用例的每一步的结果部分
	     $array_step[1]=$step_result;
	     }*/
	    $array_step[1]=$step_result_all;
// 	    $domnew->save($xml_filename);
	    return $array_step;//返回每个用例每步的步骤和结果
	}
	
	
	//创建用例的优先级
	function createImportance($i,$xp,$domnew,$xml_filename){
// 	    echo '方法8';
	    //查询属性为marker-id的元素
	    $marker_cx="//@marker-id";
	    $rst1=$xp->query($marker_cx);
	    
	    //获取用例等级并存入对应的testcase中
	    //     for($i=0;$i<($rst1->length);$i++){
	    //截取用例等级
	        $importance_id= substr(($rst1->item($i)->nodeValue), -1);
	        //xml识别用例等级3为重要用例，用例等级1为低级用例；按照用户习惯，需要将1转为3,3转为1;填其他数值都设置为1
	        if($importance_id=="3"){
	            $importance_id_new="1";
	        }
	        else if($importance_id=="1"){
	            $importance_id_new="3";
	        }
	        else if($importance_id=="2"){
	            $importance_id_new="2";
	        }else{
	            $importance_id_new="1";
	        }
	        //创建importance节点
	        $importance=$domnew->createElement('importance',$importance_id_new);
	        //将importance节点加入到对应的testcase节点中
	        //         $domnew->getElementsByTagName('testcase')->item($i)->appendChild($importance);
// 	        $domnew->save($xml_filename);
	        return $importance;
	        //     }
	        
	    }

	    
	    //创建用例的前置条件
	    function createPreconditions($i,$refspath_absolute_jq1,$xp,$domnew,$xml_filename){
// 	        echo '方法9';
	        /*
	         * 获取用例的前置条件个数(当前节点的兄弟节点following-sibling),此方法取出的数据有时候不对，用下面的方法
	         $preconditions_id ="//marker-refs/following-sibling::notes/child::plain";
	         $rst3=$xp->query($preconditions_id);
	         */
	        
	        
	        //拼装plain的路径
	        $plain_path1=$refspath_absolute_jq1."/notes/plain";
	        //查询出plain(前置条件)的路径的值
	        $rst3=$xp->query($plain_path1);
	        
	        
	        //获取用例的前置条件并保存，如果没有前置条件，则创建的$preconditions_value为空
	        if($rst3->length==0){
	            //创建preconditions的element和value
	            $preconditions=$domnew->createElement('preconditions');
	            $preconditions_value=$domnew->createCDATASection('');
	            //将前置条件加入到testcase节点中
	            $preconditions->appendChild($preconditions_value);
// 	            $domnew->save($xml_filename);
	            return $preconditions;
	        }else{
	            //获取到每个前置条件
	            $preconditions_tr=$rst3->item(0)->nodeValue;
	            //创建preconditions的element和value
	            $preconditions=$domnew->createElement('preconditions');
	            $preconditions_value=$domnew->createCDATASection($preconditions_tr);
	            //将前置条件加入到testcase节点中
	            $preconditions->appendChild($preconditions_value);
// 	            $domnew->save($xml_filename);
	            return $preconditions;
	        }
	    }
	    
	    //生成所有的各级父testsuite
	    function putParentTestsuite($arr_suite,$xp,$domnew,$xml_filename,$option=0){
// 	        echo '方法10';
	        $arr_key=array_keys($arr_suite);//取出数组中的key值组成一个一维数组$arr_key
	        //根据每个直接testsuite创建父testsuite
	        for($i=$option;$i<count($arr_suite);$i++){
	            if($arr_key[$i]=="/xmap-content/sheet/topic/title"){
	                continue;
	            }
	            if($arr_key[$i]!="/xmap-content/sheet/topic/title"){
	                $key1=preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $arr_key[$i]);
	                $key2=preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $key1);
	                $key3=preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $key2);
	                $key4=preg_replace('/(.*)\/{1}([^\/]*)/i', '$1', $key3);
	                $new_key=$key4."/title";//获取当前testsuite的父testsuite在xml原始文件中的路径
	                if(in_array($new_key, $arr_key)){//如果这个父路径在当前数组中存在，则将当前testsuite作为存在的父路径对应的testsuite的子级
	                    $arr_suite[$new_key]->appendChild($arr_suite[$arr_key[$i]]);
	                    continue;
	                }
	                else{//如果这个父路径在当前数组中存在，则要创建这个父路径对应的testsuite
	                    $new_key_query=$xp->query($new_key);
	                    $new_key_value=$new_key_query->item(0)->nodeValue;//获取父路径对应的name值，即父testsuite的name
	                    //创建父testsuite和对应的name属性
	                    $testsuite_parent=$domnew->createElement('testsuite');
	                    $testsuite_name=$domnew->createAttribute('name');
	                    $testsuite_name_val=$domnew->createTextNode($new_key_value);
	                    $testsuite_name->appendChild($testsuite_name_val);
	                    $testsuite_parent->appendChild($testsuite_name);
	                    $testsuite_parent->appendChild($arr_suite[$arr_key[$i]]);
	                    $domnew->appendChild($testsuite_parent);
	                    //                 var_dump($testsuite_parent);
	                    //将父路径与其对应的testsuite加入到原数组中并重新传参回调本函数
	                    $arr_suite[$new_key]=$testsuite_parent;
	                    
	                    //                 $key_position = array_search($arr_key[$i], $arr_key);
	                    putParentTestsuite($arr_suite,$xp,$domnew,$xml_filename,$i);
	                    break;
	                    
	                }
	            }
	        }
// 	        $domnew->save($xml_filename);
	    }
	    function getReqSpecSet(&$dbHandler,$tproject_id)
	    {
	    	$debugMsg = __FUNCTION__;
	    
	    	$tables = tlObjectWithDB::getDBTables(array('req_specs','nodes_hierarchy','requirements'));
	    
	    	// get always Latest Revision Req. Spec Title
	    	$sql = "/* $debugMsg */ " .
	    	" SELECT RSPEC.id, NHRSPEC.name AS title, RSPEC.doc_id AS rspec_doc_id, REQ.req_doc_id " .
	    	" FROM {$tables['req_specs']} RSPEC " .
	    	" JOIN {$tables['nodes_hierarchy']} NHRSPEC ON NHRSPEC.id = RSPEC.id " .
	    	" JOIN {$tables['requirements']} REQ ON REQ.srs_id = RSPEC.id " .
	    	" WHERE RSPEC.testproject_id = " . intval($tproject_id) .
	    	" ORDER BY RSPEC.id,title";
	    
	    	$rs = $dbHandler->fetchRowsIntoMap($sql,'req_doc_id');
	    
	    	return $rs;
	    }
?>
