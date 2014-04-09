#!/usr/bin/php
<?php 
/*-----------------------------------------------------
// ��� "����" // 2013-10-30 
// v.3.2 // CDR - �������������
// ��������� �������� � ������� Asterisk
-------------------------------------------------------
// ������ ������������� �� Askozia v2:
//   PHP v.4.4.9
// �������������� �����
//   /offload/rootfs/usr/www/cfe/wallboard/1c/cdr.php
// ������ ������ �������:
//   http://HOST:23600/cfe/wallboard/1c/cdr.php?limit=XXX&offset=YYY 
// 
//	 HOST - ����� ������� ���.
//	 ���  - ���������� ������� (������ ���� ������ 500)
//	 YYY  - �������� �������.
-------------------------------------------------------*/
require("guiconfig.inc");
// ���������� �������
$limit  =  $_GET['limit'];
// �������� ��� ������� ��������� �������  
$offset =  $_GET['offset']; 

if ((ctype_digit($limit)) && (ctype_digit($offset))) {
	if ($limit > 500) {
		echo ("<pre>The variable 'limit' should be less than 500</pre>");
	}else {
		$disk = storage_service_is_active("astlogs");
		$astlogdir = $disk['mountpoint']."/askoziapbx/astlogs/asterisk/master.db";
		
		$output 	= array();
		$tmp_str=exec("sqlite3  -separator '@.@' -line " .  $astdb . " 'select * from cdr limit " . $limit . " offset " . $offset ."'",$output);
		// ������ � �������� � xml
		$xml_output = "<?xml version=\"1.0\"?>\n"; 
		$xml_output.= "<cdr-table>\n"; 
		
		$atributs = "";
		foreach($output as $field){
			$stuct = trim($field);
			if($stuct == ""){
				// ����� ������
				$xml_output.= "<cdr-row $atributs />\n"; 
				// �������� �����
				$atributs = "";
			}else{
				// ��������� ���� ������
				$arr_key_val = explode('=',$stuct);
				if(count($arr_key_val)==2){
					$key = trim($arr_key_val[0]);
					$val = urlencode(trim($arr_key_val[1]) );
					// print_r("key = $key; val=$val\n");
					$atributs.=$key."=\"".$val."\" ";
				}
			}// �������� �������
		}
		if($atributs != ""){
			$xml_output.= "<cdr-row $atributs />\n"; 
			// �������� �����
			$atributs = "";
		}
		$xml_output .= "</cdr-table>"; 
		echo "$xml_output";	
	}
}else {
	echo ("<pre>Variable 'limit' and 'offset' must be numeric.</pre>");
}

?>