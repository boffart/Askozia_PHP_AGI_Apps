#!/usr/bin/php
<?php 
/*-----------------------------------------------------
// ООО "МИКО" // 2014-04-10 
// v.2.9 // Загрузка факсов / записей разговоров с сервереа Askozia на клиента
-------------------------------------------------------
Askozia 2.2.8 / 3.0.2 / 3.2
PHP 4.4.9
SoX v14.3.2
-------------------------------------------------------*/
require("guiconfig.inc");

$disk = storage_service_is_active("media");
$tmpdir = $disk['mountpoint'] . "/askoziapbx/tmp/";
$faxdir = $disk['mountpoint'] . "/askoziapbx/faxarchive/";
$recdir = $disk['mountpoint'] . "/askoziapbx/voicemailarchive/monitor/";

if ($_GET['view']){
	
	$filename = str_replace(' ', '+', $_GET['view']);
	
	if ($_GET['type']=="FAX") 
	{
		$base_name = basename(basename($filename,'.tif'),'.pdf').'.pdf';
		$fax_file_name = $faxdir.$base_name;
		if(file_exists($fax_file_name)){
			
			header("Content-Type: application/octet-stream"); 
			header("Content-Disposition: attachment; filename=".$base_name);
			passthru("cat ".$fax_file_name);
		}else{
			echo '<b>404 File not found!</b>';
		}
	}elseif ($_GET['type']=="Records") 
	{
		$wavfile = $tmpdir. basename($filename) . '.wav';
		
	    if(is_file($filename)){
	      $recordingfile = $filename;
	    }else{
	      // получим путь к медиа файлам
	      $recordingfile = $recdir.$filename;
	    }

		$extension = strtolower(substr(strrchr($wavfile,"."),1));
		if($extension == "wav"){
			system('cp '.$recordingfile.' '.$wavfile.' > /dev/null 2>&1');
		}else{
			system('sox '.$recordingfile.' -r 8000 -a '.$wavfile.' > /dev/null 2>&1');
		}

		if (file_exists($wavfile)) {
			
			$size      = filesize($wavfile);
			$name      = basename($wavfile);
		    $extension = strtolower(substr(strrchr($wavfile,"."),1));
		    
		    // This will set the Content-Type to the appropriate setting for the file
		    $ctype ='';
		    switch( $extension ) {
		      case "mp3": $ctype="audio/mpeg"; break;
		      case "wav": $ctype="audio/x-wav"; break;
		      case "gsm": $ctype="audio/x-gsm"; break;
		      // not downloadable
		      default: die("<b>404 File not found!</b>"); break ;
		    }
		    // need to check if file is mislabeled or a liar.
		    $fp=fopen($wavfile, "rb");
		    if ($ctype && $fp) {
			    header("Pragma: public");
			    header("Expires: 0");
			    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			    header("Cache-Control: public");
			    header("Content-Description: wav file");
			    header("Content-Type: " . $ctype);
			    header("Content-Disposition: attachment; filename=" . $name);
			    header("Content-Transfer-Encoding: binary");
			    header("Content-length: " . $size);
			    ob_clean();
			    fpassthru($fp);
			}else{
				echo '<b>404 File not found!</b>';
			}
		} // file_exists
	} // if($_GET['type'])
}
exit;
?>