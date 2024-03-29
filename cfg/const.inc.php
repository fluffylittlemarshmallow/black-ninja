<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * Global Constants used throughout TestLink 
 * The script is included via config.inc.php
 * 
 * @filesource	const.inc.php
 * @package 	  TestLink
 * @author 		  Martin Havlat
 * @copyright 	2007-2012, TestLink community 
 * @see 		    config.inc.php
 *
 * @internal revisions
 * No revisions logged here but each parameter must be described!
 *
 **/
 

/* [GLOBAL SETTINGS] */

/** TestLink Release version (MUST BE changed before the release day) */
define('TL_FACE_DIR', 'baires'); 
define('TL_VERSION', '2.0 (Buenos Aires - Development)');

// Last Database version:
// used to give users feedback about necesssary upgrades
// if you set this parameter also upgrade configCheck.php - checkSchemaVersion() 
define('TL_LAST_DB_VERSION', 'DB 2.0');

// needed to avoid problems in install scripts that do not include config.inc.php
// want to point to root install dir, need to remove fixed part
if (!defined('TL_ABS_PATH')) 
{
    define('TL_ABS_PATH', str_replace('cfg','',dirname(__FILE__)));
}

/** Setting up the global include path for testlink */
ini_set('include_path',ini_get('include_path') . PATH_SEPARATOR . '.' . PATH_SEPARATOR . 
                       TL_ABS_PATH . 'lib' . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR);

ini_set('include_path',ini_get('include_path') . PATH_SEPARATOR . TL_ABS_PATH . 'lib' . DIRECTORY_SEPARATOR . 'issuetrackerintegration' . DIRECTORY_SEPARATOR);                       
ini_set('include_path',ini_get('include_path') . PATH_SEPARATOR . TL_ABS_PATH . 'third_party' . DIRECTORY_SEPARATOR);
ini_set('include_path',ini_get('include_path') . 
        PATH_SEPARATOR . TL_ABS_PATH . 'third_party' . DIRECTORY_SEPARATOR . 'dBug' . DIRECTORY_SEPARATOR);



/** Localization directory base */
define('TL_LOCALE_PATH', TL_ABS_PATH . 'locale/');


// --------------------------------------------------------------------------------------
/* [GENERAL MAGIC NUMBERS] */

/** PHPMAILER */
define('PHPMAILER_METHOD_MAIL', 0);
define('PHPMAILER_METHOD_SENDMAIL', 1);
define('PHPMAILER_METHOD_SMTP', 2);

/** Descriptive constant names (actually true/false) */
define('ENABLED', 	1 );
define('DISABLED', 	0 );
define('ON',		1 );
define('OFF',		0 );
define('ACTIVE',	1 );
define('INACTIVE',	0 );
define('OPEN',		1 );
define('CLOSED',	0 );
define('OK',		1 );
define('ERROR',		0 );

/** More Descriptive constant names */
define('HIGH',		3 );
define('MEDIUM', 	2 );
define('LOW', 		1 );

/** user for notes - see BUGID 0002469: $tlCfg->exec_cfg->expand_collapse 
	very important do not change values, logic depends on values*/
define('LAST_USER_CHOICE',2);
define('COLLAPSE', 0);
define('EXPAND',1 );


/** @TODO havlatm: remove, use ON || 'OFF' constant */
define('TL_FILTER_OFF',null);

// used in several functions instead of MAGIC NUMBERS - Don't change 
define('ALL_PRODUCTS', 0);
define('TP_ALL_STATUS', null);
define('FILTER_BY_PRODUCT', 1);
define('FILTER_BY_TESTPROJECT', 1);
define('TP_STATUS_ACTIVE', 1);

define('DO_LANG_GET',1 );
define('DONT_DO_LANG_GET',0 );

define('DSN', FALSE);  // for method connect() of database.class
define('ANY_BUILD', null);
define('GET_NO_EXEC', 1);

/** @uses planTCNavigator.php */
define('FILTER_BY_BUILD_OFF', 0);
define('FILTER_BY_OWNER_OFF', 0);
define('FILTER_BY_TC_STATUS_OFF', null);
define('FILTER_BY_KEYWORD_OFF', null);
define('FILTER_BY_ASSIGNED_TO_OFF', 0);
define('SEARCH_BY_CUSTOM_FIELDS_OFF', null);
define('COLOR_BY_TC_STATUS_OFF', null);
define('CREATE_TC_STATUS_COUNTERS_OFF', 0);

// moved from testSetRemove.php
define('WRITE_BUTTON_ONLY_IF_LINKED', 1);

// moved from tc_exec_assignment.php
define('FILTER_BY_TC_OFF', null); 
define('FILTER_BY_EXECUTE_STATUS_OFF', null); 
define('ALL_USERS_FILTER', null); 
define('ADD_BLANK_OPTION', true); 

