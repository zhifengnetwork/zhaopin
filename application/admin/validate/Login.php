<?php
namespace app\admin\validate;

use think\Validate;

class Login extends Validate
{
    protected $rule = [
        'username' => 'require',
        'password' => 'require',
        'captcha|验证码'=>'require|captcha'
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
