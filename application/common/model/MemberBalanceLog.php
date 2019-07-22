<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class MemberBalanceLog extends Model
{
    public static $type_list = [
        1 => '公司预定',
        2 => '前台充值',
        3 => '开通VIP',
        4 => '申请提现',
        5 => '申请提现失败返还',
        6 => '退款返还',
        7 => '后台充值',
        8 => '个人注册成功',
    ];

    public static function getTypeTextBy($value)
    {
        return self::$type_list[$value] ?: '';
    }
}
