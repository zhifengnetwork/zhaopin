<?php
namespace app\common\logic;

use app\common\model\Coupon;
use think\Model;
use think\Db;

/**
 * 修补 AgentPerformanceOldLogic
 */
class AgentPerformanceAddLogic
{
    /**
     * 增加总业绩
     */
    public function add($user_id,$money)
    {   
        //一个人补一次
        $is_cunzai =  M('agent_performance_add')->where(['user_id'=>$user_id])->find();
        if($is_cunzai){
            return false;
        }

        if(!$user_id){
            return false;
        }
        if(!$money){
            return false;
        }

        M('agent_performance_add')->add(['user_id'=>$user_id,'money'=>$money]);
      
        return true;
    }


    /**
     * 获取修补的总数量
     */
    public function get_bu($user_id){
        if(!$user_id){
            return 0;
        }
        $data = M('agent_performance_add')->where(['user_id'=>$user_id])->field('money')->select();
        $total = 0;
        if(!empty($data)) {
            foreach ($data as $k => $v) {
                $total +=  $v['money'];
            }
            return $total;
        }
        return 0;
    }
    
}