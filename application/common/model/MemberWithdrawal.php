<?php
namespace app\common\model;

use think\Model;

/**
 * 版本管理
 */
class MemberWithdrawal extends Model
{
    protected $updateTime = false;

    protected $autoWriteTimestamp = true;

    //外部搜索调用
    public static $type_list = [
        0 => '默认全部',
        1 => '余额',
        2 => '微信',
        3 => '银行',
        4 => '支付宝',
    ];

    public function getTypeAttr($value)
    {
        return self::$type_list[$value];
    }


}