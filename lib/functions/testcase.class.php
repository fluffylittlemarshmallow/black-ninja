<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * @filesource 	testcase.class.php
 * @package 	  TestLink
 * @author 		  Francisco Mancardi (francisco.mancardi@gmail.com)
 * @copyright 	2005-2012, TestLink community 
 * @link 		    http://www.teamst.org/index.php
 *
 * @internal revisions
 * @since 2.0
 * 
 */

/** related functionality */
require_once( dirname(__FILE__) . '/requirement_mgr.class.php' );
require_once( dirname(__FILE__) . '/assignment_mgr.class.php' );
require_once( dirname(__FILE__) . '/attachments.inc.php' );
require_once( dirname(__FILE__) . '/users.inc.php' );

/** list of supported format for Test case import/export */
$g_tcFormatStrings = array ("XML" => lang_get('the_format_tc_xml_import'));

/**
 * class for Test case CRUD
 * @package 	TestLink
 */
class testcase extends tlObjectWithAttachments
{
  const AUTOMATIC_ID=0;
  const DEFAULT_ORDER=0;
  const ALL_VERSIONS=0;
  const LATEST_VERSION=-1;
  const AUDIT_OFF=0;
  const AUDIT_ON=1;
  const CHECK_DUPLICATE_NAME=1;
  const DONT_CHECK_DUPLICATE_NAME=0;
  const ENABLED=1;
  const ALL_TESTPLANS=null;
  const ANY_BUILD=null;
  const GET_NO_EXEC=1; 
  const ANY_PLATFORM=null;
  const NOXMLHEADER=true;
  const EXECUTION_TYPE_MANUAL = 1;
  const EXECUTION_TYPE_AUTO = 2;
	      
        
    
	/** @var database handler */
	var $db;
	var $tree_manager;
	var $tproject_mgr;

	var $node_types_descr_id;
	var $node_types_id_descr;
	var $my_node_type;

	var $assignment_mgr;
	var $assignment_types;
	var $assignment_status;

	var $cfield_mgr;

	var $import_file_types = array("XML" => "XML", "XLS" => "XLS" );
	var $export_file_types = array("XML" => "XML");
	var $execution_types = array();
	var $cfg;

	/**
	 * testplan class constructor
	 * 
	 * @param resource &$db reference to database handler
	 */
	function __construct(&$db)
	{
		$this->db = &$db;
		$this->tproject_mgr = new testproject($this->db);
		$this->tree_manager = &$this->tproject_mgr->tree_manager;

		$this->node_types_descr_id=$this->tree_manager->get_available_node_types();
		$this->node_types_id_descr=array_flip($this->node_types_descr_id);
		$this->my_node_type=$this->node_types_descr_id['testcase'];

		$this->assignment_mgr=New assignment_mgr($this->db);
		$this->assignment_types=$this->assignment_mgr->get_available_types();
		$this->assignment_status=$this->assignment_mgr->get_available_status();

		$this->cfield_mgr = new cfield_mgr($this->db);

		$this->execution_types = $this->get_execution_types();

    $this->cfg = new stdClass();
    $this->cfg->testcase = config_get('testcase_cfg');
		$this->cfg->results = config_get('results');

		// ATTENTION:
		// second argument is used to set $this->attachmentTableName,property that this calls
		// get from his parent
		parent::__construct($this->db,"nodes_hierarchy");
	}


	/*
	  function: get_export_file_types
	            getter
	
	  args: -
	
	  returns: map
	           key: export file type code
	           value: export file type verbose description
	
	*/
	function get_export_file_types()
	{
	    return $this->export_file_types;
	}
	
	/*
	  function: get_impor_file_types
	            getter
	
	  args: -
	
	  returns: map
	           key: import file type code
	           value: import file type verbose description
	
	*/
	function get_import_file_types()
	{
	    return $this->import_file_types;
	}
	
	/*
	   function: get_execution_types
	             getter
	
	   args: -
	
	   returns: map
	            key: execution type code
	            value: execution type verbose description
	
	*/
	static function get_execution_types()
	{
		  return array(self::EXECUTION_TYPE_MANUAL => lang_get('manual'),
                   self::EXECUTION_TYPE_AUTO => lang_get('automated'));
	}



	function create($item,$options=null)
	{
		// transform item into old interface
	    $keywords_id = '';
	    $tc_order = self::DEFAULT_ORDER;
	    $id = self::AUTOMATIC_ID;
        $execution_type = TESTCASE_EXECUTION_TYPE_MANUAL;
        $importance = 2;
        $status = null;
        $estimated_execution_duration = null;
	    extract($item);

		return $this->_create($parent_id,$name,$summary,$preconditions,$steps,$author_id,
	                   		  $keywords_id,$tc_order,$id,$execution_type,$importance,
	                   		  $status,$estimated_execution_duration,$options);
	}
	
	
	/**
	 * create a test case
	 *
	 * @internal revisions
	 *
	 * 20100905 - franciscom - added new key on ret value 'tcversion_id';
	 */
	// function create($parent_id,$name,$summary,$preconditions,$steps,$author_id,
	//                 $keywords_id='',$tc_order=self::DEFAULT_ORDER,$id=self::AUTOMATIC_ID,
    //                 $execution_type=TESTCASE_EXECUTION_TYPE_MANUAL,
    //                 $importance=2,$status=null, $options=null)

	function _create($parent_id,$name,$summary,$preconditions,$steps,$author_id,
	                 $keywords_id='',$tc_order=self::DEFAULT_ORDER,$id=self::AUTOMATIC_ID,
                     $execution_type=TESTCASE_EXECUTION_TYPE_MANUAL,
                     $importance=2,$status=null,$estimated_execution_duration=null,$options=null)

	{
		$status_ok = 1;

	    $my['options'] = array( 'check_duplicate_name' => self::DONT_CHECK_DUPLICATE_NAME, 
	                            'action_on_duplicate_name' => 'generate_new');
	                            
	                            
	    $my['options'] = array_merge($my['options'], (array)$options);
		
		$ret = $this->create_tcase_only($parent_id,$name,$tc_order,$id,$my['options']);
		if($ret["status_ok"])
		{
			if(trim($keywords_id) != "")
			{
				$a_keywords = explode(",",$keywords_id);
				$this->addKeywords($ret['id'],$a_keywords);
			}
			
			$version_number = 1;
			if(isset($ret['version_number']) && $ret['version_number'] < 0)
			{
				// We are in the special situation we are only creating a new version,
				// useful when importing test cases. Need to get last version number.
				// I do not use create_new_version() because it does a copy ot last version
				// and do not allow to set new values in different fields while doing this operation.
				$last_version_info = $this->get_last_version_info($ret['id'],array('output' => 'minimun'));
				$version_number = $last_version_info['version']+1;
				$ret['msg'] = sprintf($ret['msg'],$version_number);       
				$ret['version_number']=$version_number;
			}
			// Multiple Test Case Steps Feature
			$op = $this->create_tcversion($ret['id'],$ret['external_id'],$version_number,$summary,
			                              $preconditions,$steps,$author_id,$execution_type,
			                              $importance,$status,$estimated_execution_duration);
			
			$ret['msg'] = $op['status_ok'] ? $ret['msg'] : $op['msg'];
			$ret['tcversion_id'] = $op['status_ok'] ? $op['id'] : -1;
		}
		return $ret;
	}

	/*
	20061008 - franciscom
	           added [$check_duplicate_name]
	                 [$action_on_duplicate_name]
	
	20060725 - franciscom - interface changes
	           [$order]
	
	           [$id]
	           0 -> the id will be assigned by dbms
	           x -> this will be the id
	                Warning: no check is done before insert => can got error.
	
	return:
	       $ret['id']
	       $ret['external_id']
	       $ret['status_ok']
	       $ret['msg'] = 'ok';
		     $ret['new_name']
		     
	rev: 
		20100503 - franciscom - BUGID 3374
		20100409 - franciscom - improved check on name len.
								BUGID 3367: Error after trying to copy a test case that 
								the name is in the size limit.
		20090120 - franciscom - added new action_on_duplicate_name	     
	*/
	function create_tcase_only($parent_id,$name,$order=self::DEFAULT_ORDER,$id=self::AUTOMATIC_ID,
	                           $options=null)
	{
	
		//echo __FUNCTION__;new dBug($options);die();
		
		$dummy = config_get('field_size');
		$name_max_len = $dummy->testcase_name;
		$name = trim($name);
        $originalNameLen = tlStringLen($name);

        $getOptions = array();
		$ret = array('id' => -1,'external_id' => 0, 'status_ok' => 1,'msg' => 'ok', 
		             'new_name' => '', 'version_number' => 1, 'has_duplicate' => false);

	    $my['options'] = array( 'check_duplicate_name' => self::DONT_CHECK_DUPLICATE_NAME, 
	                            'action_on_duplicate_name' => 'generate_new'); 
	                            
	    $my['options'] = array_merge($my['options'], (array)$options);
       
	    $doCreate=true;
	 	if ($my['options']['check_duplicate_name'])
		{
			$algo_cfg = $this->cfg->testcase->duplicated_name_algorithm;
			$getOptions['check_criteria'] = ($algo_cfg->type == 'counterSuffix') ? 'like' : '='; 
			$getOptions['access_key'] = ($algo_cfg->type == 'counterSuffix') ? 'name' : 'id'; 
	        $itemSet = $this->getDuplicatesByName($name,$parent_id,$getOptions);	
	        
			if( !is_null($itemSet) && ($siblingQty=count($itemSet)) > 0 )
			{
		      $ret['has_duplicate'] = true;
			  switch($my['options']['action_on_duplicate_name'])
			  {
				    case 'block':
		            	$doCreate=false;
				   		$ret['status_ok'] = 0;
				    	$ret['msg'] = sprintf(lang_get('testcase_name_already_exists'),$name);
				    break;
				    
				    case 'generate_new':
				        $doCreate=true;
				        
			            switch($algo_cfg->type)
			            {
			            	case 'stringPrefix':
			            		$name = $algo_cfg->text . " " . $name ;
			            		$final_len = strlen($name);
			            		if( $final_len > $name_max_len)
			            		{
			            			$name = substr($name,0,$name_max_len);
			            		}
			            	break;
			            	
			            	case 'counterSuffix':
			            	    $mask =  !is_null($algo_cfg->text) ? $algo_cfg->text : '#%s';
            	            	$nameSet = array_flip(array_keys($itemSet));

            	            	// 20110109 - franciscom
            	            	// does not understand why I've choosen time ago
            	            	// to increment $siblingQty before using it
            	            	// This way if TC X exists on target parent
            	            	// I will create TC X [2] insteand of TC X [1]
            	            	// Anyway right now I will not change.
			            		$target = $name . ($suffix = sprintf($mask,++$siblingQty));

								// BUGID 3367
			            		$final_len = strlen($target);
			            		if( $final_len > $name_max_len)
			            		{
			            			$target = substr($target,strlen($suffix),$name_max_len);
			            		}
                                
                                // Need to recheck if new generated name does not crash with existent name
                                // why? Suppose you have created:
            					// TC [1]
            					// TC [2]
            					// TC [3]
            					// Then you delete TC [2].
            					// When I got siblings  il will got 2 siblings, if I create new progressive using next,
            					// it will be 3 => I will get duplicated name.
            					while( isset($nameSet[$target]) )
            					{
			            			$target = $name . ($suffix = sprintf($mask,++$siblingQty));
									// BUGID 3367
			            			$final_len = strlen($target);
			            			if( $final_len > $name_max_len)
			            			{
			            				$target = substr($target,strlen($suffix),$name_max_len);
			            			}
            					}
                                $name = $target;
			            	break;
			            } 
						
				        $ret['status_ok'] = 1;
						$ret['new_name'] = $name;
						$ret['msg'] = sprintf(lang_get('created_with_title'),$name);
						break;
				        
				    case 'create_new_version':
				        $doCreate=false;
				        
				        // If we found more that one with same name and same parent,
				        // will take the first one.
				        // BUGID 3374
				        $xx = current($itemSet);
	                    $ret['id'] = $xx['id'];            
		                $ret['external_id']=$xx['tc_external_id'];
				        $ret['status_ok'] = 1;
						$ret['new_name'] = $name;
		            	$ret['version_number'] = -1;
						$ret['msg'] = lang_get('create_new_version');
				    break;
				    
				    default:
				    break;
				}
			}
		}
	
	  if( $ret['status_ok'] && $doCreate)
	  {
	  	
	  	$safeLenName = tlSubStr($name, 0, $name_max_len);
	  	
	    // Get tproject id
	    $path2root=$this->tree_manager->get_path($parent_id);
	    $tproject_id=$path2root[0]['parent_id'];
	    $tcaseNumber=$this->tproject_mgr->generateTestCaseNumber($tproject_id);
	    $tcase_id = $this->tree_manager->new_node($parent_id,$this->my_node_type,$safeLenName,$order,$id);
	    $ret['id'] = $tcase_id;
	    $ret['external_id'] = $tcaseNumber;
		if( !$ret['has_duplicate'] && ($originalNameLen > $name_max_len) )
		{
			$ret['new_name'] = $safeLenName;
			$ret['msg'] = sprintf(lang_get('testcase_name_length_exceeded'),$originalNameLen,$name_max_len);
		}
		

	  }
	
	  return $ret;
	}
	
	/*
	  function: create_tcversion
	
	  args:
	
	  returns:
	
	  rev: 
			20110321 - franciscom - BUGID 4025 - added status	  
			20100821 - franciscom - BUGID 3696 - test case step execution type ignored	
			20100106 - franciscom - Multiple Test Case Steps Feature
	*/
	function create_tcversion($id,$tc_ext_id,$version,$summary,$preconditions,$steps,
	                          $author_id,$execution_type=TESTCASE_EXECUTION_TYPE_MANUAL,
	                          $importance=2,$status=null,$estimated_execution_duration=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$tcase_version_id = $this->tree_manager->new_node($id,$this->node_types_descr_id['testcase_version']);
		$sql = "/* $debugMsg */ INSERT INTO {$this->tables['tcversions']} " .
		       " (id,tc_external_id,version,summary,preconditions," . 
		       "author_id,creation_ts,execution_type,importance ";
		       
		$sqlValues = " VALUES({$tcase_version_id},{$tc_ext_id},{$version},'" .
	  	       		 $this->db->prepare_string($summary) . "','" . $this->db->prepare_string($preconditions) . "'," . 
	  	       		 $this->db->prepare_int($author_id) . "," . $this->db->db_now() . 
	  	       		 ", {$execution_type},{$importance} ";
		
		if( !is_null($status) )
		{
			$wf = intval($status);
			$sql .= ',status';
			$sqlValues .= ",{$wf}";
		}
			
		if( !is_null($estimated_execution_duration) )	 	 
		{
			$v = trim($estimated_execution_duration);
			if($v != '')
			{
				$sql .= ", estimated_execution_duration";
				$sqlValues .= "," . floatval($v);
			}
		}
			
		$sql .= " )" . $sqlValues . " )";
		
		 	
			
		$result = $this->db->exec_query($sql);
		$ret['msg']='ok';
		$ret['id']=$tcase_version_id;
		$ret['status_ok']=1;

		if ($result && ( !is_null($steps) && is_array($steps) ) )
		{
			$steps2create = count($steps);
			$op['status_ok'] = 1;
			for($jdx=0 ; ($jdx < $steps2create && $op['status_ok']); $jdx++)
			{
				$op = $this->create_step($tcase_version_id,$steps[$jdx]['step_number'],$steps[$jdx]['actions'],
										 $steps[$jdx]['expected_results'],$steps[$jdx]['execution_type']);
			}	 
		}
	
		if (!$result)
		{
			$ret['msg'] = $this->db->error_msg();
		  	$ret['status_ok']=0;
		  	$ret['id']=-1;
		}
	
		return $ret;
	}
	
	
	/*
	  function: getDuplicatesByname
	
	  args: $name
	        $parent_id
	
	  returns: hash
	*/
	function getDuplicatesByName($name, $parent_id, $options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;

	    $my['options'] = array( 'check_criteria' => '=', 'access_key' => 'id');
	    $my['options'] = array_merge($my['options'], (array)$options);
	    
	    $target = $this->db->prepare_string($name);
	    switch($my['options']['check_criteria'])
	    {
	    	case '=':
	    	default:
	    		$check_criteria = " AND NHA.name = '{$target}' ";
	    	break;
	    	
	    	case 'like':
	    		$check_criteria = " AND NHA.name LIKE '{$target}%' ";
	    	break;
	    	
	    }
			
	    $sql = " SELECT DISTINCT NHA.id,NHA.name,TCV.tc_external_id" .
			   " FROM {$this->tables['nodes_hierarchy']} NHA, " .
			   " {$this->tables['nodes_hierarchy']} NHB, {$this->tables['tcversions']} TCV  " .
			   " WHERE NHA.node_type_id = {$this->my_node_type} " .
			   " AND NHB.parent_id=NHA.id " .
			   " AND TCV.id=NHB.id " .
			   " AND NHB.node_type_id = {$this->node_types_descr_id['testcase_version']} " .
			   " AND NHA.parent_id={$parent_id} {$check_criteria}";
	
		$rs = $this->db->fetchRowsIntoMap($sql,$my['options']['access_key']);
	    if( is_null($rs) || count($rs) == 0 )
	    {
	        $rs=null;   
	    }
	    return $rs;
	}
	
	
	
	
	/*
	  function: get_by_name
	
	  args: $name
	        [$tsuite_name]: name of parent test suite
	        [$tproject_name]
	
	  returns: hash
	  
	  @internal revisions
	  20100831 - franciscom - BUGID 3729
	  
	*/
	function get_by_name($name, $tsuite_name = '', $tproject_name = '')
	{

		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;

	    $recordset = null;
	    $filters_on = array('tsuite_name' => false, 'tproject_name' => false);
	
		// limit all names
		$field_size = config_get('field_size');
	    $tsuite_name = tlSubStr(trim($tsuite_name),0, $field_size->testsuite_name);
	    $tproject_name = tlSubStr(trim($tproject_name),0,$field_size->testproject_name);
	    $name = tlSubStr(trim($name), 0, $field_size->testcase_name);
	    
		$sql = "/* $debugMsg */ " . 	    
	           " SELECT DISTINCT NH_TCASE.id,NH_TCASE.name,NH_TCASE_PARENT.id AS parent_id," .
	           " NH_TCASE_PARENT.name AS tsuite_name, TCV.tc_external_id " .
			   " FROM {$this->tables['nodes_hierarchy']} NH_TCASE, " .
			   " {$this->tables['nodes_hierarchy']} NH_TCASE_PARENT, " .
			   " {$this->tables['nodes_hierarchy']} NH_TCVERSIONS," .
			   " {$this->tables['tcversions']}  TCV  " .
			   " WHERE NH_TCASE.node_type_id = {$this->my_node_type} " .
			   " AND NH_TCASE.name = '{$this->db->prepare_string($name)}' " .
			   " AND TCV.id=NH_TCVERSIONS.id " .
			   " AND NH_TCVERSIONS.parent_id=NH_TCASE.id " .
			   " AND NH_TCASE_PARENT.id=NH_TCASE.parent_id ";
	   
		if($tsuite_name != "")
		{
			$sql .= " AND NH_TCASE_PARENT.name = '{$this->db->prepare_string($tsuite_name)}' " .
		            " AND NH_TCASE_PARENT.node_type_id = {$this->node_types_descr_id['testsuite']} ";
		}
		$recordset = $this->db->get_recordset($sql);
	    if(count($recordset) && $tproject_name != "")
	    {    
			list($tproject_info)=$this->tproject_mgr->get_by_name($tproject_name);
	        foreach($recordset as $idx => $tcase_info)
	        { 
	        	if( $this->get_testproject($tcase_info['id']) != $tproject_info['id'] )
	            {
	            	unset($recordset[$idx]);  
				}        
			}    
	    }
	
	    return $recordset;
	}
	
	
	/*
	get array of info for every test case
	without any kind of filter.
	Every array element contains an assoc array with testcase info
	
	*/
	function get_all()
	{
		$sql = " SELECT nodes_hierarchy.name, nodes_hierarchy.id
		         FROM  {$this->tables['nodes_hierarchy']} nodes_hierarchy
		         WHERE nodes_hierarchy.node_type_id={$my_node_type}";
		$recordset = $this->db->get_recordset($sql);
	
		return $recordset;
	}
	
	
	/**
	 * Show Test Case logic
	 * 
	 * @param object $smarty reference to smarty object (controls viewer).
	 * @param integer $id Test case unique identifier
	 * @param integer $version_id (optional) you can work on ONE test case version, 
	 * 				or on ALL; default: ALL
	 * 
	 * @internal
	
	        [viewer_args]: map with keys
	                       action
	                       msg_result
	                       refresh_tree: controls if tree view is refreshed after every operation.
	                                     default: yes
	                       user_feedback
	                       disable_edit: used to overwrite user rights
	                                        default: 0 -> no
	
	  returns:
	
	  rev :
	*/
	// 1.9.4
	// function show(&$smarty,$guiObj,$template_dir,$id,$version_id = self::ALL_VERSIONS,
	//              $viewer_args = null,$path_info=null,$mode=null)
	
	//function show(&$smarty,$env_tproject_id,$grants,$guiObj,$template_dir,$id,$version_id = self::ALL_VERSIONS,
	//              $viewer_args = null,$path_info=null,$mode=null)
	function show(&$smarty,$env_tproject_id,$grants,$guiObj,$id,$version_id = self::ALL_VERSIONS)
	{

	  $idSet = is_array($id) ? $id : (array)$id;
	  $status_ok = $idSet[0] > 0 ? 1 : 0;

		$req_mgr = new requirement_mgr($this->db);

	  $gui = initShowGui($guiObj,$grants);
	  $gui->can_do = $this->getShowViewerActions($gui->show_mode);
	  
    $dummy = templateConfiguration('tcView');
		$my_template = isset($the_tpl['tcView']) ? $the_tpl['tcView'] : 'tcView.tpl'; 

		$gui->testcase_other_versions = array();
    $userid_array = array();

		if($status_ok)
	  {

	    $path2root = $this->tree_manager->get_path($idSet[0]);
	    $gui->tproject_id = $path2root[0]['parent_id'];
	    $info = $this->tproject_mgr->get_by_id($gui->tproject_id);
			$gui->opt_requirements = $info['opt']->requirementsEnabled;
			$gui->requirementsEnabled = $info['opt']->requirementsEnabled;
			
			$platformMgr = new tlPlatform($this->db,$gui->tproject_id);
	    $gui->platforms = $platformMgr->getAllAsMap();
	        
	    $testplans = $this->tproject_mgr->get_all_testplans($gui->tproject_id,array('plan_status' =>1) );
	    $gui->has_testplans = !is_null($testplans) && count($testplans) > 0 ? 1 : 0;
	        
	    if( $gui->display_testproject )
	    {
	      $gui->tprojectName = $info['name'];
	    }
	    
	    if( $gui->display_parent_testsuite )
	    {
	      $parent_idx = count($path2root)-2;
	      $gui->parentTestSuiteName = $path2root[$parent_idx]['name'];
	    }
	    
	    $tcasePrefix = $this->tproject_mgr->getTestCasePrefix($gui->tproject_id);
	    if(trim($tcasePrefix) != "")
	    {
	      // 'Add To Testplan' button will be disabled if the testcase doesn't belong 
	      // to the current selected testproject
	      // $gui->can_do->add2tplan = 'no';
	      if($env_tproject_id != $gui->tproject_id)
	      {
		    		$gui->can_do->add2tplan = ($gui->can_do->add2tplan == 'yes') ? $grants->testplan_planning : 'no';
				}
				else
				{
					  $gui->can_do->add2tplan = 'yes';
				}
				$tcasePrefix .= $gui->tcase_cfg->glue_character;
		  }
	  }
	  
    if($status_ok && sizeof($idSet))
	  {
	    	$cfx=0;
		  	$allTCKeywords = $this->getKeywords($idSet,null,'testcase_id',' ORDER BY keyword ASC ');
		  	$allReqs = $req_mgr->get_all_for_tcase($idSet);
		  	
		  	$tcStatus2exclude = config_get('tplanDesign')->hideTestCaseWithStatusIn;
		  	foreach($idSet as $key => $tc_id)
		  	{
		  		$tc_array = $this->get_by_id($tc_id,$version_id);
		  		if (!$tc_array)
		  		{
		  			continue;
		  		}
		  		
		  		$tc_array[0]['tc_external_id'] = $tcasePrefix . $tc_array[0]['tc_external_id'];

		  		// get the status quo of execution and links of tc versions
		  		// $gui->status_quo_map[] = $this->get_versions_status_quo($tc_id);
				  $gui->status_quo[] = $this->get_versions_status_quo($tc_id);
		  		$gui->linked_versions[] = $this->get_linked_versions($tc_id);
		  		$gui->keywords_map[] = isset($allTCKeywords[$tc_id]) ? $allTCKeywords[$tc_id] : null;
		  		$tc_current = $tc_array[0];
		  		
		  		// add new attribute
		  		// enabledOnTestPlanDesign
		  		$tc_current['enabledOnTestPlanDesign'] = !isset($tcStatus2exclude[$tc_current['status']]);
		  		
		  		$tcversion_id_current = $tc_current['id']; 
		  		$gui->tc_current_version[] = array($tc_current);
		  		
		  		//Get UserID and Updater ID for current Version
		  		$userid_array[$tc_current['author_id']] = null;
		  		$userid_array[$tc_current['updater_id']] = null;

	        $cfPlaces = $this->buildCFLocationMap();
      		foreach($cfPlaces as $locationKey => $locationFilter)
		  		{ 
		  			$gui->cf_current_version[$cfx][$locationKey] = 
		  			$this->html_table_of_custom_field_values($tc_id,'design',$locationFilter,
		  			                                         null,null,$gui->tproject_id,null,$tcversion_id_current);
		  		}	
  			
	    
				  // Other versions (if exists)	    
		  		if(count($tc_array) > 1)
		  		{
		  			$gui->testcase_other_versions[] = array_slice($tc_array,1);
					  $target_idx = count($gui->testcase_other_versions) - 1;
					  $loop2do = count($gui->testcase_other_versions[$target_idx]);
					  for($qdx=0; $qdx < $loop2do; $qdx++)
					  {
						  $tcRef = &$gui->testcase_other_versions[$target_idx][$qdx];
						  $tcRef['enabledOnTestPlanDesign'] = !isset($tcStatus2exclude[$tcRef['status']]);
						  $target_tcversion = $tcRef['id'];
	      			foreach($cfPlaces as $locationKey => $locationFilter)
		  				{ 
		  					$gui->cf_other_versions[$cfx][$qdx][$locationKey] = 
		  					$this->html_table_of_custom_field_values($tc_id,'design',$locationFilter,
		  				 	                                         null,null,$gui->tproject_id,null,$target_tcversion);
		  				}	
					  }
		  		}
		  		else
		  		{
		  			$gui->testcase_other_versions[] = null;
		  			$gui->cf_other_versions[$cfx]=null;
		  		}	
		  		$cfx++;

		  		// Get author and updater id for each version
		  		// @TODO - franciscom - explain magic ZERO
		  		if ($gui->testcase_other_versions[0])
		  		{
		  			foreach($gui->testcase_other_versions[0] as $key => $version)
		  			{				
		  	  			$userid_array[$version['author_id']] = null;
		  	  			$userid_array[$version['updater_id']] = null;				
		  			}
		  		}
		  		$tcReqs = isset($allReqs[$tc_id]) ? $allReqs[$tc_id] : null;
		  		$gui->arrReqs[] = $tcReqs;

		  	} // foreach($a_id as $key => $tc_id)
	    } // if (sizeof($a_id))

		
    // Removing duplicate and NULL id's
		unset($userid_array['']);
		$passeduserarray = array_keys($userid_array);

		

    initShowGuiActions(&$gui);
		$gui->users = tlUser::getByIDs($this->db,$passeduserarray,'id');
		$smarty->assign('gui',$gui);
		$dummy = templateConfiguration('tcView');
		$smarty->display($template_dir . $dummy->default_template);
	}
	

