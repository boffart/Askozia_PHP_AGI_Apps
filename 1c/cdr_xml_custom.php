#!/usr/bin/php
<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2013-10-30 
// v.1.1 // cdr_xml_custom 
// Получение истории звонков по номерам за период
-------------------------------------------------------
// Askozia  - v2.2.6:
// PHP      - v.4.4.9
// sqlite3  - version 3.7.0
-------------------------------------------------------
// местоположение файла
//   /offload/rootfs/usr/www/cfe/wallboard/1c/cdr_xml_custom.php
// пример вызова скрипта:
//   http://HOST:23600/cfe/wallboard/1c/cdr_xml_custom.php?date1=2013-09-09&date2=2013-09-30&numbers=112-102-104
// 
//	 HOST    - адрес сервера АТС.
//	 date1   - начало периода
//	 date1   - конец периода
//   numbers - номера телефонов, разделенные символом "-"
-------------------------------------------------------*/
require("guiconfig.inc");

$date1   = $_GET['date1'];
$date2   = $_GET['date2']; 
$numbers = $_GET['numbers']; // explode("-",GetVarChannnel($agi,'v4'));

// 1. Получим путь к хранилищу логов
$disk   = storage_service_is_active("astlogs");
$db = $disk['mountpoint']."/askoziapbx/astlogs/asterisk/master.db";

if(is_file($db)){
	// проверим все ли символы в параметрах являются строковыми
	if(ctype_print($date1)&&ctype_print($date2)&&ctype_print($numbers)){
		$numbers = explode("-",$numbers);
		
		$zapros="
		SELECT DISTINCT
		      a.answer AS answer,
		      a.src AS src,
		      a.dst AS dst,
		      a.channel AS channel,
		      a.dstchannel AS dstchannel,
		      a.billsec AS billsec,
		      a.disposition AS disposition,
		      a.uniqueid AS uniqueid,
		      a.recordingfile AS recordingfile,
		      a.peer AS peer,
		      a.lastapp AS lastapp,
		      a.linkedid AS linkedid
		FROM 
		         (SELECT * FROM cdr LEFT JOIN cel ON (cdr.uniqueid = cel.linkedid  OR cdr.uniqueid = cel.uniqueid)
		          WHERE answer BETWEEN \"$date1\" AND \"$date2\") 
		        AS a
		        WHERE ";
		
		$rowCount = count($numbers);
		for($i=0; $i < $rowCount; $i++) {
		  $num = $numbers[$i];
		  if($num == ""){
		        continue;
		  }
		  if(!$i == 0)
		        $zapros=$zapros." OR ";
		
		  $zapros=$zapros."(( a.lastapp=\"Transferred Call\" 
		                                   AND a.lastdata like \"%/$num@%\")
		                    OR ((a.lastapp=\"Dial\" OR a.lastapp=\"Queue\")
		                         AND (a.channel like \"%/$num-%\"
		                              OR a.dstchannel like \"%/$num-%\"
		                              OR a.dstchannel like \"%/$num@%\"
		                              OR a.src=\"$num\"
		                              OR a.dst=\"$num\")
		                        )
		                    OR (a.peer LIKE \"%/$num-%\")
		                    OR (a.peer LIKE \"%/$num@%\")
		    )";  
		} // Условие запроса

		$output 	= array();
		$tmp_str = exec("sqlite3 -line $db  '$zapros'",$output);
		// разбор и упаковка в xml
		$xml_output = "<?xml version=\"1.0\"?>\n"; 
		$xml_output.= "<cdr-custom-table>\n"; 
			
		$atributs = "";
		foreach($output as $field){
			$stuct = trim($field);
			if($stuct == ""){
				// конец строки
				$xml_output.= "<cdr-row $atributs />\n"; 
				// обнуляем буфер
				$atributs = "";
			}else{
				// очередное поле строки
				$arr_key_val = explode('=',$stuct);
				if(count($arr_key_val)==2){
					$key = trim($arr_key_val[0]);
					$val = urlencode(trim($arr_key_val[1]) );
					// print_r("key = $key; val=$val\n");
					$atributs.=$key."=\"".$val."\" ";
				}
			}// проверка условия
		}
		if($atributs != ""){
			$xml_output.= "<cdr-row $atributs />\n"; 
			// обнуляем буфер
			$atributs = "";
		}
		$xml_output .= "</cdr-custom-table>"; 
		echo "$xml_output";	
	}else{
		// не верные параметры
		echo('Not transferred to the correct settings.');	
	}	
}else{
	// не найдена база данных
	echo('Database not found.');	
}
?>