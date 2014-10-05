<?php // Script By WendyH [06.02.2013] Copyleft.
ini_set("log_errors", 1); ini_set("error_log", $_SERVER['SCRIPT_FILENAME'].".log"); ini_set('error_reporting', E_ALL); ini_set("display_errors", 0);
set_time_limit(30);
$outImgDir      = 'img_preview';     // Каталог сохранения созданных изображений (cache)
$cacheSizeLimit = 200 * 1024 * 1024; // Максимальный размер папки кэша (первое число - мегабайты)
$fontDir        = './ttf';
$backgroundDir  = './backgrounds';
$notEnd  = false;
$lastPos = 0;

CheckCacheSizeLimit(); // Проверка размера кеша

// Параметры, которые могут быть переданы нашему скрипту
$fz       = isset($_REQUEST['fz'])       ? (int)$_REQUEST['fz']:  1;  // Номер набора параметров для размеров шрифтов (0, 1, 2, ...)
$urlPic   = isset($_REQUEST['urlpic'])   ? $_REQUEST['urlpic'] : "";  // Адрес картинки (постера), добавляемой в вверхний левый угол
$title    = isset($_REQUEST['title'])    ? $_REQUEST['title']  : "";  // Заголовок  (название фильма)
$info     = isset($_REQUEST['info'])     ? $_REQUEST['info']   : "";  // Информация (год, режиссёр, страна...)
$categ    = isset($_REQUEST['categ'])    ? $_REQUEST['categ']  : "";  // Категории  (строка категорий фильма)
$descr    = isset($_REQUEST['descr'])    ? $_REQUEST['descr']  : "";  // Описание   (любой текст, самый большой блок)
$prfx     = isset($_REQUEST['prfx'])     ? $_REQUEST['prfx']   : "";  // Префикс создаваемых файлов
$wPic     = isset($_REQUEST['wpic'])     ? (int)$_REQUEST['wpic']    : 0; // Ширина постера
$hPic     = isset($_REQUEST['hpic'])     ? (int)$_REQUEST['hpic']    : 0; // Высота постера
$xPic     = isset($_REQUEST['xpic'])     ? (int)$_REQUEST['xpic']    : 0; // "x" координата постера (смещение слева)
$yPic     = isset($_REQUEST['ypic'])     ? (int)$_REQUEST['ypic']    : 0; // "y" координата постера (смещение сверху)
$nocache  = isset($_REQUEST['nocache'])  ? (int)$_REQUEST['nocache'] : 0; // Если параметр = 1, не брать картинку из кэша, а по-любому заного формировать
$fx       = isset($_REQUEST['fx'])       ? (int)$_REQUEST['fx'] : 1; // Номер эффекта: 0-никакого, 1-размытие, 2-сильное размытие, 3-что-то ещё
$fk       = isset($_REQUEST['fk'])       ? floatval($_REQUEST['fk']) : 1; // Коэффициент размера шрифтов
$imgQual  = isset($_REQUEST['q']) ? (int)$_REQUEST['q'] : 100; // Качество картинки в процентах
$imWidth  = isset($_REQUEST['w']) ? (int)$_REQUEST['w'] : 0; // Принудительное задание ширины результирующей картинки с информацией
$imHeight = isset($_REQUEST['h']) ? (int)$_REQUEST['h'] : 0; // Принудительное задание высоты результирующей картинки с информацией
															 // Если ширина и высота не заданы - параметры берутся с файла фона $background