// 
define('FILTER_BY_SHOW_ON_EXECUTION', 1);

define('GET_ALSO_NOT_EXECUTED', null);
define('GET_ONLY_EXECUTED', 'executed');

// generateTestSpecTree()
define('FOR_PRINTING', 1);
define('NOT_FOR_PRINTING', 0);

define('HIDE_TESTCASES', 1);
define('SHOW_TESTCASES', 0);
define('FILTER_INACTIVE_TESTCASES', 1);
define('DO_NOT_FILTER_INACTIVE_TESTCASES', 0);

define('ACTION_TESTCASE_DISABLE', 0);
define('IGNORE_INACTIVE_TESTCASES', 1);

define('DO_ON_TESTCASE_CLICK', 1);
define('NO_ADDITIONAL_ARGS', '');
define('NO_KEYWORD_ID_TO_FILTER', 0);


define('RECURSIVE_MODE', TRUE);
define('NO_NODE_TYPE_TO_FILTER', null);
define('ANY_OWNER', null);

define('ALL_BUILDS', 'a');
define('ALL_PLATFORMS', 'a');
define('ALL_TEST_SUITES', 'all');

/** @todo use consts ACTIVE || INACTIVE, OPEN || CLOSED*/
define('GET_ACTIVE_BUILD', 1);
define('GET_INACTIVE_BUILD', 0);
define('GET_OPEN_BUILD', 1);
define('GET_CLOSED_BUILD', 0);

define('AUTOMATIC_ID', 0);
define('NO_FILTER_SHOW_ON_EXEC', null);
define('DONT_REFRESH', 0);
define('DEFAULT_TC_ORDER', 0);

// bug_interface->buildViewBugLink()
define('GET_BUG_SUMMARY', true);

// gen_spec_view()
define('DO_PRUNE', 1);

// executeTestCase()
define('AUTOMATION_RESULT_KO', -1);
define('AUTOMATION_NOTES_KO', -1);

/** @uses testcase.class.php */
define('TESTCASE_EXECUTION_TYPE_MANUAL', 1);
define('TESTCASE_EXECUTION_TYPE_AUTO', 2);


/** @uses testlinkInitPage() */
define('TL_UPDATE_ENVIRONMENT', true);
define('TL_CHECK_SESSION_TIMEOUT', true);

// --------------------------------------------------------------------------------------
/* [GUI] */

/** 
 * @uses planAddTC_m1-tpl 
 * 
 **/
define('TL_STYLE_FOR_ADDED_TC', 'background-color:yellow;');

/** default filenames of CSS files of current GUI theme */
define('TL_CSS_MAIN', 'testlink.css');
define('TL_CSS_PRINT', 'tl_print.css');
define('TL_CSS_DOCUMENTS', 'tl_documents.css');
define('TL_CSS_CUSTOM', 'custom.css');

/** @todo havlatm: remove - probably obsolete from 1.9 */
define('TL_CSS_TREEMENU', 'tl_treemenu.css');

/** Browser Cookie keeptime */
define('TL_COOKIE_KEEPTIME', (time()+60*60*24*30)); // 30 days

// needed for drap and drop feature
define('TL_DRAG_DROP_DIR', 'gui/drag_and_drop/');
define('TL_DRAG_DROP_JS_DIR', TL_DRAG_DROP_DIR. 'js/');
define('TL_DRAG_DROP_FOLDER_CSS', TL_DRAG_DROP_DIR . 'css/drag-drop-folder-tree.css');
define('TL_DRAG_DROP_CONTEXT_MENU_CSS', TL_DRAG_DROP_DIR . 'css/context-menu.css');

/** Mark up inactive objects (test projects, etc) in GUI lists */
define('TL_INACTIVE_MARKUP', '* ');

/** @var string Delimiter used when created a test suite path, concatenating test suite names */
$g_testsuite_sep='/';

/**
 * using niftycorners 
 **/
define('MENU_ITEM_OPEN', '<div class="menu_bubble">');
define('MENU_ITEM_CLOSE', '</div><br />');

/** 
 * Used to force the max len of this field, during the automatic creation of requirements
 * or other import features
 */ 
$g_field_size = new stdClass();
$g_field_size->node_name = 100;
$g_field_size->testsuite_name = 100;
$g_field_size->testcase_name = 100;
$g_field_size->testproject_name = 100;

// requirements and req_spec tables field sizes
$g_field_size->req_docid = 64;
$g_field_size->req_title = 100;
$g_field_size->requirement_title = 100;
$g_field_size->docid = 64;

// execution table
$g_field_size->bug_id = 16;


