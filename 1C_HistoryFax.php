<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2013-10-31 
// v.3.3 // 1C_HistoryFax // 10000444
// Получение истории факсимильных сообщений
-------------------------------------------------------
Скрипт протестирован на Askozia v2:
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

// 1.Формируем запрос и сохраняем результат выполнения во временный файл
$disk = storage_service_is_active("astlogs");
$db = $disk['mountpoint']."/askoziapbx/astlogs/asterisk/master.db";

$chan       = GetVarChannnel($agi,'v1');
$date1      = GetVarChannnel($agi,'v2');
$date2      = GetVarChannnel($agi,'v3');

$zapros="
SELECT 
     a.answer,
     a.src,a.dst,
     a.lastdata,
     a.uniqueid,
     a.lastapp,     
a.userfield,
     a.InternalCalleridNum 
     
FROM 
     (SELECT * 
      FROM cdr 
      where answer BETWEEN \"$date1\" AND \"$date2\") AS a 
WHERE a.userfield!=\"\" 
      AND (a.InternalCalleridNum=\"FAXin\" 
           OR a.InternalCalleridNum=\"FAXout\")";

// выгружаем рещультат запроса в файл
$output  = array();
$tmp_str = exec("sqlite3 -separator '@.@' $db '$zapros';",$output);

// необходимо отправлять данные пачками по 15 шт.    
$result = ""; $ch = 1;
// обходим файл построчно
foreach($output as $_data){
    // набор символов - разделитель строк
    if(! $result=="") $result = $result.".....";
    $_data = str_replace(" ", '\ ', $_data);
    $_data = rtrim($_data);

    $result = $result.$_data;
    // если необходимо отправляем данные порциями
    if($ch == 10){
        // отправляем данные в 1С, обнуляем буфер
        $agi->exec("UserEvent", "FaxFromCDR,Channel:$chan,Date:$date1,Lines:$result");
        $result = ""; $ch = 1;
    }
    $ch = $ch + 1;
}

// проверяем, есть ли остаток данных для отправки
if(!$result == ""){
    $agi->exec("UserEvent", "FaxFromCDR,Channel:$chan,Date:$date1,Lines:$result");
}
// завершающее событие пакета, оповещает 1С, что следует обновить историю
$agi->exec("UserEvent", "Refresh1CFAXES,Channel:$chan,Date:$date1");
}
// отклюаем запись CDR для приложения
$agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>​