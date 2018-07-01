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

$userAgent = "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36";

// Установка HTTP заголовков
$headers = "Accept-Encoding: gzip, deflate\r\n" .
           "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n" .
           "Referer: " . $url . "\r\n" .
           "User-Agent: $userAgent\r\n";

// Загружаем страницу iframe c moonwalk
$page = LoadPage($url, "GET", $headers);

// Добавляем HTTP заголовки для POST запроса
$headers .= "X-Requested-With: XMLHttpRequest\r\n" .
            "Origin: $urlBase\r\n";

// Поиск дополнительных HTTP заголовков, которые нужно установить
$data = GetRegexValue($page, "#VideoBalancer\((.*?)\);#is");
if (!$data) die("No VideoBalancer info in the loaded iframe.");
$options = JSDecode($data);

// Получение ссылки на js-скрипт, где есть список параметров POST запроса
$jsUrl = GetRegexValue($page, '#src="(.*?)"#');
if (!$jsUrl)  die("Not found js url in the loaded iframe.");

$jsData = LoadPage($urlBase . $jsUrl, "GET", $headers);

// Получаем параметры POST запроса из js-скрипта
$data = GetRegexValue($jsData, "#getVideoManifests.*?\{(.*?\}\);)#is");
if (!$data) die("Function getVideoManifests not found in loaded js.");

$JSONParams = GetRegexValue($data, "#t\s*=\s*({.*?})#is");
if (!$JSONParams) die("Not found json data in getVideoManifests function.");

// Приводим к одному виду ссылки на переменные, присвоенные объетку window
// window["7268338cb2fefca17ebbd2be216fd1de"] -> window.7268338cb2fefca17ebbd2be216fd1de
// чтобы отработала правильно функция JSDecode 
$JSONParams = preg_replace_callback("#window\[['\"](.*?)['\"]\]#", function($m) { return "window.".$m[1]; }, $JSONParams);

// Формируем данные для POST
$postData = JSDecode($JSONParams); $data4Encrypt = "{";
// В цикле перебираем все ключи и значения и формируем json строку
// заменяя все переменные на их значения
foreach ($postData as $name => $value) {
  $val = $value;
  if ($val=="navigator.userAgent")    $val = $userAgent;
  else if (strpos($val, "_mw_adb")>0) $val = "false";
  else if (preg_match("#this.options.(\w+)#", $val, $m)) $val = $options[$m[1]];
  else if (preg_match("#window.(\w+)#", $val, $m)) {
    // Если указана переменная со ссылкой на объект window - пытаемся найти значение в загрузенном html
  	if (preg_match("#window\[['\"]".$m[1]."['\"]]\s*=\s*['\"](.*?)['\"]#", $page  , $matches)) $val = $matches[1];
  	if (preg_match("#window\.".$m[1]."\s*=\s*['\"](.*?)['\"]#"           , $page  , $matches)) $val = $matches[1];
  	if (preg_match("#['\"]".$m[1]."['\"]]\s*=\s*['\"](.*?)['\"]#"        , $jsData, $matches)) $val = $matches[1]; // Если нашли в js, то берём оттуда
  }
  if (!is_numeric($val) && $val!="true" && $val!="false") $val = '"'.$val.'"'; // Если значение не число - обрамляем кавычками
  if ($data4Encrypt != "{") $data4Encrypt .= ","; // Если это не первая пара - добавляем запятую
  $data4Encrypt .= '"'.$name.'":'.$val;           // Добавляем пару "имя":значение
}
$data4Encrypt .= "}"; // Закончили формировать json данные для шифрования

$iv  = GetRegexValue($data, "#\bn=['\"](.*?)['\"]#");
$key = GetRegexValue($data, "#\be=['\"](.*?)['\"]#");
$iv  = "e080ee12a6b39ad18309bc89d5097b77"; // snx 2 spell

// Шифруем AES cbc PKCS7 Padding
$crypted = openssl_encrypt($data4Encrypt, 'AES-256-CBC', hex2bin("7316d0c4".$key), 0, hex2bin($iv));

// Делаем POST запрос и получаем список ссылок на потоки
$data = LoadPage($urlBase . "/vs", "POST", $headers, "q=".urlencode($crypted));

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
