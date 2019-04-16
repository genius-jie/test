<?php 
function exportExcel($list,$filename,$indexKey,$startRow=1,$excel2007=false){
	require_once dirname(__FILE__).'\\'.'..\\..\\third_party\\Classes\\PHPExcel.php';
	require_once dirname(__FILE__).'\\'.'..\\..\\third_party\\Classes\\PHPExcel\\Writer\\Excel2007.php';
	if(empty($filename)) $filename = time();
	if( !is_array($indexKey)) return false;

	$header_arr = array('A','B','C','D','E','F','G','H','I','J','K','L','M', 'N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
	$objPHPExcel = new PHPExcel();
	
// 	$objPHPExcel->getProperties()->setCreator("qqqq")
// 	->setLastModifiedBy("wwww")
// 	->setTitle("eeee")
// 	->setSubject("rrrr")
// 	->setDescription("tttt")
// 	->setKeywords("yyyy")
// 	->setCategory("uuuu");

	if($excel2007){
		$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
		$filename = $filename.'.xlsx';
	}else{
		$objWriter = new PHPExcel_Writer_Excel5($objPHPExcel);
		$filename = $filename.'.xls';
	}

	$objActSheet = $objPHPExcel->getActiveSheet();
	$stRow=2;
$value=$list[1]['Function_point'];
foreach ($list as $key => $row) {//将数据中的目录名一样的文件夹合并,先取消掉后面相同的文件名称
		if ($key>1){
			if($row['Function_point']==$value){
				$list[$key]['Function_point']='';
				$stRow++;
			}
			else{
				$value=$row['Function_point'];
				$stRow++;
			}
		}
	}
	$endrow=1;

	foreach ($list as $row) {
		foreach ($indexKey as $key => $value){
			if(gettype($row[$value])=="array"){
					$temp=$startRow;
					$i=0;
					for ($i;$i<count($row[$value]);$i++){
						$objActSheet->setCellValue($header_arr[$key].$startRow,$row[$value][$i]);
						$startRow++;
					}
					$endrow=$startRow-1;
					$startRow=$temp;
			}else{
			$objActSheet->setCellValue($header_arr[$key].$startRow,$row[$value]);
			}
		}
		$endrow=$startRow>=$endrow?$startRow:$endrow;

		for($colnum_index=1;$colnum_index<count($indexKey)+1;$colnum_index++){
			if(strlen($objActSheet->getCell($header_arr[$colnum_index].$endrow)->getValue())=='0'&&$endrow!=1){
				$objPHPExcel->getActiveSheet()->mergeCells($header_arr[$colnum_index].$startRow.":".$header_arr[$colnum_index].$endrow);
			}ELSE{			
				
			}
		}
			$startRow=$endrow+1;
		}
		
		
		
		$value=$objActSheet->getCell('A2')->getValue();
		$str=2;
		for($i=3;$i<= $objActSheet->getHighestRow();$i++){
			$value_temp=$objActSheet->getCell('A'.$i)->getValue();
			if(strlen($value_temp)!=0){
				if($i==$str+1){
					$str=$i;
				}
				else{
					$objPHPExcel->getActiveSheet()->mergeCells('A'.$str.":".'A'.($i-1));
					$str=$i;
				}
			}else{
				if($i==$objActSheet->getHighestRow()){
					$objPHPExcel->getActiveSheet()->mergeCells('A'.$str.":".'A'.($i));
				}
			}
		}
		
		
		

	
	$objPHPExcel->getDefaultStyle()->getFont()->setName("微软雅黑");
	$objPHPExcel->getDefaultStyle()->getFont()->setSize(10);
	$objPHPExcel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);//ˮƽ����
	$objPHPExcel->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_JUSTIFY);//�����
	$objPHPExcel->getDefaultStyle()->getAlignment()->setWrapText(true);

 $length=count($indexKey);
for ($i = 0; $i < $length; $i++){ 
	$objPHPExcel -> getActiveSheet() -> getColumnDimension($header_arr[$i]) -> setWidth(20);
}
$objPHPExcel->getActiveSheet()->getStyle("A1:".$header_arr[count($indexKey)-1]."1")->getFont()->setSize(12)->setBold(true); //标题字体
	


	ob_end_clean();
	ob_start();
	header('Cache-Control: must-revalidate');
	header('Content-Description: File Transfer');
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
	header("Content-Type:application/force-download");
	header("Content-Type:application/vnd.ms-execl ;charset=utf-8");
	header("Content-Type:application/octet-stream");
	header("Content-Type:application/download ");;
	header('Content-Disposition:attachment;filename='.$filename.'');
	header("Content-Transfer-Encoding:BASE64;");
	$objWriter->save('php://output');
}

function getBorderStyle($color){
	$styleArray = array(
			'borders' => array(
					'outline' => array(
							'style' => PHPExcel_Style_Border::BORDER_THICK,
							'color' => array('rgb' => $color),
					),
			),
	);
	return $styleArray;
}
?>
