<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/4/22
 * Time: 18:14
 */

namespace app\api\validate;

use think\Validate;

class Login extends Validate
{
    protected $rule = [
        'username' => 'require',
        'password' => 'require'
    ];

    protected $message = [];

    // 加载语言包
    public function loadLang()
    {
        $login_not_null = '用户名或密码不能为空！';
        $this->message  = [
            'username.require' => $login_not_null,
            'password.require' => $login_not_null,
        ];
    }

}