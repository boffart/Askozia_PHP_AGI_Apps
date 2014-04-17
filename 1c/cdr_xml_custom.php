#!/usr/bin/php
<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2014-04-17 
// v.2.1 // cdr_xml_custom 
// Получение истории звонков по номерам за период
-------------------------------------------------------
// Askozia  - v2.2.6:
// PHP      - v.4.4.9
// sqlite3  - version 3.7.0
-------------------------------------------------------
// местоположение файла
//   /offload/rootfs/usr/www/cfe/wallboard/1c/cdr_xml_custom.php
// пример вызова скрипта:
//   http://HOST:23600/cfe/wallboard/1c/cdr_xml_custom.php?date1=2013-09-09&date2=2013-09-10&numbers=112-102-104
// 
//	 date1   - начало периода
//	 date1   - конец периода
//   numbers - номера телефонов, разделенные символом "-"
-------------------------------------------------------*/
require("guiconfig.inc");

$date1   = $_GET['date1'];
$date2   = $_GET['date2']; 
$numbers = $_GET['numbers'];

// 1. Получим путь к хранилищу логов
$disk   = storage_service_is_active("astlogs");
$db = $disk['mountpoint']."/askoziapbx/astlogs/asterisk/master.db";

if(is_file($db)){
	// проверим все ли символы в параметрах являются строковыми
	if(ctype_print($date1)&&ctype_print($date2)&&ctype_print($numbers)){
		$numbers = explode("-",$numbers);
		$uid = preg_replace('%[^A-Za-z0-9]%', '', uniqid(""));
		$name_tmp_cdr = 'cdr_'.$uid; 
		$name_tmp_cel = 'cel_'.$uid;
		
		$zapros='.mode line
		DROP TABLE IF EXISTS "'.$name_tmp_cdr.'";
		CREATE TABLE "'.$name_tmp_cdr.'"	( "answer" TEXT, "src" TEXT, "dst" TEXT, "channel" TEXT, "dstchannel"  TEXT, "billsec" TEXT, "disposition" TEXT, "uniqueid" TEXT, "lastapp" TEXT, "linkedid" TEXT,"recordingfile" TEXT, "lastdata" TEXT);
		INSERT INTO "'.$name_tmp_cdr.'" ( "answer", "billsec", "channel", "disposition", "dst", "dstchannel", "lastapp", "linkedid", "recordingfile", "src", "uniqueid","lastdata") 
		SELECT
			"start", 
			"billsec", 
			"channel", 
			"disposition",
			"dst",
			"dstchannel",
			"lastapp",
			"linkedid",
			"recordingfile",
			"src",
			"uniqueid",
			"lastdata"
		FROM "cdr" WHERE "cdr"."start" BETWEEN "'.$date1.'" AND "'.$date2.'";
		DROP TABLE IF EXISTS "'.$name_tmp_cel.'";
		CREATE TABLE "'.$name_tmp_cel.'"	( "eventtime" TEXT, "eventtype" TEXT, "uniqueid" TEXT, "peer" TEXT,"linkedid"  TEXT );
		INSERT INTO "'.$name_tmp_cel.'" ("eventtime","eventtype","linkedid","peer","uniqueid") SELECT
			"eventtime",
			"eventtype",
			"linkedid",
			"peer",
			"uniqueid"
		FROM "cel"
		WHERE eventtime BETWEEN "'.$date1.'" AND "'.$date2.'" AND eventtype = "BRIDGE_START";
		SELECT DISTINCT 
			"'.$name_tmp_cdr.'"."answer",
			"'.$name_tmp_cdr.'"."src",
			"'.$name_tmp_cdr.'"."dst",
			"'.$name_tmp_cdr.'"."channel",
			"'.$name_tmp_cdr.'"."dstchannel",
			"'.$name_tmp_cdr.'"."billsec",
			"'.$name_tmp_cdr.'"."disposition",
			"'.$name_tmp_cdr.'"."uniqueid",
			"'.$name_tmp_cdr.'"."recordingfile",
			"'.$name_tmp_cel.'"."peer",
			"'.$name_tmp_cdr.'"."lastapp",
			"'.$name_tmp_cdr.'"."linkedid"
		FROM "'.$name_tmp_cdr.'" 
		LEFT JOIN "'.$name_tmp_cel.'" ON ("'.$name_tmp_cdr.'"."uniqueid" = "'.$name_tmp_cel.'"."linkedid"  OR "'.$name_tmp_cdr.'"."uniqueid" = "'.$name_tmp_cel.'"."uniqueid")
		WHERE 
		';
		
		$rowCount = count($numbers);
		for($i=0; $i < $rowCount; $i++) {
		  $num = $numbers[$i];
		  if($num == ""){
		        continue;
		  }
		  if(!$i == 0)
		        $zapros=$zapros." OR ";
		
		  $zapros=$zapros.'(( lastapp="Transferred Call" AND lastdata LIKE "%/'.$num.'@%")
		                    OR ((lastapp="Dial"OR lastapp="Queue")
		                         AND (channel LIKE "%/'.$num.'-%"
		                              OR dstchannel LIKE "%/'.$num.'-%"
		                              OR dstchannel LIKE "%/'.$num.'@%"
		                              OR src="'.$num.'"
		                              OR dst="'.$num.'")
		                        )
		                    OR (peer LIKE "%/'.$num.'-%")
		                    OR (peer LIKE "%/'.$num.'@%"))';  
		}
		
		$zapros=$zapros.';
		DROP TABLE IF EXISTS "'.$name_tmp_cdr.'";
		DROP TABLE IF EXISTS "'.$name_tmp_cel.'";
		';
		// пишем запрос во временный файл
		$temp = tempnam('/tmp', 'miko_');
		$res_file = fopen($temp, 'w');
		fwrite($res_file, $zapros);
		
		$output 	= array();
		$tmp_str = exec("sqlite3 -line -init $temp  $db .exit",$output);
		// закрываем файл / удаляем файл
		fclose($res_file);
		unlink($temp);
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