<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
return [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------
    // 应用调试模式
    'app_debug'              => true,
    // 应用Trace
    'app_trace'              => true,
    // 应用模式状态
    'app_status'             => '',
    // 是否支持多模块
    'app_multi_module'       => true,
    // 入口自动绑定模块
    'auto_bind_module'       => true,
    // 注册的根命名空间
    'root_namespace'        => [
        'mgcore' => ROOT_PATH . 'mgcore/',
    ],
    // 扩展函数文件
    'extra_file_list'        => [THINK_PATH . 'helper' . EXT],
    // 默认输出类型
    'default_return_type'    => 'html',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return'    => 'json',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler'  => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler'      => 'callback',
    // 默认时区
    'default_timezone'       => 'PRC',
    // 是否开启多语言
    'lang_switch_on'         => false,
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'         => '',
    // 默认语言
    'default_lang'           => 'zh-cn',
    // 应用类库后缀
    'class_suffix'           => false,
    // 控制器类后缀
    'controller_suffix'      => false,
    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------
    // 默认模块名
    'default_module'         => 'admin',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller'       => 'Error',
    // 操作方法后缀
    'action_suffix'          => '',
    // 自动搜索控制器
    'controller_auto_search' => false,
    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------
    // PATHINFO变量名 用于兼容模式
    'var_pathinfo'           => 's',
    // 兼容PATH_INFO获取
    'pathinfo_fetch'         => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
    // pathinfo分隔符
    'pathinfo_depr'          => '/',
    // URL伪静态后缀
    'url_html_suffix'        => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'       => true,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'         => 0,
    // 是否开启路由
    'url_route_on'           => true,
    // 路由使用完整匹配
    'route_complete_match'   => false,
    // 路由配置文件（支持配置多个）
    'route_config_file'      => ['route'],
    // 是否强制使用路由
    'url_route_must'         => false,
    // 域名部署
    'url_domain_deploy'      => false,
    // 域名根，如thinkphp.cn
    'url_domain_root'        => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert'            => true,
    // 默认的访问控制器层
    'url_controller_layer'   => 'controller',
    // 表单请求类型伪装变量
    'var_method'             => '_method',
    // 表单ajax伪装变量
    'var_ajax'               => '_ajax',
    // 表单pjax伪装变量
    'var_pjax'               => '_pjax',
    // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
    'request_cache'          => false,
    // 请求缓存有效期
    'request_cache_expire'   => null,
    // 全局请求缓存排除规则
    'request_cache_except'   => [],
    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------
    'template'               => [
        // 模板引擎类型 支持 php think 支持扩展
        'type'         => 'Think',
        // 默认模板渲染规则 1 解析为小写+下划线 2 全部转换小写
        'auto_rule'    => 1,
        // 模板路径
        'view_path'    => '',
        // 模板后缀
        'view_suffix'  => 'html',
        // 模板文件名分隔符
        'view_depr'    => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin'    => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'      => '}',
        // 标签库标签开始标记
        'taglib_begin' => '{',
        // 标签库标签结束标记
        'taglib_end'   => '}',
    ],

    'c_pub'       => [
        'img'    =>   '/public/upload/images/',
    ],

    // 视图输出字符串内容替换
    'view_replace_str'       => [
        '__INSPINIA__' => '/static/inspinia',
        '__IMG__'      => '/static/images',
    	'__STATIC__'   => '/static',
        '__LIB__'      => '/static/lib',
        '__MOBILE__'   => '/static/mobile',
        '__IMAGES__'   => '/upload/images',
        '__PLIST__'    =>  '/plist',
        '__PAGE__'    =>  '/static/page',

    ],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------
    // 异常页面的模板文件
    'exception_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',
    // 错误显示信息,非调试模式有效
    'error_message'          => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'         => false,
    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'       => '',
    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------
    'log'                    => [
        // 日志记录方式，内置 file socket 支持扩展
        'type'  => 'File',
        // 日志保存目录
        'path'  => LOG_PATH,
        // 日志记录级别
        'level' => [],
    ],
    // +----------------------------------------------------------------------
    // | Trace设置 开启 app_trace 后 有效
    // +----------------------------------------------------------------------
    'trace'                  => [
        // 内置Html Console 支持扩展
        'type' => 'Html',
    ],
    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------
    // reids 缓存
    // 'cache'                  => [
    //     // 驱动方式
    //     'type'     => 'redis',
    //     // 缓存保存目录
    //     'path'     => CACHE_PATH,
    //     // 缓存前缀
    //     'prefix'   => '',
    //     // 缓存有效期 0表示永久缓存
    //     'expire'   => 0,
    // ],
    
    // 文件缓存 (本地使用这个)
    'cache'                  => [
        // 驱动方式
        // 'type'   => 'File',
        // // 缓存保存目录
        // 'path'   => CACHE_PATH,
        // // 缓存前缀
        // 'prefix' => '',
        // // 缓存有效期 0表示永久缓存
        // 'expire' => 0,

        // 使用复合缓存类型
        'type'  =>  'complex',

        // 默认使用的缓存
        'default'   =>  [
            // 驱动方式
            'type'   => 'file',
            // 缓存保存目录
            'path'   => CACHE_PATH,
            // 缓存有效期 0表示永久缓存
            'expire' => 0,
            //缓存前缀
            'prefix' => 'File_'
        ],
        
        // redis缓存
        'redis'   =>  [
            // 驱动方式
            'type'   => 'redis',
            // 服务器地址
            'host'      => '47.107.185.253',
            //端口号
            'port'      => 6379,
            // 全局缓存有效期（0为永久有效）
            'expire'    => 0,
            // 缓存前缀
            'prefix'    => '',
            //安全认证
            'password'      => 'zfwl',
        ],
    ],
    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------
    'session'                => [
        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix'         => 'think',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
    ],
    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie'                 => [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 是否使用 setcookie
        'setcookie' => true,
    ],
    //分页配置
    'paginate'               => [
        'type'      => 'bootstrap',
        'var_page'  => 'page',
        'list_rows' => 15,
    ],
    // 队列任务
    'queue_job'              => [
    ],
    'ORDER_STATUS' =>[
        0 => '待确认',
        1 => '已确认',
        2 => '已收货',
        3 => '已取消',                
        4 => '已完成',//评价完
        5 => '已作废',
        6 => '申请退款',
        7 => '已退款',
        8 => '拒绝退款',
    ],
    'SHIPPING_STATUS' => array(
        0 => '未发货',
        1 => '已发货',
        2 => '部分发货',
        3 => '已收货',
        4 => '退货',
    ),
    'PAY_STATUS' => array(
        0 => '未支付',
        1 => '已支付',
        2 => '部分支付',
        3 => '已退款',
        4 => '拒绝退款'
    ),

    'REFUND_STATUS' => array(
        0 => '审核拒绝',
        1 => '待审核',
        2 => '审核通过',
        3 => '服务单取消',
    ),
    'REFUND_REASON' => array(
        0 => '7天无理由退款',
        1 => '退运费',
        2 => '商品描述不符',
        3 => '质量问题',
        4 => '少件漏发',
        5 => '包装/商品破损/污渍',
        6 => '发票问题',
        7 => '卖家发错货',
    ),
    'REFUND_TYPE' => array(
        0 => '支付原路退回',
        1 => '退到用户余额',
    ),
    'PAY_TYPE' => array(
        'credit' => ['pay_type'=>1,'pay_name'=>'余额支付'],
        'weixin' => ['pay_type'=>2,'pay_name'=>'微信支付'],
        'alipay' => ['pay_type'=>3,'pay_name'=>'支付宝支付'],
        'cash'   => ['pay_type'=>4,'pay_name'=>'货到付款'],
    ),

    'pay_config' => [
        'use_sandbox'               =>  false,// 是否使用沙盒模式
        'app_id'                    => '2019050264367537',
        'sign_type'                 => 'RSA2',// RSA  RSA2
    
        // ！！！注意：如果是文件方式，文件中只保留字符串，不要留下 -----BEGIN PUBLIC KEY----- 这种标记
        // 可以填写文件路径，或者密钥字符串  当前字符串是 rsa2 的支付宝公钥(开放平台获取)
        'ali_public_key'            => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArLvewr55lEYE7iL7vyZkDjZZnofIwRtMQJNBpBOJX8HSum9igthE0wMyz+YmYV7xq1utIrRLy5Zmt991CYyaOCRnzwqrv8ZlEG38QhSwtXXlnxJiqFF5u81K6DXpFAvHYdyPmcAW/sVWE+5X4S4HFvfCvndN6RGih+YdDjzcVi1uIV1JKQG6q6l8xHRiW7EGtGvJW5Q0DAwZ6VAogKB8PIyvuRrRaO3uPezbYcyAZMOJhqkyR/18ji1W0e0Qf3GNKuF+tHJjOTPJ318LvAcuMwLm6hoscD8G6PYwxAgoZ9auYwtjLBbz8a0aQcIX51WInOXrQOBzIZuLdzggqC+2AQIDAQAB',
    
        // ！！！注意：如果是文件方式，文件中只保留字符串，不要留下 -----BEGIN RSA PRIVATE KEY----- 这种标记
        // 可以填写文件路径，或者密钥字符串  我的沙箱模式，rsa与rsa2的私钥相同，为了方便测试
        'rsa_private_key'           => 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCNrD7Mh6r0hTTubDrb6rXFUcbGtSnIY9ZyGnqglAigrH6h2fyYC+LzJm8D+J2P1yNUn1TO/7SKxo5ImgjunX0RH3BsO+a+1cOj5tZ88Y1GdlPLHOt9EP09U2/EDJXD6ucJ9sb1WFXeZiWXR52UJFcklgk2F//2NCdMHGYyllFrRJ0fYjF1wOCR/t4UlTNNm51H2hbXO8VxmNX4ZVDy8B6dhM0fNay/bUj2jUSu8/Q45vZQbH4OkRb4wSNtgVcejKpqhSdsajYekKTH0uWIB6FKCus8pKwe6SlCP0DhVF8RLmw1AzmcPbKAXiE4VazEbwTsRc3MJ6vFNEeOAKxF9njdAgMBAAECggEAVhak0oReTdfkIj2CRsCJVC4tK/JKQYrpdMzCV3GdDIXFLXTZGUufzUE9lJwuoomI3pMzZdXcT7f4HgX8B4OLzCvelOaRgMVE7QQIskPWJUsh//rC3mzEdc+NywQavcKwQk3C+LOE+m/3x8Ws66hpi8HgNw6+a02l04ouT+8n6pYM1f+4Vy7Fb4LdDEkzKDkvtxj+czXDp/1gnRExvqpMkK9iwFfmvO4rO8Ubw+URjIX6vuIZytbnZqT1jpJNfmvigWPplOdRiyVk5YVtBKNAQYSlk8y6Tiftq9VuJEmRuSs3ohoxS9h128Fbze2/oYDtwLGZKXh2D6GivE2Xz9CGAQKBgQDezHVMJdNjIdO0iUh8oW0li9NOYkl1Bsk4ICKPiKWTEx5OT/qbvO/VZskUaHauWgwhMN12wkM6UI0tBonO/ZOON7Fh/5bQCC5QKHtN//EHz932sKyO/qHgCiWzib30jSZ5XtBx8ClPiKfDIu+UaZXlgCLgoi0fxcfyvWQXL7mDmQKBgQCiyOtdF1Alt0oKSE1v5qARmZ4ldb4FWWwaVQJgocEP11wwIQhq3y4ZgHBrDJP35ec7Hyrlr1i/+hjVd3nEtEzt1F4Jj0aNXHwlch+fsfKR1eN5EcP6zmS6idj/239w+lTGN0eIylvBW/J811E7VF1lWgnesYcmOXxCG+XJdJpp5QKBgDj1xqtAJGn8tPY7/tc2IgRuWgh5IlST9o+tz4gopEQUqDPXSLfWNu61B4V7K5Rpmx5FMulwwuU+wMkZGdRcigPbAzONt43Z+ZUutE99tq6LmzC9fHBWcyYnEfpzpafHCmYPMnVetAEMa+98mAm2cMcq2j/Z1nWACB1sBBHVdrVJAoGBAIkNUiPRQfhPJfYcQ54n9LJ8vIpbZD3KuNo+oj7LUOlOb15SIW0hNAXifkOSlm3LUXAUYKB6jeUr4oavDYVQK8i82OOBjmvr5tX8DKX+QvUHuHmxPGhIJsRq1Jktq1FqYb90wTRo8vGLwU/cVJb4A54WPWMR4nCLS5O5OzDujCcFAoGAARLKIuYqRdyemWv2LaHCvVd+r9T8uyzRJK/udX8hb6mfScCyQeLsMj6oavlWioTGVDZb1zHjd8Uo64buGyXsBqzmgQXPtt3w3Vgs+WvfpUCzSCsPalhSsCaPKRvxYtoxZ/HepwegX77aT3sT6sERIEksl9wyClV5Q3mqP5JYI8k=',
    
        'limit_pay'                 => [
            //'balance',// 余额
            //'moneyFund',// 余额宝
            //'debitCardExpress',// 	借记卡快捷
            //'creditCard',//信用卡
            //'creditCardExpress',// 信用卡快捷
            //'creditCardCartoon',//信用卡卡通
            //'credit_group',// 信用支付类型（包含信用卡卡通、信用卡快捷、花呗、花呗分期）
        ],// 用户不可用指定渠道支付当有多个渠道时用“,”分隔
        // 与业务相关参数
        'notify_url'                => 'http://api.zhifengwangluo.c3w.cc/pay/alipay_notify/',
        'return_url'                => 'http://zf_shop.zhifengwangluo.com/',
        'return_raw'                =>  false,// 在处理回调时，是否直接返回原始数据，默认为 true
    ],

    'pay_weixin' => [
        'use_sandbox'       => true,// 是否使用 微信支付仿真测试系统
        'app_secret'        => 'aeb753813c5e6d538905daeda4bc4932',
        'app_id'            => 'wxbfd97e7c3331e60b',  // 公众账号ID
        'mch_id'            => 'xxxxx',// 商户id
        'md5_key'           => 'xxxxxxx',// md5 秘钥
        'app_cert_pem'      => dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wx' . DIRECTORY_SEPARATOR .  'pem' . DIRECTORY_SEPARATOR . 'weixin_app_cert.pem',
        'app_key_pem'       => dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wx' . DIRECTORY_SEPARATOR .  'pem' . DIRECTORY_SEPARATOR . 'weixin_app_key.pem',
        'sign_type'         => 'MD5',// MD5  HMAC-SHA256
        'limit_pay'         => [
            //'no_credit',
        ],// 指定不能使用信用卡支付   不传入，则均可使用
        'fee_type'          => 'CNY',// 货币类型  当前仅支持该字段
    
        'notify_url'        => 'https://helei112g.github.io/v1/notify/wx',
    
        'redirect_url'      => 'https://helei112g.github.io/',// 如果是h5支付，可以设置该值，返回到指定页面
    
        'return_raw'        => false,// 在处理回调时，是否直接返回原始数据，默认为true

    ],

    //验证码
    'captcha' => [    // 验证码字符集合3.
        'codeSet' => '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY',     // 验证码字体大小(px)5.
        'fontSize' => 28,     // 是否画混淆曲线7.
        'useCurve' => false,     // 验证码图片高度9.
        'imageH'  => 50,    // 验证码图片宽度11.
        'imageW'  => 200,     // 验证码位数13.
        'length'  => 4,     // 验证成功后是否重置    15.
        'reset'  => true
    ],

];