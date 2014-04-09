#!/usr/bin/php
<?php 
/*-----------------------------------------------------
// ООО "МИКО" // 2014-03-23 
// v.1.0 // Logs
// Получение настроек с сервера Asterisk
-------------------------------------------------------
// Скрипт протестирован на Askozia v3
//   PHP v.4.4.9
// местоположение файла 
	/offload/rootfs/usr/www/cfe/wallboard/1c/get_full_log.php
// пример вызова файла	
	http://IP_Addres/cfe/wallboard/1c/get_full_log.php?command=get_file_list
	http://IP_Addres/cfe/wallboard/1c/get_full_log.php?command=get_file_log&logfilename=full
-------------------------------------------------------*/
require("guiconfig.inc");
// разбор и упаковка в xml
$xml_output = "<?xml version=\"1.0\"?>\n"; 

$disk = storage_service_is_active("astlogs");
$ast_log_dir = $disk['mountpoint']."/askoziapbx/astlogs/asterisk/";

$command  =  $_GET['command'];
if($command=='get_file_list'){
	$xml_output.= "<file-table>\n"; 
	// получение списка имен файлов логов
	foreach (glob($ast_log_dir."full*") as $filename) {
	    $log_name = urlencode(basename($filename));
		$xml_output.= "<log_name>$log_name</log_name>\n"; 
	    
	}
	$xml_output .= "</file-table>"; 
}elseif($command == 'get_file_log'){
	$xml_output.= "<log-table>\n"; 
	// получения конкретного лога
	$logfilename  =  $_GET['logfilename'];
	if(!isset($logfilename)){
		$logfilename = 'full';
	}
	$output 	= array();
	$tmp_str=exec("cat ".$ast_log_dir.$logfilename." | egrep 'UNREACHABLE|Reachable|REACHABLE'",$output);
	
	$atributs = "";
	foreach($output as $field){
		$chunks = spliti ("(\ NOTICE\[[0-9]*\]\ [a-z]*_[a-z]*[2]?.[a-z]*:\ *Peer\ )|(\ is\ now\ )|([.!\(])|(Last\ qualify)", $field, 5);	
		$stuct = "date='".urlencode($chunks[0])."' exten='".urlencode(str_replace("'", "", $chunks[1]))."' status='".urlencode($chunks[2])."'";
		// конец строки
		$xml_output.= "<log-row $stuct />\n"; 
	}
	$xml_output .= "</log-table>"; 
}	
echo "$xml_output";	
?>