$background      = isset($_REQUEST['bg']) ? $_REQUEST['bg'] : $backgroundDir."/i1d.jpg";   // Файл фона
$fzTitle  = isset($_REQUEST['fztitle'])   ? (int)$_REQUEST['fztitle'] : 0; // Размер шрифта блока Title
$fzInfo   = isset($_REQUEST['fzinfo' ])   ? (int)$_REQUEST['fzinfo' ] : 0; // Размер шрифта блока Info
$fzCateg  = isset($_REQUEST['fzcateg'])   ? (int)$_REQUEST['fzcateg'] : 0; // Размер шрифта блока Categ
$fzDescr  = isset($_REQUEST['fzdescr'])   ? (int)$_REQUEST['fzdescr'] : 0; // Размер шрифта блока Descr
$mlTitle  = isset($_REQUEST['mltitle'])   ? (int)$_REQUEST['mltitle'] : 0; // Максимальное количество строк блока Title
$mlInfo   = isset($_REQUEST['mlinfo' ])   ? (int)$_REQUEST['mlinfo' ] : 0; // Максимальное количество строк блока Info
$mlCateg  = isset($_REQUEST['mlcateg'])   ? (int)$_REQUEST['mlcateg'] : 0; // Максимальное количество строк блока Categ
$mlDescr  = isset($_REQUEST['mldescr'])   ? (int)$_REQUEST['mldescr'] : 0; // Максимальное количество строк блока Descr
$fTitle   = isset($_REQUEST['ftitle'])   ? $_REQUEST['ftitle'] : ''; // Шрифт блока Title (имя ttf файла в папке $fontDir)
$fInfo    = isset($_REQUEST['finfo' ])   ? $_REQUEST['finfo' ] : ''; // Шрифт блока Info
$fCateg   = isset($_REQUEST['fcateg'])   ? $_REQUEST['fcateg'] : ''; // Шрифт Categ
$fDescr   = isset($_REQUEST['fdescr'])   ? $_REQUEST['fdescr'] : ''; // Шрифт блока Descr
$pxTitle   = isset($_REQUEST['xtitle']) ? (int)$_REQUEST['xtitle'] : 0; // Координата X блока Title (принудительное значение)
$pxInfo    = isset($_REQUEST['xinfo' ]) ? (int)$_REQUEST['xinfo' ] : 0; // Координата X блока Info
$pxCateg   = isset($_REQUEST['xcateg']) ? (int)$_REQUEST['xcateg'] : 0; // Координата X Categ
$pxDescr   = isset($_REQUEST['xdescr']) ? (int)$_REQUEST['xdescr'] : 0; // Координата X блока Descr
$pyTitle   = isset($_REQUEST['ytitle']) ? (int)$_REQUEST['ytitle'] : 0; // Координата Y блока Title (принудительное значение)
$pyInfo    = isset($_REQUEST['yinfo' ]) ? (int)$_REQUEST['yinfo' ] : 0; // Координата Y блока Info
$pyCateg   = isset($_REQUEST['ycateg']) ? (int)$_REQUEST['ycateg'] : 0; // Координата Y блока Categ
$pyDescr   = isset($_REQUEST['ydescr']) ? (int)$_REQUEST['ydescr'] : 0; // Координата Y блока Descr
$noPicWrap = isset($_REQUEST['nw']) ? (int)$_REQUEST['nw'] : 0;  // Флаг, если установлен - не обтекать постер при выводе текста
$xMargin   = isset($_REQUEST['xm']) ? (int)$_REQUEST['xm'] : 7;  // Отступ текста слева  и права от края картинки
$yMargin   = isset($_REQUEST['ym']) ? (int)$_REQUEST['ym'] : 10; // Отступ текста сверху и снизу от края картинки
$colorText = isset($_REQUEST['ct']) ?  "#".$_REQUEST['ct'] : ""; // Цвет текста
if ($colorText) {
  $colorTitle= $colorText;
  $colorInfo = $colorText;
  $colorCateg= $colorText;
  $colorDescr= $colorText;
} else {
  $colorTitle= "#FFDFC0";
  $colorInfo = "#AEE0E9";
  $colorCateg= "#F9BF86";
  $colorDescr= "#D2EEF3";
}
$colorTitle= isset($_REQUEST['ctitle']) ? "#".$_REQUEST['ctitle'] : $colorTitle; // Цвет текста Title
$colorInfo = isset($_REQUEST['cinfo' ]) ? "#".$_REQUEST['cinfo' ] : $colorInfo;  // Цвет текста Info
$colorCateg= isset($_REQUEST['ccateg']) ? "#".$_REQUEST['ccateg'] : $colorCateg; // Цвет текста Categ
$colorDescr= isset($_REQUEST['cdescr']) ? "#".$_REQUEST['cdescr'] : $colorDescr; // Цвет текста Descr
$noShadow  = isset($_REQUEST['ns']) ? (int)$_REQUEST['ns'] : 0; // Не отбрасывать тень

$firstCharsAlign = isset($_REQUEST['ta']) ? (int)$_REQUEST['ta'] : 0; // Количество символов, по которому выравниваются переносы
$thumbName  = ''; if ($imgQual>100) $imgQual = 100;

// Проверяем, не передано ли имя картинки заднего фона как ссылка
if (substr($background, 0, 4)=='http') {
  $file = str_replace('http://', '', $background);       // Создаём имя для локального хранения
  $file = $backgroundDir."/".str_replace('/', '-', $file); 
  if (!file_exists($file) || (filesize($file)<100)) {    // Проверяем, не скачал ли он уже
    file_put_contents($file, fopen($background, 'r'));   // Если нет, скачиваем
  }
  $background = $file;                                   // Картинка долна иметь локальное имя файла
}

