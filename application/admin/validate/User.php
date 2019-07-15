<?php
// +----------------------------------------------------------------------
// | Minishop [ Easy to handle for Micro businesses]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.qasl.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: tangtanglove <dai_hang_love@126.com> <http://www.ixiaoquan.com>
// +----------------------------------------------------------------------

namespace app\admin\validate;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'nickname'        => 'require|alphaNum',
        'mobile'          => ['regex' => '^(0|86|17951)?(13[0-9]|15[012356789]|18[0-9]|14[57])[0-9]{8}$'],
        'email'           => 'email',
        'password'        => 'require',
        'repassword'      => 'require',
        'confirmpassword' => 'require|confirm:repassword',
    ];

    protected $message = [
        'nickname.require'       => '昵称不能为空',

        'nickname.alphaNum'       => '名称必须是字母、数字',

        'mobile.regex'            => '手机号格式不正确',

        'email'                   => '邮箱格式错误',

        'repassword.require'      => '请输入新密码',

        'confirmpassword.require' => '请输入确认密码',
        'confirmpassword.confirm' => '请重新输入确认密码',
    ];

    protected $scene = [
        'edit'     => ['email', 'nickname', 'mobile'],

        'password' => ['password', 'repassword', 'confirmpassword'],
    ];
}
