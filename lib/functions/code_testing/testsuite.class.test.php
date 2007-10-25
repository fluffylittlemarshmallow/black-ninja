<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * Filename $RCSfile: testsuite.class.test.php,v $
 *
 * @version $Revision: 1.1 $
 * @modified $Date: 2007/10/25 13:06:30 $ by $Author: franciscom $
 * @author Francisco Mancardi
 *
 * With this page you can launch a set of available methods, to understand
 * and have inside view about return type .
 *
 * rev :
 *
*/

require_once('../../../config.inc.php');
require_once('common.php');
require_once('dBug.php');

testlinkInitPage($db);

echo "<pre> testsuite - constructor - testsuite(&\$db)";echo "</pre>";
$tsuite_mgr=new testsuite($db);
new dBug($tsuite_mgr);

echo "<pre> testsuite - get_all()";echo "</pre>";
echo "<pre>             get_all()";echo "</pre>";
$all_tsuites_in_my_tl=$tsuite_mgr->get_all();
new dBug($all_tsuites_in_my_tl);

$tsuite_id=3;
echo "<pre> testsuite - get_by_id(\$id)";echo "</pre>";
echo "<pre>             get_by_id($tsuite_id)";echo "</pre>";
$tsuite_info=$tsuite_mgr->get_by_id($tsuite_id);
new dBug($tsuite_info);

/*

$set_of_tsuite_id=array(4,6);
echo "<pre>            get_by_id($set_of_tsuite_id)";echo "</pre>";
$set_of_tcase_info=$tsuite_mgr->get_by_id($set_of_tsuite_id);
new dBug($set_of_tcase_info);

$tsuite_id=4;
echo "<pre> testsuite - check_link_and_exec_status(\$id)";echo "</pre>";
echo "<pre>            check_link_and_exec_status($tsuite_id)";echo "</pre>";
$link_and_exec_status=$tsuite_mgr->check_link_and_exec_status($tsuite_id);
new dBug($link_and_exec_status);


echo "<pre> testsuite - get_linked_versions(\$id,\$exec_status='ALL',\$active_status='ALL')";
echo "<pre>            get_linked_versions($tsuite_id)";
$linked_versions=$tsuite_mgr->get_linked_versions($tsuite_id);
new dBug($linked_versions);

$tsuite_id=4;
echo "<pre> testsuite - get_testproject(\$id)";
echo "<pre>            get_testproject($tsuite_id)";
$testproject_id=$tsuite_mgr->get_testproject($tsuite_id);
new dBug("testproject id=" . $testproject_id);


$tsuite_id=4;
echo "<pre> testsuite - get_last_version_info(\$id)";
echo "<pre>            get_last_version_info($tsuite_id)";
$last_version_info=$tsuite_mgr->get_last_version_info($tsuite_id);
new dBug($last_version_info);


echo "<pre> testsuite - get_versions_status_quo(\$id,\$tcversion_id=null, \$testplan_id=null)";
echo "<pre>            get_versions_status_quo($tsuite_id)";
$status_quo=$tsuite_mgr->get_versions_status_quo($tsuite_id);
new dBug($status_quo);


echo "<pre> testsuite - get_exec_status(\$id)";
echo "<pre>            get_exec_status($tsuite_id)";
$testcase_exec_status=$tsuite_mgr->get_exec_status($tsuite_id);
new dBug($testcase_exec_status);


echo "<pre> testsuite - getKeywords(\$tcID,\$kwID = null)";echo "</pre>";
echo "<pre>            getKeywords($tsuite_id)";echo "</pre>";
$keywords=$tsuite_mgr->getKeywords($tsuite_id);
new dBug($keywords);


echo "<pre> testsuite - get_keywords_map(\$id,\$order_by_clause='')";echo "</pre>";
$tsuite_id=4;
echo "<pre>               get_keywords_map($tsuite_id)";echo "</pre>";
$keywords_map=$tsuite_mgr->get_keywords_map($tsuite_id);
new dBug($keywords_map);


$tsuite_id=4;
$version_id=5;
$tplan_id=8;
$build_id=1;
echo "<pre> testsuite - get_executions(\$id,\$version_id,\$tplan_id,\$build_id,<br>
                                      \$exec_id_order='DESC',\$exec_to_exclude=null)";echo "</pre>";

echo "<pre>            get_executions($tsuite_id,$version_id,$tplan_id,$build_id)";echo "</pre>";
$executions=$tsuite_mgr->get_executions($tsuite_id,$version_id,$tplan_id,$build_id);
new dBug($executions);


echo "<pre> testsuite - get_last_execution(\$id,\$version_id,\$tplan_id,\$build_id,\$get_no_executions=0)";echo "</pre>";
echo "<pre>            get_last_execution($tsuite_id,$version_id,$tplan_id,$build_id)";echo "</pre>";
$last_execution=$tsuite_mgr->get_last_execution($tsuite_id,$version_id,$tplan_id,$build_id);
new dBug($last_execution);




$tcversion_id=5;
$tplan_id=8;
echo "<pre> testsuite - get_version_exec_assignment(\$tcversion_id,\$tplan_id)";echo "</pre>";
echo "<pre>            get_version_exec_assignment($tcversion_id,$tplan_id)";echo "</pre>";
$version_exec_assignment=$tsuite_mgr->get_version_exec_assignment($tcversion_id,$tplan_id);
new dBug($version_exec_assignment);
*/


