<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2014-03-18 
// v.4.7 // 1С_CDR // 10000555 
// Передача истории звоноков в 1С 
-------------------------------------------------------
Скрипт протестирован на Askozia v3:
Asterisk 10.0.9
PHP 4.4.9
sqlite3 -version 3.7.0
AGI phpagi.php,v 2.14 2005/05/25 20:30:46
-------------------------------------------------------*/
require("phpagi.php");
require("guiconfig.inc");

function GetVarChannnel($agi, $_varName){
  $v = $agi->get_variable($_varName);
  if(!$v['result'] == 0){
    return $v['data'];
  }
  else{
    return "";
  }
} // GetVarChannnel($_agi, $_varName)

$agi = new AGI();

$EXTEN = GetVarChannnel($agi, "EXTEN");
if($EXTEN == "h"){
    // это особенность работы с Askozia, для избежания зацикливания
    // http://igorg.ru/2011/10/22/askozia-opyt-ispolzovaniya/
}else{

// 1.Формируем запрос и сохраняем результат выполнения во временный файл
$disk   = storage_service_is_active("astlogs");
$db = $disk['mountpoint']."/askoziapbx/astlogs/asterisk/master.db";

$chan       = GetVarChannnel($agi,'v1');
$date1      = GetVarChannnel($agi,'v2');
$date2      = GetVarChannnel($agi,'v3');
$numbers    = explode("-",GetVarChannnel($agi,'v4'));

$uid = preg_replace('%[^A-Za-z0-9]%', '', uniqid(""));
$name_tmp_cdr = 'cdr_'.$uid; // __vs_cdr / '.$name_tmp_cdr.'
$name_tmp_cel = 'cel_'.$uid; // __vs_cel / '.$name_tmp_cel.'

$zapros='.separator @.@
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

$temp = tempnam('/tmp', 'miko_');
$res_file = fopen($temp, 'w');
fwrite($res_file, $zapros);

$output 	= array();
$tmp_str = exec("sqlite3 -init $temp  $db .exit",$output);
fclose($res_file);
// необходимо отправлять данные пачками по 10 шт.
$result = ""; $ch = 1;
// обходим файл построчно
foreach($output as $_data){
    // набор символов - разделитель строк
    if(! $result=="") $result = $result.".....";

    $_data = str_replace(" ", '\ ', $_data);
    $_data = rtrim($_data);

    $result = $result.$_data;
    // если необходимо отправляем данные порциями
    if($ch == 6){
        // отправляем данные в 1С, обнуляем буфер
        $agi->exec("UserEvent", "FromCDR,Channel:$chan,Date:$date1,Lines:$result");
        $result = ""; $ch = 1;
    }
    $ch = $ch + 1;
}

// проверяем, есть ли остаток данных для отправки
if(!$result == ""){
    $agi->exec("UserEvent", "FromCDR,Channel:$chan,Date:$date1,Lines:$result");
}

// завершающее событие пакета, оповещает 1С, что следует обновить историю
$agi->exec("UserEvent", "Refresh1CHistory,Channel:$chan,Date:$date1");
}

// отклюаем запись CDR для приложения
$agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
unlink($temp);
?>​