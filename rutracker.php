<?php // PHP Web proxy for rutracker.org
ini_set("log_errors", 1); ini_set("error_log", $_SERVER['SCRIPT_FILENAME'].".log"); ini_set('error_reporting', E_ALL); ini_set("display_errors", 0);

$cacheDir       = 'cache_proxy';    // Каталог для кэширования файлов
$cacheSizeLimit = 10 * 1024 * 1024; // Максимальный размер папки кэша (первое число - мегабайты)
$cacheLife      = 60*60*24*2;       // Если время кеш-файла старше - скачать заново (последнее число - дни)

CheckCacheSizeLimit();              // Проверка размера кеша

$siteBase          = "https://rutracker.org/forum/";
$removeFromHeaders = ['path=/forum/;','secure;','HttpOnly']; // Remove this words from cookies for the our domen
$myProxy           = $_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"].'?';
$siteDomen         = "rutracker.org";
$maxLen            = 1024*1024*3;

// Get all passed HTTP headers for sending with request
$headers = ""; 
foreach ($_SERVER as $name => $value) { 
    if (substr($name, 0, 5) == 'HTTP_') { 
        if (stristr($name, 'host'   ) or
            stristr($name, 'referer') or
            stristr($name, 'origin' )) continue;
        $headers .= str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))). ": ".$value."\r\n"; 
    } 
} 
$headers .= "Referer: $siteBase\r\n";
$headers .= "Origin: https://$siteDomen\r\n";
$headers .= "Content-Type: application/x-www-form-urlencoded\r\n";

// Request to site page
$url  = $siteBase . urldecode($_SERVER["QUERY_STRING"]);
$file = $cacheDir."/".md5($url."sil");

if (file_exists($file)) {
    if ((time()-filemtime($file)) < $cacheLife)
        file_force_download($file);
}

$options  = array();
$options['http'] = array('header' => $headers,
                         'method' => $_SERVER["REQUEST_METHOD"],
                         'follow_location' => 0,
                         'timeout' => 3,
                         'content'=> http_build_query($_POST, '', '&'));
$page = file_get_contents($url, false, stream_context_create($options), 0, $maxLen);

//var_dump($page);
//var_dump($http_response_header); exit;

// Translate all HTTP headers from answer to returned headers from our PHP proxy
$wasHeaders = array(); $transfer = 0;
foreach($http_response_header as $c => $h) {
    $name  = "";
    $value = ""; 
    $pos = strpos($h, ':');
    if ($pos) {
        $name  = strtolower(substr($h, 0, $pos));
        $value = trim(substr($h, $pos+1));
    } else
        continue;
    if (array_key_exists($name, $wasHeaders)) continue; // no duplicate headers
    elseif ($name == 'vary') continue; // my nginx decides himself when to set it
    elseif ($name == 'location') {
        if (preg_match("#//.*?/(.*)#", $url, $m1) and preg_match("#//.*?/(.*)#", $value, $m2)) {
            if ($m1[1]==$m2[1]) continue; // no redirect to same address
        }
        $h = str_replace($siteBase, $myProxy, $h); // redirect through our php proxy
    }
    elseif ($name == 'set-cookie') {
        $h = str_replace($siteDomen, $_SERVER["HTTP_HOST"], $h); // set cookies for PHP proxy domen
    }
    $h = str_replace($removeFromHeaders, '', $h);
    header($h, false);
    $wasHeaders[$name] = $value; // store passed headers
    if ($name == 'content-disposition') $transfer = $value;
}

if ($transfer) {
    file_put_contents($file, $page); 
    if (file_exists($file)) {
      if (ob_get_level()) ob_end_clean();
      readfile($file);
    }
    exit;
}

// Check: this HTML?
if (strpos($page, '<html')) {
    // rutracker.org hacks
    if (strpos($page, 'indows-1251')) {
//        $page = mb_convert_encoding($page, 'UTF-8', 'windows-1251');
    }
    $page = str_replace('href="//', 'href="'.$_SERVER["REQUEST_SCHEME"].'://', $page);
    $page = str_replace('href="/favicon', '<!!!>'  , $page); // hide all favicon links 
    $page = str_replace('href="http', '<--!-!-!-->', $page); // hide all full links
    $page = str_replace('href="', 'href="'.$myProxy, $page); // add our PHP proxy to all links
    $page = str_replace('<--!-!-!-->', 'href="http', $page); // restore all full links
    $page = str_replace('<!!!>', 'href="/favicon'  , $page); // restore all favicon links
    $page = str_replace($siteBase, $myProxy        , $page); // add our PHP proxy to all links
    
    $page = str_replace("post2url('", "post2url('".$myProxy, $page); // add our PHP proxy to js request
    $page = str_replace('action="'  , 'action="'  .$myProxy, $page); // add our PHP proxy to forms
}

echo $page;

// -------------------------------------------------------------------------------------------
function file_force_download($file) {
  if (file_exists($file)) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($file));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    if ($fd = fopen($file, 'rb')) {
      while (!feof($fd)) {
        print fread($fd, 1024);
      }
      fclose($fd);
    }
    exit;
  }
}
// -------------------------------------------------------------------------------------------
// Проверка размера кеша на превышение лимита и подчистка файлов
function CheckCacheSizeLimit() {
  global $cacheDir, $cacheSizeLimit;
  $aFiles = array();
  // Получаем размер кеша с ифнформацией о файлах в $aFiles
  $cacheSize = dirsize($cacheDir, $aFiles);
  // Если размер кеша превышает допустимый размер
  if ($cacheSize > $cacheSizeLimit) {
    $cacheSizeLimit = $cacheSizeLimit * 0.80; // 80% лимита оставляем - остальное удаляем
    // Сортируем файлы по дате создания - снача новые, потом старые
    usort($aFiles, 'CompareBySecondField');
    $cacheSize = 0; // Проходим по файлам - и все кто не умещаются в лимит - удаляем
    foreach($aFiles as $info) {
      $cacheSize += $info[2]; // Подсчитываем размер кеша прибавляя размер файла
      if ($cacheSize > $cacheSizeLimit) {
        $cacheSize -= $info[2]; // Если превышение лимита - удаляем файл
        if (!file_exists($info[0])) unlink($info[0]); // Может быть и так, что файла уже и нет, проверяем
      }
    }
  }
}
// -------------------------------------------------------------------------------------------
function CompareBySecondField ($a,$b) {if ($a[1]>$b[1]) return -1; elseif($a[1]<$b[1]) return 1; return 0;}
// -------------------------------------------------------------------------------------------
// Получние размера директории (всех файлов в нём)
function dirsize($d, &$aFiles) {
  if (!file_exists($d)) return 0;
  $dh = opendir($d);
  $size = 0;
  while(($f = readdir($dh))!==false) {
    if ($f != "." && $f != "..") {
      $path = $d . "/" . $f;
      if(is_dir($path))      $size += dirsize($path, $aFiles);
      elseif(is_file($path)) {
        $fs = filesize($path);
        $size += $fs;
        // И для кучи запоминаем информацию о файле (для будущей сортировки и проч)
        $aFiles[] = array($path, filectime($path), $fs);
      }
    }
  }
  closedir($dh);
  return $size;
}
