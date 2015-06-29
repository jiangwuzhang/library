<?php
var_dump(alpha_id(14443));
var_dump(alpha_id("F241D5", true));
function alpha_id($in, $to_num = false, $pad_up = 6, $passkey = '', $index = '') {
    if(!$passkey) $passkey = "Dj*H&^(#x43)[=Na!";
    if(!$index) $index = "ABCDEF012345";
    if ($passkey !== null) {
        for($n = 0; $n < strlen ( $index ); $n ++) {
            $i [] = substr ( $index, $n, 1 );
        }
        
        $passhash = hash ( 'sha256', $passkey );
        $passhash = (strlen ( $passhash ) < strlen ( $index )) ? hash ( 'sha512', $passkey ) : $passhash;
        
        for($n = 0; $n < strlen ( $index ); $n ++) {
            $p [] = substr ( $passhash, $n, 1 );
        }
        
        array_multisort ( $p, SORT_DESC, $i );
        $index = implode ( $i );
    }
    
    $base = strlen ( $index );
    
    if ($to_num) {
        $in = strrev ( $in );
        $out = 0;
        $len = strlen ( $in ) - 1;
        for($t = 0; $t <= $len; $t ++) {
            $bcpow = bcpow ( $base, $len - $t );
            $out = $out + strpos ( $index, substr ( $in, $t, 1 ) ) * $bcpow;
        }
        
        if (is_numeric ( $pad_up )) {
            $pad_up --;
            if ($pad_up > 0) {
                $out -= pow ( $base, $pad_up );
            }
        }
        $out = sprintf ( '%F', $out );
        $out = substr ( $out, 0, strpos ( $out, '.' ) );
    } else {
        if (is_numeric ( $pad_up )) {
            $pad_up --;
            if ($pad_up > 0) {
                $in += pow ( $base, $pad_up );
            }
        }
        
        $out = "";
        for($t = floor ( log ( $in, $base ) ); $t >= 0; $t --) {
            $bcp = bcpow ( $base, $t );
            $a = floor ( $in / $bcp ) % $base;
            $out = $out . substr ( $index, $a, 1 );
            $in = $in - ($a * $bcp);
        }
        $out = strrev ( $out );
    }
    if ($to_num === true){
        return $out <= PHP_INT_MAX ? intval($out) : (double) $out;
    } else {
        return $out;
    }
}