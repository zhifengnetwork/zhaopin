<?php
/**
 * 收藏API
 */
namespace app\api\controller;
use think\Db;
use app\common\model\Region;

class Collection extends ApiBase
{

    /**
     * 收藏列表
     */
    public function collection_list(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $list = Db::table('collection')->alias('c')
                ->join('recruit r','r.id=c.recruit_id','LEFT')
                ->join('company co','co.id=r.company_id','LEFT')
                ->join('member m','m.id=co.user_id','LEFT')
                ->join('category ca','ca.cat_id=r.type','LEFT')
                ->field('r.id,r.title,r.type,ca.cat_name,r.work_age,r.require_cert,co.contacts,co.city,co.district,m.avatar')
                ->where('c.user_id',$user_id)
                ->select();
        foreach ($list as $key=>$value){
            $list[$key]['city']=Region::getName($list[$key]['city']);
            $list[$key]['district']=Region::getName($list[$key]['district']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$list]);
    }

    /**
     * 收藏|取消收藏
     */
    public function collection(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $recruit_id = input('recruit_id');
        if(!$recruit_id) $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);

        $res = Db::table('recruit')->where('id',$recruit_id)->find();
        if(!$res) $this->ajaxReturn(['status' => -2 , 'msg'=>'该职位不存在！','data'=>'']);

        $where['user_id'] = $user_id;
        $where['recruit_id'] = $recruit_id;

        $res = Db::table('collection')->where($where)->find();

        if($res){
            $res = Db::table('collection')->where($where)->delete();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'取消收藏！','data'=>'']);
        }else{
            $res = Db::table('collection')->insert($where);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'收藏成功！','data'=>'']);
        }
    }

}
