<?php
ini_set("log_errors", 1); ini_set("error_log", $_SERVER['SCRIPT_FILENAME'].".log"); ini_set('error_reporting', E_ALL); ini_set("display_errors", 1);
$urlBase = "http://moonwalk.cc";

// Получение ссылки на видео c moonwalk в переданных параметрах, а также тип получаемого потока.
$url     = isset($_REQUEST['url'    ]) ? $_REQUEST['url' ] : ""    ; // moonwalk.cc iframe url
$type    = isset($_REQUEST['type'   ]) ? $_REQUEST['type'] : "m3u8"; // tyle of link (f4m, m3u8, dash)
$urlonly = isset($_REQUEST['urlonly']); // Флаг, сигнализирующий отдавать ссылку на плейлист, а не само его содержимое
$attacha = isset($_REQUEST['at'     ]); // Флаг, сигнализирующий отдавать плейлист как прикреплённый файл с расширением

if (!$url) die("No moonwalk iframe url in the parameters.");

$cookies = array();

// Установка HTTP заголовков
$headers = "Accept-Encoding: gzip, deflate\r\n" .
           "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n" .
           "Referer: " . $url . "\r\n" .
           "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.99 Safari/537.36\r\n";

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

// Устанавливаем дополнительные заголовки и их значения
$data = GetRegexValue($jsData, "#headers:({.*?})#is");
if ($data) {
  $headersArr = JSDecode($data);
  foreach ($headersArr as $key => $value) {
    $val = $value;
    $var = GetRegexValue($val, "#this.options.(\w+)#");
    if ($var) $val = $options[$var];
    $headers .= $key . ": " . $val . "\r\n";
  }
}

// Получаем параметры POST запроса из js-скрипта
$data = GetRegexValue($jsData, "#var\s+\w+=(\{mw_key.*?\})#is");
if (!$data) die("POST parameters not found in loaded js.");

// Формируем данные для POST
$postData = JSDecode($data); $post = "";
foreach ($postData as $key => $value) {
  $val = $value;
  $var = GetRegexValue($val, "#this.options.(\w+)#");
  $tmp = GetRegexValue($val, "#(this.options.\w+)#");
  if ($var) $val = str_replace($tmp, $options[$var], $val);
  $var = GetRegexValue($val, "#\w+\.(\w+)#");
  if ($var && preg_match("#window\.".$var."\s*=\s*['\"](.*?)['\"]#", $page, $matches)) {
    $val = $matches[1];
  }
  if ($val=="e._mw_adb") $val="false";
  $post .= $key . "=" . $val . "&";
}
// Get global variable
if (preg_match("#window\['(\w+)'\]\s*=\s*'(\w+)'#", $page, $m1))
  if (preg_match('#n\["(\w+)"\]\s*=\s*\w+\["'.$m1[1].'#', $jsData, $m2))
    $post .= $m2[1] . "=" . $m1[2];

$link = $urlBase . "/manifests/video/" . $options["video_token"] . "/all";

// Делаем POST запрос и получаем список ссылок на потоки
$data = LoadPage($link, "POST", $headers, $post);

if ($type=="json") die($data);

// Делаем из полученных json данных ассоциативный массив PHP
$answerObject = json_decode($data, TRUE);

// Получаем значение ссылки нужного типа потока (по-умолчанию: m3u8)
$link = "";
if (isset($answerObject["mans"])) $link = $answerObject["mans"]["manifest_".$type];

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

////////////////////////////////////////////////////////////////////
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
