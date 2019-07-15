<?php
/**
 * 每日打卡API
 */
namespace app\api\controller;
use app\common\model\Clock as clockModel;
use think\Db;

class Clock extends ApiBase
{

    //生成打卡订单
    public function create_clock_order(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $clock=new clockModel();
        $clockUser=$clock->getClockUserInfo($user_id);
        $clockInfo=$clock->getClockInfo();
        if($this->is_permit_user($clockUser)){
             $punch_time=strtotime(date("Y-m-d"));
            //查看当天是否创建过订单
             $orderInfo=Db::name("clock_balance_log")->where(['uid'=>$user_id,'punch_time'=>$punch_time])->find();
             if(empty($orderInfo)){
                 $data['order_sn']=date('YmdHis',time()) . mt_rand(10000000,99999999);
                 $data['title']=$clockInfo['title'];
                 $data['uid']=$user_id;
                 $data['pay_status']=0;
                 $data['pay_money']=$clockInfo['join_money'];
                 $data['create_time']=time();
                 $data['punch_time']=$punch_time;
                 $order_id = Db::table("clock_balance_log")->insertGetId($data);
                 if($order_id){
                      $dataR['order_id']= $order_id;
                      $dataR['order_sn']=$data['order_sn'];
                      $dataR['uid']= $user_id;
                      $dataR['title']=$data['title'];
                      $dataR['content']="订单创建成功!";
                     $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功','data'=>$dataR]);
                 }else{
                     $this->ajaxReturn(['status' => -2 , 'msg'=>'网络出错','data'=>'']);
                 }
              }else{
                 if($orderInfo['pay_status']==2){
                     $this->ajaxReturn(['status' => -2 , 'msg'=>'无须重复打卡','data'=>'']);
                 }else{
                     $dataR['order_id']= $orderInfo['order_id'];
                     $dataR['order_sn']=$orderInfo['order_sn'];
                     $dataR['uid']= $user_id;
                     $dataR['title']=$orderInfo['title'];
                     $dataR['content']="订单创建成功!";
                     $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功','data'=>$dataR]);
                 }
             }

        }else{
                $this->ajaxReturn(['status' => -2 , 'msg'=>'已被禁止打卡','data'=>'']);
        }
     }

