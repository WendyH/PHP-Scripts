<?php
$outVidDir = 'out_videos';
$outImgDir = 'out_images';

$nmFx     = isset($_REQUEST['fx'])       ? (int)$_REQUEST['fx']  : 0;
$text     = isset($_REQUEST['msg'])      ? $_REQUEST['msg']      : ":(\n\nVideo is not allowed.\nSorry.";
$fontSize = isset($_REQUEST['fontsize']) ? (int)$_REQUEST['fontsize'] : 60;
$caption  = isset($_REQUEST['caption'])  ? $_REQUEST['caption']  : 'WARNING!';
$captColor= isset($_REQUEST['captcolor'])? $_REQUEST['captcolor']: '6DC3D3';
$captFont = isset($_REQUEST['captfont']) ? $_REQUEST['captfont'] : 'DejaVuSansMono-Bold';
$captSize = isset($_REQUEST['captsize']) ? (int)$_REQUEST['captsize'] : 72;
$fontname = isset($_REQUEST['font'])     ? $_REQUEST['font']     : 'LiberationMono-Regular';
$textColor= isset($_REQUEST['color'])    ? $_REQUEST['color']    : 'EFDFDF';
$bckgrnd  = isset($_REQUEST['bckgrnd'])  ? $_REQUEST['bckgrnd']  : 'i1d';
$audio    = isset($_REQUEST['audio'])    ? $_REQUEST['audio']    : 's4';
$testpic  = isset($_REQUEST['testpic'])  ? (int)$_REQUEST['testpic'] : 0;
$nocache  = isset($_REQUEST['nocache'])  ? (int)$_REQUEST['nocache'] : 0;
$imHeight = isset($_REQUEST['h']) ? (int)$_REQUEST['h'] : 0;
$imWidth  = isset($_REQUEST['w']) ? (int)$_REQUEST['w'] : 0;
$videoTime= isset($_REQUEST['time'])     ? (int)$_REQUEST['time']: 60;
$audio_file = "./mp3/".$audio.".mp3";
$font       = "./ttf/".$fontname.".ttf";
$captFont   = "./ttf/".$captFont.".ttf";
$background = "./backgrounds/".$bckgrnd.".jpg";
if (substr($textColor, 0, 1)!='#') $textColor = '#'.$textColor;
if (substr($captColor, 0, 1)!='#') $captColor = '#'.$captColor;
if(!file_exists($audio_file)) $audio_file = "";
if(!file_exists($background)) $background = "./backgrounds/i1d.jpg";
if(!file_exists($background)) die("Error! Image file does not exist at '".$background."'");
if(!file_exists($font)) "./ttf/LiberationMono-Regular.ttf";
if(!file_exists($font)) die("Error! Font file does not exist at '".$font."'");
if (!preg_match('#.#u', $text)) $text = iconv("cp1251", "UTF-8", $text);
if (!preg_match('#.#u', $caption)) $caption = iconv("cp1251", "UTF-8", $caption);

$testpic=1;
$cacheId = md5($fontname.$fontSize.$textColor.$background.$audio_file.$nmFx.$caption.$captColor.$captFont.$captSize.$imHeight.$imWidth.$videoTime.$text);
$video_file = $outVidDir."/video_"     .$cacheId.".avi";
$image_file = $outImgDir."/background_".$cacheId.".jpg";

$caption = str_replace('|', "\n", $caption);
$text = str_replace('|', "\n", $text);

if (strlen($caption) % 2) $caption.=' ';
if (strlen($text) % 2) $text.=' ';
if (($nocache==0) AND ($testpic==0) AND file_exists($video_file)) {	toGiveTheFile($video_file);
}
if (($nocache==0) AND ($testpic==1) AND file_exists($image_file)) {
	toGiveTheFile($image_file, "image/jpeg", false);
}

