<?php
$array = explode(',', $_GET['array']);

// 修正はここから
$count = count($array);
for ($i = 0; $i < $count; $i++) {
    //要素個数-1回入れ替えを行う
    for ($j = 1; $j < $count; $j++) {
        //隣と数字入れ替え
        if ($array[$j - 1] > $array[$j]) {
            $subs = $array[$j];
            $array[$j] = $array[$j - 1];
            $array[$j - 1] = $subs;
        }
    }
}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
