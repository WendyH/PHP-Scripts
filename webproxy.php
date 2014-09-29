<?php ob_start("ob_gzhandler");
//ini_set('error_reporting'       , E_ALL);
//ini_set('display_errors'        , 1);
//ini_set('display_startup_errors', 1);

$link = $_SERVER["QUERY_STRING"];
$nocache        = 0;                 // Флаг запрета использования кэша
$cacheDir       = 'mycache';         // Название каталога для файлов кеша
$cacheLifetime  = 4 * 60 * 60;       // Время жизни кеш файла в секундах
$cacheSizeLimit = 400 * 1024 * 1024; // Максимальный размер папки кэша (первое число - мегабайты)

if (!$link) { header("Location: /stepashka.php"); exit; }

// Проверяем каталог кеша, если нужно - создаём
if (!file_exists($cacheDir)) if (@mkdir($cacheDir, 0755, true)) @chmod($dirName, 0755);
CheckCacheSizeLimit();  // Проверка размера кеша. Всё, что не вписывается в наше ограничение, удаляем

$data = GetPage($link); // Получаем страницу по ссылке
echo $data;

// -------------------------------------------------------------------------------------------
// Получение страницы по ссылке или из кеша (если она там есть и не устарела)
function GetPage($link) {
  global $cacheDir, $nocache, $cacheLifetime;
  $cacheId   = md5($link);
  $cacheFile = $cacheDir."/$cacheId.z";
  $output    = ''; 
  if (!$nocache && file_exists($cacheFile)) {
    if ((filesize($cacheFile)>0) && ((time()-filemtime($cacheFile))<$cacheLifetime)) {
      $output = gzuncompress(file_get_contents($cacheFile));
    }
  }
  if ($output=='') {  
    $ch = curl_init($link);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_ENCODING      , "");
    curl_setopt($ch, CURLOPT_HTTPHEADER    , array(
      'Accept-Encoding: gzip, deflate',
      'Accept-Charset: utf-8;',
      'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:13.0) Gecko/20100101 Firefox/13.0',
      'Connection: Keep-Alive',
      'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8'));
    $output = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE)==200)
      file_put_contents($cacheFile, gzcompress($output)); // Сохраняем только удачные запросы
    curl_close($ch);
  }
  return $output;
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
    // Сортируем файлы по дате создания - сначала новые, потом старые
    usort($aFiles, 'CompareBySecondField');
    $cacheSize = 0; // Проходим по файлам и все, которые не влезают в лимит - удаляем
    foreach($aFiles as $info) {
      $cacheSize += $info[2];   // Подсчитываем размер кеша, прибавляя размер файла
      if ($cacheSize > $cacheSizeLimit) {
        $cacheSize -= $info[2]; // Если превышение лимита - удаляем файл
        unlink($info[0]);
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
        // И для кучи, запоминаем информацию о файле (для будущей сортировки и проч)
        $aFiles[] = array($path, filectime($path), $fs);
      }
    }
  }
  closedir($dh);
  return $size;
}