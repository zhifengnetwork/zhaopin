<?php
namespace app\common\logic;

use app\common\model\Coupon;
use think\Model;
use think\Db;

/**
 * 历史的 AgentPerformanceOldLogic
 */
class AgentPerformanceOldLogic
{
    /**
     * 获取历史总业绩
     */
    public function getAllData($openid)
    {   
        if(!$openid){
            return 0;
        }
        $data = M('agent_performance_old')->where(['openid'=>$openid])->field('teams,total')->select();
        $total = 0;
        if(!empty($data)) {
            foreach ($data as $k => $v) {
                $total += $v['teams'] + $v['total'];
            }
            return $total;
        }
        return 0;
    }
    
}