// --------------------------------------------------------------------------------------
/* [LOCALIZATION] */

/** 
 * String that will used as prefix, to generate an string when a label to be localized
 * is passed to lang_get() to be translated, by the label is not present in the strings file.
 * The resulting string will be:  TL_LOCALIZE_TAG . label
 * @example code specifies the key of string: lang_get('hello') -> shows "LOCALIZE: Hello"
 */
define('TL_LOCALIZE_TAG','LOCALIZE: ');

/** 
 * @var array List of supported localizations (used in user preferences to choose one)
 * DEV: Mantain the alphabetical order when adding new locales. Also check inc.ext_js_tpl
 *      to set localization for ExtJS Components and web_editor.php to set localization for
 *      CKEditor
 **/
// 
$g_locales = array(	
	'cs_CZ' => 'Czech',
	'de_DE' => 'German',
	'en_GB' => 'English (wide/UK)',
	'en_US' => 'English (US)',
	'es_AR' => 'Spanish (Argentine)',
	'es_ES' => 'Spanish',
	'fi_FI' => 'Finnish',
	'fr_FR' => 'Fran&ccedil;ais',
	'id_ID' => 'Indonesian',
	'it_IT' => 'Italian',
	'ja_JP' => 'Japanese',
	'ko_KR' => 'Korean',
	'nl_NL' => 'Dutch',
	'pl_PL' => 'Polski',
	'pt_BR' => 'Portuguese (Brazil)',
	'ru_RU' => 'Russian',
	'zh_CN' => 'Chinese Simplified'
);

/** 
 * Format of date - see strftime() in PHP manual
 * NOTE: setting according local is done in testlinkInitPage() using set_dt_formats()
 */

/** @var string Default format of date */
$g_date_format ='%d/%m/%Y';
/** @var string Default format of datetime */
$g_timestamp_format = '%d/%m/%Y %H:%M:%S';

/** @var array Localized format of date */
$g_locales_date_format = array(
	'cs_CZ' => '%d.%m.%Y',
	'de_DE' => '%d.%m.%Y',
	'en_GB' => '%d/%m/%Y',
	'en_US' => '%m/%d/%Y',
	'es_AR' => '%d/%m/%Y',
	'es_ES' => '%d/%m/%Y',
	'fi_FI' => '%d/%m/%Y',
	'fr_FR' => '%d/%m/%Y',
	'id_ID' => '%d/%m/%Y',
	'it_IT' => '%d/%m/%Y',
	'ja_JP' => '%Y/%m/%d',
	'ko_KR' => '%Y/%m/%d',
	'nl_NL' => '%d-%m-%Y',
	'pl_PL' => '%d.%m.%Y',
	'pt_BR' => '%d/%m/%Y',
	'ru_RU' => '%d/%m/%Y',
	'zh_CN' => '%Y-%m-%d'
); 

/** @var array Localized format of full timestamp */
$g_locales_timestamp_format = array(
	'cs_CZ' => '%d.%m.%Y %H:%M:%S',
	'de_DE' => '%d.%m.%Y %H:%M:%S',
	'en_GB' => '%d/%m/%Y %H:%M:%S',
	'en_US' => '%m/%d/%Y %H:%M:%S',
	'es_AR' => '%d/%m/%Y %H:%M:%S',
	'es_ES' => '%d/%m/%Y %H:%M:%S',
	'fi_FI' => '%d/%m/%Y %H:%M:%S',
	'fr_FR' => '%d/%m/%Y %H:%M:%S',
	'id_ID' => '%d/%m/%Y %H:%M:%S',
	'it_IT' => '%d/%m/%Y %H:%M:%S',
	'ja_JP' => '%Y/%m/%d %H:%M:%S',
	'ko_KR' => '%Y/%m/%d %H:%M:%S',
	'nl_NL' => '%d-%m-%Y %H:%M:%S',
	'pl_PL' => '%d.%m.%Y %H:%M:%S',
	'pt_BR' => '%d/%m/%Y %H:%M:%S',
	'ru_RU' => '%d/%m/%Y %H:%M:%S',
	'zh_CN' => '%Y-%m-%d %H:%M:%S'
); 

/** @var array localized date format for smarty templates (html_select_date function) 
 * deprecated since use of datepicker */
$g_locales_html_select_date_field_order = array(
	'cs_CZ' => 'dmY',
	'de_DE' => 'dmY',
	'en_GB' => 'dmY',
	'en_US' => 'mdY',
	'es_AR' => 'dmY',
	'es_ES' => 'dmY',
	'fi_FI' => 'dmY',
	'fr_FR' => 'dmY',
	'id_ID' => 'dmY',
	'it_IT' => 'dmY',
	'ja_JP' => 'Ymd',
	'ko_KR' => 'Ymd',
	'nl_NL' => 'dmY',
	'pl_PL' => 'dmY',
	'pt_BR' => 'dmY',
	'ru_RU' => 'dmY',
	'zh_CN' => 'Ymd'
); 



