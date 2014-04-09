<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2014-04-09 
// v.1.6 // 1С_Set_Status // 10000222 
// Передача статусов пользователей
-------------------------------------------------------
Скрипт протестирован на Askozia v2:
Asterisk 1.8.4.4
PHP 4.4.9
AGI phpagi.php,v 2.14 2005/08/29
-------------------------------------------------------*/
require('phpagi.php');
function GetVarChannnel($agi, $_varName){
    $v = $agi->get_variable($_varName);
    if(!$v['result'] == 0){
      return $v['data'];
    }else{
      return "";
    }
} // GetVarChannnel($_agi, $_varName)
$agi = new AGI();

$command = GetVarChannnel($agi,'command'); // put // show
$dbFamily= GetVarChannnel($agi,'dbFamily');
$key     = GetVarChannnel($agi,'key');
$val     = GetVarChannnel($agi,'val');
$chan    = GetVarChannnel($agi,'chan');

if($dbFamily!='CF'&&$dbFamily!='UserBuddyStatus'&&$dbFamily!='DND'){
  $agi->exec("UserEvent", "DB_ERR,user:$key,status:$val"); 

}elseif($command=='get'){
  // получение статуса конкретного пользователя
    $ret = $agi->evaluate("DATABASE GET $dbFamily $key");
    $val = $ret['data'];
    if($ret['result']==1&&$ret['code']==200){
      // успех выполнения операции
      $agi->exec("UserEvent", "DB_$dbFamily,Channel:$chan,key:$key,val:$val");    
    }else{    
      // не установлена!
      $agi->exec("UserEvent", "DB_$dbFamily,Channel:$chan,key:$key,val:");
    }
 
}elseif($command=='put'){
  if(trim($val)==''){
    $ret = $agi->evaluate("DATABASE DEL $dbFamily $key");  
  }else{
    if($dbFamily=='DND'){
      // текущий штамп времени + 8 часов
      $val = time() + (8*60*60);
    }
    
    // установка статуса
    $ret = $agi->evaluate("DATABASE PUT $dbFamily $key $val");   
  }
  if($ret['result']==1&&$ret['code']==200){
    // успех выполнения операции
    $agi->exec("UserEvent", "DB_$dbFamily,Channel:$chan,key:$key,val:$val"); 
  }else{    
    // были ошибки
    $agi->exec("UserEvent", "Error_data_put_$dbFamily,Channel:$chan,key:$key,val:$val"); 
  }   
}elseif($command=='show'){
  $output = array();
  $result ='';
  // получение статустов всех пользователей 
  $tmp_str = exec('asterisk -rx"database show '.$dbFamily.'"',$output);
  // обходим файл построчно
  foreach($output as $_data){
      // набор символов - разделитель строк
      if(! $result=="") $result = $result.".....";
  
      $_data = str_replace(' ', '', $_data);
      $_data = str_replace(':', '@.@', $_data);
      $_data = str_replace('/UserBuddyStatus/', '', $_data);
      $_data = rtrim($_data);
  
      $result = $result.$_data;
      // если необходимо отправляем данные порциями
      if($ch == 20){
          // отправляем данные в 1С, обнуляем буфер
          $agi->exec("UserEvent", "From$dbFamily,Channel:$chan,Date:$date1,Lines:$result");
          $result = ""; $ch = 1;
      }
      $ch = $ch + 1;
  }    
  // проверяем, есть ли остаток данных для отправки
  if(!$result == ""){
      $agi->exec("UserEvent", "From$dbFamily,Channel:$chan,Date:$date1,Lines:$result");
  }  
}else{
  // ошибка при установке параметров скрипта
}  
// отклюаем запись CDR для приложения
$agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>
​