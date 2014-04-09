#!/usr/bin/php
<?php 
/*-----------------------------------------------------
// ��� "����" // 2014-03-23 
// v.1.0 // Logs
// ��������� �������� � ������� Asterisk
-------------------------------------------------------
// ������ ������������� �� Askozia v3
//   PHP v.4.4.9
// �������������� ����� 
	/offload/rootfs/usr/www/cfe/wallboard/1c/get_full_log.php
// ������ ������ �����	
	http://IP_Addres/cfe/wallboard/1c/get_full_log.php?command=get_file_list
	http://IP_Addres/cfe/wallboard/1c/get_full_log.php?command=get_file_log&logfilename=full
-------------------------------------------------------*/
require("guiconfig.inc");
// ������ � �������� � xml
$xml_output = "<?xml version=\"1.0\"?>\n"; 

$disk = storage_service_is_active("astlogs");
$ast_log_dir = $disk['mountpoint']."/askoziapbx/astlogs/asterisk/";

$command  =  $_GET['command'];
if($command=='get_file_list'){
	$xml_output.= "<file-table>\n"; 
	// ��������� ������ ���� ������ �����
	foreach (glob($ast_log_dir."full*") as $filename) {
	    $log_name = urlencode(basename($filename));
		$xml_output.= "<log_name>$log_name</log_name>\n"; 
	    
	}
	$xml_output .= "</file-table>"; 
}elseif($command == 'get_file_log'){
	$xml_output.= "<log-table>\n"; 
	// ��������� ����������� ����
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
		// ����� ������
		$xml_output.= "<log-row $stuct />\n"; 
	}
	$xml_output .= "</log-table>"; 
}	
echo "$xml_output";	
?>