//if(!file_exists($background)) MyExit("Error! background image file does not exist at '".$background."'"); // Нету файла фона - не будет ничего.
// Если нам передали текст не в UTF, конвертируем его из кодовой страницы 1251 в UTF-8 (это для одного случая, передавайте в UTF-8)
if (!preg_match('#.#u', $info )) $info  = iconv("cp1251", "UTF-8", $info );
if (!preg_match('#.#u', $title)) $title = iconv("cp1251", "UTF-8", $title);
if (!preg_match('#.#u', $descr)) $descr = iconv("cp1251", "UTF-8", $descr);
if (!preg_match('#.#u', $categ)) $categ = iconv("cp1251", "UTF-8", $categ);
// Создаём ID файла будущей картинки с информацией - префикс + MD5 хеш всех параметров
$idPart1 = md5($urlPic.$title.$info.$descr.$categ.$wPic.$hPic.$xPic.$yPic.$imHeight.$imWidth.$background.$fz.$firstCharsAlign);
$idPart2 = md5($fTitle.$fInfo.$fCateg.$fDescr.$pxTitle.$pxInfo.$pxCateg.$pxDescr.$pyTitle.$pyInfo.$pyCateg.$pyDescr.$noPicWrap);
$cacheId = $prfx."_".md5($idPart1.$idPart2.$fx.$fk.$fzTitle.$fzInfo.$fzCateg.$fzDescr.$mlTitle.$mlInfo.$mlCateg.$mlDescr.$xMargin.$yMargin.$noShadow.$colorTitle.$colorInfo.$colorCateg.$colorDescr);
$image_file = $outImgDir."/".$cacheId.".jpg"; // Полное имя файла картинки
$bDontSaveCache = false;

// Если есть такой файл, его длина равна 0 и он создан уже больше минуты назад
// то удаляем его - это остаток другого недовыполненого скрипта
if (file_exists($image_file)) if ((filesize($image_file)==0) && ((time()-filemtime($image_file))>60)) unlink($image_file);

// Проверяем, нет ли уже файла с таким именем?
if (!$nocache AND file_exists($image_file)) {
  // Если файл равен нулю или просто слишком мал, значит уже выполняется другой скрипт его обрабатывающий.
  // Поэтому ждём максимум 30 сек, пока тот скрипт закончит своё дело
  for ($i=0; $i<30; $i++) if (filesize($image_file)<20000) sleep(1); else break;
  // А потом отдаём ссылку на этот файл
  toGiveTheFile($image_file, "image/jpeg", false);
}
// Если файла нет с таким ID - создаём сразу же его с нулевым размером - "столбим место",
// давая другим скриптам (паралельно запускающимся) понять, что над ним уже работают.
if (!$bDontSaveCache) touch($image_file);
  if (trim($urlPic.$title.$info.$descr.$categ)=='') { // Тест
    $urlPic = "http://www.hdkinoteatr.ru/uploads/posts/2012-11/kp50ae5ffa6564d.jpg";
    $title  = "Ёлка - ёлка! IMDb 6.1 [1 032] (1983)";
    $info   = "Ёлкин Продюсер: Георгий Малков, Сарик Андреасян, Гевонд Андреасян";
    $descr  = "Композитор: Илья Духовный, Дарин Сысоев
Премьера: 2012-03-01 Бюджет: $2 000 000
Рейтинг: КиноПоиск 7.3 votes: (12 431), IMDb 6.1 votes: (187)
Время: 103 мин.
В ролях: Сергей Безруков, Ёлкин дом, Дмитрий Дюжев, Михаил Пореченков, Егор Бероев, Гоша Куценко, Петр Федоров, Федор Добронравов, Иван Добронравов, Игорь Верник, Александр Олешко, Анастасия Заворотнюк, Светлана Ходченкова, Елена Корикова, Людмила Артемьева
Каждый год в день 8-го марта телефонные сети России передают миллионы звонков и смс-сообщений. Трудолюбивые аналитики подсчитали, что абсолютное большинство телефонных звонков адресовано самым главным женщинам в жизни каждого человека — мамам.
Мы выросли, и заготовленные заранее аппликации и букеты цветов, купленные до маминого пробуждения, — давно забытые детские привычки. Отныне мы поздравляем мам по-взрослому: дожидаемся, когда они проснутся, и набираем телефонный номер.
Но что, если случится коллапс, и из-за пиковой перегруженности телефонная сеть рухнет? Итак, связи нет. Что делать? Кто-то будет ждать, пока её восстановят — поздравит вечером или даже на следующий день. А другие, как и герои нашего киноальманаха, поменяют свои планы, чтобы поздравить своих мам именно сегодня… лично… как в детстве!
Восемь разных поздравлений и жизненных ситуаций. Восемь разных МАМ.";
    $categ  = "Категории: драма, комедия, ужасы";
  }
  $imPoster  = null;
  // Шрифты по-умолчанию
  $fontTitle = $fontDir."/AGFriquer_Bold.ttf";
