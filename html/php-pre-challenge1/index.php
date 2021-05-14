<?php
for ($i = 1; $i <= 100; $i++) {
    //ここからコードを書く
    if ($i % 3 === 0) {
        if ($i % 5 === 0) {
            print("3の倍数であり、5の倍数");
        } else {
            print("3の倍数");
        }
    } elseif ($i % 5 === 0) {
        print("5の倍数");
    } else {
        print($i);
    }
    print("<br>");
}
