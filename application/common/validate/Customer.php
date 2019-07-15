<?php
namespace app\common\validate;

use think\Validate;

class Customer extends Validate
{
    protected $rule = [
        'username|账号'              => 'require|unique:customer|alphaNum',
        'name|名称'                  => 'require',
        'status|状态'                => 'in:0,1',
        'quota_amount|额度'          => 'require|gt:0',
        'password|登录密码'            => 'require|length:6,20',
        'repassword|确认密码'          => 'require|confirm:password',
        'second_password|二级密码'     => 'require|length:6,20',
        'resecond_password|确认二级密码' => 'require|confirm:second_password',
        'remark|备注'                => 'require',
        'old_password|原始密码'        => 'require',
    ];
    protected $message = [
        'quota_amount.gt' => '额度应为0元以上',
    ];

    protected $scene = [
        'edit'                         => ['username', 'password' => 'length:6,20', 'repassword' => 'confirm:password', 'status', 'quota_amount'],
        'add'                          => ['username', 'name', 'password', 'quota_amount', 'repassword', 'second_password', 'resecond_password'],
        'agent_create'                 => ['account', 'password', 'name', 'repassword', 'pump_ratio'],
        'agent_update_password'        => ['old_password', 'password', 'repassword'],
        'settlement'                   => ['second_password', 'resecond_password'],
        'agent_update_second_password' => ['old_password', 'second_password', 'resecond_password'],
        'edit_info'                    => ['username', 'passwords' => 'length:6,20', 'repassword' => 'confirm:passwords', 'status'],
        'kf_info'                      => ['username', 'oldpassword' => 'length:6,20', 'password' => 'length:6,20', 'repassword' => 'confirm:password', 'status'],
    ];
}