  private function initShowGui($guiObj,$grantsObj)
  {	  
	  $goo = is_null($guiObj) ? new stdClass() : $guiObj;

		$gui->execution_types = $this->execution_types;
		$goo->view_req_rights = $grantsObj->mgt_view_req;

	  $goo->parentTestSuiteName='';
		$goo->tprojectName='';
	  $goo->submitCode="";
	  $goo->dialogName = '';
	  $goo->bodyOnLoad="";
	  $goo->bodyOnUnload = "storeWindowSize('TCEditPopup')";

		$goo->tableColspan = 5; // sorry magic related to table to display steps
		$goo->opt_requirements = false;

		$goo->tc_current_version = array();
		$goo->status_quo = array();
		$goo->keywords_map = array();
		$goo->arrReqs = array();

		$goo->cf_current_version = null;
		$goo->cf_other_versions = null;
	  $goo->linked_versions=null;
	  $goo->platforms = null;

		$goo->tcase_cfg = $this->cfg->testcase;


	  $viewer_defaults = array('title' => lang_get('title_test_case'),'show_title' => 'no',
	                           'action' => '', 'msg_result' => '','user_feedback' => '',
	                           'refreshTree' => 1, 'disable_edit' => 0,
	                           'display_testproject' => 0,'display_parent_testsuite' => 0,
	                           'hilite_testcase_name' => 0,'show_match_count' => 0);
	
    $viewer_defaults = array_merge($viewer_defaults, (array)$guiObj->viewerArgs);

	  $goo->display_testproject = $viewer_defaults['display_testproject'];
	  $goo->display_parent_testsuite = $viewer_defaults['display_parent_testsuite'];
	  $goo->show_title = $viewer_defaults['show_title'];
	  $goo->hilite_testcase_name = $viewer_defaults['hilite_testcase_name'];
		$goo->action = $viewer_defaults['action'];
		$goo->user_feedback = $viewer_defaults['user_feedback'];

	  $goo->pageTitle = $viewer_defaults['title'];
		$goo->refreshTree = isset($goo->refreshTree) ? $goo->refreshTree : $viewer_defaults['refreshTree'];
		$goo->sqlResult = $viewer_defaults['msg_result'];

	  $goo->show_match_count = $viewer_defaults['show_match_count'];
	  $goo->display_testcase_path = !is_null($goo->path_info);
	  if($goo->show_match_count && $goo->display_testcase_path )
	  {
	        $goo->match_count = count($goo->path_info);  
	  }

    // fine grain control of operations
	  if( $viewer_defaults['disable_edit'] == 1 || ($grantsObj->mgt_modify_tc == false) )
	  {
	        $goo->show_mode = 'editDisabled';
	  }
    else if( !is_null($goo->show_mode) && $goo->show_mode == 'editOnExec' )
	  {
	    // refers to two javascript functions present in testlink_library.js
	    // and logic used to refresh both frames when user call this
	    // method to edit a test case while executing it.
	    $goo->dialogName='tcview_dialog';
	    $goo->bodyOnLoad="dialog_onLoad($gui->dialogName)";
	    $goo->bodyOnUnload="dialog_onUnload($gui->dialogName)";
	    $goo->submitCode="return dialog_onSubmit($gui->dialogName)";
	  }

		$dummy = getConfigAndLabels('testCaseStatus','code');
		$goo->domainTCStatus = $dummy['lbl'];


	  return $goo;
	}



  private function initShowGuiActions(&$gui)
  {
  
		$gui->deleteStepAction = "lib/testcases/tcEdit.php?tproject_id=$gui->tproject_id&show_mode=$gui->show_mode" . 
								             "&doAction=doDeleteStep&step_id=";

		$gui->tcExportAction = "lib/testcases/tcExport.php?tproject_id=$gui->tproject_id&show_mode=$gui->show_mode";
		$gui->tcViewAction = "lib/testcases/archiveData.php?tproject_id={$gui->tproject_id}" . 
							 "&show_mode=$gui->show_mode&tcase_id=";

		$gui->printTestCaseAction = "lib/testcases/tcPrint.php?tproject_id=$gui->tproject_id&show_mode=$gui->show_mode";


		$gui->keywordsViewHREF = "lib/keywords/keywordsView.php?tproject_id={$gui->tproject_id} " .
						 		 ' target="mainframe" class="bold" title="' . lang_get('menu_manage_keywords') . '"';


		$gui->reqSpecMgmtHREF = "lib/general/frmWorkArea.php?tproject_id={$gui->tproject_id}&feature=reqSpecMgmt";
		$gui->reqMgmtHREF = "lib/requirements/reqView.php?tproject_id={$gui->tproject_id}" . 
							          "&showReqSpecTitle=1&requirement_id=";
		
		$gui->addTc2TplanHREF = "lib/testcases/tcAssign2Tplan.php?tproject_id={$gui->tproject_id}";

  
  }



	function update($item)
	{


		// transform item into old interface
		// THIS are mandatory
		// $id,$tcversion_id,$name,$summary,$preconditions,$steps,$user_id,
		//
		
	    $keywords_id = '';
	    $tc_order = self::DEFAULT_ORDER;
        $execution_type = TESTCASE_EXECUTION_TYPE_MANUAL;
        $importance = 2;
        $status = null;
        $estimated_execution_duration = null;
	    extract($item);

		return $this->_update($id,$tcversion_id,$name,$summary,$preconditions,$steps,
	                 		  $user_id,$keywords_id,$tc_order,
	                 		  $execution_type,$importance,
	                 		  $status,$estimated_execution_duration);
	}

	
	
	/**
	 * update test case specification
	 * 
	 * @param integer $id Test case unique identifier (node_hierarchy table)
	 * @param integer $tcversion_id Test Case Version unique ID (node_hierarchy table)
	 * @param string $name name/title
	 * @param string $summary
	 * @param string $preconditions
	 * @param array $steps steps + expected results
	 * @param integer $user_id who is doing the update
	 * @param string $keywords_id optional list of keyword id to be linked to test case
	 *				 this list will override previous keyword links (delete + insert).
	 *
	 * @param integer $tc_order optional order inside parent test suite
	 * @param integer $execution_type optional
	 * @param integer $importance optional
	 * 
	 *
	 *
	 */
	function _update($id,$tcversion_id,$name,$summary,$preconditions,$steps,
	                 $user_id,$keywords_id='',$tc_order=self::DEFAULT_ORDER,
	                 $execution_type=TESTCASE_EXECUTION_TYPE_MANUAL,$importance=2,
	                 $status=null,$estimated_execution_duration=null)
	{
		$ret['status_ok'] = 1;
		$ret['msg'] = '';
		
		
		tLog("TC UPDATE ID=($id): exec_type=$execution_type importance=$importance");
		
		// Check if new name will be create a duplicate testcase under same parent
		$checkDuplicates = config_get('check_names_for_duplicates');
		if ($checkDuplicates)
		{  	
			$check = $this->tree_manager->nodeNameExists($name,$this->my_node_type,$id);
			$ret['status_ok'] = !$check['status']; 
			$ret['msg'] = $check['msg']; 
		}    
	
		if($ret['status_ok'])
		{    
			$sql=array();
			$sql[] = " UPDATE {$this->tables['nodes_hierarchy']} SET name='" .
					 $this->db->prepare_string($name) . "' WHERE id= {$id}";
		
			// test case version 
		   	$dummy = " UPDATE {$this->tables['tcversions']} " .
		             " SET summary='" . $this->db->prepare_string($summary) . "'," .
		   		 	 " updater_id=" . $this->db->prepare_int($user_id) . ", " .
		   		 	 " modification_ts = " . $this->db->db_now() . "," .
		   		 	 " execution_type=" . $this->db->prepare_int($execution_type) . ", " . 
		   		 	 " importance=" . $this->db->prepare_int($importance) . "," .
		   		 	 " preconditions='" . $this->db->prepare_string($preconditions) . "' ";

		   	if( !is_null($status) )	 	 
			{
				$dummy .= ", status=" . intval($status); 
			}
			
		   	if( !is_null($estimated_execution_duration) )	 	 
			{
				$dummy .= ", estimated_execution_duration=";
				$v = trim($estimated_execution_duration);
				
				$dummy .= ($v == '') ? "NULL" : floatval($v);
			}

		   		 	 
		   	$dummy .= " WHERE id = " . $this->db->prepare_int($tcversion_id); 
			$sql[] = $dummy;
			
			foreach($sql as $stm)
			{
			    $result = $this->db->exec_query($stm);
			    if( !$result )
			    {
					$ret['status_ok'] = 0;
					$ret['msg'] = $this->db->error_msg;
					break;
			    }
			}
		    
		    if( $ret['status_ok'] && !is_null($steps) )
		    {
		    	$this->update_tcversion_steps($tcversion_id,$steps);
		    }
		    
			if( $ret['status_ok'] )
		    {      
		       $this->updateKeywordAssignment($id,$keywords_id);
		   	}
		}
	      
		return $ret;
	}
	
	
	/*
	  function: updateKeywordAssignment
	
	  args:
	  
	  returns: 
	
	*/
	private function updateKeywordAssignment($id,$keywords_id)
	{
		
		// To avoid false loggings, check is delete is needed
		$items = array();
		$items['stored'] = $this->get_keywords_map($id);
		if (is_null($items['stored']))
			$items['stored'] = array();
		$items['requested'] = array();
		
		if(trim($keywords_id) != "")
		{
			$a_keywords = explode(",",trim($keywords_id));
			$sql = " SELECT id,keyword " .
		       " FROM {$this->tables['keywords']} " .
		       " WHERE id IN (" . implode(',',$a_keywords) . ")";
		       
			$items['requested'] = $this->db->fetchColumnsIntoMap($sql,'id','keyword');
		}
		
		$items['common'] = array_intersect_assoc($items['stored'],$items['requested']);
		$items['new'] = array_diff_assoc($items['requested'],$items['common']);
		$items['todelete'] = array_diff_assoc($items['stored'],$items['common']);   
		
		if(!is_null($items['todelete']) && count($items['todelete']))
		{
			$this->deleteKeywords($id,array_keys($items['todelete']),self::AUDIT_ON);
		}
		
		if(!is_null($items['new']) && count($items['new']))
		{
			$this->addKeywords($id,array_keys($items['new']),self::AUDIT_ON);
		}
	}
	
	/*
	  function: logKeywordChanges
	
	  args:
	  
	  returns: 
	
	*/
	function logKeywordChanges($old,$new)
	{
	
	   // try to understand the really new
	  
	}
	
	
	
	
	
	
	
	/*
	  function: check_link_and_exec_status
	            Fore every version of testcase (id), do following checks:
	
		          1. testcase is linked to one of more test plans ?
		          2. if anwser is yes then,check if has been executed => has records on executions table
	
	  args : id: testcase id
	
	  returns: string with following values:
	           no_links: testcase is not linked to any testplan
	           linked_but_not_executed: testcase is linked at least to a testplan
	                                    but has not been executed.
	
	           linked_and_executed: testcase is linked at least to a testplan and
	                                has been executed => has records on executions table.
	
	
	*/
	function check_link_and_exec_status($id)
	{
		$status = 'no_links';
	
		// get linked versions
		// ATTENTION TO PLATFORMS
		$linked_tcversions = $this->get_linked_versions($id);
		$has_links_to_testplans = is_null($linked_tcversions) ? 0 : 1;
	
		if($has_links_to_testplans)
		{
			// check if executed
			$linked_not_exec = $this->get_linked_versions($id,"NOT_EXECUTED");
	
			$status='linked_and_executed';
			if(count($linked_tcversions) == count($linked_not_exec))
			{
				$status = 'linked_but_not_executed';
			}
		}
		return $status;
	}
	
	
	/* 
	 
	rev:
		20100107 - franciscom - Multiple Test Case Step Feature 
		20081015 - franciscom - added check to avoid bug due to no children
	
	*/
	function delete($id,$version_id = self::ALL_VERSIONS)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	  	$children=null;
	  	$do_it=true;
	  	
	  	// I'm trying to speedup the next deletes
	  	$sql="/* $debugMsg */ " . 
	  	     " SELECT NH_TCV.id AS tcversion_id, NH_TCSTEPS.id AS step_id " .
	  	     " FROM {$this->tables['nodes_hierarchy']} NH_TCV " .
	  	     " LEFT OUTER JOIN {$this->tables['nodes_hierarchy']} NH_TCSTEPS " . 
	  	     " ON NH_TCSTEPS.parent_id = NH_TCV.id ";

	  	if($version_id == self::ALL_VERSIONS)
	  	{
	  		if( is_array($id) )
	  		{
	  		  $sql .= " WHERE NH_TCV.parent_id IN (" .implode(',',$id) . ") ";
	  		}
	  		else
	  		{
	  		  $sql .= " WHERE NH_TCV.parent_id={$id} ";
	  		}
	  	}                       
	  	else
	  	{
	  		  $sql .= " WHERE NH_TCV.parent_id={$id} AND NH_TCV.id = {$version_id}";
	  	}