try
{
$im = new Imagick($background);
$im->setImageCompressionQuality(100);
if (($imWidth+$imHeight)>0) $im->resizeImage($imWidth, $imHeight, Imagick::FILTER_LANCZOS, 1);
//else $im->resizeImage(1280, 720, Imagick::FILTER_LANCZOS, 1);

if        ($nmFx == 1) {	$im->radialBlurImage(6);
	$t1 = whTextOut($im, $text, $font, $fontSize, $textColor, 1.2, 0, 0, $caption, $captColor, $captFont, $captSize);
	$im->compositeImage($t1, Imagick::COMPOSITE_DEFAULT, 0, 0);
	$t1->clear(); $t1->destroy();
} else if ($nmFx == 2) {
	$t0 = whTextOut($im, $text, $font, $fontSize, "#101010", 1.2, 0, 0, $caption, "#101010", $captFont, $captSize);
	$im->compositeImage($t0, Imagick::COMPOSITE_DEFAULT, 0, 0);
	$im->blurImage(3, 2);
	$t1 = whTextOut($im, $text, $font, $fontSize, $textColor, 1.2, 0, 0, $caption, $captColor, $captFont, $captSize);
	$im->compositeImage($t1, Imagick::COMPOSITE_DEFAULT, 0, 0);
	$t0->clear(); $t0->destroy();
	$t1->clear(); $t1->destroy();
} else {
	$t0 = whTextOut($im, $text, $font, $fontSize, "#101010", 1.2, 3, 4, $caption, "#101010", $captFont, $captSize);
	$im->compositeImage($t0, Imagick::COMPOSITE_DEFAULT, 0, 0);
	$im->blurImage(3, 3);
	$t1 = whTextOut($im, $text, $font, $fontSize, $textColor, 1.2, 0, 0, $caption, $captColor, $captFont, $captSize);
	$im->compositeImage($t1, Imagick::COMPOSITE_DEFAULT, 0, 0);
	$t0->clear(); $t0->destroy();
	$t1->clear(); $t1->destroy();
}
$im->writeImage($image_file);

if ($testpic == 1) {	header("Content-Type: image/jpeg");
	echo $im;
	$im->clear();
	$im->destroy();
	die();
}

exit;

$audioKeys = ($audio_file == '') ? '-an' : '-i "'.$audio_file.'" -acodec libvo_aacenc'; //-s hd1080 -preset medium 
$ffmpegCmd = 'ffmpeg -loop_input -shortest -qscale 1 -i "<IMG>" -t 00:00:30 -r 25 -vcodec libx264 -y "<VIDEO>"';
$ffmpegCmd = str_replace("<IMG>" ,      $image_file, $ffmpegCmd);
$ffmpegCmd = str_replace("<TIME>",      $videoTime , $ffmpegCmd);
$ffmpegCmd = str_replace("<AUDIOKEYS>", $audioKeys , $ffmpegCmd);
$ffmpegCmd = str_replace("<VIDEO>",     $video_file, $ffmpegCmd);
exec($ffmpegCmd);

} catch (Exception $e) {
	echo $e->getMessage();
}

toGiveTheFile($video_file);

exit;

function toGiveTheFile($filename, $contType='video/avi', $give=true) {	$file = ($filename);
	header("Content-Type: ".$contType);
	header("Accept-Ranges: bytes");
	header("Content-Length: ".filesize($file));
	if ($give) {
//		header("Content-Disposition: attachment; filename=".$file);
	}
	readfile($file);
	die();
}

function whTextOut($im, $text, $font_file, $font_size = '12', $color = '#000000', $spacing = 1, $x_offset = 0, $y_offset = 0, $caption = '', $captColor = '#000000', $captFont = '', $captSize = 16) {
	$angle = 0;
	$width =$im->getImageWidth();
	$height=$im->getImageHeight();
	$draw = new ImagickDraw();
	$draw->setFillColor($color);
	$draw->setFont($font_file);
	$draw->setFontSize($font_size);
	$draw->setGravity(Imagick::GRAVITY_NORTH);
	$textcanvas = new Imagick();
	$metrics = $textcanvas->queryFontMetrics($draw, 'CHECKWIDTH');
	$textcanvas->newImage($width, $height, "transparent", "png");
	if ($caption!='') {		if ($captFont == '') $captFont = $font_file;
		$drawCapt = new ImagickDraw();
		$drawCapt->setFillColor($captColor);
		$drawCapt->setFont($captFont);
		$drawCapt->setFontSize($captSize);
		$drawCapt->setGravity(Imagick::GRAVITY_NORTH);
		$textcanvas->annotateImage($drawCapt, 0, 5, $angle, trim($caption));
		$drawCapt->clear();
		$drawCapt->destroy();
	}
	$SymbInLine = round($width / ($metrics['textWidth'] / 10)) - 2;
	$text = mbWordwrap($text, $SymbInLine, "\n");
	$lines=explode("\n", $text);
	$y = abs(($height - ($font_size * $spacing * (count($lines)))) / 2) + $y_offset;
	$x = $x_offset;
 	for($i=0; $i< count($lines); $i++) {
		$newY=$y+($i * $font_size * $spacing);
		$textcanvas->annotateImage($draw, $x, $newY, $angle, trim($lines[$i]));
	}
	$draw->clear();
	$draw->destroy();
	return $textcanvas;
}

function mbWordwrap($str, $width = 74, $break = "\n", $cut = false) {
	$mes = iconv("UTF-8", "cp1251", $str);
	$mes = wordwrap($mes, $width, $break, $cut);
	$mes = iconv("cp1251", "UTF-8", $mes);
	return $mes;
}
