<?php 
ob_start("ob_gzhandler");          // Говорим клиенту, что можем и в сжатом виде отдавать
/*
Для работы этого скрипта, в файл .htaccess добавить строки:
RewriteBase
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /stepashka.php?$1 [NC,L,QSA]
*/
$host  = "online.stepashka.com";   // Сайт, который мы хотим посмотреть
$query = $_SERVER["QUERY_STRING"]; // Запрос - имя запрашиваемой страницы на сайте

// Берём домен из значения хоста (для его замены в куках на свой)
$aNames = explode('.', $host);
$domain = $aNames[Count($aNames)-2].".".$aNames[Count($aNames)-1];

// Притворяемся браузером, передаём куки, подменяем Referer'а
$opts = array(
  'http'=>array(
    'method'=>"GET",
    'header'=>"Referer: " . str_replace($_SERVER["HTTP_HOST"], $host, $_SERVER["HTTP_REFERER"]) . "\r\n" .
              "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.102 Safari/537.36\r\n" .
              "Cookie: " . $_SERVER["HTTP_COOKIE"] . "\r\n"
  )
);
$context = stream_context_create($opts);

// Делаем запрос страницы сайта с подготовленными выше параметрами
$data = file_get_contents("http://".$host."/".$query, false, $context);

$data = ReplacePlayerInThePage($data); // Приколюха: заменим плеер степашки на iframe c плеером pirateplayer

// Подменяем в содержимом значения ссылок на наш домен
$data = str_replace($host, $_SERVER["HTTP_HOST"], $data);
if (preg_match_all('#http://([^>"\']+'.$domain.')#is', $data, $matches)) {
  $aHosts = array_unique($matches[1]);
  foreach($aHosts as $m) $data = str_replace($m, $_SERVER["HTTP_HOST"].'/?http://'.$m, $data);
}

//     Подготавливаем заголовки для отдачи полученного содержимого

// Все возвращённые заголовки при запросе на сайт устанавливаем здесь, кроме следующих:
$aSkipHeaders = array('Content-Encoding', 'Transfer-Encoding', 'HTTP', 'Content-Length', 'X-Powered-By', 'Server');
// Цикл обхода всех полученных ранее заголовков и их установка в текущей сессии
foreach($http_response_header as $head) {
  $skip = false;
  // Если заголовок входит в массив пропускаемых, говорим чтобы пропускал
  foreach($aSkipHeaders as $val) { $skip = (strpos($head, $val)===0); if ($skip) break; }
  if ($skip) continue;
  // Для установки куков, устанавливаем второй параметр (не заменять) и подменяем домен на свой
  if (substr($head, 0, 11)=="Set-Cookie:") 
    header(str_replace($domain, $_SERVER["HTTP_HOST"], $head), false); 
  else header($head);
}

echo $data; // Отдаём содержимое запроса

// -----------------------------------------------------------------------------
// Ищем, раскодируем данные и заменяем встроенный плеер на iframe c плеером pirateplayer
function ReplacePlayerInThePage($data) {
  $player1 = '';
  $player2 = '';
  if (preg_match('#(<object[^>]+class="player".*?</object>)#is', $data, $m)) $player1 = $m[1];
  if (preg_match('#st=(http:.*?)"#is', $data, $m)) {
    $json = DecodeUppodText(file_get_contents($m[1]));
    if (preg_match('#(<iframe.*?</iframe>)#is', $json, $m)) $player2 = $m[1];
  }
  if ($player1 && $player2) $data = str_replace($player1, $player2, $data);
  return $data;
}
// -----------------------------------------------------------------------------
// Раскодируем данные зашифрованных json данных
function DecodeUppodText($data) {
  $a = array("G", "d", "R", "0", "M", "Y", "4", "v", "6", "u", "t", "i", "f", "c", "s", "l", "B", "5", "n", "2", "V", "Z", "J", "m", "L", "=");
  $b = array("1", "w", "Q", "o", "9", "U", "a", "N", "x", "D", "X", "7", "z", "H", "y", "3", "e", "g", "T", "W", "b", "8", "k", "I", "p", "r");
  for ($i=0; $i<count($a); $i++) {
    $data = str_replace($b[$i], "__"  , $data);
    $data = str_replace($a[$i], $b[$i], $data);
    $data = str_replace("__"  , $a[$i], $data);
  }
  return base64_decode($data);
}