<?php
/*
 * @Author       : lovefc
 * @Date         : 2022-10-21 16:36:41
 * @LastEditTime : 2022-10-24 22:45:47
 */

namespace FC;

class NginxConf
{
    public static $Configs = [];
    public static $parameters = [
      "listen",
      "server_name",
      "root",
      "index",
      "error_page",
      "ssl_certificate",
      "ssl_certificate_key",
      "access_log",
      "error_log",
      "gzip",
      "gzip_types",
      "gzip_comp_level",
    ];

    public static function defaultConf()
    {
        $dir = dirname(__DIR__);
        $conf = [
            '127.0.0.1' => [
                'listen' => [80],
                'server_name' => ['127.0.0.1'],
                'root' => [$dir.'/html'],
                'index' => ['index.html','index.htm']
            ]
        ];
        return $conf;
    }

    /**
     * 获取文件夹内指定后缀的所有文件
     * @param array $result 结果集
     * @param string $dir 指定目录
     * @param array $filter 后缀过滤，为空即全部文件
     */
    public static function getFiles(&$result, $dir, $filter = [])
    {
        $files = array_diff(scandir($dir), array('.', '..', '__MACOSX'));
        if (is_array($files)) {
            foreach ($files as $value) {
                if (is_dir($dir . '/' . $value)) {
                    self::getFiles($result, $dir . '/' . $value, $filter);
                } else {
                    $path_info = pathinfo($dir . '/' . $value);
                    $extension = array_key_exists('extension', $path_info) ? $path_info['extension'] : '';
                    if (empty($filter) || (!empty($filter) && in_array($extension, $filter))) {
                        $result[] = $dir . '/' . $value;
                    }
                }
            }
        }
    }
    /**
     * 读取nginx配置文件信息
     * @param string $file 文件名称
     */
    public static function getConf($file)
    {
        $text = file_get_contents($file);
        $matches = [];
        preg_match_all('/server\s*{(.*)\s*}/is', $text, $matches);
        $text = $matches[1][0];
        // 去掉注释
        $text = preg_replace("/\#(.*)\s+/i", "", $text);
        $arr = explode(";", $text);
        $confs = [];
        foreach ($arr as $text) {
            $text = trim($text);
            $text2 = substr($text, 0, 1);
            foreach (self::$parameters  as $v2) {
                if ($text2!='#' && preg_match("/{$v2}\s+/is", $text)) {
                    if ($v2=='root') {
                        $text = preg_replace("/\"/i", "", $text);
                    }
                    $arrs = array_filter(explode(" ", trim(substr($text, strlen($v2)))));
                    if ($v2!='index') {
                        sort($arrs);
                    }
                    $confs[$v2] = $arrs;
                }
            }
        }
        return $confs;
    }

    public static function readConf($path='', $extensions=['conf'])
    {
        if (!is_dir($path)) {
            return false;
        }
        $files = [];
        self::getFiles($files, $path, $extensions);
        foreach ($files as $v) {
            $conf = self::getConf($v);
            foreach ($conf['server_name'] as $v) {
                if (isset(self::$Configs[$v])) {
                    die(Tools::colorFont("{$v}-The domain name is bound, and it is bound repeatedly, Please check the configuration!", "红"));
                }
                self::$Configs[$v] = $conf;
            }
        }
        $conf2 = self::defaultConf();
        self::$Configs = array_merge($conf2, self::$Configs);
    }
}
