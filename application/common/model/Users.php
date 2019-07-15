<?php

namespace app\common\model;

use think\Db;
use think\Model;

class Users extends Model
{
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }

    public function oauthUsers()
    {
        return $this->hasMany('OauthUsers', 'user_id', 'user_id');
    }

    public function userLevel()
    {
        return $this->hasOne('UserLevel', 'level_id', 'level');
    }

    /**
     * 用户下线分销金额
     * @param $value
     * @param $data
     * @return float|int
     */
    public function getRebateMoneyAttr($value, $data){
        $sum_money = DB::name('rebate_log')->where(['status' => 3,'user_id'=>$data['user_id']])->sum('money');
        $rebate_money = empty($sum_money) ? (float)0 : $sum_money;
        return  $rebate_money;
    }

    /**
     * 用户一级下线数
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getFisrtLeaderNumAttr($value, $data){
        $fisrt_leader = Users::where(['first_leader'=>$data['user_id']])->count();
        return  $fisrt_leader;
    }

    /**
     * 用户二级下线数
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getSecondLeaderNumAttr($value, $data){
        $second_leader = Users::where(['second_leader'=>$data['user_id']])->count();
        return  $second_leader;
    }

    /**
     * 用户二级下线数
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getThirdLeaderNumAttr($value, $data){
        $third_leader = Users::where(['third_leader'=>$data['user_id']])->count();
        return  $third_leader;
    }

}
