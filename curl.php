<?php
function curl_get($url, $options = array()){
	$ch = curl_init();
	$data = array();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	foreach ($options as $key => $value) {
		curl_setopt($ch, $key, value);
	}
	$data['content'] = curl_exec($ch);
	$data['info'] = curl_getinfo($ch);
	curl_close($ch);
	return $data;
}

final class CURL {
    const ITEM_URL = 0;
    const ITEM_P = 1;
    const ITEM_F = 2;
    const ITEM_TRYED = 3;
    const ITEM_FP = 4;
    
    public $limit = 30;
    public $maxTry = 3;
    public $opt = array ();
    public $cache = array (
            'on' => false,
            'dir' => null,
            'expire' => 86400 
    );
    public $task = null;
    private $activeNum = 0;
    private $queueNum = 0;
    private $finishNum = 0;
    private $cacheNum = 0;
    private $failedNum = 0;
    private $taskNum = 0;
    private $taskPool = array ();
    private $taskRunning = array ();
    private $taskFailed = array ();
    private $traffic = 0;
    private $mh = null;
    private $startTime = null;
    
    
    function status($debug = false) {
        if ($debug) {
            $s = "finish:" . ($this->finishNum) . '(' . $this->cacheNum . ')';
            $s .= "  task:" . $this->taskNum;
            $s .= "  active:" . $this->activeNum;
            $s .= "  running:" . count ( $this->taskRunning );
            $s .= "  queue:" . $this->queueNum;
            $s .= "  failed:" . $this->failedNum;
            $s .= "  taskPool:" . count ( $this->taskPool );
            echo $s . "\n";
        } else {
            static $last = 0;
            static $strlen = 0;
            $now = time ();
            if ($now > $last or ($this->finishNum == $this->taskNum)) {
                $last = $now;
                $timeSpent = $now - $this->startTime;
                if ($timeSpent == 0)
                    $timeSpent = 1;
                $s = sprintf ( '%-.2f%%', round ( $this->finishNum / $this->taskNum, 4 ) * 100 );
                $s .= sprintf ( '  %' . strlen ( $this->finishNum ) . 'd/%-' . strlen ( $this->taskNum ) . 'd(%-' . strlen ( $this->cacheNum ) . 'd)', $this->finishNum, $this->taskNum, $this->cacheNum );
                $speed = ($this->finishNum - $this->cacheNum) / $timeSpent;
                $s .= sprintf ( '  %-d', $speed ) . '/s';
                $suffix = 'KB';
                $netSpeed = $this->traffic / 1024 / $timeSpent;
                if ($netSpeed > 1024) {
                    $suffix = 'MB';
                    $netSpeed /= 1024;
                }
                $s .= sprintf ( '  %-.2f' . $suffix . '/s', $netSpeed );
                $suffix = 'KB';
                $size = $this->traffic / 1024;
                if ($size > 1024) {
                    $suffix = 'MB';
                    $size /= 1024;
                    if ($size > 1024) {
                        $suffix = 'GB';
                        $size /= 1024;
                    }
                }
                $s .= sprintf ( '  %-.2f' . $suffix, $size );
                if ($speed == 0) {
                    $str = '--';
                } else {
                    $eta = ($this->taskNum - $this->finishNum) / $speed;
                    $str = ceil ( $eta ) . 's';
                    if ($eta > 3600) {
                        $str = ceil ( $eta / 3600 ) . 'h' . ceil ( ($eta % 3600) / 60 ) . 'm';
                    } elseif ($eta > 60) {
                        $str = ceil ( $eta / 60 ) . 'm' . ($eta % 60) . 's';
                    }
                }
                $s .= '  ETA ' . $str;
                $len = strlen ( $s );
                echo "\r" . $s;
                if ($len > $strlen) {
                    $strlen = $len;
                } else {
                    $t = $strlen - $len;
                    echo str_pad ( '', $t ) . str_repeat ( chr ( 8 ), $t );
                }
                if ($this->finishNum == $this->taskNum)
                    echo "\n";
            }
        }
    }
    
    
    
    function __get($name) {
        return $this->$name;
    }
    
    
    
    function download($url, $file) {
        $ch = $this->init ( $url, $file );
        $dir = dirname ( $file );
        if (! file_exists ( $dir ))
            mkdir ( $dir, 0777 );
        curl_setopt ( $ch, CURLOPT_FILE, fopen ( $file, 'w' ) );
        $r = curl_exec ( $ch );
        fclose ( $fp );
        if (curl_errno ( $ch ) !== 0) {
            user_error ( 'errno: ' . curl_errno ( $ch ) . "\nerr: " . curl_error ( $ch ) );
        }
        return $r;
    }
    
    
    