//$fontTitle = $fontDir."/TXB75.TTF";                    // Шрифт заголовка
  $fontInfo  = $fontDir."/PentaBold.ttf";                // Шрифт блока информации
  $fontCateg = $fontDir."/DejaVuSansMono.ttf";           // Шрифт строки категорий
  $fontDescr = $fontDir."/Univers_Condensed_Medium.ttf"; // Шрифт текста описания
  // $fz - Номер набора размеров шрифтов, максимального количества строк и проч.
  if ($fz==0) {
    // 0 - более мелкие шрифты, с небольшим размером картинки
    $sizeTitle = 48; $linesTitle = 2;
    $sizeInfo  = 30; $linesInfo  = 2;
    $sizeCateg = 26; $linesCateg = 1;
    $sizeText  = 34; $linesDescr = 26;
    if ($wPic==0) $wPic = 240; // Если не указана конкретная ширина постера в параметрах, задаём
  } elseif ($fz==1) {
    // 1 - Шрифты среднего размера, со средним размером картинки
    $sizeTitle = 56; $linesTitle = 2;
    $sizeInfo  = 38; $linesInfo  = 3;
    $sizeCateg = 30; $linesCateg = 1;
    $sizeText  = 40; $linesDescr = 20;
    if ($wPic==0) $wPic = 320;
  } elseif ($fz==2) {
    // 2 - Шрифты большего размера, со средним размером картинки
    $sizeTitle = 60; $linesTitle = 2;
    $sizeInfo  = 44; $linesInfo  = 3;
    $sizeCateg = 38; $linesCateg = 1;
    $sizeText  = 52; $linesDescr = 18;
    if ($wPic==0) $wPic = 280;
  } elseif ($fz==3) {
//  $fontTitle = $fontDir."/DejaVuSansMono-Bold.ttf";
    $sizeTitle = 54; $linesTitle = 2;
    $sizeInfo  = 30; $linesInfo  = 2;
    $sizeCateg = 30; $linesCateg = 1;
    $sizeText  = 34; $linesDescr = 26;
    if ($wPic==0) $wPic = 240;
  } elseif ($fz==4) {
    $sizeTitle = 60; $linesTitle = 2;
    $sizeInfo  = 42; $linesInfo  = 1;
    $sizeCateg = 30; $linesCateg = 0;
    $sizeText  = 32; $linesDescr = 32;
    if ($wPic==0) $wPic = 240;
  } elseif ($fz==5) {
    $sizeTitle = 60; $linesTitle = 0;
    $sizeInfo  = 42; $linesInfo  = 0;
    $sizeCateg = 30; $linesCateg = 0;
    $sizeText  = 32; $linesDescr = 48;
    if ($wPic==0) $wPic = 240;
  } elseif ($fz==6) {
    $sizeTitle = 60; $linesTitle = 99;
    $sizeInfo  = 42; $linesInfo  = 99;
    $sizeCateg = 30; $linesCateg = 99;
    $sizeText  = 49; $linesDescr = 99;
    if ($wPic==0) $wPic = 240;
  }
  $sizeTitle = round($sizeTitle * $fk); $linesTitle = round($linesTitle * $fk);
  $sizeInfo  = round($sizeInfo  * $fk); $linesInfo  = round($linesInfo  * $fk);
  $sizeCateg = round($sizeCateg * $fk); $linesCateg = round($linesCateg * $fk);
  $sizeText  = round($sizeText  * $fk); $linesDescr = round($linesDescr * $fk);
  if ($fzTitle) $sizeTitle = $fzTitle;
  if ($fzInfo ) $sizeInfo  = $fzInfo ;
  if ($fzCateg) $sizeCateg = $fzCateg;
  if ($fzDescr) $sizeText  = $fzDescr;
  if ($mlTitle) $linesTitle = $mlTitle;
  if ($mlInfo ) $linesInfo  = $mlInfo ;
  if ($mlCateg) $linesCateg = $mlCateg;
  if ($mlDescr) $linesText  = $mlDescr;
  if (strpos("TXB75", $fontTitle)) $title = str_replace("ё", "е", str_replace("Ё", "Е", $title)); // К сожалению, в шрифте нет ё

  // Если есть ссылка на фотографию, то скачиваем её и подгоняем размер под нам нужный
  if ($urlPic) {
    $thumbName = $image_file.".thumb.jpg";              // Формируем имя временного файла постера
    if (file_exists($thumbName)) $urlPic = $thumbName;  // Если такой есть локально, то зачем качать из интернета?
    $thumbContent = file_get_contents($urlPic);         // Получаем содержимое постера
    if ($thumbContent) {
      if ($urlPic <> $thumbName) file_put_contents($thumbName, $thumbContent); // Если небыл сохранён локально - сохраняем
      $imPoster = new Imagick($thumbName); // Создаём картинку с помощью ImageMagick из сохранённого локально файла
      $imPoster->thumbnailImage($wPic, 0); // Изменяем пропорционально (умещаем по ширине)
      $xPic = $xPic + (($wPic - $imPoster->getImageWidth ()) / 2); 
      $wPic = $imPoster->getImageWidth (); // Получаем реальный размер
      $hPic = $imPoster->getImageHeight(); // 
      unlink($thumbName); // Удаляем сохранённую картинку (зачем она, когда у нас будет готовый кеш?)
    }
  }
