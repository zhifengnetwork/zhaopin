<?php
// +----------------------------------------------------------------------
// | Minishop [ Easy to handle for Micro businesses]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.qasl.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 完美°ぜ界丶
// +----------------------------------------------------------------------

namespace app\admin\validate;

use think\Validate;

class Banner extends Validate
{

    protected $rule = [
        'name'        => 'require',
        'link'        => 'require',
        'description' => 'require',

    ];

    protected $message = [

        'name.require'       => '标题不能为空',

        'link.require'       => '链接地址不能为空',
        'link.url'            => '链接地址不合法',

        'description.require' => '描述内容不能为空',

    ];

    protected $scene = [

        'edit' => ['name', 'link', 'description'],
        'add'  => ['name', 'link', 'description'],

    ];

}
