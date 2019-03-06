<?php
ini_set("log_errors", 1); ini_set("error_log", $_SERVER['SCRIPT_FILENAME'].".log"); ini_set('error_reporting', E_ALL); ini_set("display_errors", 1);
$urlBase = "http://moonwalk.cc";

// Получение ссылки на видео c moonwalk в переданных параметрах, а также тип получаемого потока.
$url     = isset($_REQUEST['url'    ]) ? $_REQUEST['url' ] : ""    ; // moonwalk.cc iframe url
$type    = isset($_REQUEST['type'   ]) ? $_REQUEST['type'] : "m3u8"; // tyle of link (m3u8, mp4)
$urlonly = isset($_REQUEST['urlonly']); // Флаг, сигнализирующий отдавать ссылку на плейлист, а не само его содержимое
$attacha = isset($_REQUEST['at'     ]); // Флаг, сигнализирующий отдавать плейлист как прикреплённый файл с расширением

if (!$url) die("No moonwalk iframe url in the parameters.");

$cookies = array();

$userAgent = "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36";

// Установка HTTP заголовков
$headers = "Accept-Encoding: gzip, deflate, br\r\n" .
           "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n" .
           "Referer: " . $url . "\r\n" .
           "User-Agent: $userAgent\r\n";

// Загружаем страницу iframe c moonwalk
$page = LoadPage($url, "GET", $headers);

// Добавляем HTTP заголовки для POST запроса
$headers .= ":authority: moonwalk.cc\r\n";

// Поиск дополнительных HTTP заголовков, которые нужно установить
$data = GetRegexValue($page, "#VideoBalancer\((.*?)\);#is");
if (!$data) $page = LoadPage($url, "GET", $headers);
$data = GetRegexValue($page, "#VideoBalancer\((.*?)\);#is");
if (!$data) die("No VideoBalancer info in the loaded iframe.");
$options = JSDecode($data);
$urlBase = $options["proto"].$options["host"];
// Получение ссылки на js-скрипт, где есть список параметров POST запроса
$jsUrl = GetRegexValue($page, '#src="(.*?)"#');
if (!$jsUrl)  die("Not found js url in the loaded iframe.");

$jsData = LoadPage($urlBase . $jsUrl, "GET", $headers);

// Формируем параметры для POST запроса
$postData = array();
$postData["a"] = (int)$options["partner_id"];
$postData["b"] = (int)$options["domain_id"];
$postData["c"] = false;
$postData["e"] = $options["video_token"];
$postData["f"] = $userAgent;

$data4Encrypt = json_encode($postData, JSON_UNESCAPED_SLASHES);

// Вычисление значений iv и key
$jsFunc = GetRegexValue($jsData, '/{\w:this.options.partner_id(.*?)ajax/'); // Получаем текст функции getVideoManifests
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
  $a = GetRegexValue($jsFunc, "/[;,\s]$var_name_iv=(.*?)[;,{}]/"); // текст присвоения значения
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
} else { $key = "1e00f246ede198f09dbb77f500a7a0c911adb668093bd166ef43b2e2fd388c9d"; $iv  = "568e1bde13d63f770a568e08123f5b69"; }

// Если вычислить не удалось, используем указанные вручную
if ((strlen($iv)!=32) || (strlen($key)!=64)) {
  $iv  = "568e1bde13d63f770a568e08123f5b69";
  $key = "1e00f246ede198f09dbb77f500a7a0c911adb668093bd166ef43b2e2fd388c9d";
}

// Шифруем AES cbc PKCS7 Padding
$crypted = openssl_encrypt($data4Encrypt, 'aes-256-cbc', hex2bin($key), 0, hex2bin($iv));

// Делаем POST запрос и получаем список ссылок на потоки
$data = LoadPage($urlBase . "/vs", "POST", $headers, "q=".urlencode($crypted)."&ref=".$options["ref"]);

if (!$data) {
  // Данные защиты устарели, пробуем получить новые
  $ini_text  = file_get_contents("https://github.com/WendyH/PHP-Scripts/raw/master/moon4crack.ini");
  $moon_vals = parse_ini_string($ini_text);
  $iv  = $moon_vals['iv' ];
  $key = $moon_vals['key'];
  $crypted = openssl_encrypt($data4Encrypt, 'aes-256-cbc', hex2bin($key), 0, hex2bin($iv));
  $data = LoadPage($urlBase . "/vs", "POST", $headers, "q=".urlencode($crypted)."&ref=".$options["ref"]);
}

if ($type=="json") die($data);

// Делаем из полученных json данных ассоциативный массив PHP
$answerObject = json_decode($data, TRUE);

// Получаем значение ссылки нужного типа потока (по-умолчанию: m3u8)
$link = "";
if (isset($answerObject["mp4" ]) && $type=="mp4") $link = $answerObject["mp4" ];
if (isset($answerObject["m3u8"]) && $link==""   ) $link = $answerObject["m3u8"];

// Если ссылка с таким типом есть, получаем содержимое плейлиста/манифеста
if ($link) {
    if ($urlonly) 
        $data = $link;
    else {
//        if      ($type=="m3u8") header("Content-Type: application/vnd.apple.mpegurl");
//        else if ($type=="f4m" ) header("Content-Type: application/xml");
        $data = LoadPage($link, "GET", $headers);
        if ($attacha) {
            header("Content-Length: ".strlen($data));
            header("Content-Disposition: attachment; filename=play.$type");
        }
    }
} 

// Отдаём полученное
echo $data;

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
function LoadPage($url, $method, $headers, $data='') {
    global $cookies;

    // Если есть кукисы - добавляем их значения в HTTP заголовки
    $coo = "";
    foreach($cookies as $key => $val) $coo .= $key."=".urlencode($val)."; ";
    if ($coo) $headers .= "Cookie: $coo\r\n";

    $options = array();
    $options['http'] = array('method' => $method ,
                             'header' => $headers,
                             'content'=> $data   );
    $context = stream_context_create($options);
    $page    = file_get_contents($url, false, $context);
    // Перебираем HTTP заголовки ответа, чтобы установить кукис
    foreach($http_response_header as $c => $h) {
        if (stristr($h, 'content-encoding') and stristr($h, 'gzip')) {
            $page = gzdecode($page);
        } else if (preg_match('#^Set-Cookie:\s*([^;]+)#', $h, $matches)) {
            parse_str($matches[1], $tmp);
            $cookies += $tmp;
        }
    }
    return $page;
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
