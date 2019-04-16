<?php
//调用Reader
include('../../Excel/reader.php');
//创建 Reader
$data = new Spreadsheet_Excel_Reader();
//设置文本输出编码
$data->setOutputEncoding('UTF-8');
//读取Excel文件
$data->read("C:\Users\aaa\Desktop\发布流程\合同续签测试用例.xls");
echo $data->sheets[0]['numRows'];//获得行数
$xls_rows = $data->sheets[0]['cells'];//获得每一行的数据，合成数组
$xls_row_qty = sizeof($xls_rows);//获得数组的总数，即行数


$xmlFileHandle = fopen("C:\Users\aaa\Desktop\发布流程\合同续签测试用例.xml", 'w') or die("can't open file");
fwrite($xmlFileHandle,"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
fwrite($xmlFileHandle,"<testcases>\n");
fclose($xmlFileHandle);
/* for ($i = 1; $i <= $data->sheets[0]['numRows']; $i++) {
  //$data->sheets[0]['numCols']为Excel列数
  for ($j = 1; $j <= $data->sheets[0]['numCols']; $j++) {
   //显示每个单元格内容
   echo $data->sheets[0]['cells'][$i][$j];
  }
} */
create_xml_tcspec_from_xls("C:\Users\aaa\Desktop\发布流程\合同续签测试用例2.xls","C:\Users\aaa\Desktop\发布流程\合同续签测试用例.xml");
function nl2p($str)
{
	return str_replace('<p></p>', '', '<p>' . preg_replace('#\n|\r#', '</p>$0<p>', $str) . '</p>'); //MS
}
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

?>