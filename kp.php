<?php header("Content-Type: text/html; charset=utf-8");
ini_set("log_errors", 1); ini_set("error_log", $_SERVER['SCRIPT_FILENAME'].".log"); ini_set('error_reporting', E_ALL); ini_set("display_errors", 0);
$ids = $_GET['id']; if ($ids=="") $ids = 692861;
$debug          = 0;                 // Флаг отладки - вывод результата не в JSON, а через var_dump
$cacheDir       = 'kpcache';         // Каталог для кэширования файлов
$cacheSizeLimit = 2000 * 1024 * 1024; // Максимальный размер папки кэша (первое число - мегабайты)
$rateDaysUpdate = 5;                 // Если время кеш-файла старше стольки дней - обновить информацию о рейтинге
if (!file_exists($cacheDir)) if (@mkdir($cacheDir, 0755, true)) @chmod($dirName, 0755);
CheckCacheSizeLimit();               // Проверка размера кеша

$aIDs = explode(',', $ids);          // Ид могут быть переданы массивом разделёнными запятой
$info = array();

// Если переданных ИД было более одного, то обходим весь массив
if (count($aIDs)>1) {
  foreach($aIDs as $id)
    array_push($info, GetInfoAboutFilmFromKP($id));
} else {
// А если ИД был один - то один
  $info = GetInfoAboutFilmFromKP($ids);
}

if ($debug) {
  var_dump($info);

} else {
  $json = json_encode($info);
  echo $json; // Возвращаем Info в виде JSON

}

exit;

// -------------------------------------------------------------------------------------------
function GetReInfo($html, $pattern, $bList=false, $nameCase=false) {
  $result = "";
  if (preg_match('#'.$pattern.'#is', $html, $matches)) {
    $result = $matches[1];
    if ($bList) {
      preg_match_all('#<a[^>]+>(.*?)</a>#is', $result, $ar);
      $result = "";
      foreach($ar[1] as $item) {
        $val = trim(strip_tags($item));
        if (($val=="...") || ($val=="-")) continue;
        if ($result!="") $result .= ", ";
        if ($nameCase) $val = mb_convert_case($val, MB_CASE_TITLE, "UTF-8");
        $result .= $val; 
      }
    } else {
      $result = trim(strip_tags($matches[1]));
      if ($result=="-") $result = "";
    }
  }
  $result = html_entity_decode(strip_tags($result));
  $result = html_entity_decode(strip_tags($result));
  return $result;
}