// --------------------------------------------------------------------------------------
/* ATTACHMENTS */

/** Attachment key constants (do not change) */
define('TL_REPOSITORY_TYPE_DB', 1);
define('TL_REPOSITORY_TYPE_FS', 2);

define('TL_REPOSITORY_COMPRESSIONTYPE_NONE', 1);
define('TL_REPOSITORY_COMPRESSIONTYPE_GZIP', 2);


// --------------------------------------------------------------------------------------
/* [Test execution] */
/** 
 * Note: do not change existing values (you can enhance arrays of course more into custom_config)
 *           If you add new statuses, please use custom_strings.txt to add your localized strings
 */

/** User can define own test status(es) by modifying
 *   - $tlCfg->results['status_code'] (in custom_config.inc.php)
 *   - $tlCfg->results['status_label'] (in custom_config.inc.php)
 *   - $tlCfg->results['status_label_for_exec_ui'] (in custom_config.inc.php)
 *   - $tlCfg->results['charts']['status_colour'] (in custom_config.inc.php)
 *   - /locale/<your_language>/custom_strings.txt
 *   - /gui/themes/default/css/custom.css
 *   
 * DO NOT define Custom test status(es) in this file - use custom_config.inc.php
 */

/** 
 * @var array List of Test Case execution results (status_key -> DB code). 
 * code is used in DB to store results (not GUI).
 * CRITIC: 
 * DB field size is CHAR(1) => a new status can be ONLY 1 char.
 * Use only lower case.
 *
 * Do not do localisation here, i.e do not change "passed" by your national language.
 */ 
$tlCfg->results['status_code'] = array (
	'failed'        => 'f',
	'blocked'       => 'b',
	'passed'        => 'p',
	'not_run'       => 'n',
	'not_available' => 'x',
	'unknown'       => 'u',
	'all'           => 'a'
); 


/** 
 * Used to get localized string to show to users
 * Order is important, because this will be display order on GUI
 * 
 * @var array key: status ID
 * value: id to use with lang_get() to get the string, from strings.txt (or custom_strings.txt)
 * 
 * @example use the next code to get localized string of a status
 * <code>
 *		$results_cfg = config_get('results');
 *		lang_get($results_cfg['status_label']["passed"]);
 * </code>        
 */
$tlCfg->results['status_label'] = array(
	'not_run'       => 'test_status_not_run',
	'passed'        => 'test_status_passed',
	'failed'        => 'test_status_failed',
	'blocked'       => 'test_status_blocked'
//	'all'           => 'test_status_all_status',
//	'not_available' => 'test_status_not_available',
//	'unknown'       => 'test_status_unknown'
);

// Is RIGHT to have this configuration DIFFERENT from $tlCfg->results['status_label'],
// because you must choose to not allow some of previous status be available
// on execution page.
// See this as a subset of $tlCfg->results['status_label']
//
// Used to generate radio and buttons at user interface level.
//
// IMPORTANT NOTE ABOUT ORDER:
// Order is important, because this will be display order on User Interface
// And will be used on every feature that will need to do ordering
// according Test Case Execution Status.
//
//
// key   => verbose status as defined in $tlCfg->results['status_code']
// value => string id defined in the strings.txt file, 
//          used to localize the strings.
//
$tlCfg->results['status_label_for_exec_ui'] = array(
	'not_run' => 'test_status_not_run',
	'passed'  => 'test_status_passed',
	'failed'  => 'test_status_failed',
	'blocked' => 'test_status_blocked'
);

/** 
 * Selected execution result by default. Values is key from $tlCfg->results['status_label']
 * @var string 
 **/
$tlCfg->results['default_status'] = 'not_run';

/** 
 * Status colours for charts - use just RGB (not colour names)
 * Colours should be compiant with definition in CSS 
 **/
$tlCfg->results['charts']['status_colour'] = array(
	'not_run' => '000000',
	'passed'  => '006400',
	'failed'  => 'B22222',
	'blocked' => '00008B'
);

/*
 * arrays for new filter types (BUGID 2455, BUGID 3026)
 * used for testcase execution
 */
$tlCfg->execution_filter_methods['status_code'] = array('latest_execution' => 1,
                                                        'all_builds' => 2,
                                                        'any_build' => 3,
                                                        'specific_build' => 4,
                                                        'current_build' => 5);

