<?php
namespace app\common\model;

use think\Db;
use think\Model;

/**
 * 版本管理
 */
class MemberWithdrawal extends Model
{
    protected $updateTime = false;

    protected $autoWriteTimestamp = true;

    public static $status_list = [
        -1 => '审核失败',
        0 => '申请中',
        1 => '审核通过',
    ];

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

    public static function getStatusTextBy($value)
    {
        return self::$status_list[$value];
    }

    public function getTypeTextAttr($value, $data)
    {
        return self::$type_list[$data['type']];
    }

    public function getStatusTextAttr($value, $data)
    {
        return self::$status_list[$data['status']];
    }

    // 今日用户已申请提现总额
    public static function getTodayWDMoney($user_id, $time = '')
    {
        $time = $time ?: time();
        $begin_time = mktime(0, 0, 0, date('m', $time), date('d', $time), date('Y', $time));
        return Db::name('member_withdrawal')->where(['user_id' => $user_id, 'createtime' => [['EGT', $begin_time], ['LT', $time]]])->sum('money');
    }


}