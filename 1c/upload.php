#!/usr/bin/php
<?php
/*-----------------------------------------------------
// ООО "МИКО" // 2014-04-10 
// v.3.5 // Загрузка файлов на АТС
-------------------------------------------------------
// Скрипт протестирован на Askozia v2.2.8 / 3.0.2:
//   PHP v.4.4.9
// местоположение файла
//   /offload/rootfs/usr/www/cfe/wallboard/1c/upload.php
//   Формат поддерживаемых файлов TIF и PDF.
-------------------------------------------------------*/

require("guiconfig.inc");

$disk = storage_service_is_active("media");
$tmpdir = $disk['mountpoint']."/askoziapbx/tmp/";
$faxdir = $disk['mountpoint']."/askoziapbx/faxarchive/";

if(!is_dir($tmpdir)){
	mkdir($tmpdir);
}
if(!is_dir($faxdir)){
	mkdir($faxdir);
}
if (is_uploaded_file($_FILES['file']['tmp_name'])) {
			
	$filename = str_replace(" ","_",$_FILES['file']['name']);
	// get filetype
	$file_array = explode(".",$filename);
	$filetype = $file_array[count($file_array)-1];

if (strtolower ($filetype)=="pdf"){
	// move file to asterisk music-on-hold directory on media storage
	$pdf_filename = $faxdir . $filename;
	move_uploaded_file($_FILES['file']['tmp_name'], $pdf_filename);
			
	$tif_filename = $tmpdir . $file_array[0].'.tif';
	system('gs -q -dNOPAUSE -dBATCH -sDEVICE=tiffg4 -sPAPERSIZE=a4 -g1680x2285 -sOutputFile='.escapeshellarg($tif_filename).' '.escapeshellarg($pdf_filename).' > /dev/null 2>&1');

	    echo ("<pre>File upload success.</pre>");
	}else{
		echo ("<pre>Upload failed. Only PDF format!</pre>");
	}
} else{
	echo ("<pre>Upload failed.</pre>");
}
?>