try {
  // Создаём картинку с помощью ImageMagick из файла фона ($background)
  $im = new Imagick($background);
  $im->setImageCompressionQuality($imgQual);
  if (($imWidth+$imHeight)>0) $im->resizeImage($imWidth, $imHeight, Imagick::FILTER_LANCZOS, 1);
  $width =$im->getImageWidth (); 
  $height=$im->getImageHeight();
  if (!$info) {$linesDescr+= $linesInfo; $linesInfo = 0;} // Если нет текста блока $info, увеличиваем размер блока описания
  // Устанавливаем координаты блоков текста, в зависимости от размеров фотографии
  $xTitle = $xMargin; // Координата "x" всех блоков равна отступу от края
  $yTitle = $yMargin; // Отступ заголовка сверху от края
  $xInfo  = $xMargin;
  $yInfo  = $yTitle + $linesTitle * $sizeTitle + 10; // Координата "y" блока информации от заголовка сверху
  $xCateg = $xMargin;
  $yCateg = $yInfo  + $linesInfo  * $sizeInfo  +  5; // Координата "y" блока категорий от облока информации
  $xText  = $xMargin;
  $yText  = $yCateg + $linesCateg * $sizeCateg +  8; // Координата "y" блока описаня от облока категорий
  $stored_xPic = $xPic; $stored_yPic = $yPic;
  if ($pxTitle) $xTitle = $pxTitle; if ($pyTitle) $yTitle = $pyTitle;
  if ($pxInfo ) $xInfo  = $pxInfo ; if ($pyInfo ) $yInfo  = $pyInfo ;
  if ($pxCateg) $xCateg = $pxCateg; if ($pyCateg) $yCateg = $pyCateg;
  if ($pxDescr) $xText  = $pxDescr; if ($pyDescr) $yText  = $pyDescr;
  if ($fTitle) $fontTitle = $fTitle;
  if ($fInfo ) $fontInfo  = $fInfo ;
  if ($fCateg) $fontCateg = $fCateg;
  if ($fDescr) $fontDescr = $fDescr;
  if ($noPicWrap) { $xPic = 0; $yPic = 0; $hPic = 0; $wPic = 0; }

  // Массив блоков текста с параметрами шрифтов, цвета, координат и максимального количества линий
  $aBlocks = array(
    //    Text    Font        FontSize    Color   Spacing  x        y     Maximum lines
    array($title, $fontTitle, $sizeTitle, $colorTitle, 1, $xTitle, $yTitle, $linesTitle),
    array($info,  $fontInfo,  $sizeInfo,  $colorInfo , 1, $xInfo,  $yInfo,  $linesInfo ),
    array($categ, $fontCateg, $sizeCateg, $colorCateg, 1, $xCateg, $yCateg, $linesCateg),
    array($descr, $fontDescr, $sizeText,  $colorDescr, 1, $xText,  $yText,  $linesDescr, $firstCharsAlign),
  );
    
  // Вывод на фон всех текстовых блоков (последний параметр функции true - значит это тень - серый цвет и сдвинуто вниз вправо)
  if (!$noShadow)
    whPtintVideoInfo($im, $aBlocks, $wPic, $hPic, $xPic, $yPic, true);
  if      ($fx==1) $im->blurImage(3, 3); // Размываем фон вместе с выведенным серым текстом - тенью
  else if ($fx==2) $im->blurImage(5, 7);
  else if ($fx==3) $im->motionBlurImage(3, 3, 3);
  else if ($fx==4) $im->radialBlurImage(33);
  $xPic = $stored_xPic; $yPic = $stored_yPic;
  // Если была ссылка на фотографию - выводим (накладываем) по заданным координатам
  if ($imPoster) $im->compositeImage($imPoster, Imagick::COMPOSITE_DEFAULT, $xPic, $yPic);
  if ($noPicWrap) { $xPic = 0; $yPic = 0; $hPic = 0; $wPic = 0; }
  // Вывод на фон всех текстовых блоков уже в обычных своих цветах по своим координатам
  whPtintVideoInfo($im, $aBlocks, $wPic, $hPic, $xPic, $yPic);
  // Всё - записываем на диск получившуюся картиrнку со всей информацией.
  if (!$bDontSaveCache) {
    $im->writeImage($image_file);
    if ($notEnd) file_put_contents($image_file . '_lp.txt', $lastPos);
  }

  // Выводим картинку (если параметры были переданы методом POST - отдаём ссылку на файл картинки)
  if (isset($_POST['title'])) {
    if ($notEnd) $image_file .= '?lastpos='.$lastPos;
    echo 'http://'.$_SERVER["HTTP_HOST"].'/'.$image_file;
  } else {
    header("Content-Type: image/jpeg");
    echo $im;
  }
  // Сворачиваемся. Выходим и машем...
  $im->clear();
  $im->destroy();
  MyExit();

} catch (Exception $e) {
  // Если не получилось - удаляем файлы от греха, в следующий раз будем заного их формировать
  if ($thumbName && file_exists($thumbName)) unlink($thumbName);
  if (file_exists($image_file)) unlink($image_file);
  MyExit($e->getMessage());
}
exit; // Это тут непонятно для чего. Для успокоения души.