	  	$children_rs=$this->db->get_recordset($sql);
	  	$do_it = !is_null($children_rs);
	  	if($do_it)
	  	{
	  		foreach($children_rs as $value)
	  		{
	  		  $children['tcversion'][]=$value['tcversion_id'];
	  		  $children['step'][]=$value['step_id'];
	  		}
			$this->_execution_delete($id,$version_id,$children);
			$this->_blind_delete($id,$version_id,$children);
	  	}

	
		return 1;
	}
	
	/*
	  function: get_linked_versions
	            For a test case get information about versions linked to testplans.
	            Filters can be applied on:
	                                      execution status
	                                      active status
	
	  args : id: testcase id
	         [exec_status]: default: ALL, range: ALL,EXECUTED,NOT_EXECUTED
	         [active_status]: default: ALL, range: ALL,ACTIVE,INACTIVE
	         [tplan_id]
	
	    returns: map.
	           key: version id
	           value: map with following structure:
	                  key: testplan id
	                  value: map with following structure:
	
	                  testcase_id
	                  tcversion_id
	                  id -> tcversion_id (node id)
	                  version
	                  summary
	                  importance
	                  author_id
	                  creation_ts
	                  updater_id
	                  modification_ts
	                  active
	                  is_open
	                  testplan_id
	                  tplan_name
	*/
	function get_linked_versions($id,$exec_status="ALL",$active_status='ALL',$tplan_id=null,$platform_id=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	  	$active_filter='';
	  	$active_status=strtoupper($active_status);
		if($active_status !='ALL')
		{
		  $active_filter=' AND tcversions.active=' . $active_status=='ACTIVE' ? 1 : 0;
	  	}
	
		switch ($exec_status)
		{
			case "ALL":
		        $sql = "/* $debugMsg */ " . 	    
				       " SELECT NH.parent_id AS testcase_id, NH.id AS tcversion_id, " .
					   " tcversions.*, TTC.testplan_id, TTC.tcversion_id, TTC.platform_id," . 
					   " NHB.name AS tplan_name " .
					   " FROM   {$this->tables['nodes_hierarchy']} NH," .
					   " {$this->tables['tcversions']} tcversions," .
					   " {$this->tables['testplan_tcversions']} TTC, " .
					   " {$this->tables['nodes_hierarchy']} NHB    " .
					   " WHERE  TTC.tcversion_id = tcversions.id {$active_filter} " .
					   " AND    tcversions.id = NH.id " . 
					   " AND    NHB.id = TTC.testplan_id " .
					   " AND    NH.parent_id = {$id}";
						    
	      		if(!is_null($tplan_id))
	      		{
	      		    $sql .= " AND TTC.testplan_id = {$tplan_id} ";  
	      		}  					    
	      		
	      		// 20100308 - franciscom
	      		if(!is_null($platform_id))
	      		{
	      		    $sql .= " AND TTC.platform_id = {$platform_id} ";  
	      		}  					    
	      		
	        	$recordset = $this->db->fetchMapRowsIntoMap($sql,'tcversion_id','testplan_id',database::CUMULATIVE);
				// 20100330 - eloff - BUGID 3329
				if( !is_null($recordset) )
				{
					// changes third access key from sequential index to platform_id
					foreach ($recordset as $accessKey => $testplan)
					{
						foreach ($testplan as $tplanKey => $testcases)
						{
							// Use a temporary array to avoid key collisions
							$newArray = array();
							foreach ($testcases as $elemKey => $element)
							{
								$platform_id = $element['platform_id'];
								$newArray[$platform_id] = $element;
							}
							$recordset[$accessKey][$tplanKey] = $newArray;
					    }
					}
				}	
		  break;
	
	      case "EXECUTED":
		  case "NOT_EXECUTED":
				$getFilters = array('exec_status' => $exec_status,'active_status' => $active_status,
									'tplan_id' => $tplan_id, 'platform_id' => $platform_id);
		      	$recordset=$this->get_exec_status($id,$getFilters);
	      break;
	  }

	  // Multiple Test Case Steps
		if( !is_null($recordset) )
		{
			$version2loop = array_keys($recordset);
			foreach( $version2loop as $accessKey)
			{	
				$step_set = $this->get_steps($accessKey);
				$tplan2loop = array_keys($recordset[$accessKey]);
				foreach( $tplan2loop as $tplanKey)
				{	
					$elem2loop = array_keys($recordset[$accessKey][$tplanKey]);
					foreach( $elem2loop as $elemKey)
					{	
						$recordset[$accessKey][$tplanKey][$elemKey]['steps'] = $step_set;
					}
				}
				
			} 
		}
	    
	  return $recordset;
	}
	
	/*
		Delete the following info:
		req_coverage
		risk_assignment
		custom fields
		keywords
		links to test plans
		tcversions
		nodes from hierarchy
	
		rev:
			 20100825 - BUGID 3702 
		     20070602 - franciscom - delete attachments
	*/
	function _blind_delete($id,$version_id=self::ALL_VERSIONS,$children=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $sql = array();

		$destroyTC = false;
	    $item_id = $version_id;
		$tcversion_list = $version_id;
	   	$target_nodes = $version_id;
	    if( $version_id == self::ALL_VERSIONS)
	    {
	    	$destroyTC = true;
	        $item_id = $id;
		    $tcversion_list=implode(',',$children['tcversion']);
	   	 	$target_nodes = $children['tcversion'];
	    }

	    // BUGID 3431
	    $this->cfield_mgr->remove_all_design_values_from_node($target_nodes);

		// BUGID 3465: Delete Test Project - User Execution Assignment is not deleted
		// BUGID 3573: MySQL does not like ALIAS
		$sql[]="/* $debugMsg */ DELETE FROM {$this->tables['user_assignments']} " .
			   " WHERE feature_id in (" .
			   " SELECT id FROM {$this->tables['testplan_tcversions']}  " .
		       " WHERE tcversion_id IN ({$tcversion_list}))";
		
		$sql[]="/* $debugMsg */ DELETE FROM {$this->tables['testplan_tcversions']}  " .
		       " WHERE tcversion_id IN ({$tcversion_list})";
	
		// Multiple Test Case Steps Feature
		
		// BUGID 3702
		if( !is_null($children['step']) )
		{
			// remove null elements
			foreach($children['step'] as $key => $value)
			{
				if(is_null($value))
				{
					unset($children['step'][$key]);
				}
			}
			
	    	if( count($children['step']) > 0)
	    	{ 
				$step_list=trim(implode(',',$children['step']));
	    		$sql[]="/* $debugMsg */ DELETE FROM {$this->tables['tcsteps']}  " .
				       " WHERE id IN ({$step_list})";
	    	}
	    }
	    $sql[]="/* $debugMsg */ DELETE FROM {$this->tables['tcversions']}  " .
		       " WHERE id IN ({$tcversion_list})";

	    foreach ($sql as $the_stm)
	    {
			$result = $this->db->exec_query($the_stm);
	    }
    
	    if($destroyTC)
	    {
			// Remove data that is related to Test Case => must be deleted when there is no more trace
			// of test case => when all version are deleted
		    $sql = null;
		    $sql[]="/* $debugMsg */ DELETE FROM {$this->tables['testcase_keywords']} WHERE testcase_id = {$id}";
		    $sql[]="/* $debugMsg */ DELETE FROM {$this->tables['req_coverage']}  WHERE testcase_id = {$id}";

	    	foreach ($sql as $the_stm)
	    	{
				  $result = $this->db->exec_query($the_stm);
	    	}

	        $this->deleteAttachments($id);
	        // BUGID 3431
	        // $this->cfield_mgr->remove_all_design_values_from_node($id);
	    
	    }
	    
	    // Attention:
	    // After addition of test case steps feature, a test case version can be root of
	    // a subtree that contains the steps.
	    $this->tree_manager->delete_subtree($item_id);
	}
	
	
	/*
	Delete the following info:
		bugs
		executions
	  cfield_execution_values
	*/
	function _execution_delete($id,$version_id=self::ALL_VERSIONS,$children=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$sql = array();

		if( $version_id	== self::ALL_VERSIONS )
		{
			$tcversion_list=implode(',',$children['tcversion']);
			
			$sql[]="/* $debugMsg */ DELETE FROM {$this->tables['execution_bugs']} " .
		  		   " WHERE execution_id IN (SELECT id FROM {$this->tables['executions']} " .
    		       " WHERE tcversion_id IN ({$tcversion_list}))";

	      	$sql[]="/* $debugMsg */ DELETE FROM {$this->tables['cfield_execution_values']}  " .
	      		   " WHERE tcversion_id IN ({$tcversion_list})";

	      	$sql[]="/* $debugMsg */ DELETE FROM {$this->tables['executions']}  " .
	      		   " WHERE tcversion_id IN ({$tcversion_list})";
	
	    }
	    else
	    {
			$sql[]="/* $debugMsg */  DELETE FROM {$this->tables['execution_bugs']} " .
	        	   " WHERE execution_id IN (SELECT id FROM {$this->tables['executions']} " .
	               " WHERE tcversion_id = {$version_id})";
	
	        $sql[]="/* $debugMsg */ DELETE FROM {$this->tables['cfield_execution_values']} " .
	        	   " WHERE tcversion_id = {$version_id}";
	
	        $sql[]="/* $debugMsg */ DELETE FROM {$this->tables['executions']} " .
	        	   " WHERE tcversion_id = {$version_id}";
	    }
	
	    foreach ($sql as $the_stm)
	    {
	    		$result = $this->db->exec_query($the_stm);
	    }
	}
	
	
	/*
	  function: formatTestCaseIdentity
	
	  args: id: testcase id
	        external_id
	
	  returns: testproject id
	
	*/
	function formatTestCaseIdentity($id,$external_id)
	{
	    $path2root=$this->tree_manager->get_path($tc_id);
	    $tproject_id=$path2root[0]['parent_id'];
	    $tcasePrefix=$this->tproject_mgr->getTestCasePrefix($tproject_id);
	}
	
	
	/*
	  function: getPrefix
	
	  args: id: testcase id
	        [$tproject_id]
	
	  returns: array(prefix,testproject id)
	
	*/
	function getPrefix($id, $tproject_id=null)
	{
		$root = $tproject_id;
		if( is_null($root) )
		{
	    	$path2root=$this->tree_manager->get_path($id);
	    	$root=$path2root[0]['parent_id'];
	    }
	    $tcasePrefix=$this->tproject_mgr->getTestCasePrefix($root);
	    return array($tcasePrefix,$root);
	}
	
	
	
	
	/*
	  function: get_testproject
	            Given a testcase id get node id of testproject to which testcase belongs.
	  args :id: testcase id
	
	  returns: testproject id
	  
	
	*/
	function get_testproject($id)
	{
	  $a_path = $this->tree_manager->get_path($id);
	  return ($a_path[0]['parent_id']);
	}
	
	/*
	@internal revisions
	20110413 - franciscom - BUGID 4404 - set author_id = user doing copy
	20110405 - franciscom - BUGID 4374: When copying a project, external TC ID is not preserved
							added option 'preserve_external_id'		
	*/
	function copy_to($id,$parent_id,$user_id,$options=null,$mappings=null)
	{
	    $newTCObj = array('id' => -1, 'status_ok' => 0, 'msg' => 'ok', 'mappings' => null);
	    $my['options'] = array( 'check_duplicate_name' => self::DONT_CHECK_DUPLICATE_NAME,
								'action_on_duplicate_name' => 'generate_new', 
								'copy_also' => null, 'preserve_external_id' => false);

        // needed when Test Case is copied to a DIFFERENT Test Project,
        // added during Test Project COPY Feature implementation
        $my['mappings']['keywords'] = null;
        $my['mappings']['requirements'] = null;

	    $my['mappings'] = array_merge($my['mappings'], (array)$mappings);
	    $my['options'] = array_merge($my['options'], (array)$options);
	
	
	    if( is_null($my['options']['copy_also']) )
	    {
	        $my['options']['copy_also'] = array('keyword_assignments' => true,'requirement_assignments' => true);   
	    }
	    
		$tcase_info = $this->get_by_id($id);
		if ($tcase_info)
		{
			$newTCObj = $this->create_tcase_only($parent_id,$tcase_info[0]['name'],
			                                     $tcase_info[0]['node_order'],self::AUTOMATIC_ID,
	                                             $my['options']);
			if($newTCObj['status_ok'])
			{
		        $ret['status_ok']=1;
		        $newTCObj['mappings'][$id] = $newTCObj['id'];
		        
		        // BUGID 4374
				$externalID = $newTCObj['external_id'];
				if( $my['options']['preserve_external_id'] )
				{
					$externalID = $tcase_info[0]['tc_external_id'];
				}
		        
	 			foreach($tcase_info as $tcversion)
				{
					
					// 20110321 - franciscom - BUGID 4025
					// 20100221 - franciscom - 
					// IMPORTANT NOTICE:
					// In order to implement COPY to another test project, WE CAN NOT ASK
					// to method create_tcversion() to create inside itself THE STEPS.
					// Passing NULL as steps we instruct create_tcversion() TO DO NOT CREATE STEPS
					// 
					// BUGID 4404 - $tcversion['author_id'] -> user_id
					$op = $this->create_tcversion($newTCObj['id'],$externalID,$tcversion['version'],
					                              $tcversion['summary'],$tcversion['preconditions'],null,
					                              $user_id,$tcversion['execution_type'],
					                              $tcversion['importance'],$tcversion['status']);
					
	    			if( $op['status_ok'] )
	    			{
	    				$newTCObj['mappings'][$tcversion['id']] = $op['id'];

						// ATTENTION:  NEED TO UNDERSTAND HOW TO MANAGE COPY TO OTHER TEST PROJECTS
						// 
						// BUGID 3431
						$this->copy_cfields_design_values(array('id' => $id, 'tcversion_id' => $tcversion['id']),
						  								  array('id' => $newTCObj['id'], 'tcversion_id' => $op['id']));

	    				
	    				// Need to get all steps
	    				$stepsSet = $this->get_steps($tcversion['id']);
	    				$to_tcversion_id = $op['id'];
	    				if( !is_null($stepsSet) )
	    				{
	    					foreach($stepsSet as $key => $step)
	    					{
        						$op = $this->create_step($to_tcversion_id,$step['step_number'],$step['actions'],
        						                         $step['expected_results'],$step['execution_type']);			
	    					}
	    				}
					}                       
				}
				
				// Conditional copies
				if( isset($my['options']['copy_also']['keyword_assignments']) && 
				    $my['options']['copy_also']['keyword_assignments'])
				{
					$this->copyKeywordsTo($id,$newTCObj['id'],$my['mappings']['keywords']);
				}
				
				if (isset($my['options']['copy_also']['requirement_assignments']) && 
				    $my['options']['copy_also']['requirement_assignments'])
				{
					$this->copyReqAssignmentTo($id,$newTCObj['id'],$my['mappings']['requirements']);
				}
				
				// BUGID 3431 
				// $this->copy_cfields_design_values($id,$newTCObj['id']);
				// $this->copy_cfields_design_values(array('id' => $id, 'tcversion_id' =>),
				// 								  array('id' => $newTCObj['id'],));
	            
	            $this->copy_attachments($id,$newTCObj['id']);
			}
		}
		return($newTCObj);
	}
	
	
	/*
	  function: create_new_version()
	            create a new test case version, 
	            doing a copy of source test case version
	            
	
	  args : $id: testcase id
	         $user_id: who is doing this operation.
	         [$source_version_id]: default null -> source is LATEST TCVERSION 
	
	  returns:
	          map:  id: node id of created tcversion
	                version: version number (i.e. 5)
	                msg
	
	  rev : 20070701 - franciscom - added version key on return map.
	*/
	function create_new_version($id,$user_id,$source_version_id=null, $options=null)
	{
	  $tcversion_id = $this->tree_manager->new_node($id,$this->node_types_descr_id['testcase_version']);
	
	  // get last version for this test case (need to get new version number)
	  $last_version_info =  $this->get_last_version_info($id, array('output' => 'minimun'));
	  $from = $source_version_id;
	  if( is_null($source_version_id) || $source_version_id <= 0)
	  {
	  	$from = $last_version_info['id'];
	  }
	  $this->copy_tcversion($id,$from,$tcversion_id,$last_version_info['version']+1,$user_id);
	
	  $ret['id'] = $tcversion_id;
	  $ret['version'] = $last_version_info['version']+1;
	  $ret['msg'] = 'ok';
	  return $ret;
	}
	
	
	
	/*
	  function: get_last_version_info
	            Get information about last version (greater number) of a testcase.
	
	  args : id: testcase id
	         [options]
	
	  returns: map with keys  that depends of options['output']:
	
		  			 id -> tcversion_id
					   version
					   summary
					   importance
					   author_id
					   creation_ts
					   updater_id
					   modification_ts
					   active
					   is_open
	
	*/
	function get_last_version_info($id,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $my['options'] = array( 'get_steps' => false, 'output' => 'full');
	    $my['options'] = array_merge($my['options'], (array)$options);
		$tcInfo = null;
		switch($my['options']['output'])
		{
			case 'minimun':
			default:
				$fields2get = " TCV.id, TCV.version, TCV.tc_external_id ";
			break;		

			case 'full':
			default:
				$fields2get = " TCV.* ";
			break;		
		}
		
		
		$sql = "/* $debugMsg */ SELECT MAX(version) AS version " .
		       " FROM {$this->tables['tcversions']} TCV," .
		       " {$this->tables['nodes_hierarchy']} NH WHERE ".
		       " NH.id = TCV.id ".
		       " AND NH.parent_id = {$id} ";
	
		$max_version = $this->db->fetchFirstRowSingleColumn($sql,'version');
	
		$tcInfo = null;
		if ($max_version)
		{
			$sql = "SELECT {$fields2get}  FROM {$this->tables['tcversions']} TCV," .
			       " {$this->tables['nodes_hierarchy']} NH ".
			       " WHERE TCV.version = {$max_version} AND NH.id = TCV.id".
				   " AND NH.parent_id = {$id}";
	
			$tcInfo = $this->db->fetchFirstRow($sql);
		}

		// Multiple Test Case Steps Feature
	    if( !is_null($tcInfo) && $my['options']['get_steps'] )
	    {
    		$step_set = $this->get_steps($tcInfo['id']);
    		$tcInfo['steps'] = $step_set;
	    }
		return $tcInfo;
	}
	
	
	/*
	  function: copy_tcversion
	
	  args:
	
	  returns:
	
	  rev: 
	  		20100521 - franciscom - BUGID 3481 - preconditions are not copied
	  		20080119 - franciscom - tc_external_id management
	
	*/
	// // BUGID 3431
	function copy_tcversion($id,$from_tcversion_id,$to_tcversion_id,$as_version_number,$user_id)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $now = $this->db->db_now();
	    $sql="/* $debugMsg */ " . 
	         " INSERT INTO {$this->tables['tcversions']} " . 
	         " (id,version,tc_external_id,author_id,creation_ts,summary, " . 
	         "  importance,execution_type,preconditions) " .
	         " SELECT {$to_tcversion_id} AS id, {$as_version_number} AS version, " .
	         "        tc_external_id, " .
	         "        {$user_id} AS author_id, {$now} AS creation_ts," .
	         "        summary,importance,execution_type, preconditions" .
	         " FROM {$this->tables['tcversions']} " .
	         " WHERE id={$from_tcversion_id} ";
		$result = $this->db->exec_query($sql);	
	   
	    // BUGID 3431
	    // copy custom fields values JUST DESIGN AREA
	    $this->copy_cfields_design_values(array('id' => $id, 'tcversion_id' => $from_tcversion_id),
						  				  array('id' => $id, 'tcversion_id' => $to_tcversion_id));
    
	    
	    // Need to get all steps
	    $stepsSet = $this->get_steps($from_tcversion_id);
		if( !is_null($stepsSet) && count($stepsSet) > 0)
		{
	    	foreach($stepsSet as $key => $step)
	    	{
        		$op = $this->create_step($to_tcversion_id,$step['step_number'],$step['actions'],
        		                         $step['expected_results'],$step['execution_type']);			
	    	}
	    }
	}
	
	
	/*
	  function: get_by_id_bulk
	
	            IMPORTANT CONSIDERATION: 
	            how may elements can be used in an SQL IN CLAUSE?
	            Think there is a limit ( on MSSQL 1000 ?)
	                                      
	  args :
	
	  returns:
	
	*/
	function get_by_id_bulk($id,$version_id=self::ALL_VERSIONS, $get_active=0, $get_open=0)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$where_clause="";
		$where_clause_names="";
		$tcid_list ="";
		$tcversion_id_filter="";
		$sql = "";
		$the_names = null;
		if( is_array($id) )
		{
			$tcid_list = implode(",",$id);
			$where_clause = " WHERE nodes_hierarchy.parent_id IN ($tcid_list) ";
			$where_clause_names = " WHERE nodes_hierarchy.id IN ($tcid_list) ";
		}
		else
		{
			$where_clause = " WHERE nodes_hierarchy.parent_id = {$id} ";
			$where_clause_names = " WHERE nodes_hierarchy.id = {$id} ";
		}
	  	if( $version_id != self::ALL_VERSIONS )
	  	{
	  	    $tcversion_id_filter=" AND tcversions.id IN (" . implode(",",(array)$version_id) . ") ";
	  	}
	
		$sql = " /* $debugMsg */ SELECT nodes_hierarchy.parent_id AS testcase_id, ".
		       " tcversions.*, users.first AS author_first_name, users.last AS author_last_name, " .
		       " '' AS updater_first_name, '' AS updater_last_name " .
		       " FROM {$this->tables['nodes_hierarchy']} nodes_hierarchy " .
		       " JOIN {$this->tables['tcversions']} tcversions ON nodes_hierarchy.id = tcversions.id " .
	           " LEFT OUTER JOIN {$this->tables['users']} users ON tcversions.author_id = users.id " .
	           " {$where_clause} {$tcversion_id_filter} ORDER BY tcversions.version DESC";
	  $recordset = $this->db->get_recordset($sql);
	
	  if($recordset)
	  {
	  	 // get the names
		 $sql = " /* $debugMsg */ " . 
		        " SELECT nodes_hierarchy.id AS testcase_id, nodes_hierarchy.name " .
		        " FROM {$this->tables['nodes_hierarchy']} nodes_hierarchy {$where_clause_names} ";
	
		 $the_names = $this->db->get_recordset($sql);
	     if($the_names)
	     {
	    	  foreach ($recordset as  $the_key => $row )
	    	  {
	          	reset($the_names);
	          	foreach($the_names as $row_n)
	          	{
	          		  if( $row['testcase_id'] == $row_n['testcase_id'])
	          		  {
	          		    $recordset[$the_key]['name']= $row_n['name'];
	          		    break;
	          		  }
	          	}
	  	    }
	  	 }
	
	
		 $sql = " /* $debugMsg */ " . 
		        " SELECT updater_id, users.first AS updater_first_name, users.last  AS updater_last_name " .
		        " FROM {$this->tables['nodes_hierarchy']} nodes_hierarchy " .
		        " JOIN {$this->tables['tcversions']} tcversions ON nodes_hierarchy.id = tcversions.id " .
	            " LEFT OUTER JOIN {$this->tables['users']} users ON tcversions.updater_id = users.id " .
	            " {$where_clause} and tcversions.updater_id IS NOT NULL ";
	
	    $updaters = $this->db->get_recordset($sql);
	
	    if($updaters)
	    {
	    	reset($recordset);
	    	foreach ($recordset as  $the_key => $row )
	    	{
	    		if ( !is_null($row['updater_id']) )
	    		{
	      		foreach ($updaters as $row_upd)
	      		{
	            if ( $row['updater_id'] == $row_upd['updater_id'] )
	            {
	              $recordset[$the_key]['updater_last_name'] = $row_upd['updater_last_name'];
	              $recordset[$the_key]['updater_first_name'] = $row_upd['updater_first_name'];
	              break;
	            }
	      		}
	      	}
	      }
	    }
	
	  }
	
	
	  return($recordset ? $recordset : null);
	}
	
	
	
	
	/*
	  function: get_by_id
	
	  args : id: can be a single testcase id or an array od testcase id.
	
	         [version_id]: default self::ALL_VERSIONS => all versions
	                       can be an array.
	                       Useful to retrieve only a subset of versions.
	                       null => means use version_number argument

			 [filters]:		
	         			[active_status]: default 'ALL', range: 'ALL','ACTIVE','INACTIVE'
	         			                 has effect for the following version_id values:
	         			                 self::ALL_VERSIONS,TC_LAST_VERSION, version_id is NOT an array
	         			
	         			[open_status]: default 'ALL'
	         			               currently not used.
	         			               
	         			[version_number]: default 1, version number displayed at User Interface               
	
			 [options]:		
	         			[output]: default 'full'
	         					  domain 'full','essential','full_without_steps'        
	
	  returns: array 
	
	@internal revisions
	20110312 - franciscom - now id can be null, to allow get just by version id
	
	*/

	function get_by_id($id,$version_id = self::ALL_VERSIONS, $filters = null, $options=null)
	{

	    $my['filters'] = array( 'active_status' => 'ALL', 'open_status' => 'ALL', 'version_number' => 1);
	    $my['filters'] = array_merge($my['filters'], (array)$filters);

	    $my['options'] = array( 'output' => 'full', 'access_key' => 'tcversion_id');
	    $my['options'] = array_merge($my['options'], (array)$options);

		$tcid_list = null;
		$where_clause = '';
		$active_filter = '';
		$versionSQLOp = ' AND ';
		

		if( ($accessByVersionID = is_null($id) && !is_null($version_id)) )
		{
			$versionSQLOp = ' WHERE ';
		}
		else if(is_array($id))
		{
			$tcid_list = implode(",",$id);
			$where_clause = " WHERE NHTCV.parent_id IN ({$tcid_list}) ";
		}
		else
		{
			$where_clause = " WHERE NHTCV.parent_id = {$id} ";
		}
	
		if( ($version_id_is_array=is_array($version_id)) )
		{
		    $versionid_list = implode(",",$version_id);
		    $where_clause .= $versionSQLOp . " TCV.id IN ({$versionid_list}) ";
		}
		else
		{
		    // search by human version number
		    if( is_null($version_id) )
		    {
		    	// when tcase ID has not been provided this can not be used
		    	// will not do any check => leave it CRASH
		        $where_clause .= " AND TCV.version = {$my['filters']['version_number']} ";
		    }
		    else 
		    {
			    if($version_id != self::ALL_VERSIONS && $version_id != self::LATEST_VERSION)
			    {
			    	$where_clause .= $versionSQLOp .  " TCV.id = {$version_id} ";
			    }
	        }
	        
			$active_status = strtoupper($my['filters']['active_status']);
		  	if($active_status != 'ALL')
		  	{
		    	$active_filter =' AND TCV.active=' . ($active_status=='ACTIVE' ? 1 : 0) . ' ';
	    	}
		}
	
		switch($my['options']['output'])
		{
			case 'full':
			case 'full_without_steps':
				$sql = "SELECT UA.login AS updater_login,UB.login AS author_login,
			     		NHTC.name,NHTC.node_order,NHTC.parent_id AS testsuite_id,
			     		NHTCV.parent_id AS testcase_id, TCV.*,
			     		UB.first AS author_first_name,UB.last AS author_last_name,
			     		UA.first AS updater_first_name,UA.last AS updater_last_name
	         			FROM {$this->tables['nodes_hierarchy']} NHTCV
	         			JOIN {$this->tables['nodes_hierarchy']} NHTC ON NHTCV.parent_id = NHTC.id
	         			JOIN {$this->tables['tcversions']} TCV ON NHTCV.id = TCV.id
	         			LEFT OUTER JOIN {$this->tables['users']} UB ON TCV.author_id = UB.id
	         			LEFT OUTER JOIN {$this->tables['users']} UA ON TCV.updater_id = UA.id
	         			$where_clause $active_filter
	         			ORDER BY TCV.version DESC";
	         	break;
	         	
			case 'essential':
				$sql = " SELECT NHTC.name,NHTC.node_order,NHTCV.parent_id AS testcase_id, " . 
				       " NHTC.parent_id AS testsuite_id, " .
				       " TCV.version, TCV.id, TCV.tc_external_id " .
	         		   " FROM {$this->tables['nodes_hierarchy']} NHTCV " . 
	         		   " JOIN {$this->tables['nodes_hierarchy']} NHTC ON NHTCV.parent_id = NHTC.id " .
	         		   " JOIN {$this->tables['tcversions']} TCV ON NHTCV.id = TCV.id " .
	         		   " {$where_clause} {$active_filter} " .
	         		   " ORDER BY TCV.version DESC";
	         	break;
		}
		
	    // Control improvements
		if( !$version_id_is_array && $version_id == self::LATEST_VERSION)
		{
		    // 20090413 - franciscom - 
		    // But, how performance wise can be do this, instead of using MAX(version)
		    // and a group by? 
		    //           
		    // 20100309 - franciscom - 
		    // if $id was a list then this will return something USELESS
		    //           
		    if( is_null($tcid_list) )
		    {         
				$recordset = array($this->db->fetchFirstRow($sql));
			}	
			else
			{
				// Write to event viewer ???
				// throw exception ??
			}
		}
		else
		{
			$recordset = $this->db->get_recordset($sql);
	    }
		
	    // Multiple Test Case Steps
	    if( !is_null($recordset) && $my['options']['output'] == 'full')
	    {
	  		$key2loop = array_keys($recordset);
	  		foreach( $key2loop as $accessKey)
	  		{	
	  			$step_set = $this->get_steps($recordset[$accessKey]['id']);
	  			$recordset[$accessKey]['steps'] = $step_set;
	  		} 
	    }
		return ($recordset ? $recordset : null);
	}
	
	
	/*
	  function: get_versions_status_quo
	            Get linked and executed status quo.
	            
	            IMPORTANT:
	            NO INFO SPECIFIC TO TESTPLAN ITEMS where testacase can be linked to
	            is returned.
	
	
	  args : id: test case id
	         [tcversion_id]: default: null -> get info about all versions.
	                         can be a single value or an array.
	
	
	         [testplan_id]: default: null -> all testplans where testcase is linked,
	                                         are analised to generate results.
	
	                        when not null, filter for testplan_id, to analise for
	                        generating results.
	get_exec_status
	
	
	  returns: map.
	           key: tcversion_id.
	           value: map with the following keys:
	
	           tcversion_id, linked , executed
	
	           linked field: will take the following values
	                         if $testplan_id == null
	                            NULL if the tc version is not linked to ANY TEST PLAN
	                            tcversion_id if linked
	
	                         if $testplan_id != null
	                            NULL if the tc version is not linked to $testplan_id
	
	
	           executed field: will take the following values
	                           if $testplan_id == null
	                              NULL if the tc version has not been executed in ANY TEST PLAN
	                              tcversion_id if has executions.
	
	                           if $testplan_id != null
	                              NULL if the tc version has not been executed in $testplan_id
	
	rev :
	
	*/
	function get_versions_status_quo($id, $tcversion_id=null, $testplan_id=null)
	{
	    $testplan_filter='';
	    $tcversion_filter='';
	    if(!is_null($tcversion_id))
	    {
	      if(is_array($tcversion_id))
	      {
	         $tcversion_filter=" AND NH.id IN (" . implode(",",$tcversion_id) . ") ";
	      }
	      else
	      {
	         $tcversion_filter=" AND NH.id={$tcversion_id} ";
	      }
	
	    }
	
	    $testplan_filter='';
		if(!is_null($testplan_id))
	    {
	      $testplan_filter=" AND E.testplan_id = {$testplan_id} ";
	    }
	    $execution_join=" LEFT OUTER JOIN {$this->tables['executions']} E " .
	                    " ON (E.tcversion_id = NH.id {$testplan_filter})";
	
	 	$sqlx=  " SELECT TCV.id,TCV.version " .
	            " FROM {$this->tables['nodes_hierarchy']} NHA " .
	            " JOIN {$this->tables['nodes_hierarchy']} NHB ON NHA.parent_id = NHB.id " .
	            " JOIN {$this->tables['tcversions']}  TCV ON NHA.id = TCV.id " .
	            " WHERE  NHA.parent_id = {$id}";
	            
		$version_id = $this->db->fetchRowsIntoMap($sqlx,'version');
	
		$sql="SELECT DISTINCT NH.id AS tcversion_id,T.tcversion_id AS linked, " .
		     " E.tcversion_id AS executed,E.tcversion_number,TCV.version " .
		     " FROM   {$this->tables['nodes_hierarchy']} NH " .
	         " JOIN {$this->tables['tcversions']} TCV ON (TCV.id = NH.id ) " .
		     " LEFT OUTER JOIN {$this->tables['testplan_tcversions']} T ON T.tcversion_id = NH.id " .
		     " {$execution_join} WHERE  NH.parent_id = {$id} {$tcversion_filter} ORDER BY executed DESC";
	
		$rs = $this->db->get_recordset($sql);
	
	    $recordset=array();
	    $template=array('tcversion_id' => '','linked' => '','executed' => '');
	    foreach($rs as $elem)
	    {
	      $recordset[$elem['tcversion_id']]=$template;  
	      $recordset[$elem['tcversion_id']]['tcversion_id']=$elem['tcversion_id'];  
	      $recordset[$elem['tcversion_id']]['linked']=$elem['linked'];  
	      $recordset[$elem['tcversion_id']]['version']=$elem['version'];  
	    }
	    
	    foreach($rs as $elem)
	    {
	      $tcvid=null;
	      if( $elem['tcversion_number'] != $elem['version'])
	      {
	      if( !is_null($elem['tcversion_number']) )
	      {
	            $tcvid=$version_id[$elem['tcversion_number']]['id'];
	        }    
	      }
	      else
	      {
	        $tcvid=$elem['tcversion_id'];
	      }
	      if( !is_null($tcvid) )
	      {
	          $recordset[$tcvid]['executed']=$tcvid;
	          $recordset[$tcvid]['version']=$elem['tcversion_number'];
	      }    
	    }
	    return($recordset);
	}
	
	
	
	/*
	  function: get_exec_status
	            Get information about executed and linked status in
	            every testplan, a testcase is linked to. (remember we link test case VERSION)
	
	  args : id : testcase id
	         [exec_status]: default: ALL, range: ALL,EXECUTED,NOT_EXECUTED
	         [active_status]: default: ALL, range: ALL,ACTIVE,INACTIVE
	
	
	  returns: map
	           key: tcversion_id
	           value: map:
	                  key: testplan_id
	                  value: map with following keys:
	
	                  tcase_id
	 				          tcversion_id
	  			          version
	  			          testplan_id
	  				        tplan_name
	  				        linked         if linked to  testplan -> tcversion_id
					          executed       if executed in testplan -> tcversion_id
					          exec_on_tplan  if executed in testplan -> testplan_id
	
	
	  @internal revisions 
	       20100908 - franciscom - added platform name in output recordset
	       	
	       20080531 - franciscom
	       Because we allow people to update test case version linked to test plan,
	       and to do this we update tcversion_id on executions to new version
	       maintaining the really executed version in tcversion_number (version number displayed
	       on User Interface) field we need to change algorithm.
	*/
	function get_exec_status($id,$filters=null, $options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;

		$my = array();
		$my['filters'] = array(	'exec_status' => "ALL", 'active_status' => 'ALL',
								'tplan_id' => null, 'platform_id' => null);
		$my['options'] = array('addExecIndicator' => false);
	
	    $my['filters'] = array_merge($my['filters'], (array)$filters);
	    $my['options'] = array_merge($my['options'], (array)$options);
					  

	    $active_status = strtoupper($my['filters']['active_status']);
	    $exec_status = strtoupper($my['filters']['exec_status']);
	  	$tplan_id = $my['filters']['tplan_id'];
	  	$platform_id = $my['filters']['platform_id'];
	  
	    // Get info about tcversions of this test case
	    $sqlx = "/* $debugMsg */ " .
	            " SELECT TCV.id,TCV.version,TCV.active" .
	            " FROM {$this->tables['nodes_hierarchy']} NHA " .
	            " JOIN {$this->tables['nodes_hierarchy']} NHB ON NHA.parent_id = NHB.id " .
	            " JOIN {$this->tables['tcversions']}  TCV ON NHA.id = TCV.id ";
	          
	    $where_clause = " WHERE  NHA.parent_id = {$id}";
	          
	    if(!is_null($tplan_id))
	    {
	        $sqlx .= " JOIN {$this->tables['testplan_tcversions']}  TTCV ON TTCV.tcversion_id = TCV.id ";
	        $where_clause .= " AND TTCV.tplan_id = {$tplan_id} "; 
	    }    
	    $sqlx .= $where_clause; 
		$version_id = $this->db->fetchRowsIntoMap($sqlx,'version');	// DEBUG // new dBug($version_id);
		
	    $sql = "/* $debugMsg */ " .
			   " SELECT DISTINCT NH.parent_id AS tcase_id, NH.id AS tcversion_id, " .
			   " T.tcversion_id AS linked, T.platform_id, TCV.active, E.tcversion_id AS executed, " . 
			   " E.testplan_id AS exec_on_tplan, E.tcversion_number, " .
			   " T.testplan_id, NHB.name AS tplan_name, TCV.version, PLAT.name AS platform_name " .
			   " FROM   {$this->tables['nodes_hierarchy']} NH " .
			   " JOIN {$this->tables['testplan_tcversions']}  T ON T.tcversion_id = NH.id " .
			   " JOIN {$this->tables['tcversions']}  TCV ON T.tcversion_id = TCV.id " .
			   " JOIN {$this->tables['nodes_hierarchy']} NHB ON T.testplan_id = NHB.id " .
			   " LEFT OUTER JOIN {$this->tables['platforms']} PLAT " .
			   " ON T.platform_id = PLAT.id " .
			   " LEFT OUTER JOIN {$this->tables['executions']} E " .
			   " ON (E.tcversion_id = NH.id AND E.testplan_id=T.testplan_id AND E.platform_id=T.platform_id ) " .
			   " WHERE  NH.parent_id = {$id} ";
			
		if(!is_null($tplan_id))
	    {
	        $sql .= " AND T.tplan_id = {$tplan_id} "; 
	    }    
		if(!is_null($platform_id))
	    {
	        $sql .= " AND T.platform_id = {$platform_id} "; 
	    }    

	    $sql .= " ORDER BY version,tplan_name";
		$rs = $this->db->get_recordset($sql);	// DEBUG // new dBug($rs);

	    // set right tcversion_id, based on tcversion_number,version comparison
	    $item_not_executed = null;
	    $item_executed = null;
	    $link_info = null;
	    $in_set = null;
	    
	    if (sizeof($rs))
	    {
	    	foreach($rs as $idx => $elem)
			{
		    	if( $elem['tcversion_number'] != $elem['version'])
			    {
			    	
			        // Save to generate record for linked but not executed if needed
			        // (see below fix not executed section)
			        // access key => (version,test plan, platform)
			        $link_info[$elem['tcversion_id']][$elem['testplan_id']][$elem['platform_id']]=$elem;    
		
			      	// We are working with a test case version, that was used in a previous life of this test plan
			      	// information about his tcversion_id is not anymore present in tables:
			      	//
			      	// testplan_tcversions
			      	// executions
			      	// cfield_execution_values.
			      	//
			      	// if has been executed, but after this operation User has choosen to upgrade tcversion 
			      	// linked to testplan to a different (may be a newest) test case version.
			      	//
			      	// We can get this information using table tcversions using tcase id and version number 
			      	// (value displayed at User Interface) as search key.
			      	//
			      	// Important:
			      	// executions.tcversion_number:  maintain info about RIGHT TEST case version executed
			      	// executions.tcversion_id    :  test case version linked to test plan. 
			      	//
			      	//
			      	if( is_null($elem['tcversion_number']) )
			      	{
			      			// Not Executed
			      			$rs[$idx]['executed']=null;
		          			$rs[$idx]['tcversion_id']=$elem['tcversion_id'];
		          			$rs[$idx]['version']=$elem['version'];
		          			$rs[$idx]['linked']=$elem['tcversion_id'];
		          			$item_not_executed[]=$idx;  
			      	}
			      	else
			      	{
		        			// Get right tcversion_id
		        			$rs[$idx]['executed']=$version_id[$elem['tcversion_number']]['id'];
		        			$rs[$idx]['tcversion_id']=$rs[$idx]['executed'];
		        			$rs[$idx]['version']=$elem['tcversion_number'];
		        			$rs[$idx]['linked']=$rs[$idx]['executed'];
		        			$item_executed[]=$idx;
			      	}
			      	$version=$rs[$idx]['version'];
		          	$rs[$idx]['active']=$version_id[$version]['active'];	      
		      }
		      else
		      {
		          $item_executed[]=$idx;  
		      }
		
			  // needed for logic to avoid miss not executed (see below fix not executed)
			  $in_set[$rs[$idx]['tcversion_id']][$rs[$idx]['testplan_id']][$rs[$idx]['platform_id']]=$rs[$idx]['tcversion_id'];
			}
	    }
	    else
	    {
	    	$rs = array();
	    }
	    
	    // fix not executed
	    //
	    // need to add record for linked but not executed, that due to new logic to upate testplan-tcversions link 
	    // can be absent
	    if(!is_null($link_info))
	    {
	    	foreach($link_info as $tcversion_id => $elem)
	    	{
	            foreach($elem as $testplan_id => $platform_link)
	            {
	            	foreach($platform_link as $platform_id => $value)
	            	{
	            		if( !isset($in_set[$tcversion_id][$testplan_id][$platform_id]) ) 
	            		{
	            		    // missing record
	            		    $value['executed']=null;
	            		    $value['exec_on_tplan']=null;
	            		    $value['tcversion_number']=null;
	            		    $rs[]=$value;
	            		    
	            		    // Must Update list of not executed
	            		    $kix=count($rs);
	            		    $item_not_executed[]=$kix > 0 ? $kix-1 : $kix;
	            		}  
	            	
	            	}	
	            }   
	        }
	    }
	    
	    // Convert to result map.
		  switch ($exec_status)
		  {
	        case 'NOT_EXECUTED':
	             $target=$item_not_executed;
	        break;
	
	        case 'EXECUTED':
	             $target=$item_executed;
	        break;
	        
	        default:
	        	$target = array_keys($rs);
	        	break;
	    }
	
	    $recordset = null;
	    if( !is_null($target) )
	    {
	    	foreach($target as $idx)
			{
				$wkitem=$rs[$idx];
	    	   	if( $active_status=='ALL' ||
	    	   	    $active_status='ACTIVE' && $wkitem['active'] ||
	    	   	    $active_status='INACTIVE' && $wkitem['active']==0 )
	    	   	{    
	    	   	    $recordset[$wkitem['tcversion_id']][$wkitem['testplan_id']][$wkitem['platform_id']]=$wkitem;
	    	   	    if( $my['options']['addExecIndicator'] )
	    	   	 	{
	    	   	 		if( !isset($recordset['executed']) )
	    	   	 		{
	    	   	 			$recordset['executed'] = 0;
	    	   	 		}
	    	   	 		
	    	   	 		if( $recordset['executed'] == 0 )
	    	   	 		{ 
	    	   	 			if( !is_null($wkitem['executed']) )
	    	   	 			{
	    	   	 				$recordset['executed'] = 1;
	    	   	 			}
	    	   	 		}	
	    	   		}    
	    	   	}    
	    	}
	    }		  
	    if( !is_null($recordset) )
	    {
	    	// 20110402 - franciscom - unable to understand why is needed
	        ksort($recordset);
	    }
	    return $recordset;
	}
	// -------------------------------------------------------------------------------
	
	
	/**
	 * @param string stringID external test case ID
	 *      a string on the form XXXXXGNN where:
	 *          XXXXX: test case prefix, exists one for each test project
	 *          G: glue character
	 *          NN: test case number (generated using testprojects.tc_counter field)
	 *
	 * @return internal id (node id in nodes_hierarchy)
	 *		   0 -> test case prefix OK, but external id does not exists
	 *		   -1 -> test case prefix KO
	 *
	 * 20080818 - franciscom - Dev Note
	 * I'm a feeling regarding performance of this function.
	 * Surelly adding a new column to tcversions (prefix) will simplify a lot this function.
	 * Other choice (that I refuse to implement time ago) is to add prefix field
	 * as a new nodes_hierarchy column.
	 * This must be discussed with dev team if we got performance bottleneck trying
	 * to get internal id from external one.
	 *
	 * @internal Revisions:
	 * 20091229 - eloff - BUGID 3021 fixed error when tc prefix contains glue character
	 * 20090608 - franciscom - fixed error on management of numeric part (externalID)
	 * 20080126 - franciscom - BUGID 1313
	 */
	function getInternalID($stringID,$glueCharacter = null)
	{
		$internalID = 0;
		if (is_null($glueCharacter))
		{
			$glueCharacter = $this->cfg->testcase->glue_character;
		}
		
		// Find the last glue char
		$gluePos = strrpos($stringID, $glueCharacter);
		$status_ok = ($gluePos !== false);
		if($status_ok)
		{
			$internalID = -1;
			$rawTestCasePrefix = substr($stringID, 0, $gluePos);
			$rawExternalID = substr($stringID, $gluePos+1);
			$externalID = is_numeric($rawExternalID) ?  intval($rawExternalID) : 0;
	
			// Check first if Test Project prefix is valid, if not abort
			$testCasePrefix = $this->db->prepare_string($rawTestCasePrefix);
	    $sql = "SELECT id  FROM {$this->tables['testprojects']} " .
	           "WHERE prefix = '" . $testCasePrefix . "'";
			$tproject_info = $this->db->get_recordset($sql);
			$status_ok = !is_null($tproject_info);
		}
		
		if( $status_ok )
		{
			$internalID = 0;
			
			// get all test cases with requested external ID on all test projects.
			// we do not have way to work only on one test project.
			$sql = "SELECT DISTINCT NH.parent_id AS tcase_id" .
	           " FROM {$this->tables['tcversions']} TCV, {$this->tables['nodes_hierarchy']} NH" .
	           " WHERE TCV.id = NH.id " .
	           " AND  TCV.tc_external_id = {$externalID}";
	
			$testCases = $this->db->fetchRowsIntoMap($sql,'tcase_id');
			if(!is_null($testCases))
			{
				$tproject_id = $tproject_info[0]['id'];
				foreach($testCases as $tcaseID => $value)
				{
	        $path2root = $this->tree_manager->get_path($tcaseID);
					if($tproject_id == $path2root[0]['parent_id'])
					{
	          $internalID = $tcaseID;
						break;
          }
	      }	
			}
		}

		return $internalID;
	}
	
	/*
	  function: filterByKeyword
	            given a test case id (or an array of test case id) 
	            and a keyword filter, returns for the test cases given in input
	            only which pass the keyword filter criteria.
	            
	
	  args :
	  
	  returns: 
	
	*/
	function filterByKeyword($id,$keyword_id=0, $keyword_filter_type='OR')
	{
	    $keyword_filter= '' ;
	    $subquery='';
	    
	    // test case filter
	    if( is_array($id) )
	    {
	        $testcase_filter = " AND testcase_id IN (" . implode(',',$id) . ")";          	
	    }
	    else
	    {
	        $testcase_filter = " AND testcase_id = {$id} ";
	    }    
	    
	    if( is_array($keyword_id) )
	    {
	        $keyword_filter = " AND keyword_id IN (" . implode(',',$keyword_id) . ")";          	
	        
	        if($keyword_filter_type == 'AND')
	        {
			        $subquery = "AND testcase_id IN (" .
			                    " SELECT MAFALDA.testcase_id FROM
			                      ( SELECT COUNT(testcase_id) AS HITS,testcase_id
			                        FROM {$this->tables['keywords']} K, {$this->tables['testcase_keywords']}
			                        WHERE keyword_id = K.id
			                        {$keyword_filter}
			                        GROUP BY testcase_id ) AS MAFALDA " .
			                    " WHERE MAFALDA.HITS=" . count($keyword_id) . ")";
			                 
	            $keyword_filter ='';
	        }    
	    }
	    else if( $keyword_id > 0 )
	    {
	        $keyword_filter = " AND keyword_id = {$keyword_id} ";
	    }
			
			$map_keywords = null;
			$sql = " SELECT testcase_id,keyword_id,keyword
			         FROM {$this->tables['keywords']} K, {$this->tables['testcase_keywords']}
			         WHERE keyword_id = K.id
			         {$testcase_filter}
			         {$keyword_filter} {$subquery}
				       ORDER BY keyword ASC ";
	
			// $map_keywords = $this->db->fetchRowsIntoMap($sql,'testcase_id');
			$map_keywords = $this->db->fetchMapRowsIntoMap($sql,'testcase_id','keyword_id');
	
			return($map_keywords);
	} //end function
	
	
	
	// -------------------------------------------------------------------------------
	//                            Keyword related methods
	// -------------------------------------------------------------------------------
	/*
	  function: getKeywords
	
	  args :
	
	  returns:
	
	*/
	function getKeywords($tcID,$kwID = null,$column = 'keyword_id',$orderByClause = null)
	{
		$sql = "SELECT keyword_id,keywords.keyword,keywords.notes,testcase_id
		        FROM {$this->tables['testcase_keywords']} testcase_keywords, {$this->tables['keywords']} keywords
		        WHERE keyword_id = keywords.id AND testcase_id ";
		$bCumulative = 0;
		if (is_array($tcID))
		{
			$sql .= " IN (".implode(",",$tcID).")";
			$bCumulative = 1;
		}
		else
		{
			$sql .=  "= {$tcID}";
		}
		if (!is_null($kwID))
		{
			$sql .= " AND keyword_id = {$kwID}";
		}
		if (!is_null($orderByClause))
		{
			$sql .= $orderByClause;
		}	
		$tcKeywords = $this->db->fetchRowsIntoMap($sql,$column,$bCumulative);
	
		return $tcKeywords;
	}
	
	
	/*
	  function: get_keywords_map
	
	  args: id: testcase id
	        [order_by_clause]: default: '' -> no order choosen
	                           must be an string with complete clause, i.e.
	                           'ORDER BY keyword'
	
	
	  returns: map with keywords information
	           key: keyword id
	           value: map with following keys.
	
	
	*/
	function get_keywords_map($id,$order_by_clause='')
	{
		$sql = "SELECT keyword_id,keywords.keyword
		        FROM {$this->tables['testcase_keywords']} testcase_keywords, {$this->tables['keywords']} keywords
		        WHERE keyword_id = keywords.id ";
		if (is_array($id))
			$sql .= " AND testcase_id IN (".implode(",",$id).") ";
		else
			$sql .= " AND testcase_id = {$id} ";
	
		$sql .= $order_by_clause;
	
		$map_keywords = $this->db->fetchColumnsIntoMap($sql,'keyword_id','keyword');
		return $map_keywords;
	}
	
	/*
	  function: 
	
	  args :
	  
	  returns: 
	
	*/
	function addKeyword($id,$kw_id,$audit=self::AUDIT_ON)
	{
		$kw = $this->getKeywords($id,$kw_id);
		if (sizeof($kw))
		{
			return 1;
		}
		
		$sql = " INSERT INTO {$this->tables['testcase_keywords']} (testcase_id,keyword_id) " .
			   " VALUES ($id,$kw_id)";
	
		$result = ($this->db->exec_query($sql) ? 1 : 0);
		if ($result)
		{
			$tcInfo = $this->tree_manager->get_node_hierarchy_info($id);
			$keyword = tlKeyword::getByID($this->db,$kw_id);
			if ($keyword && $tcInfo && $audit == self::AUDIT_ON)
			{
				logAuditEvent(TLS("audit_keyword_assigned_tc",$keyword->name,$tcInfo['name']),
				                  "ASSIGN",$id,"nodes_hierarchy");
			}	
		}
		return $result;
	}
	
	/*
	  function: 
	
	  args :
	  
	  returns: 
	
	*/
	function addKeywords($id,$kw_ids,$audit = self::AUDIT_ON)
	{
		$status_ok = 1;
		$num_kws = sizeof($kw_ids);
		for($idx = 0; $idx < $num_kws; $idx++)
		{
			$status_ok = $status_ok && $this->addKeyword($id,$kw_ids[$idx],$audit);
		}
	
		return $status_ok;
	}
	/*
	  function: set's the keywords of the given testcase to the passed keywords
	
	  args :
	  
	  returns: 
	
	*/
	function setKeywords($id,$kw_ids,$audit = self::AUDIT_ON)
	{
		$result = $this->deleteKeywords($id);   	 
		if ($result && sizeof($kw_ids))
		{
			$result = $this->addKeywords($id,$kw_ids);
		}	
		return $result;
	}
	
	/**
	 * 
	 *
 	 * mappings is only useful when source_id and target_id do not belong to same Test Project.
	 * Because keywords are defined INSIDE a Test Project, ID will be different for same keyword
	 * in a different Test Project.
     *
 	 */
	function copyKeywordsTo($id,$destID,$mappings)
	{
		$status_ok = true;
		$this->deleteKeywords($destID);
		$sourceItems = $this->getKeywords($id);
        
		if( !is_null($sourceItems) )
		{
			// build item id list
			$keySet = array_keys($sourceItems);
			foreach($keySet as $itemPos => $itemID)
			{
		 		if( isset($mappings[$itemID]) )
		 		{
		 			$keySet[$itemPos] = $mappings[$itemID];
		 		}
				$status_ok = $status_ok && $this->addKeyword($destID,$keySet[$itemPos]);
			}	
		
		}	
		return $status_ok;
	}
	
	/*
	  function: 
	
	  args :
	  
	  returns: 
	
	*/
	function deleteKeywords($tcID,$kwID = null,$audit=self::AUDIT_ON)
	{
		$sql = " DELETE FROM {$this->tables['testcase_keywords']}  WHERE testcase_id = {$tcID} ";
		if (!is_null($kwID))
		{
			if(is_array($kwID))
		  	{
			    $sql .= " AND keyword_id IN (" . implode(',',$kwID) . ")";
			    $key4log=$kwID;
			}
			else
			{
			    $sql .= " AND keyword_id = {$kwID}";
			    $key4log = array($kwID);
			}    
		}	
		else
		{
			$key4log = array_keys((array)$this->get_keywords_map($tcID));
		}
			
		$result = $this->db->exec_query($sql);
		if ($result)
		{
			$tcInfo = $this->tree_manager->get_node_hierarchy_info($tcID);
			if ($tcInfo && $key4log)
			{
				foreach($key4log as $key2get)
				{
					$keyword = tlKeyword::getByID($this->db,$key2get);
					if ($keyword && $audit==self::AUDIT_ON)
					{
						logAuditEvent(TLS("audit_keyword_assignment_removed_tc",$keyword->name,$tcInfo['name']),
						              "ASSIGN",$tcID,"nodes_hierarchy");
					}	
				}
			}
		}
	
		return $result;
	}
	
	// -------------------------------------------------------------------------------
	//                            END Keyword related methods
	// -------------------------------------------------------------------------------
	
	/*
	  function: get_executions
	            get information about all execution for a testcase version, on a testplan
	            on a build. Execution results are ordered by execution timestamp.
	
	            Is possible to filter certain executions
	            Is possible to choose Ascending/Descending order of results. (order by exec timestamp).
	
	
	  args : id: testcase (node id) - can be single value or array.
	         version_id: tcversion id (node id) - can be single value or array.
	         tplan_id: testplan id
	         build_id: if null -> do not filter by build_id
	         platform_id: if null -> do not filter by build_id
             options: default null, map with options.
	                  [exec_id_order] default: 'DESC' - range: ASC,DESC
	                  [exec_to_exclude]: default: null -> no filter
	                                    can be single value or array, this exec id will be EXCLUDED.
	
	
	  returns: map
	           key: tcversion id
	           value: array where every element is a map with following keys
	
	                  name: testcase name
	                  testcase_id
	                  id: tcversion_id
	                  version
	                  summary: testcase spec. summary
	                  steps: testcase spec. steps
	                  expected_results: testcase spec. expected results
	                  execution_type: see const.inc.php TESTCASE_EXECUTION_TYPE_ constants
	                  importance
	                  author_id: tcversion author
	                  creation_ts: timestamp of creation
	                  updater_id: last updater of specification
	                  modification_ts:
	                  active: tcversion active status
	                  is_open: tcversion open status
	                  tester_login
	                  tester_first_name
	                  tester_last_name
	                  tester_id
	                  execution_id
	                  status: execution status
	                  execution_notes
	                  execution_ts
	                  execution_run_type: see const.inc.php TESTCASE_EXECUTION_TYPE_ constants
	                  build_id
	                  build_name
	                  build_is_active
	                  build_is_open
	                  platform_id
	                  platform_name
	
	*/
	function get_executions($id,$version_id,$tplan_id,$build_id,$platform_id,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $my['options'] = array('exec_id_order' => 'DESC', 'exec_to_exclude' => null); 	
	    $my['options'] = array_merge($my['options'], (array)$options);
		
		$filterKeys = array('build_id','platform_id');
        foreach($filterKeys as $key)
        {
        	$filterBy[$key] = '';
        	if( !is_null($$key) )
        	{
        		$itemSet = implode(',', (array)$$key);
        		$filterBy[$key] = " AND e.{$key} IN ({$itemSet}) ";
        	}
        }
	
		// --------------------------------------------------------------------
		if( is_array($id) )
		{
			$tcid_list = implode(",",$id);
			$where_clause = " WHERE NHA.parent_id IN ({$tcid_list}) ";
		}
		else
		{
			$where_clause = " WHERE NHA.parent_id = {$id} ";
		}
	
		if( is_array($version_id) )
		{
		    $versionid_list = implode(",",$version_id);
		    $where_clause  .= " AND tcversions.id IN ({$versionid_list}) ";
		}
		else
		{
				if($version_id != self::ALL_VERSIONS)
				{
					$where_clause  .= " AND tcversions.id = {$version_id} ";
				}
		}
	
	  if( !is_null($my['options']['exec_to_exclude']) )
	  {
	
				if( is_array($my['options']['exec_to_exclude']))
				{
				    if(count($my['options']['exec_to_exclude']) > 0 )
				    {
				 	  	$exec_id_list = implode(",",$my['options']['exec_to_exclude']);
		        		$where_clause  .= " AND e.id NOT IN ({$exec_id_list}) ";
		        	}
				}
				else
				{
		        $where_clause  .= " AND e.id <> {$exec_id_list} ";
				}
		}
	  // --------------------------------------------------------------------
	  // 20090517 - to manage deleted users i need to change:
	  //            users.id AS tester_id => e.tester_id AS tester_id
	  // 20090214 - franciscom - e.execution_type -> e.execution_run_type
	  //
	  $sql="/* $debugMsg */ SELECT NHB.name,NHA.parent_id AS testcase_id, tcversions.*,
			    users.login AS tester_login,
			    users.first AS tester_first_name,
			    users.last AS tester_last_name,
				e.tester_id AS tester_id,
			    e.id AS execution_id, e.status,e.tcversion_number,
			    e.notes AS execution_notes, e.execution_ts, e.execution_type AS execution_run_type,
			    e.build_id AS build_id,
			    b.name AS build_name, b.active AS build_is_active, b.is_open AS build_is_open,
   			    e.platform_id,p.name AS platform_name
		    FROM {$this->tables['nodes_hierarchy']} NHA
	        JOIN {$this->tables['nodes_hierarchy']} NHB ON NHA.parent_id = NHB.id
	        JOIN {$this->tables['tcversions']} tcversions ON NHA.id = tcversions.id
	        JOIN {$this->tables['executions']} e ON NHA.id = e.tcversion_id
	                                             AND e.testplan_id = {$tplan_id}
	                                             {$filterBy['build_id']} {$filterBy['platform_id']}
	        JOIN {$this->tables['builds']}  b ON e.build_id=b.id
	        LEFT OUTER JOIN {$this->tables['users']} users ON users.id = e.tester_id
	        LEFT OUTER JOIN {$this->tables['platforms']} p ON p.id = e.platform_id
	        $where_clause
	        ORDER BY NHA.node_order ASC, NHA.parent_id ASC, execution_id {$my['options']['exec_id_order']}";
	
	  $recordset = $this->db->fetchArrayRowsIntoMap($sql,'id');
	  return($recordset ? $recordset : null);
	}
	
	
	/*
	  function: get_last_execution
	
	  args :
  
	
	  returns: map:
	           key: tcversions.id
	           value: map with following keys:
	            			execution_id
	            			status: execution status
	                  execution_type: see const.inc.php TESTCASE_EXECUTION_TYPE_ constants
	            			name: testcase name
	            			testcase_id
	            			tsuite_id: parent testsuite of testcase (node id)
	            			id: tcversion id (node id)
	            			version
	                  summary: testcase spec. summary
	                  steps: testcase spec. steps
	                  expected_results: testcase spec. expected results
	                  execution_type: type of execution desired
	            			importance
	                  author_id: tcversion author
	                  creation_ts: timestamp of creation
	                  updater_id: last updater of specification.
	            			modification_ts
	                  active: tcversion active status
	                  is_open: tcversion open status
	            			tester_login
	            			tester_first_name
	            			tester_last_name
	            			tester_id
	            			execution_notes
	            			execution_ts
	            			execution_run_type:  how the execution was really done
	            			build_id
	            			build_name
	            			build_is_active
	            			build_is_open
	
	   rev:
	   	   20101212 - franciscom - internal bug get_last_execution() empty where clause -> do not use $id
	   	   						   this bug seems to AFFECT ONLY API CALLS
	   	   						   added new options getSteps
	   	   						   		
	       20090815 - franciscom - added platform_id argument
	       20090716 - franciscom - added options argument, removed get_no_executions
	       20080103 - franciscom - added execution_type
	
	*/
	function get_last_execution($id,$version_id,$tplan_id,$build_id,$platform_id,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$status_not_run = $this->cfg->results['status_code']['not_run'];

		$filterKeys = array('build_id','platform_id');
    foreach($filterKeys as $key)
    {
        	$filterBy[$key] = '';
        	if( !is_null($$key) )
        	{
        		$itemSet = implode(',', (array)$$key);
        		$filterBy[$key] = " AND e.{$key} IN ({$itemSet}) ";
        	}
        }
		$where_clause_1 = '';
		$where_clause_2 = '';
        $add_columns='';
	    $add_groupby='';
        $cumulativeMode=0;
       	$group_by = '';
        
		// getNoExecutions: 1 -> if testcase/version_id has not been executed return anyway
		//                       standard return structure.
		//                  0 -> default
		//
		// groupByBuild: 0 -> default, get last execution on ANY BUILD, then for a testcase/version_id
		//                    only a record will be present on return struture.
		//                    GROUP BY must be done ONLY BY tcversion_id
		//                  
		//               1 -> get last execution on EACH BUILD.
		//                    GROUP BY must be done BY tcversion_id,build_id
		//   
		$localOptions=array('getNoExecutions' => 0, 'groupByBuild' => 0, 'getSteps' => 1);
        if(!is_null($options) && is_array($options))
        {
        	$localOptions=array_merge($localOptions,$options);		
        }
		if( is_array($id) )
		{
			$tcid_list = implode(",",$id);
			$where_clause = " WHERE NHA.parent_id IN ({$tcid_list}) ";
		}
		else
		{
			$where_clause = " WHERE NHA.parent_id = {$id} ";
		}

		if( is_array($version_id) )
		{
		    $versionid_list = implode(",",$version_id);
		    $where_clause_1 = $where_clause . " AND NHA.id IN ({$versionid_list}) ";
		    $where_clause_2 = $where_clause . " AND tcversions.id IN ({$versionid_list}) ";
	
		}
		else
		{
			if($version_id != self::ALL_VERSIONS)
			{
				$where_clause_1 = $where_clause . " AND NHA.id = {$version_id} ";
				$where_clause_2 = $where_clause . " AND tcversions.id = {$version_id} ";
			}
		}
		
		// This logic (is mine - franciscom) must be detailed better!!!!!
		$group_by = ' GROUP BY tcversion_id ';
        $add_fields = ', e.tcversion_id AS tcversion_id';
        if( $localOptions['groupByBuild'] )
        {
        	$add_fields .= ', e.build_id';
	        $group_by .= ', e.build_id';
            $cumulativeMode = 1;
	    	
	    	// Hummm!!! I do not understand why this can be needed
	    	$where_clause_1 = $where_clause;
	    	$where_clause_2 = $where_clause;
        }

		// 20101212 - franciscom - may be not the best logic but ...      	
	    $where_clause_1 = ($where_clause_1 == '') ? $where_clause : $where_clause_1;
	    $where_clause_2 = ($where_clause_2 == '') ? $where_clause : $where_clause_2;

	  	$sql="/* $debugMsg */ " . 
	  	     " SELECT COALESCE(MAX(e.id),0) AS execution_id {$add_fields}" .
	  		 " FROM {$this->tables['nodes_hierarchy']} NHA " .
	  	     " JOIN {$this->tables['executions']} e ON NHA.id = e.tcversion_id AND e.testplan_id = {$tplan_id} " .
	  	     " {$filterBy['build_id']} {$filterBy['platform_id']}" .
	  	     " AND e.status IS NOT NULL " .
	  	     " $where_clause_1 {$group_by}";
	     
      	// 20090716 - order of columns changed
	  	$recordset = $this->db->fetchColumnsIntoMap($sql,'execution_id','tcversion_id');
	  	$and_exec_id='';
	  	if( !is_null($recordset) && count($recordset) > 0)
	  	{
	  		$the_list = implode(",", array_keys($recordset));
	  		if($the_list != '')
	  		{
	  			if( count($recordset) > 1 )
	  			{
	  				$and_exec_id = " AND e.id IN ($the_list) ";
	  			}
	  			else
	  			{
	  				$and_exec_id = " AND e.id = $the_list ";
	  			}
	  		}
	  	}
	  	
	  	$executions_join=" JOIN {$this->tables['executions']} e ON NHA.id = e.tcversion_id " .
	  	                 " AND e.testplan_id = {$tplan_id} {$and_exec_id} {$filterBy['build_id']} " .
	  	                 " {$filterBy['platform_id']} ";
	                   
	  if( $localOptions['getNoExecutions'] )
	  {
	     $executions_join = " LEFT OUTER " . $executions_join;
	  }
	  else
	  {
	  	 // @TODO understand if this condition is really needed - 20090716 - franciscom
	     $executions_join .= " AND e.status IS NOT NULL ";
	  }
	
	  // 20090517 - to manage deleted users i need to change:
	  //            users.id AS tester_id => e.tester_id AS tester_id
	  // 20090214 - franciscom - we need tcversions.execution_type and executions.execution_type
	  // 20090208 - franciscom
	  //            found bug due to use of tcversions.*, because field execution_type
	  //            exist on both execution and tcversion table.
	  //            At least with Postgres tcversions.execution_type was used always
	  //
	  // 20080103 - franciscom - added execution_type in recordset
	  // 20060921 - franciscom -
	  // added NHB.parent_id  to get same order as in the navigator tree
	  //
	  $sql= "/* $debugMsg */ SELECT e.id AS execution_id, " .
   			" COALESCE(e.status,'{$status_not_run}') AS status, " .
	        " e.execution_type AS execution_run_type," .
	        " NHB.name,NHA.parent_id AS testcase_id, NHB.parent_id AS tsuite_id," .
	        " tcversions.id,tcversions.tc_external_id,tcversions.version,tcversions.summary," .
	        " tcversions.preconditions," .
	        // " tcversions.steps,tcversions.expected_results,tcversions.importance,tcversions.author_id," .
	        " tcversions.importance,tcversions.author_id," .
	        " tcversions.creation_ts,tcversions.updater_id,tcversions.modification_ts,tcversions.active," .
	        " tcversions.is_open,tcversions.execution_type," .
	        " users.login AS tester_login,users.first AS tester_first_name," .
			" users.last AS tester_last_name, e.tester_id AS tester_id," .
			" e.notes AS execution_notes, e.execution_ts, e.build_id,e.tcversion_number," .
			" builds.name AS build_name, builds.active AS build_is_active, builds.is_open AS build_is_open," .
	        " e.platform_id,p.name AS platform_name" .
		    " FROM {$this->tables['nodes_hierarchy']} NHA" .
	        " JOIN {$this->tables['nodes_hierarchy']} NHB ON NHA.parent_id = NHB.id" .
	        " JOIN {$this->tables['tcversions']} tcversions ON NHA.id = tcversions.id" .
	        " {$executions_join}" .
	        " LEFT OUTER JOIN {$this->tables['builds']} builds ON builds.id = e.build_id" .
	        "                 AND builds.testplan_id = {$tplan_id}" .
	        " LEFT OUTER JOIN {$this->tables['users']} users ON users.id = e.tester_id " .
   	        " LEFT OUTER JOIN {$this->tables['platforms']} p ON p.id = e.platform_id" .
	        " $where_clause_2" .
	        " ORDER BY NHB.parent_id ASC, NHA.node_order ASC, NHA.parent_id ASC, execution_id DESC";
      
		$recordset = $this->db->fetchRowsIntoMap($sql,'id',$cumulativeMode);
	  
	  	// Multiple Test Case Steps Feature
	  	if( !is_null($recordset) && $localOptions['getSteps'] )
	  	{
	  	   	$itemSet = array_keys($recordset);
	  		foreach( $itemSet as $sdx)
	  		{
	  			$step_set = $this->get_steps($recordset[$sdx]['id']);
	  			$recordset[$sdx]['steps'] = $step_set;
	  		} 

	  	}
	  	return($recordset ? $recordset : null);
	}
	
	
	/*
	  function: exportTestCaseDataToXML
	
	  args :
	
			$tcversion_id: can be testcase::LATEST_VERSION
			
	  returns:
	
	  @internal revisions
		20110604 - franciscom - TICKET 4564: Test Case Export/import - new field STATUS is not managed
	*/
	function exportTestCaseDataToXML($tcase_id,$tcversion_id,$tproject_id=null,
	                                 $bNoXMLHeader = false,$optExport = array())
	{
		static $reqMgr; 
		static $keywordMgr;
		static $cfieldMgr; 
	  	if( is_null($reqMgr) )
	  	{
	  	    $reqMgr = new requirement_mgr($this->db);      
	  	    $keywordMgr = new tlKeyword();      
	  	    $cfieldMgr = new cfield_mgr($this->db);      
	  	}

		// Useful when you need to get info but do not have tcase id	
		$tcase_id = intval((int)($tcase_id));
		$tcversion_id = intval((int)($tcversion_id));
		if( $tcase_id <= 0 && $tcversion_id > 0)
		{
			$info = $this->tree_manager->get_node_hierarchy_info($tcversion_id);
			$tcase_id = $info['parent_id'];
		}
		
		$tc_data = $this->get_by_id($tcase_id,$tcversion_id);
		$testCaseVersionID = $tc_data[0]['id'];
		
		if (!$tproject_id)
		{
			$tproject_id = $this->getTestProjectFromTestCase($tcase_id);
		}

        // Get Custom Field Data
		if (isset($optExport['CFIELDS']) && $optExport['CFIELDS'])
		{
			$cfMap = $this->get_linked_cfields_at_design($tcase_id,$testCaseVersionID,null,null,$tproject_id);        	                                                                                  
        	
	    	// ||yyy||-> tags,  {{xxx}} -> attribute 
	    	// tags and attributes receive different treatment on exportDataToXML()
	    	//
	    	// each UPPER CASE word in this map KEY, MUST HAVE AN OCCURENCE on $elemTpl
	    	// value is a key inside $tc_data[0]
	    	//
			if( !is_null($cfMap) && count($cfMap) > 0 )
			{
				$tc_data[0]['xmlcustomfields'] = $cfieldMgr->exportValueAsXML($cfMap);
			} 
		}
		
		// Get Keywords
		if (isset($optExport['KEYWORDS']) && $optExport['KEYWORDS'])
		{
			$keywords = $this->getKeywords($tcase_id);
			if(!is_null($keywords))
			{
				$xmlKW = "<keywords>" . $keywordMgr->toXMLString($keywords,true) . "</keywords>";
				$tc_data[0]['xmlkeywords'] = $xmlKW;
			}
		}
    	
    	// Get Requirements
		if (isset($optExport['REQS']) && $optExport['REQS'])
		{
	  		$requirements = $reqMgr->get_all_for_tcase($tcase_id);
	  		if( !is_null($requirements) && count($requirements) > 0 )
	  		{
				$reqRootElem = "\t<requirements>\n{{XMLCODE}}\t</requirements>\n";
				$reqElemTemplate = "\t\t<requirement>\n" .
				                   "\t\t\t<req_spec_title><![CDATA[||REQ_SPEC_TITLE||]]></req_spec_title>\n" .
				                   "\t\t\t<doc_id><![CDATA[||REQ_DOC_ID||]]></doc_id>\n" .
				                   "\t\t\t<title><![CDATA[||REQ_TITLE||]]></title>\n" .
				                   "\t\t</requirement>\n";
				      	                 
				$reqDecode = array ("||REQ_SPEC_TITLE||" => "req_spec_title",
				                    "||REQ_DOC_ID||" => "req_doc_id","||REQ_TITLE||" => "title");
				$tc_data[0]['xmlrequirements'] = exportDataToXML($requirements,$reqRootElem,$reqElemTemplate,$reqDecode,true);
	  		}
		}
		// ------------------------------------------------------------------------------------
        // Multiple Test Case Steps Feature
       	$stepRootElem = "<steps>{{XMLCODE}}</steps>";
        $stepTemplate = "\n" . '<step>' . "\n" .
				   		"\t<step_number><![CDATA[||STEP_NUMBER||]]></step_number>\n" .
				   		"\t<actions><![CDATA[||ACTIONS||]]></actions>\n" .
		           		"\t<expectedresults><![CDATA[||EXPECTEDRESULTS||]]></expectedresults>\n" .
		                "\t<execution_type><![CDATA[||EXECUTIONTYPE||]]></execution_type>\n" .
		           		"</step>\n";
        $stepInfo = array("||STEP_NUMBER||" => "step_number",
						  "||ACTIONS||" => "actions",
						  "||EXPECTEDRESULTS||" => "expected_results",
						  "||EXECUTIONTYPE||" => "execution_type" );

        $stepSet = $tc_data[0]['steps'];
		$xmlsteps = exportDataToXML($stepSet,$stepRootElem,$stepTemplate,$stepInfo,true);
        $tc_data[0]['xmlsteps'] = $xmlsteps;
        // ------------------------------------------------------------------------------------
		
		
		$rootElem = "{{XMLCODE}}";
		if (isset($optExport['ROOTELEM']))
		{
			$rootElem = $optExport['ROOTELEM'];
		}
		$elemTpl = "\n".'<testcase internalid="{{TESTCASE_ID}}" name="{{NAME}}">' . "\n" .
				       "\t<node_order><![CDATA[||NODE_ORDER||]]></node_order>\n" .
				       "\t<externalid><![CDATA[||EXTERNALID||]]></externalid>\n" .
				       "\t<version><![CDATA[||VERSION||]]></version>\n" .
		               "\t<summary><![CDATA[||SUMMARY||]]></summary>\n" .
		               "\t<preconditions><![CDATA[||PRECONDITIONS||]]></preconditions>\n" .
		               "\t<execution_type><![CDATA[||EXECUTIONTYPE||]]></execution_type>\n" .
		               "\t<importance><![CDATA[||IMPORTANCE||]]></importance>\n" .
		               "\t<status><![CDATA[||STATUS||]]></status>\n" .
		               "||STEPS||\n" .
		               "||KEYWORDS||||CUSTOMFIELDS||||REQUIREMENTS||</testcase>\n";
	
	
	    // ||yyy||-> tags,  {{xxx}} -> attribute 
	    // tags and attributes receive different treatment on exportDataToXML()
	    //
	    // each UPPER CASE word in this map KEY, MUST HAVE AN OCCURENCE on $elemTpl
	    // value is a key inside $tc_data[0]
	    //
		  $info = array("{{TESTCASE_ID}}" => "testcase_id",
		  			  "{{NAME}}" => "name",
		  			  "||NODE_ORDER||" => "node_order",
		  			  "||EXTERNALID||" => "tc_external_id",
		  			  "||VERSION||" => "version",
		  			  "||SUMMARY||" => "summary",
		  			  "||PRECONDITIONS||" => "preconditions",
		  			  "||EXECUTIONTYPE||" => "execution_type",
		  			  "||IMPORTANCE||" => "importance",
		  			  "||STATUS||" => "status",
		  			  "||STEPS||" => "xmlsteps",
		  	          "||KEYWORDS||" => "xmlkeywords",
		  			  "||CUSTOMFIELDS||" => "xmlcustomfields",
		  			  "||REQUIREMENTS||" => "xmlrequirements");
			
		  $xmlTC = exportDataToXML($tc_data,$rootElem,$elemTpl,$info,$bNoXMLHeader);
		  return $xmlTC;
	}
	
	
	/*
	  function: get_version_exec_assignment
	            get information about user that has been assigned
	            test case version for execution on a testplan
	
	  args : tcversion_id: test case version id
	         tplan_id
	
	
	
	  returns: map
	           key: tcversion_id
	           value: map with following keys:
	 				          tcversion_id
					          feature_id: identifies row on table testplan_tcversions.
	
	
					          user_id:  user that has reponsibility to execute this tcversion_id.
					                    null/empty string is nodoby has been assigned
	
					          type    type of assignment.
					                  1 -> testcase_execution.
					                  See assignment_types tables for updated information
					                  about other types of assignemt available.
	
					          status  assignment status
					                  See assignment_status tables for updated information.
					                  1 -> open
	                          2 -> closed
	                          3 -> completed
	                          4 -> todo_urgent
	                          5 -> todo
	
					          assigner_id: who has assigned execution to user_id.
	
	
	
	*/
	function get_version_exec_assignment($tcversion_id, $tplan_id, $build_id)
	{
		// 20110622 - asimon - TICKET 4600: Blocked execution of testcases
		$sql =  "SELECT T.tcversion_id AS tcversion_id,T.id AS feature_id,T.platform_id, " .
				"       UA.user_id,UA.type,UA.status,UA.assigner_id ".
				" FROM {$this->tables['testplan_tcversions']}  T " .
				" LEFT OUTER JOIN {$this->tables['user_assignments']}  UA ON UA.feature_id = T.id " .
				" WHERE T.testplan_id={$tplan_id} AND UA.build_id = {$build_id} " .
				" AND   T.tcversion_id = {$tcversion_id} " .
				" AND   (UA.type=" . $this->assignment_types['testcase_execution']['id'] .
				"        OR UA.type IS NULL) ";
	
	
		// $recordset = $this->db->fetchRowsIntoMap($sql,'tcversion_id');
        $recordset = $this->db->fetchMapRowsIntoMap($sql,'tcversion_id','platform_id');
		
		return $recordset;
	}
	
	
	/**
	 * get_assigned_to_user()
	 * Given a user and a tesplan id, get all test case version id linked to
	 * test plan, that has been assigned for execution to user.
	 *
	 * @param int user_id
	 *
	 * @param mixed tproject_id list of test project id to search.  
	 *                          int or array
	 *
	 * @param array [tplan_id] list of test plan id to search.  
	 *                         null => all test plans
	 *
	 * @param object [options] options->mode='full_path'
	 *                         testcase name full path will be returned
	 *                         Only available when acces_keys ='testplan_testcase'
	 *                        
	 *                         options->access_keys
	 *                         possible values: 'testplan_testcase','testcase_testplan'
	 *                         changes access key in result map of maps.
	 *                         if not defined or null -> 'testplan_testcase' 
	 *						   
	 * @param object [filters] 'tplan_status' => 'active','inactive','all'
	 *                     	
	 *
	 * @return map key: (test plan id or test case id depending on options->access_keys,
	 *                   default is test plan).
	 *
	 *             value: map key: (test case id or test plan id depending on options->access_keys,
	 *                              default is test case). 
	 *                        value:
	 *                         
	 * @since 20090131 - franciscom
	 *
	 * @internal revision
	 *  20100906 - asimon -  BUGID 3749
	 *  20100813 - asimon - deactivated last slash on full path
	 *                      to remove it from test suite name in "tc assigned to user" tables
	 *  20100802 - asimon - 3647
	 *  20100731 - asimon - added option to load assignments for all users,
	 *                      added user_id, build_id, platform_id to SELECT part of statement
	 *  20100722 - asimon - BUGID 3406 - modified statement to get build name
	 *  20100712 - asimon - inserted missing semicolon
	 *	20100708 - franciscom - BUGID 3575 - add plaftorm in output set
	 */
	function get_assigned_to_user($user_id,$tproject_id,$tplan_id=null,$options=null, $filters=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    
   	    $my['filters'] = array( 'tplan_status' => 'all');
	    $my['filters'] = array_merge($my['filters'], (array)$filters);

	    // to load assignments for all users OR one given user
	    $user_sql = ($user_id != TL_USER_ANYBODY) ? " AND UA.user_id = {$user_id} " : "";
	    
	    $filters = "";
	    
	    $has_options=!is_null($options);
	    $access_key=array('testplan_id','testcase_id');

	    $sql="/* $debugMsg */ SELECT TPROJ.id as testproject_id,TPTCV.testplan_id,TPTCV.tcversion_id, " .
	         " TCV.version,TCV.tc_external_id, NHTC.id AS testcase_id, NHTC.name, TPROJ.prefix, " .
	         " UA.creation_ts ,UA.deadline_ts, UA.user_id as user_id, " . 
	         " COALESCE(PLAT.name,'') AS platform_name, COALESCE(PLAT.id,0) AS platform_id, " .
	         " (TPTCV.urgency * TCV.importance) AS priority, BUILDS.name as build_name, " .
	         " BUILDS.id as build_id " .
	         " FROM {$this->tables['user_assignments']} UA " . 
	         " JOIN {$this->tables['testplan_tcversions']} TPTCV ON TPTCV.id = UA.feature_id " .
	         " JOIN {$this->tables['tcversions']} TCV ON TCV.id=TPTCV.tcversion_id " .
	         " JOIN {$this->tables['nodes_hierarchy']} NHTCV ON NHTCV.id = TCV.id " .
	         " JOIN {$this->tables['nodes_hierarchy']} NHTC ON NHTC.id = NHTCV.parent_id " .
	         " JOIN {$this->tables['nodes_hierarchy']} NHTPLAN ON  NHTPLAN.id=TPTCV.testplan_id " .
	         " JOIN {$this->tables['testprojects']} TPROJ ON  TPROJ.id = NHTPLAN.parent_id " .
	         " JOIN {$this->tables['testplans']} TPLAN ON  TPLAN.id = TPTCV.testplan_id " .
	         " JOIN {$this->tables['builds']} BUILDS ON  BUILDS.id = UA.build_id " .
	         " LEFT OUTER JOIN {$this->tables['platforms']} PLAT ON  PLAT.id = TPTCV.platform_id " .
	         " WHERE UA.type={$this->assignment_types['testcase_execution']['id']} " .
	         //" AND UA.user_id = {$user_id} " .
	         " {$user_sql} " .
	         " AND TPROJ.id IN (" . implode(',', array($tproject_id)) .") " ;
	    
	    if( !is_null($tplan_id) )
	    {
	        $filters .= " AND TPTCV.testplan_id IN (" . implode(',',$tplan_id) . ") "; 
	    }     
	    
		// BUGID 3647
	    if (isset($my['filters']['build_id'])) {
			$filters .= " AND UA.build_id = {$my['filters']['build_id']} ";
		}
	    
	    switch($my['filters']['tplan_status'])
	    {
	    	case 'all':
	    	break;
	    	
	    	case 'active':
	        	$filters .= " AND TPLAN.active = 1 ";
	    	break;
	    	
	    	case 'inactive':
	        	$filters .= " AND TPLAN.active = 0 ";
	    	break;
	    }

		// BUGID 3749
		if(isset($my['filters']['build_status'])) {
			switch($my['filters']['build_status'])
			{
				case 'open':
					$filters .= " AND BUILDS.is_open = 1 ";
				break;
	
				case 'closed':
					$filters .= " AND BUILDS.is_open = 0 ";
				break;
	
			case 'all':
			default:
				break;
			}
		}

	    $sql .= $filters;
	    
	    if( $has_options && isset($options->access_keys) )
	    {
	        switch($options->access_keys)
	        {
	            case 'testplan_testcase':
	            break;
	            
	            case 'testcase_testplan':   
	                $access_key=array('testcase_id','testplan_id');
	            break;
	        }
	    }
	    
	    $rs=$this->db->fetchMapRowsIntoMap($sql,$access_key[0],$access_key[1],database::CUMULATIVE);
	    if( $has_options && !is_null($rs))
	    {
	        if( isset($options->mode) )
	        {
	            switch($options->mode)
	            {
	                case 'full_path':
	                    if( !isset($options->access_keys) || 
	                        (is_null($options->access_keys) || $options->access_keys='testplan_testcase') )
	                    { 
	                        $tcaseSet=null;
	                        $main_keys = array_keys($rs);
	       					foreach($main_keys as $maccess_key)
	       					{
	       						$sec_keys = array_keys($rs[$maccess_key]);
	       						foreach($sec_keys as $saccess_key)
	       						{
	       							// is enough I process first element
	       							$item = $rs[$maccess_key][$saccess_key][0];
	                                if(!isset($tcaseSet[$item['testcase_id']]))
	                                {
	                                    $tcaseSet[$item['testcase_id']]=$item['testcase_id'];  
	                                }  
	       						}
	       					}

	                        $path_info = $this->tree_manager->get_full_path_verbose($tcaseSet);

	                        // Remove test project piece and convert to string
	                        $flat_path=null;
	                        foreach($path_info as $tcase_id => $pieces)
	                        {
	                            unset($pieces[0]);
	                            // 20100813 - asimon - deactivated last slash on path
	                            // to remove it from test suite name in "tc assigned to user" tables
	                            $flat_path[$tcase_id]=implode('/',$pieces);
	                        }
	                        $main_keys = array_keys($rs);

	       					foreach($main_keys as $idx)
	       					{
	       						$sec_keys = array_keys($rs[$idx]);
	       						foreach($sec_keys as $jdx)
	       						{
									$third_keys = array_keys($rs[$idx][$jdx]);
	       							foreach($third_keys as $tdx)
	       							{
	       								$fdx = $rs[$idx][$jdx][$tdx]['testcase_id'];
	                                	$rs[$idx][$jdx][$tdx]['tcase_full_path']=$flat_path[$fdx];
									}
	       						}
	       					}
	                    }
	                break;  
	            }  
	        }
	    }
	    return $rs;
	}
	
	
	
	/*
	  function: update_active_status
	
	  args : id: testcase id
	         tcversion_id
	         active_status: 1 -> active / 0 -> inactive
	
	  returns: 1 -> everything ok.
	           0 -> some error
	  rev:
	  	  BUGID - 3849 -> not completely fixed -> BUGID 4204
	*/
	function update_active_status($id,$tcversion_id,$active_status)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$sql = 	" /* $debugMsg */ UPDATE {$this->tables['tcversions']} " .
				" SET active={$active_status}" .
			   	" WHERE id = {$tcversion_id}";
	
		$result = $this->db->exec_query($sql);
	
		return $result ? 1: 0;
	}
	
	/*
	  function: update_order
	
	  args : id: testcase id
	         order
	
	  returns: -
	
	*/
	function update_order($id,$order)
	{
	  	$result=$this->tree_manager->change_order_bulk(array($order => $id));  	
		return $result ? 1: 0;
	}
	
	
	/*
	  function: update_external_id
	
	  args : id: testcase id
	         external_id
	
	  returns: -
	
	*/
	function update_external_id($id,$external_id)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$sql =	"/* $debugMsg */ UPDATE {$this->tables['tcversions']} " .
				" SET tc_external_id={$external_id} " .
				" WHERE id IN (" .
				" SELECT id FROM {$this->tables['nodes_hierarchy']} WHERE parent_id={$id} ) ";
	      
	  $result=$this->db->exec_query($sql);
		return $result ? 1: 0;
	}
	

	/** 
	 * Copy attachments from source testcase to target testcase
	 * 
	 **/
	function copy_attachments($source_id,$target_id)
	{
		$this->attachmentRepository->copyAttachments($source_id,$target_id,$this->attachmentTableName);
	}

	
	/**
	 * copyReqAssignmentTo
	 * copy requirement assignments for $from test case id to $to test case id 
	 *
  	 * mappings is only useful when source_id and target_id do not belong to same Test Project.
	 * Because keywords are defined INSIDE a Test Project, ID will be different for same keyword
	 * in a different Test Project.
	 *
	 */
	function copyReqAssignmentTo($from,$to,$mappings)
	{
		static $req_mgr;
		if( is_null($req_mgr) )
		{
			$req_mgr=new requirement_mgr($this->db);
		}
		
		$itemSet=$req_mgr->get_all_for_tcase($from);
		if( !is_null($itemSet) )
		{
			$loop2do=count($itemSet);
			for($idx=0; $idx < $loop2do; $idx++)
			{
		 		if( isset($mappings[$itemSet[$idx]['id']]) )
		 		{
                	$items[$idx]=$mappings[$itemSet[$idx]['id']];
                }				
                else
                {
					$items[$idx]=$itemSet[$idx]['id'];
				}
			}
			$req_mgr->assign_to_tcase($items,$to); 
		} 
	}
	
	/**
	 * 
	 *
	 */
	private function getShowViewerActions($mode)
	{
	    // fine grain control of operations
	    $viewerActions= new stdClass();
	    $viewerActions->edit='no';
	    $viewerActions->delete_testcase='no';
	    $viewerActions->delete_version='no';
	    $viewerActions->deactivate='no';
	    $viewerActions->create_new_version='no';
	    $viewerActions->export='no';
	    $viewerActions->move='no';
	    $viewerActions->copy='no';
	    $viewerActions->add2tplan='no';
	
	    switch ($mode) 
	    {
	        case 'editOnExec':
	            $viewerActions->edit='yes';
	            // 20100530 - franciscom - $viewerActions->create_new_version='yes';    
	        break;
	
	        case 'editDisabled':
	        break;
	
	        default:
	        foreach($viewerActions as $key => $value)
	        {
	            $viewerActions->$key='yes';        
	        }
	        break;
	    }
	    return $viewerActions;     
	}
	
	/**
     * given an executio id delete execution and related data.
     *
     */
    function deleteExecution($executionID)
    {
        $whereClause = " WHERE execution_id = {$executionID} "; 
		$sql = array("DELETE FROM {$this->tables['execution_bugs']} {$whereClause} ", 
		             "DELETE FROM {$this->tables['cfield_execution_values']} {$whereClause} ",
		             "DELETE FROM {$this->tables['executions']} WHERE id = {$executionID}" );
	
		foreach ($sql as $the_stm)
		{
			$result = $this->db->exec_query($the_stm);
			if (!$result)
			{
				break;
			}
		}
    }
	
	
	
	
	// ---------------------------------------------------------------------------------------
	// Custom field related functions
	// ---------------------------------------------------------------------------------------
	
	/*
	  function: get_linked_cfields_at_design
	            Get all linked custom fields that must be available at design time.
	            Remember that custom fields are defined at system wide level, and
	            has to be linked to a testproject, in order to be used.
	
	  args: id: testcase id
	  		tcversion_id: testcase version id  ---- BUGID 3431
	        [parent_id]: node id of parent testsuite of testcase.
	                     need to understand to which testproject the testcase belongs.
	                     this information is vital, to get the linked custom fields.
	                     Presence /absence of this value changes starting point
	                     on procedure to build tree path to get testproject id.
	
	                     null -> use testcase_id as starting point.
	                     !is_null -> use this value as starting point.
	
	        [$filters]:default: null
	                    
	                   map with keys:
	
	                   [show_on_execution]: default: null
	                                        1 -> filter on field show_on_execution=1
	                                             include ONLY custom fields that can be viewed
	                                             while user is execution testcases.
	                   
	                                        0 or null -> don't filter
	
	                   [show_on_testplan_design]: default: null
	                                              1 -> filter on field show_on_testplan_design=1
	                                                   include ONLY custom fields that can be viewed
	                                                   while user is designing test plan.
	                                              
	                                              0 or null -> don't filter
	
	                   [location] new concept used to define on what location on screen
			                      custom field will be designed.
			                      Initally used with CF available for Test cases, to
			                      implement pre-requisites.
                                  null => no filtering
	
	
	                   More comments/instructions on cfield_mgr->get_linked_cfields_at_design()
	                   
	  returns: map/hash
	           key: custom field id
	           value: map with custom field definition and value assigned for choosen testcase,
	                  with following keys:
	
	            			id: custom field id
	            			name
	            			label
	            			type: custom field type
	            			possible_values: for custom field
	            			default_value
	            			valid_regexp
	            			length_min
	            			length_max
	            			show_on_design
	            			enable_on_design
	            			show_on_execution
	            			enable_on_execution
	            			display_order
	            			value: value assigned to custom field for this testcase
	            			       null if for this testcase custom field was never edited.
	
	            			node_id: testcase id
	            			         null if for this testcase, custom field was never edited.
	
	
	  rev :
	       20070302 - check for $id not null, is not enough, need to check is > 0
	
	*/
	// BUGID 3431
	function get_linked_cfields_at_design($id,$tcversion_id,$parent_id=null,$filters=null,$tproject_id = null)
	{
		if (!$tproject_id)
		{
			$tproject_id = $this->getTestProjectFromTestCase($id,$parent_id);
		}
		
		// BUGID 3431 - NEED CHANGE
		$cf_map = $this->cfield_mgr->get_linked_cfields_at_design($tproject_id,
		                                                          self::ENABLED,$filters,'testcase',$tcversion_id);
		return $cf_map;
	}
	
	
	
	/*
	  function: getTestProjectFromTestCase
	
	  args: id: testcase id
	        [parent_id]: node id of parent testsuite of testcase.
	                     need to understand to which testproject the testcase belongs.
	                     this information is vital, to get the linked custom fields.
	                     Presence /absence of this value changes starting point
	                     on procedure to build tree path to get testproject id.
	
	                     null -> use testcase_id as starting point.
	                     !is_null -> use this value as starting point.
	*/
	function getTestProjectFromTestCase($id,$parent_id)
	{
		$the_path = $this->tree_manager->get_path( (!is_null($id) && $id > 0) ? $id : $parent_id);
		$path_len = count($the_path);
		$tproject_id = ($path_len > 0)? $the_path[0]['parent_id'] : $parent_id;
		
		return $tproject_id;
	}

		
	/*
	  function: html_table_of_custom_field_inputs
	            Return html code, implementing a table with custom fields labels
	            and html inputs, for choosen testcase.
	            Used to manage user actions on custom fields values.
	
	
	  args: $id: IMPORTANT: 
	             we can receive 0 in this arguments and THERE IS NOT A problem
	             if parent_id arguments has a value.
	             Because argument id or parent_id are used to understand what is
	             testproject where test case belong, in order to get custom fields
	             assigned/linked to test project. 
	                     
	                
	        [parent_id]: node id of parent testsuite of testcase.
	                     need to undertad to which testproject the testcase belongs.
	                     this information is vital, to get the linked custom fields.
	                     Presence /absence of this value changes starting point
	                     on procedure to build tree path to get testproject id.
	
	                     null -> use testcase_id as starting point.
	                     !is_null -> use this value as starting point.
	
	        [$scope]: 'design' -> use custom fields that can be used at design time (specification)
	                  'execution' -> use custom fields that can be used at execution time.
	
	        [$name_suffix]: must start with '_' (underscore).
	                        Used when we display in a page several items
	                        example:
	                                during test case execution, several test cases
	                                during testplan design (assign test case to testplan).
	                        
	                        that have the same custom fields.
	                        In this kind of situation we can use the item id as name suffix.
	
	        [link_id]: default null
	                   scope='testplan_design'.
	                   link_id=testplan_tcversions.id this value is also part of key
	                   to access CF values on new table that hold values assigned
	                   to CF used on the 'tesplan_design' scope.
	                   
	                   scope='execution'
	                   link_id=execution id
	                   
	                   BUGID 3431 
	                   scope='design'
	                   link_id=tcversion id
	                   
	
	        [tplan_id]: default null
	                    used when scope='execution' and YOU NEED to get input with value
	                    related to link_id
	
	        [tproject_id]: default null
	                       used to speedup feature when this value is available.
	
	
	  returns: html string
	  
	  rev: 20080811 - franciscom - BUGID 1650 (REQ)
	
	BUGID 3431 - 
	
	*/
	function html_table_of_custom_field_inputs($id,$parent_id=null,$scope='design',$name_suffix='',
	                                           $link_id=null,$tplan_id=null,
	                                           $tproject_id = null,$filters=null, $input_values = null)
	{
		$cf_smarty = '';
	  	$cf_scope=trim($scope);
	  	$method_name='get_linked_cfields_at_' . $cf_scope;
	  
	  	switch($cf_scope)
	  	{
	  	    case 'testplan_design':
	  	        $cf_map = $this->$method_name($id,$parent_id,null,$link_id,null,$tproject_id);    
	  	    break;
	  	
	  	    case 'design':
	  	    	$cf_map = $this->$method_name($id,$link_id,$parent_id,$filters,$tproject_id);    
	  	    break;
	  	    	
	  	    case 'execution':
	  	        $cf_map = $this->$method_name($id,$parent_id,null,$link_id,$tplan_id,$tproject_id);    
	  	    break;
	  	      
	  	}
	  
		if(!is_null($cf_map))
		{
			$cf_smarty = $this->cfield_mgr->html_table_inputs($cf_map,$name_suffix,$input_values);
        }
		return $cf_smarty;
	}
	
	
	/*
	  function: html_table_of_custom_field_values
	            Return html code, implementing a table with custom fields labels
	            and custom fields values, for choosen testcase.
	            You can think of this function as some sort of read only version
	            of html_table_of_custom_field_inputs.
	
	
	  args: $id: Very Important!!!
	             scope='design'    -> this is a testcase id
	             scope='execution' -> this is a testcase VERSION id
	             scope='testplan_design' -> this is a testcase VERSION id 
	              
	        [$scope]: 'design' -> use custom fields that can be used at design time (specification)
	                  'execution' -> use custom fields that can be used at execution time.
	                  'testplan_design' 
	
	        [$filters]:default: null
	                    
	                   map with keys:
	
	                   [show_on_execution]: default: null
	                                        1 -> filter on field show_on_execution=1
	                                             include ONLY custom fields that can be viewed
	                                             while user is execution testcases.
	                   
	                                        0 or null -> don't filter
	
	                   [show_on_testplan_design]: default: null
	                                              1 -> filter on field show_on_testplan_design=1
	                                                   include ONLY custom fields that can be viewed
	                                                   while user is designing test plan.
	                                              
	                                              0 or null -> don't filter
	
	                   [location] new concept used to define on what location on screen
			                      custom field will be designed.
			                      Initally used with CF available for Test cases, to
			                      implement pre-requisites.
                                  null => no filtering
	                   
	                   More comments/instructions on cfield_mgr->get_linked_cfields_at_design()
	                              
	
	        [$execution_id]: null -> get values for all executions availables for testcase
	                         !is_null -> only get values or this execution_id
	
	        [$testplan_id]: null -> get values for any tesplan to with testcase is linked
	                        !is_null -> get values only for this testplan.
	
	        [$tproject_id]
	        [$formatOptions]
	        [$link_id]: default null
	                   scope='testplan_design'.
	                   link_id=testplan_tcversions.id this value is also part of key
	                   to access CF values on new table that hold values assigned
	                   to CF used on the 'tesplan_design' scope.
	
					   BUGID 3431
					   scope='design'.
	                   link_id=tcversion_id
					    	
	
	
	
	  returns: html string
	
	*/
	function html_table_of_custom_field_values($id,$scope='design',$filters=null,$execution_id=null,
	                                           $testplan_id=null,$tproject_id = null,
	                                           $formatOptions=null,$link_id=null)
	{
		$label_css_style = ' class="labelHolder" ';
		$value_css_style = ' ';

	    $add_table=true;
	    $table_style='';
	    if( !is_null($formatOptions) )
	    {
			$label_css_style = isset($formatOptions['label_css_style']) ? 
										 $formatOptions['label_css_style'] : $label_css_style;
			$value_css_style = isset($formatOptions['value_css_style']) ? 
										 $formatOptions['value_css_style'] : $value_css_style;

	        $add_table=isset($formatOptions['add_table']) ? $formatOptions['add_table'] : true;
	        $table_style=isset($formatOptions['table_css_style']) ? $formatOptions['table_css_style'] : $table_style;
	    } 
		
		$cf_smarty = '';
		
		$location=null; // no filter
        $filterKey='location';
        if( isset($filters[$filterKey]) && !is_null($filters[$filterKey]) )
        {
            $location = $filters[$filterKey];
        }

	    switch($scope)
	    {
	        case 'design':
	            $cf_map = $this->get_linked_cfields_at_design($id,$link_id,null,$filters,$tproject_id);
	        break;
	    
	        case 'testplan_design':
	            $cf_map = $this->get_linked_cfields_at_testplan_design($id,null,$filters,$link_id,
	                                                                   $testplan_id,$tproject_id);
	        break;
	    
	        case 'execution':
	            $cf_map = $this->get_linked_cfields_at_execution($id,null,$filters,$execution_id,
	                                                             $testplan_id,$tproject_id,$location);
	        break;
	    }   

	    $show_cf = config_get('custom_fields')->show_custom_fields_without_value;

		if(!is_null($cf_map))
		{
			foreach($cf_map as $cf_id => $cf_info)
			{
				// if user has assigned a value, then node_id is not null
				// BUGID 3989
				if(isset($cf_info['node_id']) || $show_cf)
				{
	                // true => do not create input in audit log
	                $label=str_replace(TL_LOCALIZE_TAG,'',lang_get($cf_info['label'],null,true));
	
					$cf_smarty .= "<tr><td {$label_css_style}> " .	htmlspecialchars($label) . ":</td>" . 
									"<td {$value_css_style}>" .
									$this->cfield_mgr->string_custom_field_value($cf_info,$id) .
									"</td></tr>\n";
				}
			}
	
			if((trim($cf_smarty) != "") && $add_table)
			{
				$cf_smarty = "<table {$table_style}>" . $cf_smarty . "</table>";
			}
		}
		return $cf_smarty;
	} // function end
	
	
	/*
	  function: get_linked_cfields_at_execution
	
	
	  args: $id
	        [$parent_id]
	        [$show_on_execution]: default: null
	                              1 -> filter on field show_on_execution=1
	                              0 or null -> don't filter
	                              //@TODO - 20090718 - franciscom 
	                              // this filter has any sense ? 
	                              // review and remove if needed
	
	
	        [$execution_id]: null -> get values for all executions availables for testcase
	                         !is_null -> only get values or this execution_id
	
	        [$testplan_id]: null -> get values for any tesplan to with testcase is linked
	                        !is_null -> get values only for this testplan.
	
	        [$tproject_id]:
	
	  returns: hash
	           key: custom field id
	           value: map with custom field definition, with keys:
	
					          id: custom field id
	          				name
	          				label
	          				type
	          				possible_values
	          				default_value
	          				valid_regexp
	          				length_min
	          				length_max
	          				show_on_design
	          				enable_on_design
	          				show_on_execution
	          				enable_on_execution
	          				display_order
	
	
	
	
	*/
	function get_linked_cfields_at_execution($id,$parent_id=null,$show_on_execution=null,
	                                         $execution_id=null,$testplan_id=null,
	                                         $tproject_id = null, $location=null)
	{
		$thisMethod=__FUNCTION__;
		if (!$tproject_id)
		{
		    $tproject_id = $this->getTestProjectFromTestCase($id,$parent_id);
		}
			
		// VERY IMPORTANT WARNING:
		// I'm setting node type to test case, but $id is the tcversion_id, because
		// execution data is related to tcversion NO testcase
		//
		$cf_map = $this->cfield_mgr->$thisMethod($tproject_id,self::ENABLED,'testcase',
		                                         $id,$execution_id,$testplan_id,'id',
		                                         $location);
		return $cf_map;
	}
	
	
	/*
	  function: copy_cfields_design_values
	            Get all cfields linked to any testcase of this testproject
	            with the values presents for $from_id, testcase we are using as
	            source for our copy.
	
	  args: source: map('id' => testcase id, 'tcversion_id' => testcase id) 
	        destination: map('id' => testcase id, 'tcversion_id' => testcase id) 
	
	  returns: -
	
	
	BUGID 3431 NEEDE CHANGE -> from_id -> from tcversion id 
	*/
	function copy_cfields_design_values($source,$destination)
	{
	  // Get all cfields linked to any testcase of this test project
	  // with the values presents for $from_id, testcase we are using as
	  // source for our copy
	  $cfmap_from = $this->get_linked_cfields_at_design($source['id'],$source['tcversion_id']);
	
	  $cfield=null;
	  if( !is_null($cfmap_from) )
	  {
	    foreach($cfmap_from as $key => $value)
	    {
	      $cfield[$key]=array("type_id"  => $value['type'], "cf_value" => $value['value']);
	    }
	  }
	  $this->cfield_mgr->design_values_to_db($cfield,$destination['tcversion_id'],null,'tcase_copy_cfields');
	}
	
	
	/*
	  function: get_linked_cfields_at_testplan_design
	
	
	  args: $id
	        [$parent_id]
	
	        [$filters]:default: null
	                    
	                   map with keys:
	
	                   [show_on_execution]: default: null
	                                        1 -> filter on field show_on_execution=1
	                                             include ONLY custom fields that can be viewed
	                                             while user is execution testcases.
	                   
	                                        0 or null -> don't filter
	
	                   [show_on_testplan_design]: default: null
	                                              1 -> filter on field show_on_testplan_design=1
	                                                   include ONLY custom fields that can be viewed
	                                                   while user is designing test plan.
	                                              
	                                              0 or null -> don't filter
	
	                   More comments/instructions on cfield_mgr->get_linked_cfields_at_design()
	
	        [$link_id]: 
	
	        [$testplan_id]: null -> get values for any tesplan to with testcase is linked
	                        !is_null -> get values only for this testplan.
	
	  returns: hash
	           key: custom field id
	           value: map with custom field definition, with keys:
	
					          id: custom field id
	          				name
	          				label
	          				type
	          				possible_values
	          				default_value
	          				valid_regexp
	          				length_min
	          				length_max
	          				show_on_design
	          				enable_on_design
	          				show_on_execution
	          				enable_on_execution
	          				display_order
	
	
	*/
	function get_linked_cfields_at_testplan_design($id,$parent_id=null,$filters=null,
	                                               $link_id=null,$testplan_id=null,$tproject_id = null)
	{
		if (!$tproject_id)
		{
		    $tproject_id = $this->getTestProjectFromTestCase($id,$parent_id);
		}	
		
		// Warning:
		// I'm setting node type to test case, but $id is the tcversion_id, because
		// link data is related to tcversion NO testcase
		//
		$cf_map = $this->cfield_mgr->get_linked_cfields_at_testplan_design($tproject_id,self::ENABLED,'testcase',
		                                                                   $id,$link_id,$testplan_id);
		return $cf_map;
	}
	
	
	/**
	 * returns map with key: verbose location (see custom field class $locations
	 *                  value: array with fixed key 'location'
	 *                         value: location code
	 *
	 */
	static function buildCFLocationMap()
	{
		return cfield_mgr::buildLocationMap('testcase');
  }
	
	
	/**
	 * given a set of test cases, will return a map with 
	 * test suites name that form test case path to root test suite.
	 *
	 *                  example:
	 *
	 *                  communication devices [ID 4]
	 *                      |__ Subspace channels [ID 20]
	 *                             |
	 *                             |__ TestCase100
	 *                             |  
	 *                             |__ short range devices [ID 21]
	 *	                                    |__ TestCase1
	 *                                      |__ TestCase2
     *
     * if test case set: TestCase100,TestCase1
     *
     *   4  Communications
     *  20 	Communications/Subspace channels
     *  21 	Communications/Subspace channels/short range devices
     *                
     *                
	 * returns map with key: test suite id
	 *                  value: test suite path to root
	 *
	 *
	 */
	function getPathLayered($tcaseSet)
	{
		$xtree=null;
		foreach($tcaseSet as $item)
    	{
			$path_info = $this->tree_manager->get_path($item); 
    		$testcase = end($path_info);
    		
    		// This check is useful when you have several test cases with same parent test suite
    		if( !isset($xtree[$testcase['parent_id']]['value']) )
    		{
    			$level=0;
				foreach($path_info as $elem)
				{
                    $level++;
					$prefix = isset($xtree[$elem['parent_id']]['value']) ? ($xtree[$elem['parent_id']]['value'] . '/') : '';
					if( $elem['node_table'] == 'testsuites' )
					{
						$xtree[$elem['id']]['value'] = $prefix . $elem['name'];
						$xtree[$elem['id']]['level']=$level;
					}	
				}
			}
		}	
		return $xtree;
	} // getPathLayered($tcaseSet)



    /**
	 * 
 	 *
 	 */
	function getPathTopSuite($tcaseSet)
	{
		$xtmas=null;
		foreach($tcaseSet as $item)
    	{
			$path_info = $this->tree_manager->get_path($item); 
    		$top = current($path_info);
    		$xtmas[$item] = array( 'name' => $top['name'], 'id' => $top['id']);
		}	
		return $xtmas;
	} // getPathTopSuite($tcaseSet)
	
	
	
    /*
	  function: getByPathName
	            pathname format
	            Test Project Name::SuiteName::SuiteName::...::Test case name
	
	  args: $pathname
	  returns: hash
	*/
	function getByPathName($pathName,$pathSeparator='::')
	{
	    $recordset = null;
		$retval=null;
	
        // First get root -> test project name and leaf => test case name	    
	    $parts = explode($pathSeparator,$pathName);
	    $partsQty = count($parts);
	    $tprojectName = $parts[0];
	    $tsuiteName = $parts[$partsQty-2];
	    $tcaseName = end($parts);

	    // get all testcases on test project with this name and parent test suite
        $recordset = $this->get_by_name($tcaseName, $tsuiteName ,$tprojectName);
        if( !is_null($recordset) && count($recordset) > 0 )
        {
        	foreach($recordset as $value)
        	{
  		        $dummy = $this->tree_manager->get_full_path_verbose($value['id']);
                $sx = implode($pathSeparator,current($dummy)) . $pathSeparator . $tcaseName;
                if( strcmp($pathName,$sx ) == 0 )
                {
                	
                	$retval = $value;
                	break;
                }
        	}
	    }
	    return $retval;
	}
	
	/**
	 * 
 	 *
     */
	function buildDirectWebLink($base_href,$id,$tproject_id=null)
	{
	    list($external_id,$prefix,$glue,$tc_number) = $this->getExternalID($id,$tproject_id);

		$dl = $base_href . 'linkto.php?tprojectPrefix=' . urlencode($prefix) . 
		      '&item=testcase&id=' . urlencode($external_id);
        return $dl;
    }

    /**
	 * 
 	 *
     */
	function getExternalID($id,$tproject_id=null,$prefix=null)
	{
		static $root;
		static $tcase_prefix;
      	
		if( is_null($prefix) )
		{
			if( is_null($root) ||  ($root != $tproject_id) )
			{
       			list($tcase_prefix,$root) = $this->getPrefix($id,$tproject_id);
       		}	
		}
		else
		{
			$tcase_prefix = $prefix;
		}
		$info = $this->get_last_version_info($id, array('output' => 'minimun'));
    $external = $info['tc_external_id'];
    $identity = $tcase_prefix . $this->cfg->testcase->glue_character . $external;
		return array($identity,$tcase_prefix,$this->cfg->testcase->glue_character,$external);
	}


    /**
	 * returns just name, tc_external_id, version.
	 * this info is normally enough for user feednack.
 	 *
 	 * @param int $id test case id
 	 * @param array $accessVersionBy 'number' 	=> contains test case version number
 	 *								 'id' 		=> contains test case version ID
 	 * 
 	 * @return array with one element with keys: name,version,tc_external_id
     */
	function get_basic_info($id,$accessVersionBy)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$sql = "/* $debugMsg */ " .
		  	   " SELECT NH_TCASE.id, NH_TCASE.name, TCV.version, TCV.tc_external_id, TCV.id AS tcversion_id " .
		       " FROM {$this->tables['nodes_hierarchy']} NH_TCASE " .
		       " JOIN {$this->tables['nodes_hierarchy']} NH_TCV ON NH_TCV.parent_id = NH_TCASE.id" .
		       " JOIN {$this->tables['tcversions']} TCV ON  TCV.id = NH_TCV.id ";

		$accessBy = array('number' => 'version', 'id' => 'id'); 
		foreach( $accessBy as $key => $field) 
		{
			if( isset($accessVersionBy[$key]) )
			{
	    		$where_clause = " WHERE TCV.{$field} = " . intval($accessVersionBy[$key]) ;
	    		break;
			}	
		}
		$where_clause .= " AND NH_TCASE .id = {$id} ";
        $sql .= $where_clause;
       
        $result = $this->db->get_recordset($sql);
        return $result;
    }



	/**
     * 
     *
     */
	function create_step($tcversion_id,$step_number,$actions,$expected_results,
                         $execution_type=TESTCASE_EXECUTION_TYPE_MANUAL)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $ret = array();
	    
	    // defensive programming
	    $dummy = $this->db->prepare_int($execution_type);
        $dummy = (isset($this->execution_types[$dummy])) ? $dummy : TESTCASE_EXECUTION_TYPE_MANUAL;
	    
		$item_id = $this->tree_manager->new_node($tcversion_id,$this->node_types_descr_id['testcase_step']);
		$sql = "/* $debugMsg */ INSERT INTO {$this->tables['tcsteps']} " .
		       " (id,step_number,actions,expected_results,execution_type) " .
		       " VALUES({$item_id},{$step_number},'" . $this->db->prepare_string($actions) . "','" .
		  	   $this->db->prepare_string($expected_results) . "', " . $this->db->prepare_int($dummy) . ")";
      
		$result = $this->db->exec_query($sql);
		$ret = array('msg' => 'ok', 'id' => $item_id, 'status_ok' => 1, 'sql' => $sql);
		if (!$result)
		{
	        $ret['msg'] = $this->db->error_msg();
		    $ret['status_ok']=0;
		    $ret['id']=-1;
		}
		return $ret;
	}

	/**
     * 
     *
     *	@internal Revisions
     */
	function get_steps($tcversion_id,$step_number=0,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;

	    $my['options'] = array( 'fields2get' => '*', 'accessKey' => null);
	    $my['options'] = array_merge($my['options'], (array)$options);
		
		$step_filter = $step_number > 0 ? " AND step_number = {$step_number} " : "";
		$safe_tcversion_id = $this->db->prepare_int($tcversion_id);
		
		$sql = "/* $debugMsg */ " . 
		       " SELECT TCSTEPS.{$my['options']['fields2get']} " .
		       " FROM {$this->tables['tcsteps']} TCSTEPS " .
		       " JOIN {$this->tables['nodes_hierarchy']} NH_STEPS " .
		       " ON NH_STEPS.id = TCSTEPS.id " . 
		       " WHERE NH_STEPS.parent_id = {$safe_tcversion_id} {$step_filter} ORDER BY step_number";

		if( is_null($my['options']['accessKey']) )
		{
			$result = $this->db->get_recordset($sql);
		}
		else
		{
			$result = $this->db->fetchRowsIntoMap($sql,$my['options']['accessKey']);
		}

		// TICKET 4797: Test case step reuse		
		if(!is_null($result))
		{
			$this->renderGhostSteps($result);
		}
		return $result;
	}

	/**
     * 
     *
     */
	function get_step_by_id($step_id)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$sql = "/* $debugMsg */ " . 
		       " SELECT TCSTEPS.* FROM {$this->tables['tcsteps']} TCSTEPS " .
		       " JOIN {$this->tables['nodes_hierarchy']} NH_STEPS " .
		       " ON NH_STEPS.id = TCSTEPS.id " . 
		       " WHERE TCSTEPS.id = {$step_id} ";
		$result = $this->db->get_recordset($sql);
		
		return is_null($result) ? $result : $result[0];
	}


	function get_step_numbers($tcversion_id)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$sql = "/* $debugMsg */ " . 
		       " SELECT TCSTEPS.id, TCSTEPS.step_number FROM {$this->tables['tcsteps']} TCSTEPS " .
		       " JOIN {$this->tables['nodes_hierarchy']} NH_STEPS " .
		       " ON NH_STEPS.id = TCSTEPS.id " . 
		       " WHERE NH_STEPS.parent_id = {$tcversion_id} ORDER BY step_number";

		$result = $this->db->fetchRowsIntoMap($sql,'step_number');
		return $result;
	}



	/**
     * 
     *
     */
	function get_latest_step_number($tcversion_id)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$sql = "/* $debugMsg */ " . 
		       " SELECT MAX(TCSTEPS.step_number) AS max_step FROM {$this->tables['tcsteps']} TCSTEPS " .
		       " JOIN {$this->tables['nodes_hierarchy']} NH_STEPS " .
		       " ON NH_STEPS.id = TCSTEPS.id " . 
		       " WHERE NH_STEPS.parent_id = {$tcversion_id} ";

		$result = $this->db->get_recordset($sql);
		$max_step = (!is_null($result) && isset($result[0]['max_step']) )? $result[0]['max_step'] : 0;
		return $max_step;
	}


	/**
     * 
     *
     *	@internal Revisions
     *	20100821 - franciscom - $step_id can be an array
     */
	function delete_step_by_id($step_id)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		
		$sql = array();
	    $whereClause = " WHERE id IN (" . implode(',',(array)$step_id) . ")";
		
		$sqlSet[] = "/* $debugMsg */ DELETE FROM {$this->tables['tcsteps']} {$whereClause} ";
		$sqlSet[] = "/* $debugMsg */ DELETE FROM {$this->tables['nodes_hierarchy']} {$whereClause} ";
		foreach($sqlSet as $sql)
		{
			$this->db->exec_query($sql);
		} 
	}


	/**
     * 
     *
     * @internal revision
     * BUGID 4207 - MSSQL
     */
	function set_step_number($step_number)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
        
        foreach($step_number as $step_id => $value)
        {
        	$sql = "/* $debugMsg */ UPDATE {$this->tables['tcsteps']} " . 
        	 	   " SET step_number = {$value} WHERE id = {$step_id} ";
        	$this->db->exec_query($sql); 	    
        }

	}

	/**
     * 
     *
     */
	function update_step($step_id,$step_number,$actions,$expected_results,$execution_type)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $ret = array();
		$sql = "/* $debugMsg */ UPDATE {$this->tables['tcsteps']} " .
		       " SET step_number=" . $this->db->prepare_int($step_number) . "," .
		       " actions='" . $this->db->prepare_string($actions) . "', " .
		       " expected_results='" . $this->db->prepare_string($expected_results) . "', " .
		       " execution_type = " . $this->db->prepare_int($execution_type)  .
		       " WHERE id = " . $this->db->prepare_int($step_id);
       
		$result = $this->db->exec_query($sql);
		$ret = array('msg' => 'ok', 'status_ok' => 1, 'sql' => $sql);
		if (!$result)
		{
	        $ret['msg'] = $this->db->error_msg();
		    $ret['status_ok']=0;
		}
		return $ret;
	}

	/**
	 * get by external id
	 *
	 * @param mixed filters: 
	 */
	function get_by_external($external_id, $parent_id,$filters=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $recordset = null;
	    
	    $my = array();
	    $my['filters'] = array('version' => null);
	    $my['filters'] = array_merge($my['filters'], (array)$filters);
	    
		$sql = "/* $debugMsg */ " . 	    
	           " SELECT DISTINCT NH_TCASE.id,NH_TCASE.name,NH_TCASE_PARENT.id AS parent_id," .
	           " NH_TCASE_PARENT.name AS tsuite_name, TCV.tc_external_id " .
			   " FROM {$this->tables['nodes_hierarchy']} NH_TCASE, " .
			   " {$this->tables['nodes_hierarchy']} NH_TCASE_PARENT, " .
			   " {$this->tables['nodes_hierarchy']} NH_TCVERSIONS," .
			   " {$this->tables['tcversions']}  TCV  " .
			   " WHERE NH_TCVERSIONS.id=TCV.id " .
			   " AND NH_TCVERSIONS.parent_id=NH_TCASE.id " .
			   " AND NH_TCASE_PARENT.id=NH_TCASE.parent_id " .
			   " AND NH_TCASE.node_type_id = {$this->my_node_type} " .
			   " AND TCV.tc_external_id=$external_id ";

		$add_filters = ' ';
		foreach($my['filters'] as $field => $value)
		{
			switch($my['filters'])
			{
				case 'version':
				if( !is_null($value) )
				{
					$add_filters .= ' AND TCV.version = intval($value) ';
				}
			}
		}
	   
		$sql .= $add_filters;
		$sql .= " AND NH_TCASE_PARENT.id = {$parent_id}"; // echo __FUNTION__; echo $sql;
		$recordset = $this->db->fetchRowsIntoMap($sql,'id');
	    return $recordset;
	}


	/**
	 * for a given set of test cases, search on the ACTIVE version set, and returns for each test case, 
	 * an map with: the corresponding MAX(version number), other info
	 *
	 * @param mixed $id: test case id can be an array
	 * @param map $filters OPTIONAL - now only 'cfields' key is supported
	 * @param map $options OPTIONAL
	 *
	 * @internal revisions
	 *
	 * 20110322 - franciscom - BUGID 4025 - added filters['wkstatus']
	 * 20101025 - franciscom - BUGID 3889: Add Test Cases to Test plan - Right pane does not honor custom field filter
	 * 20100417 - franciscom - added importance on output data
	 */
	function get_last_active_version($id,$filters=null,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $recordset = null;
	    $itemSet = implode(',',(array)$id);

		$my = array();
	    $my['filters'] = array( 'cfields' => null, 'status' => null);
	    $my['filters'] = array_merge($my['filters'], (array)$filters);

	    $my['options'] = array( 'max_field' => 'tcversion_id', 'access_key' => 'tcversion_id');
	    $my['options'] = array_merge($my['options'], (array)$options);
	    
	    
	    
	    switch($my['options']['max_field'])
	    {
	   		case 'version':
	   			$maxClause = " SELECT MAX(TCV.version) AS version ";
	   			$selectClause = " SELECT TCV.version AS version ";
	   		break;	

	   		case 'tcversion_id':
	   			$maxClause = " SELECT MAX(TCV.id) AS tcversion_id ";
	   			$selectClause = " SELECT TCV.id AS tcversion_id ";
	   		break;	
	   		
	   	}
	    
	    //
	    $status['in'] = '';
	    $status['not_in'] = '';
	    if( !is_null($my['filters']['status']) && is_array($my['filters']['status']) )
	    {
	    	if( isset($my['filters']['status']['in']) )
	    	{
	    		$status['in'] = 'AND TCV.status IN (' . 
	    						  implode(',',$my['filters']['status']['in']) . ')';
	    	}
	    	if( isset($my['filters']['status']['not_in']) )
	    	{
	    		$status['not_in'] = 'AND TCV.status NOT IN (' . 
	    						  	  implode(',',$my['filters']['status']['not_in']) . ')';
	    	}
	    }
	    
	    
		$sql = "/* $debugMsg */ " . 	    
			   " {$maxClause}, NH_TCVERSION.parent_id AS testcase_id " .
			   " FROM {$this->tables['tcversions']} TCV " .
			   " JOIN {$this->tables['nodes_hierarchy']} NH_TCVERSION " .
			   " ON NH_TCVERSION.id = TCV.id AND TCV.active=1 " .
				$status['in'] . ' ' . $status['not_in'] .	
			   " AND NH_TCVERSION.parent_id IN ({$itemSet}) " .
			   " GROUP BY NH_TCVERSION.parent_id " .
			   " ORDER BY NH_TCVERSION.parent_id ";

		// $recordset = $this->db->fetchRowsIntoMap($sql,$my['options']['access_key']);
		// HERE FIXED access keys
		$recordset = $this->db->fetchRowsIntoMap($sql,'tcversion_id');

		$cfSelect = '';
		$cfJoin = '';
		$cfQuery = '';
		$cfQty = 0;

		if( !is_null($recordset) )
		{
			$or_clause = '';
			$cf_query = '';
			if( !is_null($my['filters']['cfields']) )
			{
				$cf_hash = &$my['filters']['cfields'];
				$cfQty = count($cf_hash);
				$countmain = 1;
				// 20101025 - build custom fields filter
				// do not worry!! it seems that filter criteria is OR, but really is an AND,
				// OR is needed to do a simple query.
				// with processing on recordset becomes an AND
				// BUGID 3995
				foreach ($cf_hash as $cf_id => $cf_value)
				{
					if ( $countmain != 1 ) 
					{
						$cfQuery .= " OR ";
					}
					if (is_array($cf_value)) 
					{
						$count = 1;

						foreach ($cf_value as $value) 
						{

							if ($count > 1) 
							{
								$cfQuery .= " AND ";
							}
							$cfQuery .=  " ( CFDV.value LIKE '%{$value}%' AND CFDV.field_id = {$cf_id} )";
							$count++;
						}
					} 
					else
					{
		    			$cfQuery .=  " ( CFDV.value LIKE '%{$cf_value}%' ) ";
					}
					$countmain++;			
				}
				$cfSelect = ", CFDV.field_id, CFDV.value ";
				$cfJoin = " JOIN {$this->tables['cfield_design_values']} CFDV ON CFDV.node_id = TCV.id ";
				$cfQuery = " AND ({$cfQuery}) ";

			}

			$keySet = implode(',',array_keys($recordset));
			$sql = "/* $debugMsg */ " . 	    
				   " {$selectClause}, NH_TCVERSION.parent_id AS testcase_id, " .
				   " TCV.version,TCV.execution_type,TCV.importance,TCV.status {$cfSelect} " .
				   " FROM {$this->tables['tcversions']} TCV " .
				   " JOIN {$this->tables['nodes_hierarchy']} NH_TCVERSION " .
				   " ON NH_TCVERSION.id = TCV.id {$cfJoin} " .
				   " AND NH_TCVERSION.id IN ({$keySet}) {$cfQuery}";

			$recordset = $this->db->fetchRowsIntoMap($sql,$my['options']['access_key'],database::CUMULATIVE);

			// now loop over result, entries whose count() < number of custom fields has to be removed
			if( !is_null($recordset) && $cfQty > 0)
			{
				$key2loop = array_keys($recordset);
				foreach($key2loop as $key)
				{
					if( count($recordset[$key]) < $cfQty)
					{
						unset($recordset[$key]); // remove
					}
					else
					{
						$recordset[$key] = $recordset[$key][0]; 
						unset($recordset[$key]['value']);
						unset($recordset[$key]['field_id']);
					}
				}
				if( count($recordset) <= 0 )
				{
					$recordset = null;
				}
			}
		}
	    return $recordset;
	}


	/**
	 *
	 */
	function filter_tcversions_by_exec_type($tcversion_id,$exec_type,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $recordset = null;
	    $itemSet = implode(',',(array)$tcversion_id);

	    $my['options'] = array( 'access_key' => 'tcversion_id');
	    $my['options'] = array_merge($my['options'], (array)$options);
	    
	    
	    
		$sql = "/* $debugMsg */ " . 	    
			   " SELECT TCV.id AS tcversion_id, NH_TCVERSION.parent_id AS testcase_id, TCV.version " .
			   " FROM {$this->tables['tcversions']} TCV " .
			   " JOIN {$this->tables['nodes_hierarchy']} NH_TCVERSION " .
			   " ON NH_TCVERSION.id = TCV.id AND TCV.execution_type={$exec_type}" .
			   " AND NH_TCVERSION.id IN ({$itemSet}) ";

		$recordset = $this->db->fetchRowsIntoMap($sql,$my['options']['access_key']);
	    return $recordset;
	}

	/**
	 * 
	 *
	 */
	function filter_tcversions($tcversion_id,$filters,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $recordset = null;
	    $itemSet = implode(',',(array)$tcversion_id);

	    $my['options'] = array( 'access_key' => 'tcversion_id');
	    $my['options'] = array_merge($my['options'], (array)$options);
	    
		$sql = "/* $debugMsg */ " . 	    
			   " SELECT TCV.id AS tcversion_id, NH_TCVERSION.parent_id AS testcase_id, TCV.version " .
			   " FROM {$this->tables['tcversions']} TCV " .
			   " JOIN {$this->tables['nodes_hierarchy']} NH_TCVERSION " .
			   " ON NH_TCVERSION.id = TCV.id ";

		if ( !is_null($filters) )
		{
			foreach($filters as $key => $value)
			{
				if( !is_null($value) )
				{	   
					$sql .= " AND TCV.{$key}={$value} "; // Hmmm some problems coming with strings
				}	  
			}
		}
		$sql .= " AND NH_TCVERSION.id IN ({$itemSet}) ";

		$recordset = $this->db->fetchRowsIntoMap($sql,$my['options']['access_key']);
	    return $recordset;
	}



	/**
	 * given a test case version id, the provided steps will be analized in order
	 * to update whole steps/expected results structure for test case version.
	 * This can result in some step removed, other updated and other new created.
	 *	
	 * @internal Revisions
	 * 20100821 - franciscom - needed to fix import feature (BUGID 3634).
	 */
	function update_tcversion_steps($tcversion_id,$steps)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;

		// delete all current steps (if any exists)
	    // Attention:
	    // After addition of test case steps feature, a test case version can be root of
	    // a subtree that contains the steps.
		// Remember we are using (at least on Postgres FK => we need to delete in a precise order

		$stepSet = $this->get_steps($tcversion_id,0,array('fields2get' => 'id', 'accessKey' => 'id'));				
	    if( count($stepSet) > 0 )
	    {
			$this->delete_step_by_id(array_keys($stepSet));
	    }

		// Now insert steps
		$loop2do = count($steps);
		for($idx=0; $idx < $loop2do; $idx++)
		{
			$this->create_step($tcversion_id,$steps[$idx]['step_number'],$steps[$idx]['actions'],
							   $steps[$idx]['expected_results'],$steps[$idx]['execution_type']);
		}
	}

	/**
	 * update_last_modified
 	 *
 	 * @internal revision
 	 * 20101016 - franciscom - refixing of BUGID 3849
 	 */
	function update_last_modified($tcversion_id,$user_id,$time_stamp=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$changed_ts = !is_null($time_stamp) ? $time_stamp : $this->db->db_now();
		$sql = " UPDATE {$this->tables['tcversions']} " .
		       " SET updater_id=" . $this->db->prepare_int($user_id) . ", " .
			   " modification_ts = " . $changed_ts . 
		   	   " WHERE id = " . $this->db->prepare_int($tcversion_id); 
		$this->db->exec_query($sql);
	}


	/**
	 * Given a tcversion set, returns a modified set, where only tcversion id
	 * that has requested values on Custom fields are returned.
	 *
	 * @param mixed tcversion_id: can be a single value or an array
	 * @param map cf_hash: custom fields id plus values
	 * @param map options: OPTIONAL 
	 *
	 * @return map key: tcversion_id , element: array numerical index with as much element as custom fields
	 *
	 *
	 */
	function filter_tcversions_by_cfields($tcversion_id,$cf_hash,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    $recordset = null;
	    $itemSet = implode(',',(array)$tcversion_id);

	    $my['options'] = array( 'access_key' => 'tcversion_id');
	    $my['options'] = array_merge($my['options'], (array)$options);
	    
	    $or_clause = '';
		$cf_query = '';
		$cf_qty = count($cf_hash);
		
		// do not worry!! it seems that filter criteria is OR, but really is an AND,
		// OR is needed to do a simple query.
		// with processing on recordset becomes an AND
		foreach ($cf_hash as $cf_id => $cf_value)
		{
		    $cf_query .= $or_clause . " (CFDV.field_id=" . $cf_id . " AND CFDV.value='" . $cf_value . "') ";
			$or_clause = ' OR ';			
		}
	    
		$sql = "/* $debugMsg */ " . 	    
			   " SELECT TCV.id AS tcversion_id, NH_TCVERSION.parent_id AS testcase_id, TCV.version," .
			   " CFDV.field_id,CFDV.value " .
			   " FROM {$this->tables['tcversions']} TCV " .
			   " JOIN {$this->tables['nodes_hierarchy']} NH_TCVERSION ON NH_TCVERSION.id = TCV.id " .
			   " JOIN {$this->tables['cfield_design_values']} CFDV ON CFDV.node_id = TCV.id " .
			   " AND NH_TCVERSION.id IN ({$itemSet}) AND ({$cf_query}) ";

		$recordset = $this->db->fetchRowsIntoMap($sql,$my['options']['access_key'],database::CUMULATIVE);

		// now loop over result, entries whose count() < number of custom fields has to be removed
		if( !is_null($recordset) )
		{
			$key2loop = array_keys($recordset);
			foreach($key2loop as $key)
			{
				if( count($recordset[$key]) < $cf_qty)
				{
					// remove
					unset($recordset[$key]);
				}
			}
			if( count($recordset) <= 0 )
			{
				$recordset = null;
			}
		}
	    return $recordset;
	}

	/**
	 * Attention:
	 * has limited scope i.e.: used to do search on custom fields that hold simple values 
	 * example: strings and numbers.
	 *
	 *
	 */
	function get_id_by_custom_field($cf_name, $cf_value, $tproject_id)
	{
		static $lbl;
		if( is_null($lbl) )
		{
			$lbl = array();
			$lbl['no_linked_cfields_for_testcase'] = lang_get('no_linked_cfields_for_testcase');
			$lbl['no_cfield_with_this_name'] = lang_get('no_cfield_with_this_name');
		}
		$ret = array('status_ok' => false, 'msg' => '', 'id' => null);
    	
    	
    	// check if custom field is linked to test project 
    	$cfields_set = $this->tproject_mgr->get_linked_custom_fields($tproject_id,'testcase','name'); 
    	$ret['status_ok'] = !is_null($cfields_set);
    	if(!$ret['status_ok'])
    	{
			$ret['msg'] = $lbl['no_linked_cfields_for_testcase'];
    	
    	}
    	    	
    	if($ret['status_ok'])
    	{
    		if( ($ret['status_ok'] = isset($cfields_set[$cf_name])) )
    		{
    			$cfield_id = $cfields_set[$cf_name]['id'];
    		}
    		else
    		{
    			$ret['msg'] = sprintf($lbl['no_cfield_with_this_name'],$cf_name);
    		}
    	}

    	if($ret['status_ok'])
    	{
			// go for the test case
			// Need to get all node id (will be test case VERSION ID), that has cf with requested value
			// After this I need to get ONLY ID of test case that belong to Test Project
			// If more than one Test Case exists -> we have a problem ?
			// If this method has to be generic, then will be possible 
			// Check test cases that have a certain value on custom field
			// (We alredy are doing this may be in a different way when applying filters on  CF values).
			//  
			$cfget_opt = array('access_key' => 'parent_id', 'secondary_access_key' => 'node_id');
			
			// map key: test case id
			// map value: map indexed by test case version id
			$itemSet = $this->cfield_mgr->get_linked_items_at_design($cfield_id,$cf_value,'testcase',$cfget_opt);
			
			// // Now filter result
			if( !is_null($itemSet) )
			{
				$tcaseSet = array_keys($itemSet);
				$cache = array('tproject_id' => array());
								
				foreach($tcaseSet as $tcase_id)
				{
					if( !isset($cache['tproject_id'][$tcase_id]) )
					{
						$cache['tproject_id'][$tcase_id] = $this->tree_manager->getTreeRoot($tcase_id);
					}
					if( $tproject_id == $cache['tproject_id'][$tcase_id] )
					{
						$ret['id'] = array($tcase_id => $itemSet[$tcase_id]);
						break;
					}
				}
			}
		}

		return $ret;    	
	}



	
	// externalID but WITHOUT prefix
	function getDuplicatesByExternalID($externalID, $parent_id, $options=null)
	{
		$criteria = " AND TCV.tc_external_id=" . intval($externalID);
		$ret = $this->getDuplicatesByCriteria($parent_id, $criteria, $options);
		return $ret;			
			
	}

	function getDuplicatesByCriteria($parent_id, $criteria, $options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;

	    $my['options'] = array('access_key' => 'id');
	    $my['options'] = array_merge($my['options'], (array)$options);
	    
	    $sql = " SELECT DISTINCT NHA.id,NHA.name,TCV.tc_external_id" .
			   " FROM {$this->tables['nodes_hierarchy']} NHA, " .
			   " {$this->tables['nodes_hierarchy']} NHB, {$this->tables['tcversions']} TCV  " .
			   " WHERE NHA.node_type_id = {$this->my_node_type} " .
			   " AND NHB.parent_id=NHA.id " .
			   " AND TCV.id=NHB.id " .
			   " AND NHB.node_type_id = {$this->node_types_descr_id['testcase_version']} " .
			   " AND NHA.parent_id={$parent_id} {$criteria}";
	
		echo $sql;
		$rs = $this->db->fetchRowsIntoMap($sql,$my['options']['access_key']);
	    if( is_null($rs) || count($rs) == 0 )
	    {
	        $rs=null;   
	    }
	    return $rs;
	}
	

	/**
	 *
	 *
	 */
	function getExecutionSet($id,$filters=null,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;

	    $my['filters'] = array('version_id' => null,'tplan_id' => null,'platform_id' => null, 'build_id' => null); 	
	    $my['filters'] = array_merge($my['filters'], (array)$filters);

	    $my['options'] = array('exec_id_order' => 'DESC'); 	
	    $my['options'] = array_merge($my['options'], (array)$options);
		
		$filterKeys = array('build_id','platform_id','tplan_id');
        foreach($filterKeys as $key)
        {
        	$filterBy[$key] = '';
        	if( !is_null($my['filters'][$key]) )
        	{
        		$itemSet = implode(',', (array)$$key);
        		$filterBy[$key] = " AND e.{$key} IN ({$itemSet}) ";
        	}
        }
	
		// --------------------------------------------------------------------
		if( is_array($id) )
		{
			$tcid_list = implode(",",$id);
			$where_clause = " WHERE NHTCV.parent_id IN ({$tcid_list}) ";
		}
		else
		{
			$where_clause = " WHERE NHTCV.parent_id = {$id} ";
		}
	
		// if( is_array($version_id) )
		// {
		//     $versionid_list = implode(",",$version_id);
		//     $where_clause  .= " AND tcversions.id IN ({$versionid_list}) ";
		// }
		// else
		// {
		// 		if($version_id != self::ALL_VERSIONS)
		// 		{
		// 			$where_clause  .= " AND tcversions.id = {$version_id} ";
		// 		}
		// }
	

	  $sql="/* $debugMsg */ SELECT NHTC.name,NHTCV.parent_id AS testcase_id, tcversions.*,
			    users.login AS tester_login,
			    users.first AS tester_first_name,
			    users.last AS tester_last_name,
				e.tester_id AS tester_id,
			    e.id AS execution_id, e.status,e.tcversion_number,
			    e.notes AS execution_notes, e.execution_ts, e.execution_type AS execution_run_type,
			    e.build_id AS build_id,
			    b.name AS build_name, b.active AS build_is_active, b.is_open AS build_is_open,
   			    e.platform_id,p.name AS platform_name,
   			    e.testplan_id,NHTPLAN.name AS testplan_name
		    FROM {$this->tables['nodes_hierarchy']} NHTCV
	        JOIN {$this->tables['nodes_hierarchy']} NHTC ON NHTCV.parent_id = NHTC.id
	        JOIN {$this->tables['tcversions']} tcversions ON NHTCV.id = tcversions.id
	        JOIN {$this->tables['executions']} e ON NHTCV.id = e.tcversion_id
	                                             {$filterBy['tplan_id']}
	                                             {$filterBy['build_id']} {$filterBy['platform_id']}
	        JOIN {$this->tables['builds']}  b ON e.build_id=b.id
	        JOIN {$this->tables['testplans']} TPLAN ON TPLAN.id = e.testplan_id
	        JOIN {$this->tables['nodes_hierarchy']} NHTPLAN ON NHTPLAN.id = TPLAN.id
	        LEFT OUTER JOIN {$this->tables['users']} users ON users.id = e.tester_id
	        LEFT OUTER JOIN {$this->tables['platforms']} p ON p.id = e.platform_id
	        $where_clause " .
	        " ORDER BY execution_id {$my['options']['exec_id_order']}";

	        // " ORDER BY NHTCV.node_order ASC, NHTCV.parent_id ASC, execution_id {$my['options']['exec_id_order']}";
	

	
	  // echo $sql;
	  $recordset = $this->db->fetchArrayRowsIntoMap($sql,'id');
	  return($recordset ? $recordset : null);
	}



	/**
	 * for test case id and filter criteria return set with platforms 
	 * where test case has a version that has been executed.
	 *
	 */
	function getExecutedPlatforms($id,$filters=null,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;

	    $my['filters'] = array(	'version_id' => null,'tplan_id' => null,
	    						'platform_id' => null, 'build_id' => null); 	
	    $my['filters'] = array_merge($my['filters'], (array)$filters);

	    $my['options'] = array('exec_id_order' => 'DESC'); 	
	    $my['options'] = array_merge($my['options'], (array)$options);
		
		$filterKeys = array('build_id','platform_id','tplan_id');
        foreach($filterKeys as $key)
        {
        	$filterBy[$key] = '';
        	if( !is_null($my['filters'][$key]) )
        	{
        		$itemSet = implode(',', (array)$$key);
        		$filterBy[$key] = " AND e.{$key} IN ({$itemSet}) ";
        	}
        }
	
		// --------------------------------------------------------------------
		if( is_array($id) )
		{
			$tcid_list = implode(",",$id);
			$where_clause = " WHERE NHTCV.parent_id IN ({$tcid_list}) ";
		}
		else
		{
			$where_clause = " WHERE NHTCV.parent_id = {$id} ";
		}  
	    
		// if( is_array($version_id) )
		// {
		//     $versionid_list = implode(",",$version_id);
		//     $where_clause  .= " AND tcversions.id IN ({$versionid_list}) ";
		// }
		// else
		// {
		// 		if($version_id != self::ALL_VERSIONS)
		// 		{
		// 			$where_clause  .= " AND tcversions.id = {$version_id} ";
		// 		}
		// }
	
	  $sql = "/* $debugMsg */ SELECT DISTINCT e.platform_id,p.name " .
		     " FROM {$this->tables['nodes_hierarchy']} NHTCV " . 
	         " JOIN {$this->tables['tcversions']} tcversions ON NHTCV.id = tcversions.id " .
	         " JOIN {$this->tables['executions']} e ON NHTCV.id = e.tcversion_id " .
	         " {$filterBy['tplan_id']} {$filterBy['build_id']} {$filterBy['platform_id']} " .
	         " JOIN {$this->tables['builds']}  b ON e.build_id=b.id " .
	         " LEFT OUTER JOIN {$this->tables['platforms']} p ON p.id = e.platform_id " .
	         $where_clause;

	  // echo $sql;
	  $recordset = $this->db->fetchRowsIntoMap($sql,'platform_id');
	  return($recordset ? $recordset : null);
	}

	/**
	 *
	 */
	function renderGhostSteps(&$steps2render)
	{
		$loop2do = count($steps2render);
		$tlBeginMark = '[ghost]';
		$tlEndMark = '[/ghost]';
		
		// I've discovered that working with Web Rich Editor generates 
		// some additional not wanted entities, that disturb a lot
		// when trying to use json_decode().
		// Hope this set is enough.
		$replaceSet = array($tlEndMark, '</p>', '<p>','&nbsp;');
		
		$rse = &$steps2render;
		for($gdx=0; $gdx < $loop2do; $gdx++)
		{
			$start = strpos($rse[$gdx]['actions'],$tlBeginMark);
			$action = $rse[$gdx]['actions'];
			if($start > 0)
			{
				$xx = explode($tlBeginMark,$rse[$gdx]['actions']);
				$xx2do = count($xx);
				$action = '';
				for($xdx=0; $xdx < $xx2do; $xdx++)
				{
					if(strpos($xx[$xdx],$tlEndMark) > 0)
					{
						$dx = trim(str_replace($replaceSet,'',$xx[$xdx]));
						$dx = '{' . html_entity_decode(trim($dx,'\n')) . '}';
						$dx = json_decode($dx,true);
						$xid = $this->getInternalID($dx['TestCase']);
						// echo('xdi::' . $xid);
						if( ($xid = $this->getInternalID($dx['TestCase'])) > 0 )
						{
							$fi = $this->get_basic_info($xid,array('number' => $dx['Version']));
							if(!is_null($fi))
							{
								$stx = $this->get_steps($fi[0]['tcversion_id'],$dx['Step']);
								$action .= $stx[0]['actions'];
							}
						}	
					}
				}
			}
			if($action != '')
			{
				$rse[$gdx]['actions'] = $action;
			}					
		}
	}		
	
	function get_created_per_user($tproject_id,$tplan_id, $options)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
	    
		$has_options=!is_null($options);
	    $access_key=array('testplan_id','testcase_id');

	    $sql = "/* $debugMsg */ SELECT ".
				"TPROJ.id AS testproject_id, ".
				"TPTCV.testplan_id, ".
				"TCV.id   AS tcversion_id,". 
				"TCV.version, ".
				"TCV.tc_external_id,". 
				"NHTC.id  AS testcase_id,". 
				"NHTC.name, ". 
				"TCV.creation_ts, " . 
				"TCV.modification_ts, " .
				"TPROJ.prefix, ".
				"U.first  AS first_name,". 
				"U.last   AS last_name, ".
				"U.login,  " .
				"(TPTCV.urgency * TCV.importance) AS priority " .
	    		"FROM " .
					"users U " . 
						"JOIN tcversions TCV " . 
						"ON U.id = TCV.author_id  " .
							"JOIN nodes_hierarchy NHTCV " . 
							"ON TCV.id = NHTCV.id " . 
								"JOIN nodes_hierarchy NHTC " . 
								"ON NHTCV.parent_id = NHTC.id " . 
									"JOIN testplan_tcversions TPTCV " . 
									"ON TCV.id = TPTCV.tcversion_id " . 
										"JOIN testplans TPLAN " . 
										"ON TPTCV.testplan_id = TPLAN.id " . 
											"JOIN testprojects TPROJ " . 
											"ON TPLAN.testproject_id = TPROJ.id " . 
				"WHERE " .
					"TPROJ.id = \"$tproject_id\" AND " .
					"TPTCV.testplan_id = \"$tplan_id\"";
	    
	    if( $has_options && isset($options->access_keys) )
	    {
	        switch($options->access_keys)
	        {
	            case 'testplan_testcase':
	            break;
	            
	            case 'testcase_testplan':   
	                $access_key=array('testcase_id','testplan_id');
	            break;
	        }
	    }
	    
	    $rs=$this->db->fetchMapRowsIntoMap($sql,$access_key[0],$access_key[1],database::CUMULATIVE);
	    
	    if( $has_options && !is_null($rs))
	    {
	    	if( isset($options->mode) )
	    	{
	    		switch($options->mode)
	    		{
	    			case 'full_path':
	    				if( !isset($options->access_keys) ||
	    				(is_null($options->access_keys) || $options->access_keys='testplan_testcase') )
	    				{
	    					$tcaseSet=null;
	    					$main_keys = array_keys($rs);
	    					foreach($main_keys as $maccess_key)
	    					{
	    						$sec_keys = array_keys($rs[$maccess_key]);
	    						foreach($sec_keys as $saccess_key)
	    						{
	    							// is enough I process first element
	    							$item = $rs[$maccess_key][$saccess_key][0];
	    							if(!isset($tcaseSet[$item['testcase_id']]))
	    							{
	    								$tcaseSet[$item['testcase_id']]=$item['testcase_id'];
	    							}
	    						}
	    					}
	    
	    					$path_info = $this->tree_manager->get_full_path_verbose($tcaseSet);
	    
	    					// Remove test project piece and convert to string
	    					$flat_path=null;
	    					foreach($path_info as $tcase_id => $pieces)
	    					{
	    						unset($pieces[0]);
	    						// 20100813 - asimon - deactivated last slash on path
	    						// to remove it from test suite name in "tc assigned to user" tables
	    						$flat_path[$tcase_id]=implode('/',$pieces);
	    					}
	    					$main_keys = array_keys($rs);
	    
	    					foreach($main_keys as $idx)
	    					{
	    						$sec_keys = array_keys($rs[$idx]);
	    						foreach($sec_keys as $jdx)
	    						{
	    							$third_keys = array_keys($rs[$idx][$jdx]);
	    							foreach($third_keys as $tdx)
	    							{
	    								$fdx = $rs[$idx][$jdx][$tdx]['testcase_id'];
	    								$rs[$idx][$jdx][$tdx]['tcase_full_path']=$flat_path[$fdx];
	    							}
	    						}
	    					}
	    				}
	    				break;
	    		}
	    	}
	    }
	    
	    return $rs;
	}	


	
	/**
	 * updateSimpleFields
	 * used to update fields of type int, string on test case version
	 *
	 * @param int	$tcversionID	item ID to update
	 * @param hash	$fieldsValues	key DB field to update
	 *							   	supported fields:
	 *								summary,preconditions,execution_type,importance,status
	 *
	 * @internal revisions
	 *
	 */
	function updateSimpleFields($tcversionID,$fieldsValues)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$fieldsConvertions = array('summary' => 'prepare_string','preconditions' => 'prepare_string',
								   'execution_type' => 'prepare_int', 'importance' => 'prepare_int',
								   'status' => 'prepare_int', 
								   'estimated_execution_duration' => null);
		$dummy = null;
		$sql = null;
		$ddx = 0;
		foreach($fieldsConvertions as $fkey => $fmethod)
		{
			if( isset($fieldsValues[$fkey]) )
			{
				$dummy[$ddx] = $fkey . " = ";
				if( !is_null($fmethod) )
				{
					$sep = ($fmethod == 'prepare_string') ? "'" : "";
					$dummy[$ddx] .= $sep . $this->db->$fmethod($fieldsValues[$fkey]) . $sep; 
				}
				else
				{
					$dummy[$ddx] .= $fieldsValues[$fkey];
				}
				$ddx++;
			}
		}
		if( !is_null($dummy) )
		{
			$sqlSET = implode(",",$dummy);
			$sql = "/* {$debugMsg} */ UPDATE {$this->tables['tcversions']} " .
				   "SET {$sqlSET} WHERE id={$tcversionID}";
				   		
			$this->db->exec_query($sql);
		}
		return $sql;
	}

	
	/**
	 * updateName
	 * check for duplicate name under same parent
	 *
	 * @param int	 $id test case id
	 * @param string $name
	 *
	 * @internal revisions
	 *
	 */
	function updateName($id,$name)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$ret['status_ok'] = true; 
		$ret['msg'] = 'ok'; 
		$ret['debug'] = '';
		$ret['API_error_code'] = 0; 

		$field_size = config_get('field_size');
	    $new_name = trim($name);

		if( ($nl = strlen($new_name)) <= 0 )
		{
			$ret['status_ok'] = false; 
			$ret['API_error_code'] = 'TESTCASE_EMPTY_NAME';
			$ret['msg'] = lang_get('API_' . $ret['API_error_code']); 
		}

		if( $ret['status_ok'] && $nl > $field_size->testcase_name)
		{
			$ret['status_ok'] = false; 
			$ret['API_error_code'] = 'TESTCASE_NAME_LEN_EXCEEDED';
			$ret['msg'] = sprintf(lang_get('API_' . $ret['API_error_code']),$nl,$field_size->testcase_name); 
		}
				
		if( $ret['status_ok'] )
		{
			// Go ahead
			$check = $this->tree_manager->nodeNameExists($name,$this->my_node_type,$id);
			$ret['status_ok'] = !$check['status']; 
			$ret['API_error_code'] = 'TESTCASE_SIBLING_WITH_SAME_NAME_EXISTS';
			$ret['msg'] = sprintf(lang_get('API_' . $ret['API_error_code']),$name); 
			$ret['debug'] = ''; 
		}

		if($ret['status_ok'])
		{    
			
			$rs = $this->tree_manager->get_node_hierarchy_info($id);
			if( !is_null($rs) && $rs['node_type_id'] == $this->my_node_type)
			{
				$sql = "/* {$debugMsg} */ UPDATE {$this->tables['nodes_hierarchy']} " .
				       " SET name='" . $this->db->prepare_string($name) . "' " .
				       " WHERE id= {$id}";
				$this->db->exec_query($sql);
				$ret['debug'] = "Old name:{$rs['name']} - new name:{$name}";
			}
		}
		return $ret;
	}



  function writeExecToDB(&$exec_signature,&$exec_data)
  {
  	$cf_prefix = $this->cfield_mgr->get_name_prefix();
  	$len_cfp = tlStringLen($cf_prefix);
  	$cf_nodeid_pos = 4;
  	$bulk_notes = '';
  	
  	$cf_map = $this->cfield_mgr->get_linked_cfields_at_execution($exec_signature->tproject_id,1,'testcase');
  	$has_custom_fields = is_null($cf_map) ? 0 : 1;
  	
  	// extract custom fields id.
  	$map_nodeid_array_cfnames=null;
  	foreach($exec_data as $input_name => $value)
  	{
  		if( strncmp($input_name,$cf_prefix,$len_cfp) == 0 )
  		{
  			$dummy = explode('_',$input_name);
  			$map_nodeid_array_cfnames[$dummy[$cf_nodeid_pos]][]=$input_name;
  		} 
  	}
  	
  	if( isset($exec_data['do_bulk_save']) )
  	{
  		// create structure to use common algoritm
  		$item2loop= $exec_data['status'];
  		$is_bulk_save=1;
  		$bulk_notes = $this->db->prepare_string(trim($exec_data['bulk_exec_notes']));		
  	}	
  	else
  	{
  		$item2loop= $exec_data['save_results'];
  		$is_bulk_save=0;
  	}
  	
  	foreach ( $item2loop as $tcversion_id => $val)
  	{
  		$tcase_id = $exec_data['tc_version'][$tcversion_id];
  		$current_status = $exec_data['status'][$tcversion_id];
  		$version_number = $exec_data['version_number'][$tcversion_id];;
  		$has_been_executed = ($current_status != $this->cfg->results['status_code']['not_run'] ? TRUE : FALSE);
  		if($has_been_executed)
  		{ 
  			
  			$my_notes = $is_bulk_save ? $bulk_notes : $this->db->prepare_string(trim($exec_data['notes'][$tcversion_id]));		
  			$sql = "INSERT INTO {$this->tables['executions']} ".
  				"(build_id,tester_id,status,testplan_id,tcversion_id," .
  				" execution_ts,notes,tcversion_number,platform_id)".
  				" VALUES ( {$exec_signature->build_id}, {$exec_signature->user_id}, '{$exec_data['status'][$tcversion_id]}',".
  				"{$exec_signature->tplan_id}, {$tcversion_id},{$this->db->db_now()},'{$my_notes}'," .
  				"{$version_number},{$exec_signature->platform_id}" . 
  				")";
  			$this->db->exec_query($sql);  	
  			
  			// at least for Postgres DBMS table name is needed. 
  			$execution_id = $this->db->insert_id($this->tables['executions']);
  			
  			if( $has_custom_fields )
  			{
  				// test useful when doing bulk update, because some type of custom fields
  				// like checkbox can not exist on exec_data. => why ??
  				//
  				$hash_cf = null;
  				$access_key = $is_bulk_save ? 0 : $tcase_id;
  				if( isset($map_nodeid_array_cfnames[$access_key]) )
  				{ 
  					foreach($map_nodeid_array_cfnames[$access_key] as $cf_v)
  					{
  						$hash_cf[$cf_v]=$exec_data[$cf_v];
  					}  
  				}                          
  				$this->cfield_mgr->execution_values_to_db($hash_cf,$tcversion_id, $execution_id, 
  				                                          $exec_signature->tplan_id,$cf_map);
  			}                                     
  		}
  	}
  }





  static function createExecutionResultsMenu()
  {
  	$cfg = config_get('results');
  	
  	$menu[$cfg['status_code']['all']] = isset($cfg['status_label']['all']) ? $cfg['status_label']['all'] : '';
  	$menu[$cfg['status_code']['not_run']] = lang_get($cfg['status_label']['not_run']);
  	
  	// loop over status for user interface, because these are the statuses
  	// user can assign while executing test cases
  	foreach($cfg['status_label_for_exec_ui'] as $verbose => $label)
  	{
  		$menu[$cfg['status_code'][$verbose]] = lang_get($label); 
  	}
  	return $menu;
  }

  // ---------------------------------------------------------------------------------------
  // from 1.9.4
	/**
	 * @param map $identity: id, version_id
	 * @param map $execContext: tplan_id, platform_id,build_id
	 * @internal revisions
	 *
	 * @since 1.9.4
	 **/
	function getLatestExecSingleContext($identity,$execContext,$options=null)
	{
		$debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
		$status_not_run = $this->cfg->results['status_code']['not_run'];
		
		$my = array('opt' => array('output' => 'full'));
		$my['opt'] = array_merge($my['opt'],(array)$options);
		$safeContext = $execContext;
		$safeIdentity = $identity;
		foreach($safeContext as &$ele)
		{
			$ele = intval($ele);
		}
		foreach($safeIdentity as &$ele)
		{
			$ele = intval($ele);
		}
		
		// we have to manage following situations
		// 1. we do not know test case version id.
		if($safeIdentity['version_id'] > 0)
		{
			$addJoinLEX = '';
			$addWhereLEX = " AND EE.tcversion_id = " . $safeIdentity['version_id']; 
			$addWhere = " AND TPTCV.tcversion_id = " . $safeIdentity['version_id']; 
		}
		else
		{                  
			$addJoinLEX = " JOIN {$this->tables['nodes_hierarchy']} H2O " .
					   	      " ON H2O.id = EE.tcversion_id ";
			$addWhereLEX = " AND H2O.parent_id = " . $safeIdentity['id'];
			$addWhere = " AND NHTC.id = " . $safeIdentity['id'];
		}

		$sqlLEX = ' SELECT EE.tcversion_id,EE.testplan_id,EE.platform_id,EE.build_id,' .
    				  ' MAX(EE.id) AS id ' .
    				  " FROM {$this->tables['executions']} EE " . 
    				  $addJoinLEX .
    				  ' WHERE EE.testplan_id = ' . $safeContext['tplan_id'] . 
    				  ' AND EE.platform_id = ' . $safeContext['platform_id'] . 
    				  ' AND EE.build_id = ' . $safeContext['build_id'] .
    				  $addWhereLEX .
    				  ' GROUP BY EE.tcversion_id,EE.testplan_id,EE.platform_id ,EE.build_id ';
		
		$out = null;
		switch($my['opt']['output'])
		{
			case 'exec_id':
				  $dummy = $this->db->get_recordset($sqlLEX);
				  $out = (!is_null($dummy) ? $dummy[0]['id'] : null);	
			break;	
		
			case 'full':
			default:
				  $sql= "/* $debugMsg */ SELECT E.id AS execution_id, " .
			   			  " COALESCE(E.status,'{$status_not_run}') AS status, E.execution_type AS execution_run_type," .
				        " NHTC.name, NHTC.id AS testcase_id, NHTC.parent_id AS tsuite_id," .
				        " TCV.id AS tcversion_id,TCV.tc_external_id,TCV.version,TCV.summary," .
				        " TCV.preconditions,TCV.importance,TCV.author_id," .
				        " TCV.creation_ts,TCV.updater_id,TCV.modification_ts,TCV.active," .
				        " TCV.is_open,TCV.execution_type," .
				        " U.login AS tester_login,U.first AS tester_first_name," .
    						" U.last AS tester_last_name, E.tester_id AS tester_id," .
    						" E.notes AS execution_notes, E.execution_ts, E.build_id,E.tcversion_number," .
    						" B.name AS build_name, B.active AS build_is_active, B.is_open AS build_is_open," .
				        " COALESCE(PLATF.id,0) AS platform_id,PLATF.name AS platform_name, TPTCV.id AS feature_id " .
  					    " FROM {$this->tables['nodes_hierarchy']} NHTCV " .
	  				    " JOIN {$this->tables['testplan_tcversions']} TPTCV ON TPTCV.tcversion_id = NHTCV.id" .
				        " JOIN {$this->tables['nodes_hierarchy']} NHTC ON NHTC.id = NHTCV.parent_id " .
				        " JOIN {$this->tables['tcversions']} TCV ON TCV.id = NHTCV.id " .
						
		    				" LEFT OUTER JOIN ({$sqlLEX}) AS LEX " .
				    		" ON  LEX.testplan_id = TPTCV.testplan_id " .
    						" AND LEX.platform_id = TPTCV.platform_id " .
    						" AND LEX.tcversion_id = TPTCV.tcversion_id " .
    						" AND LEX.build_id = {$safeContext['build_id']} " .
    			
    						" LEFT OUTER JOIN {$this->tables['executions']} E " . 
    						" ON E.id = LEX.id " .
			
				        " JOIN {$this->tables['builds']} B ON B.id = {$safeContext['build_id']} " .
				        " LEFT OUTER JOIN {$this->tables['users']} U ON U.id = E.tester_id " .
		   	        " LEFT OUTER JOIN {$this->tables['platforms']} PLATF ON PLATF.id = {$safeContext['platform_id']} " .
    						" WHERE TPTCV.testplan_id = {$safeContext['tplan_id']} " .
		    				" AND TPTCV.platform_id = {$safeContext['platform_id']} " .
				    		$addWhere .
						    " AND (E.build_id = {$safeContext['build_id']} OR E.build_id IS NULL)";
						
    						// using database::CUMULATIVE is just a trick to return data structure
		    				// that will be liked on execSetResults.php
				    		$out = $this->db->fetchRowsIntoMap($sql,'testcase_id',database::CUMULATIVE);
			break;
		}
		return $out;	
	}	

  // wrappers  
  // just a wrapper that can be useful sometimes
  function get_full_path_verbose($id)
  {
    return $this->tree_manager->get_full_path_verbose($id);
  }
  
  function getTestSuiteID($id)
  {
    $dummy = $this->tree_manager->get_node_hierarchy_info($id);
    return $dummy['parent_id'];
  }

  
} // end class
?>