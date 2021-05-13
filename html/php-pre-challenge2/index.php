<?php
$array = array(3, 2, 1, 4, 15, 18, 13, 99, 77, 66, 1, 100, 0);

// 修正はここから
for ($i = 0; $i < count($array); $i++) {
    //要素個数-1回入れ替えを行う
    for ($j = 1; $j < count($array); $j++) {
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
