<?php
namespace app\common\validate;

use think\Validate;
use think\Db;

class Cart extends Validate
{
    // 验证规则
    protected $rule = [
        'user_note' => 'max:50',
        'address_id'=> 'require'
    ];
    //错误信息
    protected $message = [
        'user_note.max' => '留言长度最多为50个字符',
        'address_id.require' =>'请先填写收货人信息'
    ];

    protected $scene = [
        'is_virtual' => 'user_note'
    ];
}