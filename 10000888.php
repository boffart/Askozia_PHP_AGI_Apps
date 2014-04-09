<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2013-01-20 
// v.1.6 //
// Получение информации по номеру с сервера 1С
-------------------------------------------------------
Скрипт протестирован на Askozia 2:
Asterisk 1.8.4.4
PHP 4.4.9
AGI phpagi.php,v 2.14 2005/05/25 20:30:46
-------------------------------------------------------
curl --header "Content-Type: text/xml; charset=utf-8" --header "Authorization: Basic YWRtaW46MTIz" -d '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><m:identify xmlns:m="http://wiki.miko.ru/doc:1cajam:identifynumber"><m:Number>104</m:Number></m:identify></soap:Body></soap:Envelope>' http://192.168.1.188/Test/ws/1C_MIKO_identify_number.1cws

// http://172.16.32.203/TestComponenta/ws/1C_MIKO_identify_number.1cws?wsdl
// php -q /tmp/test/1.3_php4_xml_post_http_soap.php '/TestComponenta/ws/1C_MIKO_identify_number.1cws' '192.168.1.188' 80 '104' 'admin' '123'
-------------------------------------------------------*/
require('phpagi.php');

$start_found = false; $end_found = false;
$ret_value = '';
function tagStart($parse, $name, $attribs){
  global $start_found;
  if($name == 'M:RETURN'){
    // echo($name);
    $start_found = true;
  }
}
function tagEnd($parser, $name){
  global $start_found;
  global $end_found;
  if($name == 'M:RETURN'){
    $end_found = true;
  }
}
function dataGet($parser, $data){
  global $start_found;
  global $ret_value;
  global $end_found;
  if($start_found&&!$end_found){
    $ret_value = ''.$data;
  }
}
function parse_response($response){
  // создаем xml парсер
  $xml_parse = xml_parser_create();
  xml_set_element_handler($xml_parse, 'tagStart', 'tagEnd');
  xml_set_character_data_handler ($xml_parse, 'dataGet'); 
  xml_parse($xml_parse, $response);
  // освобождаем память, занятую парсером
  xml_parser_free($xml_parse);
}

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

$path   = '/crm/ws/1C_MIKO_identify_number.1cws';
$server = 'a.miko.ru';
$port   = 80;
$number = GetVarChannnel($agi, "CALLERID(num)");
$user_1c= "askozia";
$pass_1c= 'askozia';

$auth = base64_encode($user_1c.':'.$pass_1c);
$crlf = "\r\n"; 
// данные для передачи urlencode()
$xmlDocument = (
'<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <m:identify xmlns:m="http://wiki.miko.ru/doc:1cajam:identifynumber">
      <m:Number>'.$number.'</m:Number>
    </m:identify>
  </soap:Body>
</soap:Envelope>');
$contentLength = strlen($xmlDocument);
  
// создаем сокет
if (($http_soket = @fsockopen($server, $port, $errno, $errstr,1.5)) == false) 
  return;
$query  = "POST $path HTTP/1.1"                  .$crlf;
$query .= "Host: $server"                      	 .$crlf; 
$query .= "Content-Type: text/xml; charset=utf-8".$crlf; 
$query .= "Authorization: Basic $auth"           .$crlf; 
$query .= "Content-Length: $contentLength"       .$crlf;
$query .= $crlf; 
$query .= $xmlDocument; 
    
// устанавливаем таймаут на поток 1секунда 0 микросекунд
stream_set_timeout($http_soket, 0, 500000); 
// отправляем запрос
fputs($http_soket, $query); 
    
$result = '';
while ($line = fgets($http_soket)) 
  $result .= $line; 

$result = substr($result, strpos($result, $crlf.$crlf) + 4); 
fclose($http_soket);  

parse_response($result); 
if($ret_value != ''){
  $agi->set_variable('CALLERID(name)', $ret_value);
}
?>​