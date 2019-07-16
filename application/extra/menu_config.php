<?php
return [
    //商品分类






    
    
    //配置管理
    'pz_config' => [
        'id'    => 50000,
        'title' => '配置管理',
        'sort'  => 8,
        'url'   => 'config/index',
        'hide'  => 1,
        'icon'  => 'glyphicon glyphicon-link',
        'child' => [
            [
                'id'    => 50100,
                'title' => '首页轮播图',
                'sort'  => 1,
                'url'   => 'advertisement/index',
                'hide'  => 1,
                'icon'  => 'fa-th-large',
                'child' => [
                    [
                        'id'    => 50101,
                        'title' => '页面广告轮播',
                        'sort'  => 1,
                        'url'   => 'advertisement/index',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 50201,
                        'title' => '轮播图编辑',
                        'sort'  => 1,
                        'url'   => 'advertisement/edit',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 50301,
                        'title' => '页面编辑',
                        'sort'  => 1,
                        'url'   => 'advertisement/page_edit',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 50401,
                        'title' => '广告轮播列表',
                        'sort'  => 1,
                        'url'   => 'advertisement/list',
                        'hide'  => 0,
                    ],
                ],
            ],
        ],
    ],



    //系统设置
    'sys_config'      => [
        'id'    => 210000,
        'title' => '系统设置',
        'sort'  => 21,
        'url'   => 'mguser/index',
        'hide'  => 1,
        'icon'  => 'glyphicon glyphicon-cog',
        'child' => [
            [
                'id'    => 210100,
                'title' => '管理员',
                'sort'  => 1,
                'url'   => 'mguser/index',
                'hide'  => 1,
                'icon'  => 'fa-th-large',
                'child' => [
                   
                    [
                        'id'    => 210101,
                        'title' => '编辑',
                        'sort'  => 1,
                        'url'   => 'mguser/edit',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 210102,
                        'title' => '用户授权',
                        'sort'  => 2,
                        'url'   => '',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 210103,
                        'title' => '修改密码',
                        'sort'  => 3,
                        'url'   => 'mguser/update_pwsd',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 210104,
                        'title' => '管理人员',
                        'sort'  => 1,
                        'url'   => 'mguser/index',
                        'hide'  => 1,
                    ],
                ],
            ],
            [
                'id'    => 210200,
                'title' => '权限分组',
                'sort'  => 2,
                'url'   => 'auths/auth_group',
                'hide'  => 1,
                'icon'  => 'fa-th-large',
                'child' => [
                  
                    [
                        'id'    => 210201,
                        'title' => '编辑分组',
                        'sort'  => 1,
                        'url'   => 'auths/edit',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 210202,
                        'title' => '分组授权',
                        'sort'  => 2,
                        'url'   => 'auths/manage_auths',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 210203,
                        'title' => '授权用户',
                        'sort'  => 3,
                        'url'   => 'auths/auth_user',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 210204,
                        'title' => '权限分组',
                        'sort'  => 1,
                        'url'   => 'auths/auth_group',
                        'hide'  => 1,
                    ],
                ],
            ],
            [
                'id'    => 210300,
                'title' => '系统菜单',
                'sort'  => 3,
                'url'   => 'menu/index',
                'hide'  => 1,
                'icon'  => 'fa-th-large',
                'child' => [
                    [
                        'id'    => 210301,
                        'title' => '菜单列表',
                        'sort'  => 1,
                        'url'   => 'menu/index',
                        'hide'  => 1,
                    ],
                ],
            ],
            [
                'id'    => 210400,
                'title' => '微信管理',
                'sort'  => 1,
                'url'   => 'wxfans/index',
                'hide'  => 1,
                'icon'  => 'fa-th-large',
                'child' => [
                   
                    [
                        'id'    => 210401,
                        'title' => '粉丝列表',
                        'sort'  => 1,
                        'url'   => 'wxfans/index',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 210402,
                        'title' => '微信菜单',
                        'sort'  => 2,
                        'url'   => 'wxmenu/index',
                        'hide'  => 1,
                    ],
                ],
            ],
        ],
     ],

     //分销管理


    'user' => [
        'id'    => 90000,
        'title' => '我的会员',
        'sort'  => 9,
        'url'   => 'member/index',
        'hide'  => 1,
        'icon'  => 'glyphicon glyphicon-user',
        'child' => [
            [
                'id'    => 90100,
                'title' => '会员设置',
                'sort'  => 1,
                'url'   => 'member/index',
                'hide'  => 1,
                'icon'  => 'fa-th-large',
                'child' => [
                    [
                        'id'    => 90101,
                        'title' => '会员管理',
                        'sort'  => 1,
                        'url'   => 'member/index',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 90102,
                        'title' => '删除会员',
                        'sort'  => 1,
                        'url'   => 'member/delete',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 90103,
                        'title' => '会员详情',
                        'sort'  => 1,
                        'url'   => 'member/info',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 90104,
                        'title' => '黑名单设置',
                        'sort'  => 1,
                        'url'   => 'member/black',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 90105,
                        'title' => '会员等级',
                        'sort'  => 2,
                        'url'   => 'member/level',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 90106,
                        'title' => '等级编辑',
                        'sort'  => 3,
                        'url'   => 'member/level_edit',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 90107,
                        'title' => '等级新增',
                        'sort'  => 4,
                        'url'   => 'member/level_add',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 90108,
                        'title' => '会员分组',
                        'sort'  => 4,
                        'url'   => 'member/group',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 90109,
                        'title' => '分组编辑',
                        'sort'  => 4,
                        'url'   => 'member/group_edit',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 90110,
                        'title' => '分组新增',
                        'sort'  => 4,
                        'url'   => 'member/group_add',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 90111,
                        'title' => '会员设置',
                        'sort'  => 4,
                        'url'   => 'member/set',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 90112,
                        'title' => '会员详情',
                        'sort'  => 4,
                        'url'   => 'member/member_edit',
                        'hide'  => 0,
                    ],
                ],
            ],
        ],
    ],


];