    //执行打卡
    public function play_clock(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $clock=new clockModel();
        $dayClockUser=$clock->day_clock_user($user_id);
        $nDate=date("Y-m-d H:i:s");
        $clockInfo=$clock->getClockInfo();
        $day=substr($nDate,0,10);
        $start_time=$day." ".$clockInfo['start_time'].":00";
        $end_time=$day." ".$clockInfo['end_time'].":00";
        if($start_time<=$nDate&&$nDate<$end_time){
            if($dayClockUser){
                $where['uid']=$user_id;
                $where['punch_time'] = strtotime(date("Y-m-d",strtotime("-1 day")));
                $res=Db::name("clock_day")->where($where)->update(['status'=>1,'money'=>$clockInfo['money']]);
                if($res){
                    $data['is_success']=1;
                    $data['clock_money']=$clockInfo['clock_money'];
                    $data['get_money']=$clockInfo['money'];
                    $data['user_num']=$clock->getParentNum(1);
                    $data['start_time']=$clockInfo['start_time'];
                    $data['end_time']=$clockInfo['end_time'];
                    $data['content']="获得".$clockInfo['money']."元";
                    Db::startTrans();
                    $res=Db::name("clock_user")->where(['uid'=>$user_id])->setInc('get_money',$clockInfo['money']);
                    $res2=Db::name("clock_balance_log")->where(['uid'=>$user_id,'punch_time'=>$where['punch_time'] ])->update(['get_money'=>$clockInfo['money'],'get_time'=>time()]);
                     if($res&&$res2){
                         Db::commit();
                         $this->ajaxReturn(['status' => 1 , 'msg'=>'打卡成功','data'=>$data]);
                     }else{
                         Db::rollback();
                         $data['is_success']=0;
                         $this->ajaxReturn(['status' => -2 , 'msg'=>'打卡失败','data'=>'']);
                     }

                }else{
                    $data['is_success']=0;
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'打卡失败','data'=>'']);
                }
            }else{
                $this->ajaxReturn(['status' => -2 , 'msg'=>'无法打卡','data'=>'']);
            }
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'当前时间无法打卡','data'=>'']);
        }

    }

    //打卡页面显示
    public function play_clock_show(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $clock=new clockModel();
        $dayClockUser=$clock->day_clock_user($user_id);
        if($dayClockUser){
            $nDate=date("Y-m-d H:i:s");
            $clockInfo=$clock->getClockInfo();
            $day=substr($nDate,0,10);
            $start_time=$day." ".$clockInfo['start_time'].":00";
            $end_time=$day." ".$clockInfo['end_time'].":00";
            //未到打卡时间
            if($nDate<$start_time){
                $data['is_play']=0;
                $data['user_play']=0;
                $data['seconds']=strtotime($start_time)-strtotime($nDate);
                $data['user_num']=0;
                $data['content']="打卡";
            }elseif( $start_time<=$nDate&&$nDate<$end_time){
                if($dayClockUser['status']==1){
                    $content="已打卡";
                    $user_play=1;
                }else{
                    $content="打卡";
                    $user_play=0;
                }
                $data['is_play']=1;
                $data['user_play']=$user_play;
                $data['seconds']=strtotime($end_time)-strtotime($nDate);
                $data['user_num']=$clock->getParentNum(1);
                $data['content']=$content;

            }elseif($nDate>$end_time){
                if($dayClockUser['status']==1){
                    $content="已打卡";
                    $user_play=1;
                }else{
                    $content="未打卡";
                    $user_play=2;
                    //更改未打卡状态
                    $where['uid']=$user_id;
                    $where['punch_time'] = strtotime(date("Y-m-d",strtotime("-1 day")));
                    Db::name("clock_day")->where($where)->update(['status'=>2]);
                }
                $data['is_play']=2;
                $data['user_play']=$user_play;
                $data['seconds']=0;
                $data['user_num']=$clock->getParentNum(1);
                $data['content']=$content;
            }
            $data['uid']=$user_id;
            $data['clock_money']=$clockInfo['clock_money'];
            $data['start_time']=$clockInfo['start_time'];
            $data['end_time']=$clockInfo['end_time'];
            $this->ajaxReturn(['status' =>1 , 'msg'=>'请求成功','data'=>$data]);

        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'当前不可以打卡','data'=>'']);
        }

    }

    //检查该用户当天是否可以打卡
    public function day_clock_user(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $clock=new clockModel();
        $dayClockUser=$clock->day_clock_user($user_id);
        if(empty($dayClockUser)){
            $data["is_clock_user"]=0;
        }else{
            $data["is_clock_user"]=1;
        }

        $this->ajaxReturn(['status' => 1 ,'msg'=>'获取成功','data'=>$data]);
    }

    //打卡明细
    public function get_user_clock_detail(){
        $user_id = $this->get_user_id();
        $pageNum=input('page_num') ? input('page_num'):1;
        $pageSize=10;
        $offset=($pageNum-1);
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $clock=new clockModel();
        $clockUserInfo=$clock->getClockUserInfo($user_id);
        if(empty($clockUserInfo)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'打卡用户不存在','data'=>'']);
        }
        //总的记录数
        $count=Db::name("clock_day")->where(['uid'=>$user_id])->count();
        $detailList=Db::name("clock_day")->where(['uid'=>$user_id])->limit($offset,$pageSize)->order("punch_time DESC")->select();
        $list=array();
        foreach ($detailList as $key =>$value){
            $list[$key]['time']=date("Y/m/d",$value['punch_time']);
             switch ($value['status']){
                 case 0: $list[$key]['content']="待瓜分";break;
                 case 1: $list[$key]['content']=$value['money']."元";break;
                 case 2: $list[$key]['content']="未瓜分";break;
             }
        }
        //当前加载了多少条
        $sumPage=$pageSize*$pageNum;
        if($sumPage>=$count){
            $data['is_finish']=1;
        }else{
            $data['is_finish']=0;
        }
        $pageNum++;
        $data['uid']=$user_id;
        $data['pay_num']=$pageNum;
        $data['pay_money']=$clockUserInfo['pay_money'];
        $data['join_day']=$clockUserInfo['join_day'];
        $data['get_money']=$clockUserInfo['get_money'];
        $data['detail']=$list;
        $this->ajaxReturn(['status' => 1 ,'msg'=>'获取成功','data'=>$data]);

    }


    //老打卡用户打卡
    public function old_join_clock(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $clock=new clockModel();
        $clockUserInfo=$clock->getClockUserInfo($user_id);
        if(empty($clockUserInfo)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'打卡用户不存在','data'=>'']);
        }
        $newDay=$clock->getUserNewDay($user_id);
        if($newDay==date("Y-m-d",time())){
            $data['is_clock']= 1 ;
        }else{
            $data['is_clock']= 0 ;
        }
        $clockInfo=$clock->getClockInfo();
        //当前参加人数
        $data['uid']= $user_id ;
        $data['title']=$clockInfo['title'];
        $data['img_url']=SITE_URL."/uploads/images/".$clockInfo['img'];
        $data['clock_rule']=$clockInfo['clock_rule'];
        $data['join_day']= $clockUserInfo['join_day'];
        $data['join_money']=$clockInfo['join_money'];
        $data['clock_money']=$clockInfo['clock_money'];
        $data['start_time']=$clockInfo['start_time'];
        $data['end_time']=$clockInfo['end_time'];
        $data['person_num']=$clock->getParentNum();
        $this->ajaxReturn(['status' => 1 ,'msg'=>'获取成功','data'=>$data]);


    }

    //新用户参与打卡
     public function  new_join_clock(){
         $clock=new clockModel();
         $clockInfo=$clock->getClockInfo();
         //当前参加人数
         $data['title']=$clockInfo['title'];
         $data['join_money']=$clockInfo['join_money'];
         $data['clock_money']=$clockInfo['clock_money'];
         $data['start_time']=$clockInfo['start_time'];
         $data['end_time']=$clockInfo['end_time'];
         $data['person_num']=$clock->getParentNum();
         $this->ajaxReturn(['status' => 1 ,'msg'=>'获取成功','data'=>$data]);
     }

    //获取打卡规则信息
     public function get_clock_rule(){
         $clock=new clockModel();
         $clockInfo=$clock->getClockInfo();
         $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>['clock_rule'=>$clockInfo['clock_rule']]]);
     }


   //检查某个用户是否是新的打卡用户
    public function check_clock_user(){
          $user_id    = $this->get_user_id();
          $res=Db::name("clock_user")->where(["uid"=>$user_id])->find();
          if($res){
              $data['new_clock_user']=0;
          }else{
              $data['new_clock_user']=1;
          }
        $this->ajaxReturn(['status' => 1 ,'msg'=>'获取成功','data'=>$data]);
    }

    //是否允许用户打卡
    public function is_permit_user( $clockUserInfo){
        if(empty($clockUserInfo)){
           return true;
        }else{
           if($clockUserInfo['status']==0){
               return false;
           }else{
               return true;
           }
        }
    }




}
