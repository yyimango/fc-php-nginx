<?php
/*
 * @Author       : lovefc
 * @Date         : 2022-09-03 02:11:36
 * @LastEditTime : 2022-10-23 17:42:53
 */

define("PATH", __DIR__);

require(PATH.'/require.php');

// 检查环境
\FC\Tools::checkEnvironment();

$arg = getopt('c:');

$confFile = isset($arg['c']) ? $arg['c'] : '';

if(empty($confFile)){
    $method = $argv[1] ?? 0;
}else{
	$method = $argv[3] ?? 0;
}

if($method){
	if($method == 'start'){
		\FC\App::run($confFile);
		echo \FC\Tools::colorFont("PHP-NGINX Starting....","绿").PHP_EOL;
	}
	if($method == 'stop'){
		$text = \FC\App::stop();
		echo \FC\Tools::colorFont($text,"绿").PHP_EOL;
	}
	if($method == 'restart'){
		\FC\App::run($confFile);
		echo \FC\Tools::colorFont("PHP-NGINX Restarting....","绿").PHP_EOL;		
	}
}else{
    echo \FC\Tools::colorFont("There are no operation parameters.","红").PHP_EOL;
}