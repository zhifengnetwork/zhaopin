<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/4/19
 * Time: 11:49
 */

namespace app\api\controller;
use think\Db;

class Message extends ApiBase
{

    public function message_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $page=I('page',1);
        $limit=I('limit',10);
        $start=($page-1)*$limit;
        $message=Db::name('message')->where(['show'=>1])->limit($start,$limit)->select();
        if (!empty($message)){
            return json(['code'=>1,'msg'=>'获取成功','data'=>$message]);
        }else{
            return json(['code'=>0,'msg'=>'没有数据哦','data'=>$message]);
        }
    }
    public function message_detail(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $id=input('id');
        $message=Db::name('message')->where(['show'=>1,'id'=>$id])->find();
        if (!empty($message)){
            return json(['code'=>1,'msg'=>'获取成功','data'=>$message]);
        }else{
            return json(['code'=>0,'msg'=>'没有数据哦','data'=>$message]);
        }
    }
}