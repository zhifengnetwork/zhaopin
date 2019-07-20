<?php

namespace app\common\model;

use think\Model;
use think\Db;
const DEFAULT_VIP_MEMBER = 10;
const DEFAULT_CARD_MONEY = 1000;
const DEFAULT_COMMISSION = 500;
const DEFAULT_VIP_AMOUNT = 100000;
class Sysset extends Model
{
    static function getSetsAttr()
    {
        $sets = Db::table('sysset')->where(['id' => 1])->value('sets');
        $sets = unserialize($sets);
        return $sets;
    }

    // 提现开关
    static function getWDShow()
    {
        $sets = Sysset::getSetsAttr();
        return isset($sets['withdrawal']['show']) ? $sets['withdrawal']['show'] : 0;
    }

    // 可提现金额倍数
    static function getWDTimes()
    {
        $sets = Sysset::getSetsAttr();
        return isset($sets['withdrawal']['times']) ? $sets['withdrawal']['times'] : 100;
    }

    // 每次可提现最高
    static function getWDMax()
    {
        $sets = Sysset::getSetsAttr();
        return isset($sets['withdrawal']['max']) ? $sets['withdrawal']['max'] : 10000;
    }

    // 每天可提现最高
    static function getWDPerDay()
    {
        $sets = Sysset::getSetsAttr();
        return isset($sets['withdrawal']['max_preday']) ? $sets['withdrawal']['max_preday'] : 900000;
    }

    /**
     * 手续费费率,默认返回百分比
     * @param string $unit 'percent'、'decimals'
     * @return float|int
     */
    static function getWDRate($unit = 'percent')
    {
        $sets = Sysset::getSetsAttr();
        $rate = isset($sets['withdrawal']['rate']) ? $sets['withdrawal']['rate'] : 0.1;//后台设置的单位为 %
        return $unit == 'decimals' ? ($rate / 100) : $rate;
    }
}

