<?php
/**
 * 收藏API
 */
namespace app\api\controller;
use think\Db;

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
                ->join('goods g','g.goods_id=c.goods_id','LEFT')
                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                ->field('g.goods_id,g.goods_name,g.price,gi.picture img')
                ->where('user_id',$user_id)
                ->where('gi.main',1)
                ->select();
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

        $goods_id = input('goods_id');
        if(!$goods_id) $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);

        $res = Db::table('goods')->where('goods_id',$goods_id)->find();
        if(!$res) $this->ajaxReturn(['status' => -2 , 'msg'=>'该商品不存在！','data'=>'']);

        $where['user_id'] = $user_id;
        $where['goods_id'] = $goods_id;

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