// -------------------------------------------------------------------------------------------
// Функция записи сообщений в файл (для ошибок и отладки)
function LogMe($msg="") {
  if ($msg) file_put_contents($_SERVER['SCRIPT_FILENAME'].".log", date("Y-m-d H:i:s")." ".$msg."\n", FILE_APPEND);
}

// -------------------------------------------------------------------------------------------
// Функция выхода и, если есть, записи сообщения в лог (вместо вывода на экран)
function MyExit($msg="") {
  if ($msg) LogMe($msg);
  die();
}

// -------------------------------------------------------------------------------------------
// Функция вывода блоков текста, перечисленных в массиве $aBlocks
function whPtintVideoInfo($im, $aBlocks, $wPic=0, $hPic=0, $xPic=0, $yPic=0, $bShadow=false) {
  global $notEnd, $lastPos;
  $width  = $im->getImageWidth();
  $height = $im->getImageHeight();
  $draw   = new ImagickDraw();
  $shiftUp = 0; // Величина смещения, поднимающая вверх блок текста, если количество строк (линий)
                // предыдущего было меньше минимального значения линий
  $notEnd = false;
  for ($b=0; $b<count($aBlocks); $b++) {
    $text      = $aBlocks[$b][0];
    $font_file = $aBlocks[$b][1];
    $font_size = $aBlocks[$b][2];
    $color     = $aBlocks[$b][3];
    $spacing   = $aBlocks[$b][4];
    $x_offset  = $aBlocks[$b][5];
    $y_offset  = $aBlocks[$b][6];
    $maxLines  = $aBlocks[$b][7];
    $firstCharsAlign = 0;
    if (count($aBlocks[$b])>8) $firstCharsAlign = $aBlocks[$b][8];
    $xRedLine  = $xPic + $wPic + $x_offset; // Координата начала красной строки (для обтекания постера)
    $text  = str_replace('|', "\n", $text);
    $y_offset -= $shiftUp;
    $BlockWidth  = $im->getImageWidth()  - $x_offset -  7;
    $BlockHeight = $im->getImageHeight() - $y_offset - 10;
    $shadow_xoffset = 0; $shadow_yoffset = 0;
    if ($bShadow) {$color = "#101010"; $shadow_xoffset = 3; $shadow_yoffset = 4;}
    $draw->Clear();
    $draw->setFillColor($color);
    $draw->setFont($font_file);
    $draw->setFontSize($font_size);
    $draw->setGravity(Imagick::GRAVITY_NORTHWEST);
    $aText = explode(" ", $text);
    $line = 0; $x = 0; $y = 0; $nSt=0; $xLineAlign = 0; $newY = 0;
    if ($firstCharsAlign) {
      $metrics    = $im->queryFontMetrics($draw, substr(preg_replace('/<\\w+:.*?>/', '', $text), 0, $firstCharsAlign));
      $xLineAlign = $metrics['textWidth'];
    }
    // Если координата x красной строки больше сдвига текста и постер по высоте перекрывает текст
    // делаем вывод текста с красной строки (обтекание фотографии справа)
    if (($xRedLine>$x_offset) && (($hPic+$yPic)>$y_offset)) {
      // Цикл вывода текста с красной строки
      for($i=0; $i<count($aText); $i++) {
      	$word = $aText[$i]; if ($i>0) $word = " ".$word;
        $aLines = explode("\n", $word); $line--;
        for($n=0; $n<count($aLines); $n++) {
        	$wordbr = $aLines[$n]; $line++; if ($n>0) $x=$xLineAlign;
          CheckKeywords($wordbr, $draw, $x, $y, $x_offset, $y_offset, array($bShadow, $font_file, $font_size, $color));
          if ($wordbr=='') continue;
          $metrics = $im->queryFontMetrics($draw, $wordbr);
          if ($x+$xRedLine+$metrics['textWidth']>$BlockWidth) {$line++; $x=$xLineAlign; $wordbr = trim($wordbr);}
          if (($maxLines>0) AND ($line+1>$maxLines)) break;
          $newY = $y +($line * $font_size * $spacing);
          if ($newY > (($hPic+$yPic)-$y_offset)) break;
          $im->annotateImage($draw, $x+$xRedLine+$shadow_xoffset, $newY+$y_offset+$shadow_yoffset, 0, $wordbr);
          $x += $metrics['textWidth'];
        }
        if (($maxLines>0) AND ($line+1>$maxLines)) break;
        if ($newY > (($hPic+$yPic)-$y_offset)) break;
      }
      $aText = array_slice($aText, $i);
      $nSt = $n; //$maxLines-= $line+1;
    }
    // Далее цикл вывода текста обычным образом
    $x = 0; if ($maxLines<1) $maxLines = 1; if ($nSt>0) $x = $xLineAlign;
    for($i=0; $i< count($aText); $i++) {
      $word = $aText[$i]; if ($i>0) $word = " ".$word;
      $aLines = explode("\n", $word); $line--;
      for($n=0; $n<count($aLines); $n++) {
        if ($nSt>0) {$n=$nSt; $nSt=0;}
        $wordbr = $aLines[$n]; $line++; if ($n>0) $x=$xLineAlign;
        CheckKeywords($wordbr, $draw, $x, $y, $x_offset, $y_offset, array($bShadow, $font_file, $font_size, $color));
        if ($wordbr=='') continue;
        $metrics = $im->queryFontMetrics($draw, $wordbr);
        if ($x+$x_offset+$metrics['textWidth']>$BlockWidth) {$line++; $x=$xLineAlign; $wordbr = trim($wordbr);}
        if (($maxLines>0) AND ($line+1>$maxLines)) break;
        $newY = $y + ($line * $font_size * $spacing);
        if (($y+(($line+1) * $font_size * $spacing))>$BlockHeight) {$notEnd=true; break;} // Текст не влазит
        $im->annotateImage($draw, $x+$x_offset+$shadow_xoffset, $newY+$y_offset+$shadow_yoffset, 0, $wordbr);
        $x += $metrics['textWidth'];
      }
      if (($maxLines>0) AND ($line+1>$maxLines)) break;
      if (($y+(($line+1) * $font_size * $spacing))>$BlockHeight) {$notEnd=true; break;}; // Текст не влазит
    }
    if (($line+1<$maxLines) AND ($i<count($aText)) AND ($x>0)) $im->annotateImage($draw, $x+$x_offset, $newY+$y_offset, 0, '...');
    if ($line+1<$maxLines) {$shiftUp += ($maxLines-($line+1)) * ($font_size * $spacing);}
    if ($notEnd) $lastPos = mb_strlen($text)-mb_strlen(implode(" ", array_slice($aText, $i)));
    //LogMe("line = $line, maxLines = $maxLines, shiftUp = $shiftUp");
    //LogMe("notEnd=$notEnd, strlen(text)=".mb_strlen($text).", i=$i, strlen(implode...=".mb_strlen(implode(" ", array_slice($aText, $i))));
    //LogMe("text=$text");
  }
  $draw->clear(); $draw->destroy();
}

