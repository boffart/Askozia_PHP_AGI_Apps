<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2013-10-31 
// v.3.3 // 1С_SetupEnv // 10000111
// Получение настроек с сервера Asterisk
-------------------------------------------------------
Скрипт протестирован на Askozia v2:
Asterisk 1.8.4.4
PHP 4.4.9
AGI phpagi.php,v 2.14 2005/05/25 20:30:46
ESP Ghostscript 8.15.2 (2006-04-19) 
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
	// узнаем номер порта для обмена файлами
	$response = exec("netstat -tpln | grep 23600");
	if($response=='') $port = "80"; else $port = "23600";
	
    // 
    $Chan = GetVarChannnel($agi, "v1");;
    $DialplanVer = "1.0.0.6";
    $GSVER       = rtrim(substr(exec("gs -v"),15,4));
    $Statistic   = trim(exec("cat /etc/httpd.conf | grep '/cfe/wallboard/:statistic:' | sed 's/^\/cfe\/wallboard\/://g'"));
    $FaxSendUrl  = "$port/cfe/wallboard/1c/upload.php";
    $SkypeContext="SIP-PROVIDER-Skype-OUT-MIKO";
    $skypeprefix ='';
    $DefaultContext=""; 
    $agi->exec("UserEvent", "AsteriskSettings,"
                           ."Channel:$Chan,"
                           ."FaxSendUrl:$FaxSendUrl,"
                           ."DefaultContext:$DefaultContext,"
                           ."SkypeContext:$SkypeContext,"
                           ."skypeprefix:$skypeprefix,"
                           ."DialplanVer:$DialplanVer,"
                           ."Statistic:$Statistic,"
                           ."autoanswernumber:*8,"
                           ."GhostScriptVer:$GSVER");
    //
    $agi->exec("UserEvent", "HintsEnd,"."Channel:$Chan");
} 


// отклюаем запись CDR для приложения
$agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer();
?>
​