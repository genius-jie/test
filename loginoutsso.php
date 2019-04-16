<?php
require_once('lib/functions/configCheck.php');
checkConfiguration();
require_once('config.inc.php');
require_once('doAuthorize.php');
require_once('common.php');
doSessionStart();
$query_str = $_SERVER['QUERY_STRING'];
parse_str($query_str);
$data = array('subToken' => $subToken,'clientNo' => '1');
$result =doPost("http://mt-opensso-vip/opensso/auth/validateSubToken",$data);
$objss = json_decode($result, true);

$objson3= $objss['data']['subTokenObj']['ssoToken'];
$data1 = array("clientNo"=>"1","ssoToken"=>$objson3);
$result =doPost("http://mt-opensso-vip/opensso/auth/ssoLogout",$data1);
redirect("http://sitetest.tf56.com/openssoWeb/opensso/login?clientNo=1&redirectUrl=http://localhost/testlink-1.9.18/loginsso.php");
// redirect("http://sso.tf56.com/openssoWeb/opensso/login?clientNo=1&redirectUrl=http://localhost/testlink-1.9.18/loginsso.php");
//post模式访问http接口
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
