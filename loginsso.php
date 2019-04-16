<?php
require_once('lib/functions/configCheck.php');
checkConfiguration();
require_once('config.inc.php');
require_once('common.php');
require_once('doAuthorize.php');
require_once('lib/functions/users.inc.php');
doSessionStart();
/* http://localhost/aaa/index.php?p=222&q=333
结果：
$_SERVER['QUERY_STRING'] = "p=222&q=333";
$_SERVER["QUERY_STRING"]  获取查询 语句，实例中可知，获取的是?后面的值
$_SERVER["REQUEST_URI"]   获取 http://localhost 后面的值，包括/
$_SERVER["SCRIPT_NAME"]   获取当前脚本的路径，如：index.php
$_SERVER["PHP_SELF"]      当前正在执行脚本的文件名*/
$query_str = $_SERVER['QUERY_STRING'];
parse_str($query_str);
$data = array('subToken' => $subToken,'clientNo' => '1');
$result =doPost("http://mt-opensso-vip/opensso/auth/validateSubToken",$data);
$objss = json_decode($result, true);
$objson3= $objss['data']['subTokenObj']['userSession']['userId'];
$objson4= $objss['data']['subTokenObj']['userSession']['userName'];
$mysqli = new mysqli("localhost", "root", "", "testlink");
$mysqli->query("SET NAMES UTF8");
$result=$mysqli->query("select * from users where users.login='".$objson3."';");
if($result->num_rows==0){
	doDBConnect($db);
	$args1 = init_args1();
	$highlight->create_user = 1;
	$args1->login=$objson3;
	$args1->emailAddress=$objson3.'@etransfar.com';
	$args1->firstName=$objson4;
	$args1->lastName=$objson4;
	$args1->rights_id='9';
	$args1->user_is_active='1';
	$args1->locale='zh_CN';
	$gui->op = doCreate($db,$args1);
}
$_SESSION['currentusername'] = $objson3;

$templateCfg = templateConfiguration();
$doRenderLoginScreen = false;
$doAuthPostProcess = false;
doDBConnect($db, database::ONERROREXIT);
$args = init_args();
$gui = init_gui($db,$args);
// if these checks fail => we will redirect to login screen with some message
doBlockingChecks($db,$gui);
doSessionStart(true);
		$options = array('doSessionExistsCheck' => ($args->action=='doLogin'));
		$user=$_SESSION['currentusername'];
		$op = doAuthorize1($db,$user,$options);
		$doAuthPostProcess = true;
		$gui->draw = true;
if( $doAuthPostProcess )
{
	list($doRenderLoginScreen,$gui->note) = authorizePostProcessing($args,$op);
}

function init_args1()
{
	$_REQUEST=strings_stripSlashes($_REQUEST);
	$iParams = array("delete" => array(tlInputParameter::INT_N),
			"user" => array(tlInputParameter::INT_N),
			"user_id" => array(tlInputParameter::INT_N),
			"rights_id" => array(tlInputParameter::INT_N),
			"doAction" => array(tlInputParameter::STRING_N,0,30),
			"firstName" => array(tlInputParameter::STRING_N,0,50),
			"lastName" => array(tlInputParameter::STRING_N,0,50),
			"emailAddress" => array(tlInputParameter::STRING_N,0,100),
			"locale" => array(tlInputParameter::STRING_N,0,10),
			"login" => array(tlInputParameter::STRING_N,0,100),
			"password" => array(tlInputParameter::STRING_N,0,32),
			"authentication" => array(tlInputParameter::STRING_N,0,10),
			"user_is_active" => array(tlInputParameter::CB_BOOL));

	$args = new stdClass();
	R_PARAMS($iParams,$args);

	$args->user = $_SESSION['currentUser'];
	return $args;
}

/*
 function: doCreate

 args:

 returns: object with following members
 user: tlUser object
 status:
 template: will be used by viewer logic.
 null -> viewer logic will choose template
 other value -> viever logic will use this template.



 */
function doCreate(&$dbHandler,&$argsObj)
{
	$op = new stdClass();
	$op->user = new tlUser();
	initializeUserProperties($op->user,$argsObj);
	$op->status = $op->user->writeToDB($dbHandler);
	if($op->status >= tl::OK)
	{
		$statusOk = true;
		$op->template = null;
		logAuditEvent(TLS("audit_user_created",$op->user->login),"CREATE",$op->user->dbID,"users");
		$op->user_feedback = sprintf(lang_get('user_created'),$op->user->login);
	}


	if (!$statusOk)
	{
		$op->operation = 'create';
		$op->user_feedback = getUserErrorMessage($op->status);
	}

	return $op;
}


