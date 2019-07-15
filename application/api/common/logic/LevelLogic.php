<?php
namespace app\common\logic;

use app\common\logic\PerformanceLogic;
use think\Model; 
use think\Db;

/**
 * 活动逻辑类
 */
class LevelLogic extends Model
{
	/**
	 * 判断一个人是否可以升级
	 */
	public function user_in($user_id)
	{
		
		$user = M('users')->where('user_id',$user_id)->field('is_agent,user_id,first_leader')->find();

		//判断是否购买指定产品
		$con['user_id'] = $user_id;
		$con['pay_status'] = 1;
		$con['total_amount'] = array('egt',399);
		$is_399 = M('order')->where($con)->field('user_id,pay_status,total_amount')->select();
		$num = count($is_399);
		if($num == 0){
			return false;
		}
		
		//如果不是 代理，则返回
		if($user['is_agent'] != 1){
			return false;
		}
		
		//判断是否为代理
		$agentGrade = M('agent_info')->where(['uid'=>$user['user_id']])->find();
		
		if($agentGrade['level_id'] == 5 ){
			return true;
		}
		
		//判断条件是否满足
		if($this->check_can_upgrade($agentGrade['level_id'], $user) == true){
			//dump('符合条件');

			$up = $agentGrade['level_id'] + 1;
			$userdata = array(
				'agent_user'=> $up,
				'is_agent' => 1,
				'is_distribut' => 1,
			);
			M('users')->where(['user_id'=>$user['user_id']])->update($userdata);
			//写完user表
		
	
			//写  agent_info
			//判断是否存在
			$is_cunzai = M('agent_info')->where(['uid'=>$user['user_id']])->find();
			if(!$is_cunzai){
				$new_data = array(
					'uid' => $user['user_id'],
					'head_id' => $user['first_leader'],
					'level_id' => $up,
					'create_time' => time(),
					'update_time' => time()
				);
				M('agent_info')->add($new_data);

			}else{

				$update = array(
					'level_id' => $up,
					'update_time' => time()
				);

				M('agent_info')->where(['uid'=>$user['user_id']])->update($update);
			}
			

		}

	}

	/**
	 * 代理升级
	 */
	private function check_can_upgrade($grade,$user)
	{
		//升级 下一级  需要 的条件
		$grade = (int)$grade + 1;

		$grade_condition = M('user_level')->where(['level'=>$grade])->find();
	
		$per_logic =  new PerformanceLogic();
		$money_total = $per_logic->distribut_caculate();

		//比较条件
		if($money_total['money_total'] >= $grade_condition['max_money'] && $money_total['moneys'] >= $grade_condition['remaining_money']){
			// dump("符合条件");
			return true;
		}else{
			//dump("不符合条件");
			return false;
		}
	}

}