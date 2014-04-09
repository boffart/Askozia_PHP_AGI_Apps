<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2014-03-07 
// v.4.1 // 1C_SendFax // 10000333
// Отпрака факсимильного сообщения
-------------------------------------------------------
Скрипт протестирован на Askozia v3.0.1:
Asterisk 1.8.4.4
PHP 4.4.9
AGI phpagi.php,v 2.14 2005/05/25 20:30:46

[miko_ajam_fax_tx]
exten => _X!,1,Noop(------------------- FAX from ${CALLERID(number)} ------------------)
exten => _X!,n,ResetCDR()
exten => _X!,n,Answer
exten => _X!,n,Wait(2)
exten => _X!,n,SendFAX(${outbox_path}.tif,d)
exten => _X!,n,Set(CALLERID(num)=${src})
exten => _X!,n,Set(CDR(InternalCalleridNum)=FAXout)
exten => _X!,n,Noop(--- ${FAXSTATUS} ---${FAXERROR} ---${REMOTESTATIONID} ---)
exten => _X!,n,Hangup

exten => h,1,Noop(------------------- FAX to ${EXTEN} with ${FAXSTATUS} -----------------)
exten => h,n,GotoIf($["${FAXSTATUS}" = "SUCCESS"]?h,success:h,failed)
exten => h,n(failed),UserEvent(SendFaxStatusFail,Channel: ${chan},CallerID: ${faxcallerid})
exten => h,n,Hangup
exten => h,n(success),UserEvent(SendFaxStatusOk,Channel: ${chan},CallerID: ${faxcallerid})
exten => h,n,Set(CDR(recordingfile)=${faxfile}.tif)
exten => h,n,Hangup
;--== end of [miko_ajam_fax_tx] ==--;
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
    // http://igorg.ru/2011/10/22/askozia-opyt-ispolzovaniya/
}else{
    $agi->Answer();  
    $agi->exec("NoCDR", "");

    $chan          = GetVarChannnel($agi, "chan");
  $src       = GetVarChannnel($agi, "cid_user");
  if($src==''){
    $arr_src = explode('/',$chan);
    $src = $arr_src[1];      
  } 
    
    $faxfile       = GetVarChannnel($agi, "faxfile");
    $faxcallerid   = GetVarChannnel($agi, "faxcallerid");
    $CALLERID     = GetVarChannnel($agi, "CALLERID(num)");

    $agi->exec("Playback", "press_start_to_receive_a_fax");        
    $agi->exec("Wait", "1");
    $agi->exec("Playback", "vm-goodbye"); 
    
    $disk = storage_service_is_active("faxarchive");
    $ajamfaxfile= $disk['mountpoint']."/askoziapbx/tmp/$faxfile";
   
    $agi->exec("Set", "_src=$src");
    $agi->exec("Set", "_chan=$chan");
    $agi->exec("Set", "_faxcallerid=");
    $agi->exec("Set", "_faxfile=$faxfile");
    $agi->exec("Set", "_outbox_path=$ajamfaxfile");
    $agi->exec("Dial", "LOCAL/$CALLERID@miko_ajam_fax_tx,,g"); 
}
?>​