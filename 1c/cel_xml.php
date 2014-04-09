#!/usr/bin/php
<?php 
/*-----------------------------------------------------
// ООО "МИКО" // 2014-01-02 
// v.3.4 // CEL - синхронизация 
// Получение настроек с сервера Asterisk
-------------------------------------------------------
// Скрипт протестирован на Askozia v2:
//   PHP v.4.4.9
// местоположение файла
//   /offload/rootfs/usr/www/cfe/wallboard/1c/cel.php
// пример вызова скрипта:
//   http://HOST:23600/cfe/wallboard/1c/cel_xml.php?limit=XXX&offset=YYY  
// 
//	 HOST - адрес сервера АТС.
//	 ХХХ  - количество пакетов (должно быть меньше 500)
//	 YYY  - смещение выборки.
-------------------------------------------------------*/
require("guiconfig.inc");
// Количество записей
$limit  =  $_GET['limit'];
// Смещение для выборки следующих пакетов  
$offset =  $_GET['offset']; 

if ((ctype_digit($limit)) && (ctype_digit($offset))) {
	if ($limit > 500) {
		echo ("<pre>The variable 'limit' should be less than 500</pre>");
	}else {
		$disk = storage_service_is_active("astlogs");
		$astdb = $disk['mountpoint'] . "/askoziapbx/astlogs/asterisk/master.db";
		
		$output 	= array();
		$tmp_str=exec("sqlite3  -separator '@.@' -line " .  $astdb . " 'select * from cel limit " . $limit . " offset " . $offset ."'",$output);
		// разбор и упаковка в xml
		$xml_output = "<?xml version=\"1.0\"?>\n"; 
		$xml_output.= "<cel-table>\n"; 
		
		$atributs = "";
		foreach($output as $field){
			$stuct = trim($field);
			if($stuct == ""){
				// конец строки
				$xml_output.= "<cel-row $atributs />\n"; 
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
			$xml_output.= "<cel-row $atributs />\n"; 
			// обнуляем буфер
			$atributs = "";
		}
		$xml_output .= "</cel-table>"; 
		echo "$xml_output";		
	}
}else{
	echo ("<pre>Variable 'limit' and 'offset' must be numeric.</pre>");
}
?>