<?php
namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;
use think\cache;


class Clock extends Model
{

    protected $autoWriteTimestamp = true;
    public   $timeArr=['00:00','01:00','02:00','03:00','04:00','05:00','06:00','07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00'];
   protected $clockInfo;

   //使用缓存
   public function __construct()
   {
         if(empty(Cache::get("clockInfo"))){
             $clockInfo=Db::name('clock')->where(['id'=>1])->find();
             cache::set("clockInfo",$clockInfo);
         }
         $this->clockInfo=Cache::get("clockInfo");
   }

   //检查当前用户当天是否可以打卡
    public function day_clock_user($userId){

        $yDay=date("Y-m-d",strtotime("-1 day"));
        $where['uid'] = $userId;
        $where['punch_time'] = strtotime($yDay);
        $dayClock=Db::name("clock_day")->where($where)->find();
        return $dayClock;

    }

   //获取某个用户打卡信息
    public function getClockUserInfo($userId){

        $userInfo=Db::name('clock_user')->where(['uid'=>$userId])->find();
        return  $userInfo;

    }

    //检查某个用户最新的打卡日期
    public function getUserNewDay($userId){

        $userClockDay=Db::name("clock_day")->where(['uid'=>$userId])->order("punch_time DESC")->limit(1)->select();
        return date("Y-m-d",$userClockDay[0]['punch_time']);
    }

    //获取每日打卡信息
    public function getClockInfo(){
        return  $this->clockInfo;
    }

    //参数空返回全部参与人数，参数0等待打卡，参数1已打卡，参数2为打卡
    public function getParentNum($status=null){
          $date=date("Y-m-d");
          if(!empty($status)){
              $where['status']= $status;
          }
          $where['punch_time'] = strtotime($date);
          $userList=Db::name("clock_day")->where($where)->select();
          $num=count($userList);
          return $num;
    }

    //获取打卡配置信息
    public function  getSetting(){

        $settingInfo=Db::name('clock')->where(['id'=>1])->find();
        if(empty($settingInfo)){
            $settingInfo['id']=1;
            $settingInfo['title']=null;
            $settingInfo['banner']=null;
            $settingInfo['join_money']=null;
            $settingInfo['clock_money']=null;
            $settingInfo['money']=null;
            $settingInfo['start_time']=null;
            $settingInfo['end_time']=null;
            $settingInfo['clock_rule']=null;
            $settingInfo['status']=1;
        }
        return $settingInfo;

    }

    //获取打卡时间段
    public function getTime(){
        return $this->timeArr;
    }




}
