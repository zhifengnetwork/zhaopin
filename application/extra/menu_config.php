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
            ]
        ],
     ],
    'category' => [
        'id'    => 30000,
        'title' => '职位管理',
        'sort'  => 3,
        'url'   => 'category/index',
        'hide'  => 1,
        'icon'  => 'glyphicon glyphicon-briefcase',
        'child' => [
            [
                'id'    => 30100,
                'title' => '职位管理',
                'sort'  => 1,
                'url'   => 'category/index',
                'hide'  => 1,
                'icon'  => 'fa-th-large',
                'child' => [
                    [
                        'id'    => 30301,
                        'title' => '分类列表',
                        'sort'  => 1,
                        'url'   => 'category/index',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 30302,
                        'title' => '添加分类',
                        'sort'  => 2,
                        'url'   => 'category/add',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 30303,
                        'title' => '修改分类',
                        'sort'  => 3,
                        'url'   => 'category/edit',
                        'hide'  => 0,
                    ],

                ],
            ],
            [
                'id'    => 31000,
                'title' => '招聘管理',
                'sort'  => 1,
                'url'   => 'company/recruit_list',
                'hide'  => 1,
                'icon'  => 'fa-th-large',
                'child' => [
                    [
                        'id'    => 31001,
                        'title' => '招聘列表',
                        'sort'  => 1,
                        'url'   => 'company/recruit_list',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 31002,
                        'title' => '添加分类',
                        'sort'  => 2,
                        'url'   => 'category/add',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 31003,
                        'title' => '修改分类',
                        'sort'  => 3,
                        'url'   => 'category/edit',
                        'hide'  => 0,
                    ],

                ],
            ],
        ],
    ],


    'finance'      => [
        'id'    => 60000,
        'title' => '财务管理',
        'sort'  => 2,
        'url'   => 'finance/balance_logs',
        'hide'  => 1,
        'icon'  => 'glyphicon glyphicon-file',
        'child' => [
            [
                'id'    => 60100,
                'title' => '余额',
                'sort'  => 1,
                'url'   => 'finance/index',
                'hide'  => 1,
                'child' => [
                    [
                        'id'    => 60101,
                        'title' => '余额记录',
                        'sort'  => 1,
                        'url'   => 'finance/balance_logs',
                        'hide'  => 1,
                    ]
                ],

            ],
            [
                'id'    => 60200,
                'title' => '提现',
                'sort'  => 2,
                'url'   => 'finance/withdrawal_list',
                'hide'  => 1,
                'child' => [
                    [
                        'id'    => 60110,
                        'title' => '提现列表',
                        'sort'  => 1,
                        'url'   => 'finance/withdrawal_list',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 601111,
                        'title' => '提现设置',
                        'sort'  => 1,
                        'url'   => 'finance/withdrawalset',
                        'hide'  => 1,
                    ],

                ],
            ],
        ],
    ],
     //分销管理


    'user' => [
        'id'    => 90000,
        'title' => '用户管理',
        'sort'  => 9,
        'url'   => 'company/vip_set',
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
                        'hide'  => 0,
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
                        'hide'  => 0,
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
                    [
                        'id'    => 90113,
                        'title' => '会员等级设置',
                        'sort'  => 4,
                        'url'   => 'company/vip_set',
                        'hide'  => 1,
                    ],

                ],
            ],
            [
                'id'    => 91000,
                'title' => '审核管理',
                'sort'  => 5,
                'url'   => 'company/index',
                'hide'  => 1,
                'icon'  => 'fa-th-large',
                'child' => [

                    [
                        'id'    => 91001,
                        'title' => '公司审核列表',
                        'sort'  => 4,
                        'url'   => 'company/index',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 91002,
                        'title' => '第三方审核列表',
                        'sort'  => 4,
                        'url'   => 'company/third',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 91003,
                        'title' => '个人审核列表',
                        'sort'  => 4,
                        'url'   => 'company/person_list',
                        'hide'  => 1,
                    ],
                    [
                        'id'    => 91004,
                        'title' => '公司审核详情',
                        'sort'  => 4,
                        'url'   => 'company/company_details',
                        'hide'  => 0,
                    ],
                    [
                        'id'    => 91005,
                        'title' => '个人审核详情',
                        'sort'  => 4,
                        'url'   => 'company/person_details',
                        'hide'  => 0,
                    ],
                ],
            ],
        ],
    ],


];
