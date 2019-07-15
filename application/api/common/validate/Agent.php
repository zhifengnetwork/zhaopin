<?php
namespace app\common\validate;

use think\Validate;

class Agent extends Validate
{
    protected $rule = [
        'account|账号'               => 'require|unique:user_agent|alphaNum',
        'name|姓名'                  => 'require',
        'pump_ratio|抽水率'           => 'require|between:1,100',
        'status|状态'                => 'in:0,1',
        'tel|手机号'                  => 'unique:user_agent|number',
        'password|登录密码'            => 'require|length:6,20',
        'repassword|确认密码'          => 'require|confirm:password',
        'second_password|二级密码'     => 'require|length:6,20',
        'resecond_password|确认二级密码' => 'require|confirm:second_password',
        'remark|备注'                => 'require',
        'old_password|原始密码'        => 'require',
        'package_id|包来源'           => 'require',
    ];

    protected $scene = [
        'edit'                         => ['account', 'name', 'email', 'status'],
        'add'                          => ['account', 'name', 'password', 'pump_ratio', 'repassword', 'second_password', 'resecond_password', 'tel'],
        'agent_create'                 => ['account', 'password', 'name', 'repassword', 'pump_ratio'],
        'agent_update_password'        => ['old_password', 'password', 'repassword'],
        'settlement'                   => ['second_password', 'resecond_password'],
        'agent_update_second_password' => ['old_password', 'second_password', 'resecond_password'],
        'edit_info'                    => ['name', 'password' => 'length:6,20', 'repassword' => 'confirm:password', 'status'],
    ];
}