// -------------------------------------------------------------------------------------------
// Функция проверки ключевых слов в тексте
function CheckKeywords(&$word, &$draw, &$x, &$y, &$xOffset, &$yOffset, $aDefVals) { 
  global $fontDir;
  $f           = $aDefVals[0];
  $defFont     = $aDefVals[1];
  $defSize     = $aDefVals[2];
  $defColor    = $aDefVals[3];

  // Если это не вывод тени,проверяем ключевые слова изменения цвета, шрифта, размера
  $m = array();
  if (preg_match('#<c:(.*?)>#i' , $word, $m)) {
    if(!$f && preg_match('/^#([a-f0-9]{6}|[a-f0-9]{3})$/i', $m[1])) // hex color is valid
      $draw->setFillColor($m[1]);
  } else if (preg_match('#</c>#i'    , $word, $m)) {
    if (!$f) $draw->setFillColor($defColor);
  }
  if (count($m)>0) $word = str_replace($m[0], '', $word);
  
  if (preg_match('#<s:(\\d+)>#i', $word, $m)) $draw->setFontSize($m[1]);
  else if (preg_match('#</s>#i', $word, $m)) $draw->setFontSize($defSize);
  if (count($m)>0) $word = str_replace($m[0], '', $word);

  if (preg_match('#<x:([+-]?\\d+)>#i', $word, $m)) {
    if      (substr($m[1], 0, 1)=='+') $x += substr($m[1], 1);
    else if (substr($m[1], 0, 1)=='-') $x -= substr($m[1], 1);
    else $x = $m[1];
  }
  if (count($m)>0) $word = str_replace($m[0], '', $word);

  if (preg_match('#<y:([+-]?\\d+)>#i', $word, $m)) {
    if      (substr($m[1], 0, 1)=='+') $y += substr($m[1], 1);
    else if (substr($m[1], 0, 1)=='-') $y -= substr($m[1], 1);
    else $y = $m[1];
  }
  if (count($m)>0) $word = str_replace($m[0], '', $word);

  if (preg_match('#<xo:([+-]?\\d+)>#i', $word, $m)) {
    if      (substr($m[1], 0, 1)=='+') $xOffset += substr($m[1], 1);
    else if (substr($m[1], 0, 1)=='-') $xOffset -= substr($m[1], 1);
    else $xOffset = $m[1];
  }
  if (count($m)>0) $word = str_replace($m[0], '', $word);

  if (preg_match('#<yo:([+-]?\\d+)>#i', $word, $m)) {
    if      (substr($m[1], 0, 1)=='+') $yOffset += substr($m[1], 1);
    else if (substr($m[1], 0, 1)=='-') $yOffset -= substr($m[1], 1);
    else $yOffset = $m[1];
  }
  if (count($m)>0) $word = str_replace($m[0], '', $word);

  if (preg_match('#<f:(.*?)>#i', $word, $m)) {
    $fileTTF = $fontDir."/".$m[1];
    if (file_exists($fileTTF)) $draw->setFont($fileTTF);
  } else if (preg_match('#</f>#i', $word, $m)) $draw->setFont($defFont);
  if (count($m)>0) $word = str_replace($m[0], '', $word);
}