// -------------------------------------------------------------------------------------------
function GetInfoAboutFilmFromKP($id) {
  global $cacheDir, $rateDaysUpdate, $debug;
  $info = array();
  $onlyRaiting = false;
  $cacheFile = $cacheDir."/info_$id.gz";

  if (!$debug && file_exists($cacheFile)) {
    $onlyRating = ((time()-filemtime($cacheFile)) > (60*60*24*$rateDaysUpdate)); // Проверяем, не пора ли обновить рейтинг
    $json = gzuncompress(file_get_contents($cacheFile));
    $info = json_decode($json, true);
    // По закону больших чисел или если кино давнее, то можно уже и не обновлять рейтинг (лишний раз запросы не слать)
    if (($info["kpcount"] > 800) || ($info["Year"]>0 && $info["Year"]<2010)) $onlyRating = false;
    if (!$onlyRating) return $info;
  }

  $html = GetMoviePage($id);

  if (!$onlyRaiting) {
    $info["id"       ] = $id;
    $info["Title"    ] = GetReInfo($html, '(<h1.*?</h1>)');
    $info["Title_eng"] = GetReInfo($html, '(<span[^>]+alternativeHeadline.*?</span>)');
    $info["Year"     ] = GetReInfo($html, '>год<.*?(\d{4})');
    $info["Country"  ] = GetReInfo($html, '>страна<.*?(<td.*?</td>)', true);
    $info["Genre"    ] = GetReInfo($html, '>жанр<.*?(<td.*?</td>)', true, true);
    $info["Director" ] = GetReInfo($html, '>режиссер<.*?(<td.*?</td>)', true, true);
    $info["Producer" ] = GetReInfo($html, '>продюсер<.*?(<td.*?</td>)', true, true);
    $info["Author"   ] = GetReInfo($html, '>сценарий<.*?(<td.*?</td>)', true, true);
    $info["Operator" ] = GetReInfo($html, '>оператор<.*?(<td.*?</td>)', true, true);
    $info["Composer" ] = GetReInfo($html, '>композитор<.*?(<td.*?</td>)', true, true);
    $info["Slogan"   ] = GetReInfo($html, '>слоган<.*?(<td.*?</td>)');
    $info["Actors"   ] = GetReInfo($html, '>В главных ролях:<.*?(<ul.*?</ul>)', true, true);
    $info["Budget"   ] = GetReInfo($html, '>бюджет<.*?(<td.*?</td>)');
    $info["Poster"   ] = 'http://www.kinopoisk.ru/images/film_big/'.$id.'.jpg';
    $info["Genre"    ] = mb_ereg_replace(', Слова', '', $info["Genre"]);
    if (preg_match('#>время<.*?(\d+)\\s*?мин#is', $html, $matches)) {
      $info["Duration"] = $matches[1]*60;
      $info["Time_HMS"] = gmdate("H:i:s", $matches[1]*60).".000";
    } else {
      $info["Duration"] = 3600;
      $info["Time_HMS"] = "01:40:00.000";
    }
  }
  if (preg_match('#getTrailersDomain.*?["\'](.*?)["\'].*?trailerFile"\s*?:\s*?"(.*?)"#is', $html, $matches))
    $info["Trailer"] = 'http://'.$matches[1].'/'.$matches[2];
  else 
    $info["Trailer"] = "";
  
  if (preg_match('#rating_ball">(.*?)</.*?ratingCount.*?>(.*?)</#is', $html, $matches)) {
    $ball  = str_replace(array('&nbsp;', ' '), '', trim($matches[1]));
    $count = str_replace(array('&nbsp;', ' '), '', trim($matches[2]));
    $info["raiting_kp"  ] = array("rating_ball"=>$ball, "ratingCount"=>$count);
    $info["rate_kp"     ] = $ball." (".$count.")";
    $info["kpcount"     ] = $count;
  } else {
    $info["raiting_kp"  ] = array("rating_ball"=>"", "ratingCount"=>"");
    $info["rate_kp"     ] = "";
    $info["kpcount"     ] = 0;
  }

  if (preg_match('#>IMDb:(.*?)\((.*?)\)#is', $html, $matches)) {
    $ball  = str_replace(array('&nbsp;', ' '), '', trim($matches[1]));
    $count = str_replace(array('&nbsp;', ' '), '', trim($matches[2]));
    $info["raiting_imdb"] = array("rating_ball"=>$ball, "ratingCount"=>$count);
    $info["rate_imdb"   ] = $ball." (".$count.")";
  } else {
    $info["raiting_imdb"] = array("rating_ball"=>"", "ratingCount"=>"");
    $info["rate_imdb"   ] = "";
  }
  $info["Timestamp"] = time();
  $info["DateTime" ] = date("Y.m.d H:i:s");
  
  if ($info["Director"]!="") file_put_contents($cacheFile, gzcompress(json_encode($info)));
  return $info;
}

// -------------------------------------------------------------------------------------------
function GetMoviePage($id) {
  global $cacheDir;
  $kpFilmUrl = "http://www.kinopoisk.ru/film/$id/";
  $cacheFile = $cacheDir."/Film_$id.gz";
  if (file_exists($cacheFile)) {
    $output = gzuncompress(file_get_contents($cacheFile));
  } else {  
    $ch = curl_init($kpFilmUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_ENCODING      , "");
    curl_setopt($ch, CURLOPT_HTTPHEADER    , array(
      'Accept-Encoding: gzip, deflate',
      'Accept-Charset: utf-8;',
      'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:13.0) Gecko/20100101 Firefox/13.0',
      'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8'));
    $output = curl_exec($ch);
    curl_close($ch);
    //file_put_contents($cacheFile, gzcompress($output));
  }
  $output = iconv('windows-1251', 'UTF-8', $output);
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
    // Сортируем файлы по дате создвния - снача новые, потом старые
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
// -------------------------------------------------------------------------------------------
// Функция записи сообщений в файл (для ошибок и отладки)
function LogMe($msg="") {
  if ($msg) file_put_contents($_SERVER['SCRIPT_FILENAME'].".log", date("Y-m-d H:i:s")." ".$msg."\n", FILE_APPEND);
}
