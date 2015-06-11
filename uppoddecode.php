<?php

$l = $_GET["l"];
$a = str_split($_GET["a"]);
$b = str_split($_GET["b"]);

$data = file_get_contents($l);

for ($i=0; $i<count($a); $i++) {
  $data = str_replace($b[$i], "__"  , $data);
  $data = str_replace($a[$i], $b[$i], $data);
  $data = str_replace("__"  , $a[$i], $data);
}

echo base64_decode($data);
