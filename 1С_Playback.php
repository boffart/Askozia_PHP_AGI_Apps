<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2014-08-25 
// v.3.5 // 1С_Playback // 10000777 
// Поиск имени файла записи для воспроизведения в 1С 
-------------------------------------------------------
Скрипт протестирован на Askozia v3.2:
Asterisk 1.8.4.4
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
  $chan = GetVarChannnel($agi, "chan");
  $uniqueid1c = GetVarChannnel($agi, "uniqueid1c");
  // получим путь к базе данных
  $disk = storage_service_is_active("astlogs");
  $cdr_db = $disk['mountpoint']."/askoziapbx/astlogs/asterisk/master.db";
  
 
  // форируем и выполняем запрос
  // select MAX(recordingfile) from cdr where recordingfile!='' AND linkedid like '$uniqueid1c%' GROUP BY linkedid     
  $zapros ="SELECT 
  				MAX(recordingfile) 
  			FROM cdr 
  			WHERE recordingfile!='' AND linkedid LIKE '$uniqueid1c%' 
  			GROUP BY linkedid";     
        
  $var_path = rtrim(exec("sqlite3 $cdr_db \"$zapros\""));  

  if(is_file($var_path)){
    $recordingfile = $var_path;
  }else{
    // получим путь к медиа файлам
    $diskmedia = storage_service_is_active("media");
    $recordingfile = $diskmedia['mountpoint']."/askoziapbx/voicemailarchive/monitor/$var_path";
  }
  
  if(is_file($recordingfile)) {
    $response = "CallRecord,Channel:$chan,FileName:$recordingfile";
  }else{
    $response = "CallRecordFail,Channel:$chan,uniqueid1c:$uniqueid1c";
  }
  // отсылаем сообщение в 1С
  $agi->exec("UserEvent", $response);  
}

// отклюаем запись CDR для приложения
$agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>​