$tlCfg->execution_filter_methods['status_label'] = array('latest_execution' => 'filter_result_latest_execution',
                                                         'all_builds' => 'filter_result_all_builds',
                                                         'any_build' => 'filter_result_any_build',
                                                         'specific_build' => 'filter_result_specific_build',
                                                         'current_build' => 'filter_result_current_build');

$tlCfg->execution_filter_methods['default_type'] = $tlCfg->execution_filter_methods['status_code']['current_build'];

/*
 * same as above, but without current build
 * these are used for testcase execution assignment
 */
$tlCfg->execution_assignment_filter_methods['status_code'] = array('latest_execution' => 1,
                                                                   'all_builds' => 2,
                                                                   'any_build' => 3,
                                                                   'specific_build' => 4);

$tlCfg->execution_assignment_filter_methods['status_label'] = array('latest_execution' => 'filter_result_latest_execution',
                                                                    'all_builds' => 'filter_result_all_builds',
                                                                    'any_build' => 'filter_result_any_build',
                                                                    'specific_build' => 'filter_result_specific_build');

$tlCfg->execution_assignment_filter_methods['default_type'] = $tlCfg->execution_assignment_filter_methods['status_code']['latest_execution'];



// --------------------------------------------------------------------------------------
/* [Users & Roles] */

define('TL_USER_NOBODY', -1);
define('TL_USER_SOMEBODY', -2); //new user for new filtertypes in 2455 & 3026
define('TL_NO_USER', TL_USER_NOBODY);
define('TL_USER_ANYBODY', 0);

/** must be changes if codes are changed in roles table */
define('TL_ROLES_ADMIN', 8);
define('TL_ROLES_TESTER', 7);
define('TL_ROLES_GUEST', 5);
define('TL_ROLES_NO_RIGHTS', 3);
define('TL_ROLES_UNDEFINED', 0);
define('TL_ROLES_INHERITED', 0);

// Roles with id > to this role can be deleted from user interface
define('TL_LAST_SYSTEM_ROLE', 9);


// used on user management page to give different colour 
// to different roles.
// If you don't want use colouring then configure in this way
// $g_role_colour = array ( );
$g_role_colour = array ( 
	'admin'         => 'white',
	'tester'        => 'wheat',
	'leader'        => 'acqua',
	'senior tester' => '#FFA',
	'guest'         => 'pink',
	'test designer' => 'cyan',
	'<no rights>'   => 'grey',
	'<inherited>'   => 'seashell' 
);


// --------------------------------------------------------------------------------------
/** LDAP authentication errors */
define( 'ERROR_LDAP_AUTH_FAILED',				1400 );
define( 'ERROR_LDAP_SERVER_CONNECT_FAILED',		1401 );
define( 'ERROR_LDAP_UPDATE_FAILED',				1402 );
define( 'ERROR_LDAP_USER_NOT_FOUND',			1403 );
define( 'ERROR_LDAP_BIND_FAILED',				1404 );


// --------------------------------------------------------------------------------------
/* [Priority, Urgency, Importance] */

/** @var array importance levels */
$tlCfg->importance_levels = array(HIGH => 3,MEDIUM => 2,LOW => 1);

$tlCfg->importance['verbose_code'] = array('high' => 3, 'medium' => 2, 'low' => 1);
$tlCfg->importance['verbose_label'] = array('high' => 'importance_high', 
                                            'medium' => 'importance_medium', 'low' => 'importance_low');

$tlCfg->urgency['verbose_code'] = array('high' => 3, 'medium' => 2, 'low' => 1);
$tlCfg->urgency['verbose_label'] = array('high' => 'urgency_high', 
                                         'medium' => 'urgency_medium', 'low' => 'urgency_low');

/** @var integer Default Test case Importance offered in GUI */
$tlCfg->testcase_importance_default = $tlCfg->importance['verbose_code']['medium'];

/** @var integer Default Test case Urgency offered in GUI */
$tlCfg->testcase_urgency_default = $tlCfg->urgency['verbose_code']['medium'];



// --------------------------------------------------------------------------------------
/* [States & Review] */

/**
 * data status constants are applicable for data like requirement, test case, Test Plan 
 * @since 2.0 
 */
/** Review status: design phase; data are not available for review or using */ 
define('TL_REVIEW_STATUS_DRAFT', 	1);

/** Review status: data was reviewed and are available for using */
define('TL_REVIEW_STATUS_FINAL', 	2);

/** Review status: data wait for review */ 
define('TL_REVIEW_STATUS_REVIEW', 	3);

/** Review status: data are not applicable for using (not listed in reports and lists) */ 
define('TL_REVIEW_STATUS_OBSOLETE', 4); 
define('TL_REVIEW_STATUS_FUTURE', 	5); 

/** 
 * @var array localization identifiers for review states
 * @since 2.0 
 **/