    function read($url) {
        if ($this->cache ['on']) {
            $r = $this->cache ( $url );
            if (null !== $r)
                return $r;
        }
        $r = array ();
        $ch = $this->init ( $url );
        $content = curl_exec ( $ch );
        if (curl_errno ( $ch ) === 0) {
            $r ['info'] = curl_getinfo ( $ch );
            $r ['content'] = $content;
            if ($this->cache ['on'])
                $this->cache ( $url, $r );
        } else {
            user_error ( 'error: code ' . curl_errno ( $ch ) . ", " . curl_error ( $ch ), E_USER_WARNING );
        }
        return $r;
    }
    
    
    
    function add($url = array(), $p = array(), $f = array()) {
        if (! is_array ( $url ) or empty ( $url [0] )) {
            var_dump ( $url );
            user_error ( 'url is invalid', E_USER_ERROR );
        }
        if ((isset ( $p [0] ) and ! is_array ( $p [1] )) or (isset ( $f [0] ) and ! is_array ( $f [1] ))) {
            user_error ( 'callback function parameter must be an array', E_USER_ERROR );
        }
        if (empty ( $url [1] ))
            $url [1] = null;
        if (empty ( $p ))
            $p = array (
                    null,
                    array () 
            );
        if (empty ( $f ))
            $f = array (
                    null,
                    array () 
            );
        $task = array ();
        $task [self::ITEM_URL] = $url;
        $task [self::ITEM_P] = $p;
        $task [self::ITEM_F] = $f;
        $task [self::ITEM_TRYED] = 0; 
        $task [self::ITEM_FP] = null; 
        $this->taskPool [] = $task;
        $this->taskNum ++;
    }
    

    
    function go() {
        $failure_count = 0;
        static $running = false;
        if ($running)
            user_error ( 'CURL can only run one instance', E_USER_ERROR );
        $this->mh = curl_multi_init ();
        for($i = 0; $i < $this->limit; $i ++)
            $this->addTask ();
        $this->startTime = time ();
        $running = true;
        do {
            $this->exec ();
            curl_multi_select ( $this->mh );
            while ( $curlInfo = curl_multi_info_read ( $this->mh, $this->queueNum ) ) {
                $ch = $curlInfo ['handle'];
                $info = curl_getinfo ( $ch );
                $this->traffic += $info ['size_download'];
                $k = ( int ) $ch;
                $task = $this->taskRunning [$k];
                if (empty ( $task )) {
                    user_error ( "can't get running task", E_USER_WARNING );
                }
                $callFail = false;
                if ($curlInfo ['result'] == CURLE_OK) {
                    if (isset ( $task [self::ITEM_P] )) {
                        $param = array ();
                        $param ['info'] = $info;
                        if (! isset ( $task [self::ITEM_URL] [1] ))
                            $param ['content'] = curl_multi_getcontent ( $ch );
                        array_unshift ( $task [self::ITEM_P] [1], $param );
                    }
                    if ($this->cache ['on'] and ! isset ( $task [self::ITEM_URL] [1] ))
                        $this->cache ( $task [self::ITEM_URL] [0], $param );
                } else {
                    if ($task [self::ITEM_TRYED] >= $this->maxTry) {
                        $msg = 'curl error: code ' . $curlInfo ['result'] . ', ' . curl_error ( $ch );
                        if (isset ( $task [self::ITEM_F] [0] )) {
                            array_unshift ( $task [self::ITEM_F] [1], $msg );
                            $callFail = true;
                        } else {
                            echo $msg . "\n";
                            $failure_count++;
                        }
                        $this->failedNum ++;
                    } else {
                        $task [self::ITEM_TRYED] ++;
                        $this->taskFailed [] = $task;
                        $this->taskNum ++;
                    }
                }
                curl_multi_remove_handle ( $this->mh, $ch );
                curl_close ( $ch );
                if (isset ( $task [self::ITEM_FP] ))
                    fclose ( $task [self::ITEM_FP] );
                unset ( $this->taskRunning [$k] );
                $this->finishNum ++;
                if ($curlInfo ['result'] == CURLE_OK) {
                    call_user_func_array ( $task [self::ITEM_P] [0], $task [self::ITEM_P] [1] );
                } elseif ($callFail) {
                    call_user_func_array ( $task [self::ITEM_F] [0], $task [self::ITEM_F] [1] );
                }
                $this->addTask ();
                $this->exec ();
            }
        } while ( $this->activeNum || $this->queueNum || ! empty ( $this->taskFailed ) || ! empty ( $this->taskRunning ) || ! empty ( $this->taskPool ) );
        unset ( $this->startTime );
        curl_multi_close ( $this->mh );
        $running = false;
        return $failure_count;
    }
    

    
    private function exec() {
        while ( curl_multi_exec ( $this->mh, $this->activeNum ) === CURLM_CALL_MULTI_PERFORM ) {
        }
    }
    
    
    