// -------------------------------------------------------------------------------------------
// Функция отдачи файла картинки или ссылки на неё
function toGiveTheFile($filename, $contType='image/jpeg', $give=true) {
  // Для обратной совместимости проверяем
  if (isset($_POST['title'])) {
    // если параметры переданы методом POST, то отдаём ссылку на файл.
    $add = "";
    $f = $filename . '_lp.txt';
    if (file_exists($f)) $add = '?lastpos='.file_get_contents($f);
    echo 'http://'.$_SERVER["HTTP_HOST"].'/'.$filename . $add;
  } else {
    // Если методом GET - то отдаём саму картинку
    $file = ($filename);
    header("Content-Type: ".$contType);
    header("Accept-Ranges: bytes");
    header("Content-Length: ".filesize($file));
    if ($give) header("Content-Disposition: attachment; filename=".$file);
    readfile($file);
  }
	die(); // И умираем... Миссия выполнена.
}

// -------------------------------------------------------------------------------------------
// Проверка размера кеша на превышение лимита и подчистка файлов
function CheckCacheSizeLimit() {
  global $outImgDir, $cacheSizeLimit;
  $aFiles = array();
  // Получаем размер кеша с ифнформацией о файлах в $aFiles
  $cacheSize = dirsize($outImgDir, $aFiles);
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
        unlink($info[0]);
      }
    }
  }
}

// -------------------------------------------------------------------------------------------
// Получние размера директории (всех файлов в нём)
function dirsize($d, &$aFiles) {
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
function CompareBySecondField ($a,$b) {if ($a[1]>$b[1]) return -1; elseif($a[1]<$b[1]) return 1; return 0;}
