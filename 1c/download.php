#!/usr/bin/php
<?php 
/*-----------------------------------------------------
// ООО "МИКО" // 2014-03-06 
// v.2.1 // Загрузка факсов / записей разговоров с сервереа Askozia на клиента
-------------------------------------------------------
Askozia 3.0
PHP 4.4.9
SoX v14.3.2
-------------------------------------------------------*/
require_once("guiconfig.inc");
$disk = storage_service_is_active("media");
$tmpdir = $disk['mountpoint'] . "/askoziapbx/tmp/";
$faxdir = $disk['mountpoint'] . "/askoziapbx/faxarchive/";
$recdir = $disk['mountpoint'] . "/askoziapbx/voicemailarchive/monitor/";

$filename = str_replace(' ', '+', ($_GET['view'])); // ???

if ($_GET['type']=="FAX" && file_exists($faxdir . $_GET['view']) ) 
{
	header("Content-Type: application/octet-stream"); 
	header("Content-Disposition: attachment; filename=" . basename($_GET['view']));
	passthru("cat " . $faxdir . $_GET['view']);
}elseif ($_GET['type']=="Records" && file_exists($recdir.$filename) ) 
{
	$wavfile = $tmpdir.basename($filename) . '.wav';		
	system("sox '".$recdir.$filename."' -r 8000 -a '".$wavfile."' > /dev/null 2>&1");
	if (file_exists($wavfile)) {
		header("Content-Type: application/octet-stream"); 
		header("Content-Disposition: attachment; filename=" . basename($_GET['view'] . '.wav'));
		passthru("cat " . $wavfile);
	}
}
exit;