echo "<pre> testsuite - get_linked_cfields_at_design(\$id,\$parent_id=null,\$show_on_execution=null)";echo "</pre>";
echo "<pre>            get_linked_cfields_at_design($tsuite_id)";echo "</pre>";
$linked_cfields_at_design=$tsuite_mgr->get_linked_cfields_at_design($tsuite_id);
new dBug($linked_cfields_at_design);



echo "<pre> testsuite - get_linked_cfields_at_execution(\$id,\$parent_id=null,<br>
                                                       \$show_on_execution=null,<br>
                                                       \$execution_id=null,\$testplan_id=null)";echo "</pre>"; 
echo "<pre>            get_linked_cfields_at_execution($tsuite_id)";echo "</pre>";
$linked_cfields_at_execution=$tsuite_mgr->get_linked_cfields_at_execution($tsuite_id);
new dBug($linked_cfields_at_execution);



echo "<pre> testsuite - html_table_of_custom_field_inputs(\$id,\$parent_id=null,\$scope='design',\$name_suffix='')";echo "</pre>";
echo "<pre>            html_table_of_custom_field_inputs($tsuite_id)";echo "</pre>";
$table_of_custom_field_inputs=$tsuite_mgr->html_table_of_custom_field_inputs($tsuite_id);
echo "<pre>"; echo $table_of_custom_field_inputs; echo "</pre>";


echo "<pre> testsuite - html_table_of_custom_field_values(\$id,\$scope='design',<br>
                                                         \$show_on_execution=null,<br>
                                                         \$execution_id=null,\$testplan_id=null) ";echo "</pre>";
                                                         
echo "<pre> testsuite - html_table_of_custom_field_values($tsuite_id)";echo "</pre>";
$table_of_custom_field_values=$tsuite_mgr->html_table_of_custom_field_values($tsuite_id); 
echo "<pre><xmp>"; echo $table_of_custom_field_values; echo "</xmp></pre>";

/*

function testsuite(&$db)
function create($parent_id,$name,$details,$order=null,
function update($id, $name, $details)
function get_by_name($name)
function get_by_id($id)
function get_all()
function show(&$smarty,$id, $sqlResult = '', $action = 'update',$modded_item_id = 0)
function viewer_edit_new(&$smarty,$amy_keys, $oFCK, $action, $parent_id, 
function copy_to($id, $parent_id, $user_id,
function get_testcases_deep($id,$bIdsOnly = false)
function delete_deep($id)
function getKeywords($id,$kw_id = null)
function get_keywords_map($id,$order_by_clause='')
function addKeyword($id,$kw_id)
function addKeywords($id,$kw_ids)
function deleteKeywords($id,$kw_id = null)
function exportTestSuiteDataToXML($container_id,$optExport = array())
function get_spec_cfields($id) 
function get_linked_cfields_at_design($id,$parent_id=null,$show_on_execution=null) 
function get_linked_cfields_at_execution($id,$parent_id=null,$show_on_execution=null) 
function html_table_of_custom_field_inputs($id,$parent_id=null,$scope='design') 
function html_table_of_custom_field_values($id,$scope='design',$show_on_execution=null) 


*/



?>
