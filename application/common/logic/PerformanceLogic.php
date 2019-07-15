<?php
namespace app\common\logic;

use think\Db;

/**
 * PerformanceLogic.
 */
class PerformanceLogic
{
  
  
     /**
     * 抽离
     */
    public function distribut_caculate(){
        $user_id = session('user.user_id');
        $openid = session('user.openid');
       
        $user_agent_money = M('agent_performance')->where(['user_id'=>$user_id])->find();
     
        $tuandui_money =  (float)$user_agent_money['agent_per'];

        $logic = new \app\common\logic\AgentPerformanceOldLogic();
        $oldPerformance = $logic->getAllData($openid);
        //这是老的历史业绩，加上新的
       

        $add_logic = new \app\common\logic\AgentPerformanceAddLogic();
        $xiubu_yeji = $add_logic->get_bu($user_id);

        $zong_yeji = $tuandui_money + $oldPerformance + $xiubu_yeji;
        //总业绩
    
        $per_logic = new \app\common\logic\PerformanceLogic();
        $max_team_total  = $per_logic->tuandui_max_yeji($user_id);

        //加上 老系统的 最大用户业绩
        //$max_team_total = $max_team_total + session('user.team');

        $money_total = array(
            'money_total' => (float)$zong_yeji,
            'max_moneys'  => (float)$max_team_total,
            'moneys' => (float)bcsub((float)$zong_yeji,(float)$max_team_total,2),
            'oldPerformance' => $oldPerformance
        );

        return $money_total;

    }




    /**
     * 个人的业绩
     */
    public function person_yeji($user_id){
    
        $order = M('order')->where(['user_id'=>$user_id,'pay_status'=>1])->field('user_money,order_amount')->select();
        $total = 0;
        foreach($order as $k => $v){
        $total += (float)$v['user_money'] + (float)$v['order_amount'];
        }
        return $total;
    }
    
    /**
     * 一个人旗下  团队的  最大  的  那个业绩
     */
    public function tuandui_max_yeji($user_id){
        
        $user = M('users')->where(['first_leader'=>$user_id])->column('user_id');
       
        $yeji = M('agent_performance')->where('user_id', ['in', $user])->field('agent_per,user_id')->select();
        
        $logic = new \app\common\logic\AgentPerformanceOldLogic();
       
        $add_logic = new \app\common\logic\AgentPerformanceAddLogic();
      

        //所有下级业绩
        $all_yeji = array();
        foreach($yeji as $k => $v){
            $openid = M('users')->where(['user_id'=>$v['user_id']])->value('openid');

            $oldPerformance = $logic->getAllData($openid);

            $xiubu_yeji = $add_logic->get_bu($v['user_id']);
            
            // $yeji[$k]['agent_per'] = $v['agent_per'] + $oldPerformance + $xiubu_yeji;
            $all_yeji[] = $v['agent_per'] + $oldPerformance + $xiubu_yeji;
        }

        //排序取最大业绩
        if($all_yeji){
            rsort($all_yeji);
        }
        $res = $all_yeji[0];
        // $res = $yeji[0]['agent_per'];
       
        if($res == 0){
            $res = M('users')->where(['user_id'=>$user_id])->value('team');
        }
       
        $res = $res == 0 ? 0 : $res;

        return $res;
    }
    
}