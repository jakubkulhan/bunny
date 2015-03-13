<?php
define("N", 1000000);

function swapEndian64Strrev($s)
{
    return strrev($s);
}

function swapEndian64Concat($s)
{
		return $s[7] . $s[6] . $s[5] . $s[4] . $s[3] . $s[2] . $s[1] . $s[0];
}

function swapEndian64Index($s)
{
    $rs = "00000000";
    $rs[0] = $s[7];
    $rs[1] = $s[6];
    $rs[2] = $s[5];
    $rs[3] = $s[4];
    $rs[4] = $s[3];
    $rs[5] = $s[2];
    $rs[6] = $s[1];
    $rs[7] = $s[0];
    return $rs;
}

$s = pack("NN", 1, 1);

$t = microtime(true);
for ($i = 0; $i < N; ++$i) {
    swapEndian64Strrev($s);
}
var_dump(microtime(true) - $t);

$t = microtime(true);
for ($i = 0; $i < N; ++$i) {
    swapEndian64Concat($s);
}
var_dump(microtime(true) - $t);

$t = microtime(true);
for ($i = 0; $i < N; ++$i) {
    swapEndian64Index($s);
}
var_dump(microtime(true) - $t);
