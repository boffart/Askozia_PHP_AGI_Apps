<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2014-08-25
// v.3.4 // 1C_Download // 10000666
// Загрузка факсов / записей разговоров на клиента
-------------------------------------------------------
Скрипт протестирован на Askozia v2:
Asterisk 1.8.4.4
PHP 4.4.9
sqlite3 -version 3.7.0
AGI phpagi.php,v 2.14 2005/05/25 20:30:46

// Сама загрузка на клиента возлагается на скрипт:
/offload/rootfs/usr/www_provisioning/1c/download.php 
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
} 
$agi = new AGI();

$EXTEN = GetVarChannnel($agi, "EXTEN");
if($EXTEN == "h"){
  // это особенность работы с Askozia, для избежания зацикливания
  // http://igorg.ru/2011/10/22/askozia-opyt-ispolzovaniya
}else{
  
  $chan         = GetVarChannnel($agi,'v1');
  $uniqueid1c  = GetVarChannnel($agi,'v2'); 
  $faxrecfile   = GetVarChannnel($agi,'v3'); 
  $RecFax        = GetVarChannnel($agi,'v6'); 

  if(strlen($faxrecfile) <= 4 && strlen($uniqueid1c) >= 4){
    // 1.Формируем запрос
    $disk = storage_service_is_active("astlogs");
  // проверим, есть ли временная база
  // если есть, то запросы к ней
  $cdr_db = $disk['mountpoint']."/askoziapbx/astlogs/asterisk/master.db";
    
  $zapros ="SELECT 
          MAX(recordingfile) 
        FROM cdr 
        WHERE recordingfile!='' AND linkedid LIKE '$uniqueid1c%' 
        GROUP BY linkedid";     

    // 2. Выполняем запрос
    $faxrecfile = rtrim(exec("sqlite3 $cdr_db \"$zapros\""));
  }
  
  if(strlen($faxrecfile) <= 4 && $RecFax == "FAX"){
    $agi->exec("UserEvent", "FailDownloadFax,Channel:$chan");
  }elseif(strlen($faxrecfile) > 4 && $RecFax == "FAX"){
    $agi->exec("UserEvent", "StartDownloadFax,"
                  ."Channel:$chan,"
                  ."FileName:$faxrecfile");
  }elseif(strlen($faxrecfile) <= 4 && $RecFax == "Records"){
    $agi->exec("UserEvent", "FailDownloadRecord,Channel:$chan");
  }elseif(strlen($faxrecfile) > 4 && $RecFax == "Records"){
    $agi->exec("UserEvent", "StartDownloadRecord,"
                  ."Channel:$chan,"
                  ."FileName:$faxrecfile");
  } 
}

// отклюаем запись CDR для приложения
$agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>

​