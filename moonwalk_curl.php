<?php
ini_set("log_errors", 0); 
ini_set("error_log", $_SERVER['SCRIPT_FILENAME'].".log");
 ini_set('error_reporting', E_ALL); 
 ini_set("display_errors", 1);
$urlBase = "https://streamguard.cc";
// Получение ссылки на видео c moonwalk в переданных параметрах, а также тип получаемого потока.
$url     = isset($_REQUEST['url'    ]) ? $_REQUEST['url' ] : ""; // moonwalk.cc iframe url

if (!$url) die("No moonwalk iframe url in the parameters.");
$userAgent = "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0";
// Загружаем страницу iframe c moonwalk
$page = curl($url);
preg_match("#VideoBalancer\(([^\)]+)\);#i",$page,$data);

if (!$data[0]) die("No VideoBalancer info in the loaded iframe.");
$Token = GetRegexValue($data[0],"#video_token:\s'([^']+)',#");
$Trailer_Token = GetRegexValue($data[0],"#trailer_token:\s'([^']+)',#");
$Partner = GetRegexValue($data[0],"#partner_id:\s(\d+),#");
$Domain = GetRegexValue($data[0],"#domain_id:\s(\d+),#");
// Получение ссылки на js-скрипт, где есть список параметров POST запроса
$jsUrl = GetRegexValue($page, '#src="(.*?)"#');
if (!$jsUrl)  die("Not found js url in the loaded iframe.");
$jsData = curl($urlBase . $jsUrl);
// Получаем параметры POST запроса из js-скрипта
$data = GetRegexValue($jsData, "#getVideoManifests.*?(\{.*?\})#is");
if (!$data) die("Function getVideoManifests not found in loaded js.");
$JSONParams = GetRegexValue($data, "#\D=(\{.*\})#is");
if (!$JSONParams) die("Not found json data in getVideoManifests function.");
// Формируем данные для POST
$postData = JSDecode($JSONParams); 
$data4Encrypt = "{";
// В цикле перебираем все ключи и значения и формируем json строку
// заменяя все переменные на их значения
foreach ($postData as $name => $value) {
    
  $val = $value;
  if ($val == "navigator.userAgent")    $val = $userAgent;
  else if (strpos($val, "_mw_adb")>0) $val = "false";
  else if ($val == 'this.options.partner_id') $val = $Partner;
  else if ($val == 'this.options.domain_id') $val = $Domain;
  else if ($val == 'this.options.video_token') $val = $Token;
  if (!is_numeric($val) && $val!="true" && $val!="false") $val = '"'.$val.'"'; // Если значение не число - обрамляем кавычками
  if ($data4Encrypt != "{") $data4Encrypt .= ","; // Если это не первая пара - добавляем запятую
  $data4Encrypt .= '"'.$name.'":'.trim($val);           // Добавляем пару "имя":значение
}


$data4Encrypt .= "}"; // Закончили формировать json данные для шифрования
preg_match_all('#r=\[(.*?)\];#',$jsData,$args);
preg_match('#,a="([^"]+)",#',$jsData,$ivs);
$k =  explode(',',$args[1][8]);
if(@$ivs[1]){
$iv = $ivs[1];
} else {
preg_match('#(\w{32})+#',implode('',$k),$vs);
$iv = $vs[1];
}
$key = "c9f93e8ebe7fc883d1bc443e4a2eef42125b063e6b62f55c57a77a1e62acee08";
// Шифруем AES cbc PKCS7 Padding
$crypted = openssl_encrypt($data4Encrypt, 'AES-256-CBC', hex2bin($key), 0, hex2bin($iv));
// Делаем POST запрос и получаем список ссылок на потоки
$post = 'q='.urlencode($crypted);
$data = curl('http://streamguard.cc/vs', $post);
// Делаем из полученных json данных ассоциативный массив PHP
$answerObject = json_decode($data, TRUE);
  if($answerObject["mp4" ]){
  $data = curl($answerObject["mp4" ]);
  }
  if($answerObject["m3u8" ]){
  $data1 = curl($answerObject["m3u8" ]);
  }

// Отдаём полученное
if($data){
    echo '<hr><b>Ссылки на mp4 файлы </b><br />';
   $job = json_decode($data,1);
   foreach($job as $k => $v){
   echo $k .'<br />'.$v.'<br />';
  }
  echo '<hr>';
}
if($data1){
echo '<b>Ссылки на m3u8 файлы</b><br />';
if(preg_match_all('~#EXT.*RESOLUTION=(\d+x\d+),.*\s(.*)~',$data1,$link)){
    $r = $link[1];
    $url = $link[2];
    for($i=0;$i<count($r);$i++){
    if($r[$i]){
    echo '<b>'.$r[$i].'</b><br />'.$url[$i].'<br />';
            }
                                }
    echo '<hr>';                                                                    }
          }
if($Trailer_Token){
echo '<b>Ссылка на трейлер</b><br />
<iframe src="https://trailerclub.me/video/'.$Trailer_Token.'/iframe"></iframe>';
}
///////////////////////////////////////////////////////////////////////////////
// Получение страницы с указанными методом и заголовками
//////////////////////////////////////////////////////////////
function curl($url, $post='', $mode=array()) {
     
    $defaultmode = array('charset' => 'utf-8', 'ssl' => 0, 'cookie' => 1, 'headers' => 0, 'useragent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0');
     
    foreach ($defaultmode as $k => $v) {
    if (!isset($mode[$k]) ) {
    $mode[$k] = $v;
    }
    }
     
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, $mode['headers']);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $mode['useragent']);
    curl_setopt($ch, CURLOPT_ENCODING, $mode['charset']);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 200);
    if ($post) {
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    if ($mode['cookie']) {
    curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__).'/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookies.txt');
    }
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    if ($mode['ssl']) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
    }
/////////////////
// Функция получения значения по указанному регулярному выражению
function GetRegexValue($text, $pattern, $group=1) {
    if (preg_match($pattern, $text, $matches))
        return $matches[$group];
    return "";
}
///////////////////////////////////////////////////////////////////////////////
// Функция получения массива из JS кода вместо json_decode
function JSDecode($data) {
  $data = str_replace("encodeURIComponent(", "", $data); // Убираем левые js команды
  $data = str_replace("'),", "',", $data);
  $data = str_replace("'", "\""  , $data); // Заменяем одинарные кавычки на экранированные обычные
  $data = str_replace(["\n","\r"], "", $data);                    // Убираем переносы строк
  $data = preg_replace('/([^\w"\.])(\w+)\s*:/','$1"$2":', $data); // Берём в кавычки имена
  $data = preg_replace('/("\w+")\s*:\s*([\w\.]+)/' ,'$1:"$2"', $data); // Берём в кавычки все значения
  $data = preg_replace('/(,\s*)(})/','$2', $data);                     // Убираем лишние пробелы
  $json = json_decode($data, true);
  return $json;
}

?>
