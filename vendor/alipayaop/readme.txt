
# DEMO仅供参考，实际开发中需要结合具体业务场景修改使用
#
# 运行环境:PHP5及以上
# demo使用前必读

# 运行demo步骤如下:
1、修改配置文件demo/config/AlipayConfig.php中的参数
2、将项目放置到PHP目录下，浏览器打开main.php文件; 


### 当面付2.0demo代码结构TradePayDemo ###

├── AopSdk.php  #SDK 入口文件
├── aop  #SDK
├── base
│   ├── common.php  # 通用静态资源应用页
│   ├── foot.php    # 通用尾部
│   └── head.php    # 通用头部
├── demo
│   ├── config
│   │   ├── AlipayConfig.php   # 配置文件的读取类，用于读取配置文件以及配置文件更新等操作
│   │   └── DefaultAlipayClientFactory.php  # 用于初始化sdk的客户端
│   ├── entites
│   │   ├── ApiInfoModel.php   # 接口信息的实体类，用于存储接口各项信息
│   │   └── ApiParamModel.php  # 接口参数信息的实体类，用于存储接口参数各项信息
│   ├── model
│   │   ├── builder
│   │   │   ├── {接口名}ContentBuilder.php #各接口请求的controller，文件名中{接口名}是接口首字母大写的形式，用于接收前端的请求参数，进行与支付宝的对接
│   │   │   └── ContentBuilder.php
│   │   └── result
│   │       └── {接口名}Result.php #各接口处理结果的实体类，用于前端请求的规范处理结果
│   └── service
│       ├── ${apiInfo.apiNameFirstUpper}Service.php
│       ├── ${}MainService.php
│       └── AlipayTradeService.php
├── lotusphp_runtime   #SDK使用的一个第三方php框架
├── main.php
└── static
    ├── css
    ├── images
    └── js
        ├── bootstrap
        ├── jquery-3.2.1.js
        ├── main.js  demo展示页js
        └── tabPanel.js


# DEMO仅供参考，实际开发中需要结合具体业务场景修改使用