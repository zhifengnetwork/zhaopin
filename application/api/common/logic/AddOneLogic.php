<?php


namespace app\common\logic;

use think\Model;
use think\Db;


class AddOneLogic extends Model
{
    //上级团队总人数更改
    public function team_total($leader_id)
    {
        //$is_exist为查找上级结束标志
        global $is_finish;
        $is_finish = false;

        //上级id为空则退出
        if(!$leader_id) return 'leader_id为空';

        //没有这个上级的记录则退出
        $is_exist = M('users')->where('user_id', $leader_id)->find();
        if(!$is_exist) return '没有这个上级的记录';

        //开启事务
        // Db::startTrans();
        $this->addOne($leader_id);
    
        if($is_finish){
            // 提交事务
            // Db::commit();   
            return true;
        }else{
            // 回滚事务
            // Db::rollback();
            return false;
        } 
    }

    //递归给所有上级的团队总人数加一
    public function addOne($user_id)
    {
        global $is_finish;
        $flag = M('users')->where('user_id', $user_id)->setInc('underling_number');
        if(!$flag) return false;

        //再找上一级的id
        $top_leader = M('users')->where('user_id', $user_id)->value('first_leader');
        if((int)$top_leader > 0){
            $this->addOne($top_leader);
        }else{
            $is_finish = true;
        }
    }
}