<?php
/*
 * @Author       : lovefc
 * @Date         : 2022-09-03 02:11:36
 * @LastEditTime : 2022-10-25 11:03:22
 */

namespace FC;

class App
{
    public static $phpPath;
	
    // 获取php文件位置
    public static function getPhpPath()
    {
        if (defined('PHP_BINARY') && PHP_BINARY && in_array(PHP_SAPI, array('cli', 'cli-server')) && is_file(PHP_BINARY)) {
            return PHP_BINARY;
        } elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $paths = explode(PATH_SEPARATOR, getenv('PATH'));
            foreach ($paths as $path) {
                if (substr($path, strlen($path)-1) == DIRECTORY_SEPARATOR) {
                    $path = substr($path, 0, strlen($path)-1);
                }
                if (substr($path, strlen($path) - strlen('php')) == 'php') {
                    $response = $path.DIRECTORY_SEPARATOR . 'php.exe';
                    if (is_file($response)) {
                        return $response;
                    }
                } elseif (substr($path, strlen($path) - strlen('php.exe')) == 'php.exe') {
                    if (is_file($response)) {
                        return $response;
                    }
                }
            }
        } else {
            $paths = explode(PATH_SEPARATOR, getenv('PATH'));
            foreach ($paths as $path) {
                if (substr($path, strlen($path)-1) == DIRECTORY_SEPARATOR) {
                    $path = substr($path, strlen($path)-1);
                }
                if (substr($path, strlen($path) - strlen('php')) == 'php') {
                    if (is_file($path)) {
                        return $path;
                    }
                    $response = $path.DIRECTORY_SEPARATOR . 'php';
                    if (is_file($response)) {
                        return $response;
                    }
                }
            }
        }
        return null;
    }
    
	// 获取php.ini配置
    public static function config()
    {
        $php_ini = '';
        // php -info(-ini)也能获取到phpinfo的配置
        ob_start();
        phpinfo();
        $txt = ob_get_contents();
        ob_end_clean();
        if (preg_match("/Loaded\s+Configuration\s+File\s+=>\s+(.*)/i", $txt, $matches)) {
            $php_ini = $matches[1] ?? '';
        }
        return $php_ini;
    }
       
	// 执行命令
    public static function execCmd($cmd,$cmd2)
    {
        if (substr(php_uname(), 0, 7) == "Windows") {
			$cmd = "start {$cmd}";
            pclose(popen($cmd, "r"));
        } else {
            $cwd = $env = null;
			$cmd .= ' &';
            $process = proc_open($cmd, [], $pipes, $cwd, $env);
            if (is_resource($process)) {
                proc_close($process);
            }
        }
    }
   
    public static function run($confFile= '')
    {
		if(!is_file($confFile) || empty($confFile)){
            NginxConf::readAllConf(PATH.'/conf/vhosts');
		}else{
			NginxConf::readConf($confFile);
		}
		$php_path = self::getPhpPath();
        foreach (NginxConf::$Configs as $k=>$v) {
			if(!isset($v['listen'])) break;
            $server_name = $k;
            foreach ($v['listen'] as $port) {
                self::$phpPath = $php_path;
                $cmd = $php_path.' '.PATH.'/app.php -h '.$server_name.' -p '.$port.' -c '.$confFile;
				$cmd2 = 'Start-Process '.$php_path.' -ArgumentList "'.PATH.'/app.php -h '.$server_name.' -p '.$port.' -c '.$confFile;
                self::execCmd($cmd,$cmd2);
            }
        }
    }

    public static function work($server_name, $port, $confFile='')
    {
		$process_title = "php.nginx-{$server_name}-{$port}";
		if(!empty($confFile)) $process_title .= "-".md5($confFile);
        cli_set_process_title($process_title);// PHP 5.5.0 可用
        $cert = NginxConf::$Configs[$server_name]['ssl_certificate'][0] ?? null;
        $key  = NginxConf::$Configs[$server_name]['ssl_certificate_key'][0] ?? null;
        if (!empty($cert) && !empty($key)) {
            $context_option = [
                'ssl' => [
                    'local_cert'  => $cert, // 也可以是crt文件
                    'local_pk'    => $key,
                    'verify_peer' => false, // 是否需要验证 SSL 证书,默认为true
                ]
            ];
            $obj = new \FC\Protocol\Https("0.0.0.0:{$port}", $context_option);
        } else {
            $obj = new \FC\Protocol\Http("0.0.0.0:{$port}", []);
        }
        /*
        $obj->on('connect', function ($fd) {
        });
        $obj->on('message', function ($server, $data) {
            $server->send('Welcome php-nginx');
        });
        $obj->on('close', function ($fd) {
        });
        */
        $obj->start();
    }

    public static function getArgs($key)
    {
        $args = getopt("{$key}:");
        $arg = isset($args[$key]) ? $args[$key] : null;
        return $arg;
    }

    // 启动
    public static function start()
    {
        $arg = getopt('h:p:c:');
        $server_name = isset($arg['h']) ? $arg['h'] : '127.0.0.1';
        $port = isset($arg['p']) ? $arg['p'] : '80';
	    $confFile = isset($arg['c']) ? $arg['c'] : '';
		if(!is_file($confFile) || empty($confFile)){
            NginxConf::readAllConf(PATH.'/conf/vhosts');
		}else{
			NginxConf::readConf($confFile);
		}
		//echo $server_name.$port.$confFile;
		//print_r(NginxConf::$Configs);
        self::work($server_name, $port, $confFile);
    }

    // 重启
    public static function reStart($confFile='')
    {
		self::stop($confFile);
		self::run($confFile);
    }
	
	
    public static function linuxStop($confFile='')
    {
		$name = 'php.nginx';
		if(!empty($confFile)){
			$name = md5($confFile);
		}
	    $linux_cmd = "ps -ef | grep '$name' | grep -v 'grep' | awk '{print \$2}'";
		$output = shell_exec($linux_cmd);
		if(empty($output)) return 'Please start php-nginx first!';
		$arr = explode(PHP_EOL,$output);
		$s = array_filter($arr);
		foreach($arr as $pid){
		    shell_exec("kill -9 {$pid} 2>&1");
		}
		return 'PHP-NGINX Stoping....';
    }
	
	public static function winStop(){
		$win_cmd = 'taskkill /T /F /im php.exe 2>NUL 1>NUL';
		system($win_cmd);
		return 'PHP-NGINX Stoping....';
	}
	
    // 停止
    public static function stop($confFile='')
    {
		if(IS_WIN == false){
			return self::linuxStop($confFile);
		}else{
			return self::winStop();
		}
    }
	
}
