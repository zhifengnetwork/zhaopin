<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// [ 应用入口文件 ]
define('HTTP_HOST', $_SERVER['HTTP_HOST']);

//http://zfwl.zhifengwangluo.c3w.cc/
//根据域名做相应处理
if (preg_match("/(.*)\.(.*)\.c3w\.cc/i", HTTP_HOST, $matches)) {
    $partner = $matches[1];
    $key     = $matches[2];
    $modules = [
        'zfwl'              => 'admin',
        'dist'              => 'zf_shop',
        'api'               => 'api',
    ];
    $module = isset($modules[$partner]) ? $modules[$partner] : 'home';
    define('BIND_MODULE', $module);
} else {
    $terrace = [
//        '127.0.0.1:10059' => 'agent',
//        '127.0.0.1:10058' => 'home',
//        '127.0.0.1:10057' => 'sapi',
//        '127.0.0.1:10056' => 'api',
//        '127.0.0.1:12588' => 'admin',
//        '127.0.0.1:12580' => 'admin',
        'agent.zfwl.local' => 'agent',
        'home.zfwl.local' => 'home',
        'sapi.zfwl.local' => 'sapi',
        'api.zfwl.local' => 'api',
        'admin.zfwl.local' => 'admin',
//         '127.0.0.1:10059' => 'agent',
//         '127.0.0.1:10058' => 'home',
//         '127.0.0.1:10057' => 'sapi',
//         '127.0.0.1:10056' => 'api',
//         '127.0.0.1:12588' => 'admin',
//         '127.0.0.1:12580' => 'api',
//        'demo.zfwl.top' => 'admin',
//        'test.com' => 'admin',
//        // '127.0.0.1:12580' => 'api',
    ];
//    $terrace = Config::get('terrace');
    if (!empty($terrace[HTTP_HOST])) {
        $module = $terrace[HTTP_HOST];
        define('BIND_MODULE', $module);
    }
 }

$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http';
define('SITE_URL',$http.'://'.$_SERVER['HTTP_HOST']); // 网站域名

// 定义应用目录
define('APP_PATH', __DIR__ . '/../../application/');
// 加载框架引导文件
require __DIR__ . '/../../thinkphp/start.php';
