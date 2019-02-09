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
$data = GetRegexValue($page, "#VideoBalancer\((.*?)\);#is");
if (!$data) die("No VideoBalancer info in the loaded iframe.");
$options = JSDecode($data);
$urlBase = $options["proto"].$options["host"];

// Получение ссылки на js-скрипт, где есть список параметров POST запроса
$jsUrl = GetRegexValue($page, '#src="(.*?)"#');
if (!$jsUrl)  die("Not found js url in the loaded iframe.");
$jsData = curl($urlBase . $jsUrl);

// Формируем параметры для POST запроса
$postData = array();
$postData["a"] = (int)$options["partner_id"];
$postData["b"] = (int)$options["domain_id"];
$postData["c"] = false;
$postData["e"] = $options["video_token"];
$postData["f"] = $userAgent;
$data4Encrypt = json_encode($postData, JSON_UNESCAPED_SLASHES);

// Вычисление значений iv и key
$jsFunc = GetRegexValue($jsData, '/{\w:this.options.partner_id(.*?)ajax/');
$stringsText  = GetRegexValue($jsFunc, '/,.=\[("[\w=]+","[\w=]+".*?")\]/');
$stringsArray = explode('","', $stringsText);
for ($i=0; $i < count($stringsArray); $i++)
  $stringsArray[$i] = base64_decode($stringsArray[$i]);
$shiftCount   = (int)GetRegexValue($jsFunc, '/}\(.,(\d+)\)/');
while ($shiftCount > 0) {
  array_push($stringsArray, array_shift($stringsArray));
  $shiftCount--;
}
$valuesText  = GetRegexValue($jsFunc, '/;(\w\[[^}{;,]+=.*?);/'); 
$valuesArray = explode(',', $valuesText);
$e = array();
for ($i=0; $i<count($valuesArray); $i++)
  $valuesArray[$i] = EvalValuesInString($valuesArray[$i], $stringsArray, $e);
if (preg_match('|CryptoJS.*?,.*?\((\w+)\),.*?iv:.*?\((\w+)\)|', $jsFunc, $matches)) {
  $var_name_key = $matches[1];
  $var_name_iv  = $matches[2];
  $stringsText  = GetRegexValue($jsFunc, '/var\s.=\[("[\w=]+","[\w=]+".*?")\]/');
  $stringsArray = explode('","', $stringsText);
  for ($i=0; $i < count($stringsArray); $i++)
    $stringsArray[$i] = base64_decode($stringsArray[$i]);
  $a = GetRegexValue($jsFunc, "/[;,\s]$var_name_iv=(.*?)[;,{}]/");
  if ($a) {
    $ptrn = str_replace(['[',']','(',')'], ['\[','\]','\(','\)'], $a);
    if (preg_match("|$ptrn=(.*?)[;,}{]|", $jsFunc, $m))
      $a = $m[1];
  }
  $iv = EvalValuesInString($a, $stringsArray, $e);
  $s = GetRegexValue($jsFunc, "/[;,\s]$var_name_key=(.*?)[;,{}]/");
  if ($s) {
    $ptrn = str_replace(['[',']','(',')'], ['\[','\]','\(','\)'], $s);
    if (preg_match("|$ptrn=(.*?)[;,}{]|", $jsFunc, $m))
      $s = $m[1];
  }
  $key = EvalValuesInString($s, $stringsArray, $e);
} else { $key = "4f47bb738c13e8e3773b6cd9faf45edc3e0b6f7f45f7778f8897f9069c991e16"; $iv  = "5c6d2c36bbfd9b2df2f968aca7bea0ad"; }

// Если вычислить не удалось, используем указанные вручную
if ((strlen($iv)!=32) || (strlen($key)!=64)) {
  $iv  = "5c6d2c36bbfd9b2df2f968aca7bea0ad";
  $key = "4f47bb738c13e8e3773b6cd9faf45edc3e0b6f7f45f7778f8897f9069c991e16";
}

// Шифруем AES cbc PKCS7 Padding
$crypted = openssl_encrypt($data4Encrypt, 'aes-256-cbc', hex2bin($key), 0, hex2bin($iv));
// Делаем POST запрос и получаем список ссылок на потоки
$data = curl($urlBase."/vs", "q=".urlencode($crypted)."&ref=".$options["ref"]);
if (!$data) {
  // Данные защиты устарели, пробуем получить новые
  $ini_text  = file_get_contents("https://github.com/WendyH/PHP-Scripts/raw/master/moon4crack.ini");
  $moon_vals = parse_ini_string($ini_text);
  $iv  = $moon_vals['iv' ];
  $key = $moon_vals['key'];
  $crypted = openssl_encrypt($data4Encrypt, 'aes-256-cbc', hex2bin($key), 0, hex2bin($iv));
  $data = curl($urlBase."/vs", "q=".urlencode($crypted)."&ref=".$options["ref"]);
}

// Делаем из полученных json данных ассоциативный массив PHP
$answerObject = json_decode($data, TRUE);
if ($answerObject["mp4"]) {
  $data = curl($answerObject["mp4" ]);
}
if ($answerObject["m3u8"]) {
  $data1 = curl($answerObject["m3u8"]);
}

// Отдаём полученное
if ($data) {
  echo '<hr><b>Ссылки на mp4 файлы </b><br />';
  $job = json_decode($data,1);
  foreach ($job as $k => $v) {
    echo $k .'<br />'.$v.'<br />';
  }
  echo '<hr>';
}

if ($data1) {
  echo '<b>Ссылки на m3u8 файлы</b><br />';
  if (preg_match_all('~#EXT.*RESOLUTION=(\d+x\d+),.*\s(.*)~',$data1,$link)) {
    $r = $link[1];
    $url = $link[2];
    for ($i=0; $i<count($r); $i++) {
      if ($r[$i]) {
        echo '<b>'.$r[$i].'</b><br />'.$url[$i].'<br />';
      }
    }
    echo '<hr>';
  }
}

if (isset($options["trailer_token"]) && $options["trailer_token"]) {
  echo '<b>Ссылка на трейлер</b><br />
  <iframe src="https://trailerclub.me/video/'.$options["trailer_token"].'/iframe"></iframe>';
}

///////////////////////////////////////////////////////////////////////////////
// Преобразование значений в строке c использованием переданного массива
function EvalValuesInString($line, $stringsArray, &$e) {
  $line = preg_replace_callback('/\w\("*(0x.*?)"*\)/', function ($m) use ($stringsArray) { return '"'.$stringsArray[hexdec($m[1])].'"'; }, $line);
  $line = preg_replace_callback('/\w\.(\w+)/'        , function ($m) use ($stringsArray) { return 'e["'.$m[1].'"]'; }, $line);
  $line = preg_replace_callback('/\w\["*(\w+)"*\]/'  , function ($m) use ($e) { return isset($e[$m[1]]) ? $e[$m[1]] : $m[0]; }, $line);
  $line = preg_replace_callback('/\w\[(\w+)\]/'      , function ($m) use ($e) { return isset($e[$m[1]]) ? $e[$m[1]] : $m[0]; }, $line);
  if (preg_match('/\w[\[\.]"*(\w+)"*\]=(.*)/', $line, $m))
    $e[$m[1]] = str_replace('"', '', $m[2]);
    $line = str_replace(['+', '"'], '', $line);
  return $line;
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

///////////////////////////////////////////////////////////////////////////////
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