$tlCfg->text_status_labels = array(
		TL_REVIEW_STATUS_DRAFT => 'review_status_draft', 
		TL_REVIEW_STATUS_FINAL => 'review_status_final', 
		TL_REVIEW_STATUS_REVIEW => 'review_status_review',
		TL_REVIEW_STATUS_OBSOLETE => 'review_status_obsolete', 
		TL_REVIEW_STATUS_FUTURE => 'review_status_future');

/** 
 * @deprecated 1.9
 * @TODO havlatm: obsolete - remove (use consts above) 
 * TL_REQ_STATUS_NOT_TESTABLE -> TL_REQ_TYPE_INFO
 * TL_REQ_STATUS_VALID -> TL_REQ_TYPE_FEATURE
 **/
define('TL_REQ_STATUS_VALID', 		'V');
define('TL_REQ_STATUS_NOT_TESTABLE','N');
define('TL_REQ_STATUS_DRAFT','D');
define('TL_REQ_STATUS_REVIEW','R');
define('TL_REQ_STATUS_REWORK','W');
define('TL_REQ_STATUS_FINISH','F');
define('TL_REQ_STATUS_IMPLEMENTED','I');
define('TL_REQ_STATUS_OBSOLETE','O');

// key: status; value: text label
$tlCfg->req_cfg = new stdClass();
$tlCfg->req_cfg->status_labels = array(TL_REQ_STATUS_DRAFT => 'req_status_draft',
					                   TL_REQ_STATUS_REVIEW => 'req_status_review',
					                   TL_REQ_STATUS_REWORK => 'req_status_rework',
					                   TL_REQ_STATUS_FINISH => 'req_status_finish',
					                   TL_REQ_STATUS_IMPLEMENTED => 'req_status_implemented',
					                   TL_REQ_STATUS_VALID => 'review_status_valid', 
					                   TL_REQ_STATUS_NOT_TESTABLE => 'req_status_not_testable',
					                   TL_REQ_STATUS_OBSOLETE => 'req_status_obsolete');

/** 
 * Types of requirements (with respect to standards)
 * <ul>
 * <li><b>Info</b> -informational character, project and user documentation.
 * 		The type is not testable = not used for testing logic (except metrics).</li>
 * <li><b>Feature</b> - valid and testable functional definition (default selection)</li>
 * <li><b>Use case</b></li>
 * <li><b>Interface</b> - user interface, communication protocols</li>
 * <li><b>Non-functional</b> - performance, infrastructure, robustness, security, safety, etc.</li>
 * <li><b>Constrain</b> - Constraints and Limitations</li>
 * </ul>
 *
 * CRITIC: DO NOT REMOVE ANY OF THIS CONSTANTS, BECAUSE TL EXPECT THIS TO BE DEFINED
 *
 * @since TestLink 1.9
 *
 * IMPORTANT NOTICE: this value will be written on DB on field of type CHAR(1)
 **/
define('TL_REQ_TYPE_INFO', '1');
define('TL_REQ_TYPE_FEATURE','2');
define('TL_REQ_TYPE_USE_CASE','3'); 
define('TL_REQ_TYPE_INTERFACE','4');
define('TL_REQ_TYPE_NON_FUNCTIONAL','5');
define('TL_REQ_TYPE_CONSTRAIN','6');
define('TL_REQ_TYPE_SYSTEM_FUNCTION','7');


/** 
 * @var array localization identifiers for requirements types 
 * @since TestLink 1.9
 **/
$tlCfg->req_cfg->type_labels = array(
		TL_REQ_TYPE_INFO => 'req_type_info', 
		TL_REQ_TYPE_FEATURE => 'req_type_feature',
		TL_REQ_TYPE_USE_CASE => 'req_type_use_case', 
		TL_REQ_TYPE_INTERFACE => 'req_type_interface', 
		TL_REQ_TYPE_NON_FUNCTIONAL => 'req_type_non_functional', 
		TL_REQ_TYPE_CONSTRAIN => 'req_type_constrain',
		TL_REQ_TYPE_SYSTEM_FUNCTION => 'req_type_system_function');

		

/** 
 * All possible types of requirement relations (BUGID 1748).
 * 
 * Important:
 * When you add your own relation types here, you also have to add localization strings
 * and configure those below.
 * 
 * Add you types ONLY AFTER LAST RESERVED
 *
 * @since TestLink 1.9
 *
 * IMPORTANT NOTICE this will be written on DB on an INT field
 **/