    private function addTask() {
        $c = $this->limit - count ( $this->taskRunning );
        while ( $c > 0 ) {
            $task = array ();
            if (! empty ( $this->taskFailed )) {
                $task = array_pop ( $this->taskFailed );
            } else {
                if (0 < $left = ( int ) ($this->limit - count ( $this->taskPool )) and isset ( $this->task )) {
                    while ( $left -- > 0 ) {
                        call_user_func ( $this->task );
                        if (count ( $this->taskPool ) >= $this->limit)
                            break;
                    }
                }
                if (! empty ( $this->taskPool ))
                    $task = array_pop ( $this->taskPool );
            }
            $cache = null;
            if (! empty ( $task )) {
                if ($this->cache ['on'] == true and ! isset ( $task [self::ITEM_URL] [1] )) {
                    $cache = $this->cache ( $task [self::ITEM_URL] [0] );
                    if (null !== $cache) {
                        array_unshift ( $task [self::ITEM_P] [1], $cache );
                        $this->finishNum ++;
                        $this->cacheNum ++;
                        call_user_func_array ( $task [1] [0], $task [self::ITEM_P] [1] );
                    }
                }
                if (! $cache) {
                    $ch = $this->init ( $task [self::ITEM_URL] [0] );
                    if (is_resource ( $ch )) {
                        if (isset ( $task [self::ITEM_URL] [1] )) {
                            $dir = dirname ( $task [self::ITEM_URL] [1] );
                            if (! file_exists ( $dir ))
                                mkdir ( $dir, 0777 );
                            $task [self::ITEM_FP] = fopen ( $task [self::ITEM_URL] [1], 'w' );
                            curl_setopt ( $ch, CURLOPT_FILE, $task [self::ITEM_FP] );
                        }
                        curl_multi_add_handle ( $this->mh, $ch );
                        $this->taskRunning [( int ) $ch] = $task;
                    } else {
                        user_error ( '$ch is not resource,curl_init failed.', E_USER_WARNING );
                    }
                }
            }
            if (! $cache)
                $c --;
        }
    }
    
    
    
    private function cache($url, $content = null) {
        $key = md5 ( $url );
        if (! isset ( $this->cache ['dir'] ))
            user_error ( 'Cache dir is not defined', E_USER_ERROR );
        $dir = $this->cache ['dir'] . DIRECTORY_SEPARATOR . substr ( $key, 0, 3 );
        $file = $dir . DIRECTORY_SEPARATOR . substr ( $key, 3 );
        if (! isset ( $content )) {
            if (file_exists ( $file )) {
                if ((time () - filemtime ( $file )) < $this->cache ['expire']) {
                    return unserialize ( file_get_contents ( $file ) );
                } else {
                    unlink ( $file );
                }
            }
        } else {
            $r = false;
            if (! is_dir ( $this->cache ['dir'] )) {
                user_error ( "Cache dir doesn't exists", E_USER_ERROR );
            } else {
                $dir = dirname ( $file );
                if (! file_exists ( $dir ) and ! mkdir ( $dir, 0777 ))
                    user_error ( "Create dir failed", E_USER_WARNING );
                $content = serialize ( $content );
                if (file_put_contents ( $file, $content, LOCK_EX ))
                    $r = true;
                else
                    user_error ( 'Write cache file failed', E_USER_WARNING );
            }
            return $r;
        }
    }
    
    private function init($url) {
        $ch = curl_init ();
        $opt = array ();
        $opt [CURLOPT_URL] = $url;
        $opt [CURLOPT_HEADER] = false;
        $opt [CURLOPT_CONNECTTIMEOUT] = 15;
        $opt [CURLOPT_TIMEOUT] = 300;
        $opt [CURLOPT_AUTOREFERER] = true;
        $opt [CURLOPT_USERAGENT] = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)';
        $opt [CURLOPT_RETURNTRANSFER] = true;
        $opt [CURLOPT_FOLLOWLOCATION] = true;
        $opt [CURLOPT_MAXREDIRS] = 10;
        if (! empty ( $this->opt ))
            foreach ( $this->opt as $k => $v )
                $opt [$k] = $v;
        curl_setopt_array ( $ch, $opt );
        return $ch;
    }
}