<?php
$array = explode(',', $_GET['array']);

// 修正はここから
for ($i = 0; $i < count($array); $i++) {
    //バブルソート
    for ($j = $i - 1; $j < count($array); $j++) {
        if ($array[$j - 1] > $array[$j]); {
            $subs = $array[$j];
            $array[$n] = $array[$n - 1];
            $array[$n - 1] = $subs;
        }
    }
}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