define('TL_REQ_REL_TYPE_PARENT_CHILD', 1);
define('TL_REQ_REL_TYPE_BLOCKS_DEPENDS', 2);
define('TL_REQ_REL_TYPE_RELATED', 3);
define('TL_REQ_REL_TYPE_RESERVED_1', 4);
define('TL_REQ_REL_TYPE_RESERVED_2', 5);
define('TL_REQ_REL_TYPE_RESERVED_3', 6);
define('TL_REQ_REL_TYPE_RESERVED_4', 7);
define('TL_REQ_REL_TYPE_RESERVED_5', 8);
define('TL_REQ_REL_TYPE_RESERVED_6', 9);



/** 
 * Localization identifiers for requirement relation types (BUGID 1748).
 * Types, which are configured above, have to be configured 
 * here too with attributes "source" and "destination".
 *
 * Last value will be selected in GUI as default.
 * 
 * Form has to be like this:
 * 
 * $tlCfg->req_cfg->rel_type_labels = array(
 *		RELATIONNAME => array(
 *			'source' => 'SOURCE_LOCALIZATION_KEY',
 *			'destination' => 'DESTINATION_LOCALIZATION_KEY'),
 *		...
 * 
 * @since TestLink 1.9
 **/
$tlCfg->req_cfg->rel_type_labels = array(
	TL_REQ_REL_TYPE_PARENT_CHILD => array(
		'source' => 'req_rel_is_parent_of',
		'destination' => 'req_rel_is_child_of'),
	TL_REQ_REL_TYPE_BLOCKS_DEPENDS => array(
		'source' => 'req_rel_blocks',
		'destination' => 'req_rel_depends'),
	TL_REQ_REL_TYPE_RELATED => array( // this is a flat relation, so strings are identical
		'source' => 'req_rel_is_related_to',
		'destination' => 'req_rel_is_related_to')
	);



$tlCfg->req_cfg->rel_type_description = array(TL_REQ_REL_TYPE_PARENT_CHILD => 'parent_child',
	                                          TL_REQ_REL_TYPE_BLOCKS_DEPENDS => 'blocks_depends',
	                                          TL_REQ_REL_TYPE_RELATED => 'related_to');
	

/** 
 * @var array controls is expected_coverage must be requested at user interface.
 * following conditions (OR LOGIC) must be verified to request value:
 *
 * a. key is NOT PRESENT (!isset())
 * b. key is present with value TRUE
 *
 * Working in this way configuration is simplified.
 *
 * @since TestLink 1.9
 **/
$tlCfg->req_cfg->type_expected_coverage = array(TL_REQ_TYPE_INFO => false);



// IMPORTANT NOTICE: this value will be written on DB on field of type CHAR(1)
define('TL_REQ_SPEC_TYPE_SECTION', '1'); 
define('TL_REQ_SPEC_TYPE_USER_REQ_SPEC', '2');
define('TL_REQ_SPEC_TYPE_SYSTEM_REQ_SPEC', '3');


// define('TL_REQ_SPEC_TYPE_FUNCTIONAL_AND_DATA', 1);
// define('TL_REQ_SPEC_TYPE_LOOK_AND_FEEL',2);
// define('TL_REQ_SPEC_TYPE_USABILITY_AND_HUMANITY',3); 
// define('TL_REQ_SPEC_TYPE_PERFORMANCE',4);
// define('TL_REQ_SPEC_TYPE_OPERATIONAL_AND_ENVIRONMENTAL',5);
// define('TL_REQ_SPEC_TYPE_MAINTAINABILITY_AND_SUPPORT',6);
// define('TL_REQ_SPEC_TYPE_SECURITY',7);
// define('TL_REQ_SPEC_TYPE_CULTURAL_AND_POLITICAL',8);
// define('TL_REQ_SPEC_TYPE_LEGAL',9);

$tlCfg->req_spec_cfg = new stdClass();
$tlCfg->req_spec_cfg->type_labels = array(
		TL_REQ_SPEC_TYPE_SECTION => 'req_spec_type_section', 
		TL_REQ_SPEC_TYPE_USER_REQ_SPEC => 'req_spec_type_user_req_spec',
		TL_REQ_SPEC_TYPE_SYSTEM_REQ_SPEC => 'req_spec_type_system_req_spec');


/**
 * @deprecated 1.9 
 * @todo havlatm: replace by $tlCfg->req_cfg->type_labels 
 **/
define('TL_REQ_TYPE_1', 'V');
define('TL_REQ_TYPE_2', 'N');
define('NON_TESTABLE_REQ', 'n');
define('VALID_REQ', 'v');


// --------------------------------------------------------------------------------------
/* [MISC] */

/** 
 * Review types - user can define type for his review comment (disabled by default)
 * @since TestLink version 2.0 
 **/
$tlCfg->review_types = array(1 => 'undefined',
	                         2 => 'typo', 
	                         3 => 'recommendation', 
	                         4 => 'question', 
	                         5 => 'unclear', 
	                         6 => 'major problem'
);