function initializeUserProperties(&$userObj,&$argsObj)
{
	if (!is_null($argsObj->login))
	{
		$userObj->login = $argsObj->login;
	}
	$userObj->emailAddress = $argsObj->emailAddress;

	// The Black List - Jon Bokenkamp
	$reddington = array('/','\\',':','*','?','<','>','|');
	$userObj->firstName = str_replace($reddington,'',$argsObj->firstName);
	$userObj->lastName = str_replace($reddington,'',$argsObj->lastName);

	$userObj->globalRoleID = $argsObj->rights_id;
	$userObj->locale = $argsObj->locale;
	$userObj->isActive = $argsObj->user_is_active;
	$userObj->authentication = trim($argsObj->authentication);
}

function checkRights(&$db,&$user)
{
	return $user->hasRight($db,'mgt_users');
}

/**
 *
 *
 */
function init_args()
{
	$pwdInputLen = config_get('loginPagePasswordMaxLenght');

	// 2010904 - eloff - Why is req and reqURI parameters to the login?
	$iParams = array("note" => array(tlInputParameter::STRING_N,0,255),
			"tl_login" => array(tlInputParameter::STRING_N,0,30),
			"tl_password" => array(tlInputParameter::STRING_N,0,$pwdInputLen),
			"req" => array(tlInputParameter::STRING_N,0,4000),
			"reqURI" => array(tlInputParameter::STRING_N,0,4000),
			"action" => array(tlInputParameter::STRING_N,0, 10),
			"destination" => array(tlInputParameter::STRING_N, 0, 255),
			"loginform_token" => array(tlInputParameter::STRING_N, 0, 255),
			"viewer" => array(tlInputParameter::STRING_N, 0, 3),
	);
	$pParams = R_PARAMS($iParams);

	$args = new stdClass();
	$args->note = $pParams['note'];
	$args->login = $pParams['tl_login'];
	$args->pwd = $pParams['tl_password'];
	$args->reqURI = urlencode($pParams['req']);
	$args->preqURI = urlencode($pParams['reqURI']);
	$args->destination = urldecode($pParams['destination']);
	$args->loginform_token = urldecode($pParams['loginform_token']);

	$args->viewer = $pParams['viewer'];

	$k2c = array('ajaxcheck' => 'do','ajaxlogin' => 'do');
	if (isset($k2c[$pParams['action']]))
	{
		$args->action = $pParams['action'];
	}
	else if (!is_null($args->login))
	{
		$args->action = 'doLogin';
	}
	else
	{
		$args->action = 'loginform';
	}

	return $args;
}

/**
 *
 *
 */
function init_gui(&$db,$args)
{
	$gui = new stdClass();
	$gui->viewer = $args->viewer;

	$secCfg = config_get('config_check_warning_frequence');
	$gui->securityNotes = '';
	if( (strcmp($secCfg, 'ALWAYS') == 0) ||
			(strcmp($secCfg, 'ONCE_FOR_SESSION') == 0 && !isset($_SESSION['getSecurityNotesDone'])) )
	{
		$_SESSION['getSecurityNotesDone'] = 1;
		$gui->securityNotes = getSecurityNotes($db);
	}

	$gui->authCfg = config_get('authentication');
	$gui->user_self_signup = config_get('user_self_signup');

	$gui->external_password_mgmt = false;
	$domain = $gui->authCfg['domain'];
	$mm = $gui->authCfg['method'];
	if( isset($domain[$mm]) )
	{
		$ac = $domain[$mm];
		$gui->external_password_mgmt = !$ac['allowPasswordManagement'];
	}

	$gui->login_disabled = (('LDAP' == $gui->authCfg['method']) && !checkForLDAPExtension()) ? 1 : 0;

	switch($args->note)
	{
		case 'expired':
			if(!isset($_SESSION))
			{
				session_start();
			}
			session_unset();
			session_destroy();
			$gui->note = lang_get('session_expired');
			$gui->reqURI = null;
			break;

		case 'first':
			$gui->note = lang_get('your_first_login');
			$gui->reqURI = null;
			break;

		case 'lost':
			$gui->note = lang_get('passwd_lost');
			$gui->reqURI = null;
			break;

		default:
			$gui->note = '';
			break;
	}
	$gui->reqURI = $args->reqURI ? $args->reqURI : $args->preqURI;
	$gui->destination = $args->destination;
	$gui->pwdInputMaxLenght = config_get('loginPagePasswordMaxLenght');

	return $gui;
}


