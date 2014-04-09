<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2014-01-10 
// v.3.4 // 1С_Get_Context // 10000109
// Получение context с сервера Asterisk
-------------------------------------------------------
Скрипт протестирован на Askozia v2:
Asterisk 1.8.4.4
PHP 4.4.9
AGI phpagi.php,v 2.14 2005/05/25 20:30:46
ESP Ghostscript 8.15.2 (2006-04-19) 
-------------------------------------------------------*/
require("phpagi.php");

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
    // 
  $exten   = GetVarChannnel($agi, "number");
  $tehnology  = GetVarChannnel($agi, "tehnology");
  $output   = array(); $result='';
  
  if($tehnology == 'SIP'){
    $result = exec("asterisk -rx\"sip show peer $exten\" | grep Context | awk -F'[:]+[ ]+' ' { print $2  } '",$output);   
  }elseif($tehnology == 'DAHDI'){
    $result = exec("asterisk -rx\"dahdi show channel $exten\" | grep Context | awk -F'[:]+[ ]+' ' { print $2  } '",$output);    
  }elseif($tehnology == 'IAX'){
    $result = exec("asterisk -rx\"iax2 show peer $exten\" | grep Context | awk -F'[:]+[ ]+' ' { print $2  } '",$output);    
  }
  $agi->exec("UserEvent", "GetContest,"
                           ."Channel:$tehnology/$exten,"
                           ."context:$result");
} 

// отклюаем запись CDR для приложения
$agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer();
?>
​