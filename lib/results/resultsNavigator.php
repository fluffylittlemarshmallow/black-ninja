<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 * @version $Id: resultsNavigator.php,v 1.45 2008/10/07 19:13:44 schlundus Exp $ 
 * @author	Martin Havlat <havlat@users.sourceforge.net>
 * 
 * Scope: Launcher for Test Results and Metrics.
 *
 * rev :
 *      20071109,11 - havlatm - move data to config + refactorization; removed obsolete build list
 * 							 move functions into class  
 *      20070930 - franciscom - 
 *      20070916 - franciscom - added logic to choose test plan
 *      20070826 - franciscom - disable resultsImport
 * 
 **/
 
 
require('../../config.inc.php');
require_once('common.php');
require_once('builds.inc.php');
require_once('reports.class.php');
testlinkInitPage($db);
tLog('resultsNavigator.php called');

$template_dir = 'results/';

$do_report = array();
$do_report['status_ok'] = 1;
$do_report['msg'] = '';
$selectedReportType = null;
$workframe = "";

$tproject_id = isset($_SESSION['testprojectID']) ? $_SESSION['testprojectID'] : 0;
$tplan_id = isset($_REQUEST['tplan_id']) ? $_REQUEST['tplan_id'] : $_SESSION['testPlanId'];
$btsEnabled = config_get('bugInterfaceOn');

$tplan_mgr = new testplan($db);
$reports_magic = new tlReports($db, $tplan_id);

// -----------------------------------------------------------------------------
// Do some checks to understand if reports make sense

// Check if there are linked test cases to the choosen test plan.
$tc4tp_count = $reports_magic->get_count_testcase4testplan();
tLog('TC in TP count = ' . $tc4tp_count);
if( $tc4tp_count == 0)
{
   // Test plan without test cases
   $do_report['status_ok'] = 0;
   $do_report['msg'] = lang_get('report_tplan_has_no_tcases');       
}

// Build qty
$build_count = $reports_magic->get_count_builds();
tLog('Active Builds count = ' . $build_count);
if( $build_count == 0)
{
   // Test plan without builds can have execution data
   $do_report['status_ok'] = 0;
   $do_report['msg'] = lang_get('report_tplan_has_no_build');       
}

// -----------------------------------------------------------------------------
// get navigation data
$href_map = array();
$map_tplans = array();
if($do_report['status_ok'])
{
  	if (isset($_GET['format']))
		$selectedReportType = intval($_GET['format']);
  	else
		$selectedReportType = sizeof($tlCfg->reports_formats) ? key($tlCfg->reports_formats) : null;

	// create a list or reports
	$href_map = $reports_magic->get_list_reports($btsEnabled ,$_SESSION['testprojectOptReqs'], 
		$tlCfg->reports_formats[$selectedReportType]);

}
$tplans = getAccessibleTestPlans($db, $tproject_id, $_SESSION['userID'], 1);
foreach($tplans as $key => $value)
{
  	$map_tplans[$value['id']] = $value['name'];
}

$workframe = $_SESSION['basehref'] . "lib/general/staticPage.php?key=showMetrics";

$smarty = new TLSmarty();
$smarty->assign('workframe', $workframe);
$smarty->assign('do_report', $do_report);
$smarty->assign('arrData', $href_map);
$smarty->assign('tplans', $map_tplans);
$smarty->assign('arrReportTypes', $tlCfg->reports_formats);
$smarty->assign('tplan_id', $tplan_id);
$smarty->assign('selectedReportType', $selectedReportType);
$smarty->display($template_dir .'resultsNavigator.tpl');
?>