/**
 * doBlockingChecks
 *
 * wrong Schema version will BLOCK ANY login action
 *
 * @param &$dbHandler DataBase Handler
 * @param &$guiObj some gui elements that will be used to give feedback
 *
 */
function doBlockingChecks(&$dbHandler,&$guiObj)
{
	$op = checkSchemaVersion($dbHandler);
	if( $op['status'] < tl::OK )
	{
		// Houston we have a problem
		// This check to kill session was added to avoid following situation
		// TestLink 1.9.5 installed
		// Install TestLink 1.9.6 in another folder, pointing to same OLD DB
		// you logged in TL 1.9.5 => session is created
		// you try to login to 1.9.6, you get the Update DB Schema message but
		// anyway because a LIVE AND VALID session you are allowed to login => BAD
		if(isset($op['kill_session']) && $op['kill_session'])
		{
			session_unset();
			session_destroy();
		}

		$guiObj->draw = false;
		$guiObj->note = $op['msg'];
		renderLoginScreen($guiObj);
		die();
	}
}


/**
 * renderLoginScreen
 * simple piece of code used to clean up code layout
 *
 * @global  $g_tlLogger
 * @param stdClassObject $guiObj
 */
function renderLoginScreen($guiObj)
{
	global $g_tlLogger;
	$templateCfg = templateConfiguration();
	$logPeriodToDelete = config_get('removeEventsOlderThan');
	$g_tlLogger->deleteEventsFor(null, strtotime("-{$logPeriodToDelete} days UTC"));

	$smarty = new TLSmarty();
	$smarty->assign('gui', $guiObj);

	$tpl = str_replace('.php','.tpl',basename($_SERVER['SCRIPT_NAME']));
	$tpl = 'login-model-marcobiedermann.tpl';
	$smarty->display($tpl);
}


/**
 *
 * @param stdClassObject $argsObj
 * @param hash $op
 */
function authorizePostProcessing($argsObj,$op)
{
	// 	echo $args->login;
	// 	print_r  ($argsObj);
	// 	print_r ($argsObj->login);
	$note = null;
	$renderLoginScreen = false;
	if($op['status'] == tl::OK)
	{
		// Login successful, redirect to destination
		logAuditEvent(TLS("audit_login_succeeded",$argsObj->login,
				$_SERVER['REMOTE_ADDR']),"LOGIN",$_SESSION['currentUser']->dbID,"users");

		if ($argsObj->action == 'ajaxlogin')
		{
			echo json_encode(array('success' => true));
		}
		else
		{
			// If destination param is set redirect to given page ...
			if (!empty($argsObj->destination) && preg_match("/linkto.php/", $argsObj->destination))
			{
				redirect($argsObj->destination);
			}
			else
			{
				// ... or show main page
				$_SESSION['viewer'] = $argsObj->viewer;
				redirect($_SESSION['basehref'] . "index.php?caller=login&viewer={$argsObj->viewer}" .
				($argsObj->preqURI ? "&reqURI=".urlencode($argsObj->preqURI) :""));

			}
			exit(); // hmm seems is useless
		}
	}
	else
	{
		$note = is_null($op['msg']) ? lang_get('bad_user_passwd') : $op['msg'];
		if($argsObj->action == 'ajaxlogin')
		{
			echo json_encode(array('success' => false,'reason' => $note));
		}
		else
		{
			$renderLoginScreen = true;
		}
	}

	return array($renderLoginScreen,$note);
}

/**
 *
 *
 */
function processAjaxCheck(&$dbHandler)
{
	// Send a json reply, include localized strings for use in js to display a login form.
	doSessionStart(true);
	echo json_encode(array('validSession' => checkSessionValid($dbHandler, false),
			'username_label' => lang_get('login_name'),
			'password_label' => lang_get('password'),
			'login_label' => lang_get('btn_login'),
			'timeout_info' => lang_get('timeout_info')));

}

function doPost($url, $data = null) {
	$data = json_encode($data);
	if (!$ch = curl_init()) throw new \Exception('curl初始化失败');

	// 设置选项
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	if ($data) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}

	$result = curl_exec($ch);
	if (curl_errno($ch)) throw new \Exception(curl_error($ch));

	curl_close($ch);

	return $result;
}

?>