/**
 * Top Menu definition
 *
 * structure
 * - label: label to display, will be localized
 * - url: resource to access when users click on menu item
 * - right: user right need to display menu item.
 *        null => no right check needed
 * - condition: specific condition = ['','TestPlanAvailable','ReqMgmtEnabled']
 * - shortcut: keyboard HTML shortcut
 * - target: window/frame name (mainframe in the most of cases)
 * - addTProject: need to add test project ID on on url call
 * - addTPlan: need to add test plan ID on on url call
  * @since TestLink version 1.9 
 */
$tlCfg->guiTopMenu[1] = array(
		'label' => 'home',
		'url' => 'index.php',
		'right' => null,
		'condition'=>'',
		'shortcut'=>'h',
		'target'=>'_parent',
		'addTProject' => true,
		'addTPlan' => true		
);

$tlCfg->guiTopMenu[2] = array(
		'label' => 'title_requirements',
		'url' => 'lib/general/frmWorkArea.php?feature=reqSpecMgmt',
		'right' => 'mgt_view_req',
		'condition'=>'ReqMgmtEnabled',
		'shortcut'=>'r',
		'target'=>'mainframe',
		'addTProject' => true,
		'addTPlan' => false		
); 

$tlCfg->guiTopMenu[3] = array(
		'label' => 'title_specification',
		'url' => 'lib/general/frmWorkArea.php?feature=editTc',
		'right' => 'mgt_view_tc',
		'condition'=>'',
		'shortcut'=>'t',
		'target'=>'mainframe',
		'addTProject' => true,
		'addTPlan' => false		
); 

$tlCfg->guiTopMenu[4] = array(
		'label' => 'title_execute',
		'url' => 'lib/general/frmWorkArea.php?feature=executeTest',
		'right' => 'testplan_execute',
		'condition'=>'TestPlanAvailable',
		'shortcut'=>'e',
		'target'=>'mainframe',
		'addTProject' => true,
		'addTPlan' => true		
); 

$tlCfg->guiTopMenu[5] = array(
		'label' => 'title_results',
		'url' => 'lib/general/frmWorkArea.php?feature=showMetrics',
		'right' => 'testplan_metrics',
		'condition'=>'TestPlanAvailable',
		'shortcut'=>'r',
		'target'=>'mainframe',
		'addTProject' => true,
		'addTPlan' => true		
); 

$tlCfg->guiTopMenu[6] = array(
		'label' => 'title_admin',
		'url' => 'lib/usermanagement/usersView.php',
		'right' => 'mgt_users',
		'condition'=>'',
		'shortcut'=>'u',
		'target'=>'mainframe',
		'addTProject' => true,
		'addTPlan' => false		
); 

$tlCfg->guiTopMenu[7] = array(
		'label' => 'title_events',
		'url' => 'lib/events/eventviewer.php',
		'right' => 'events_mgt',
		'condition'=>'',
		'shortcut'=>'v',
		'target'=>'mainframe',
		'addTProject' => false,
		'addTPlan' => false		
); 


define( 'PARTIAL_URL_TL_FILE_FORMATS_DOCUMENT',	'docs/tl-file-formats.pdf');


// Configure Charts dimension
$tlCfg->results['charts']['dimensions'] = 
	array('topLevelSuitesBarChart'  => array('chartTitle' => 'results_top_level_suites',
											 'XSize' => 900,'YSize' => 400,'beginX' => 40, 'beginY' => 100,
											 'legendXAngle' => 35 ),
	      'keywordBarChart'  => array('chartTitle' => 'results_by_keyword',
							 		  'XSize' => 900,'YSize' => 400,'beginX' => 40, 'beginY' => 100,
									  'legendXAngle' => 25 ),
	      'ownerBarChart'  => array('chartTitle' => 'results_by_tester',
							 		  'XSize' => 900,'YSize' => 400,'beginX' => 40, 'beginY' => 100,
									  'legendXAngle' => 35 ),
	      'overallPieChart'  => array('chartTitle' => 'results_by_tester',
							 		  'XSize' => 400,'YSize' => 400,'radius' => 150, 'legendX' => 10, 'legendY' => 15 ),
	      'platformPieChart'  => array('chartTitle' => 'results_by_tester',
							 		  'XSize' => 400,'YSize' => 400,'radius' => 150, 'legendX' => 10, 'legendY' => 15 )
	);							
	

// if you need to define new one, start on 20 please.
$tlCfg->testCaseStatus = array(	'draft' => 1, 'readyForReview' => 2, 
								'reviewInProgress' => 3, 'rework' => 4, 
 								'obsolete' => 5, 'future' => 6, 'final' => 7 );		
// ----- END ----------------------------------------------------------------------------
?>