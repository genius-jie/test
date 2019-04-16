<?php  
require_once 'TestCaseExport.php';
// $content = <<<XML
// XML;







// $test = new SimpleXMLElement($content);
// $arr[]=array( 'Function_point'=>'模块',
// 		'Test_Case_ID'=>'测试用例编号',
// 		'Test_case_name'=>'测试用例名称',
// 		'abstract'=>'摘要',
// 		'Precondition'=>'前置条件',
// 		'testing_procedure'=>'测试步骤',
// 		'Expected_results'=>'预期结果',
// 		'priority'=>'优先级');
// 	$Function_point='';
// 	fff($test,$Function_point);
// 	print_r($arr) ;
// $arr=oneTestCaseExport($test,$arr);
// $arr=TestsuiteExport($test,$arr);
// $index = array( 'Function_point','Test_Case_ID','Test_case_name','abstract','Precondition','testing_procedure','Expected_results','priority');
// exportExcel($arr,'111',$index);

	
//========================方法
function fff($test,$Function_point){
	//这个目录下获取目录和用例
	$temptt = $test -> testsuite;
	$temptest= $test -> testcase;
	//判断是不是开始的dirname ,如果不是就连接,是就初始化;
	global  $arr;
	if($Function_point!=''&&$Function_point!=' '){
		$tempv= (string)$test->attributes()->name;
		$Function_point =  $Function_point."/".$tempv;
	}else{
		$Function_point = str_replace("<p>","",str_replace("</p>","" ,(string)$test->attributes()->name));
	}
	//如果不是空,就将用例添加进数组
	if ($temptest!=""){
		oneTestCaseExport($test,$Function_point);
	}elseif($temptest==""&&$temptt==''){
		$arr[]=array('Function_point'=>$Function_point,
				'Test_Case_ID'=>"",
				'Test_case_name'=>"",
				'abstract'=>"",
				'Precondition'=>"",
				'testing_procedure'=>"",
				'Expected_results'=>"",
				'priority'=>"");
		}
	
	//判断目录是否为空,如果有的话就递归
	if($temptt!=''){
		$tempfp=$Function_point;
		foreach ($test -> testsuite as $testsuite ){
			 fff($testsuite,$Function_point);
			 $Function_point =$tempfp;
		}
	}else{
	}	
}
	


			//========================方法
function oneTestCaseExport($test,$Function_point){
				global  $arr;
				foreach($test->testcase as $b ) {
					$Test_Case_ID =str_replace("<p>","",str_replace("</p>","" ,(string)$b->externalid));
					$Test_case_name = str_replace("<p>","",str_replace("</p>","" ,(string)$b->attributes()->name));
					$abstract = str_replace("<p>","",str_replace("</p>","" ,(string)$b->summary));
					$Precondition = str_replace("<p>","",str_replace("</p>","" ,(string)$b->preconditions));
					if($b->steps!=''){
						foreach($b->steps->step as $a ) {
							if($a->actions!=""){
							$testing_procedure[]=str_replace("<p>","",str_replace("</p>","" ,(string)$a->actions));
							$Expected_results[]=str_replace("<p>","",str_replace("</p>","" ,(string)$a->expectedresults));
						}}}
					else{
						$testing_procedure=array();
						$Expected_results=array();
						}
					
					$priority = str_replace("<p>","",str_replace("</p>","" ,(string)$b->importance));
					switch ($priority)
					{
						case "1":
							$priority="低";
							break;
						case "2":
							$priority="中";
							break;
						case "3":
							$priority="高";
					}
					$arrn=array('Function_point'=>$Function_point,
							'Test_Case_ID'=>$Test_Case_ID,
							'Test_case_name'=>$Test_case_name,
							'abstract'=>$abstract,
							'Precondition'=>$Precondition,
							'testing_procedure'=>$testing_procedure,
							'Expected_results'=>$Expected_results,
							'priority'=>$priority);
					foreach ($arrn as $key=>$value){
						if(is_array( $value )){
								$i=0;
							foreach ($value as $value_1){
								$value_1=str_replace("&nbsp;"," ",$value_1);
								$value_1=str_replace("<br />","",$value_1);
								$value_1=str_replace("&ldquo;",'"',$value_1);
								$value_1=str_replace("&rdquo;",'"',$value_1);
								$value_1=str_replace("&quot;","'",$value_1);
								$value_1=str_replace("&amp;",'&',$value_1);
								$value_1=str_replace("&#39;","'",$value_1);
								$value_1=str_replace("&lt;","<",$value_1);
								$value_1=str_replace("&gt;",">",$value_1);
								$arrn[$key][$i]=strip_tags($value_1);
								$i++;
							}
						}else{
						$value=strip_tags($value);
						$value=str_replace("&nbsp;"," ",$value);
						$value=str_replace("<br />","",$value);
						$value=str_replace("&ldquo;",'"',$value);
						$value=str_replace("&rdquo;",'"',$value);
						$value=str_replace("&quot;","'",$value);
						$value=str_replace("&amp;",'&',$value);
						$value=str_replace("&#39;","'",$value);
						$value=str_replace("&lt;","<",$value);
						$value=str_replace("&gt;",">",$value);
						$arrn[$key]=$value;}
					}
					$arrn['Function_point']=str_replace("&"," ",$arrn['Function_point']);
					$arr[]=$arrn;
					array_splice($Expected_results, 0, count($Expected_results));//清空数据
					array_splice($testing_procedure, 0, count($testing_procedure));
				}
				
				return $arr;
			}		
			
		
				
