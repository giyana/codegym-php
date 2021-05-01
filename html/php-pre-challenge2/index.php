<?php
$array = explode(',', $_GET['array']);

// 修正はここから
for ($i = 0; $i < count($array); $i++) {

}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
