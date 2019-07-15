<?php
namespace app\admin\controller;
use think\Loader;
use think\Db;

class Coupon extends Common
{   

    public function coupon_list()
    {

        $where = [];
        $pageParam = ['query' => []];
        
        $title = input('title');
        if( $title ){
            $where['c.title'] = ['like', "%{$title}%"];
            $pageParam['query']['title'] = $title;
        }

        $goods_name = input('goods_name');
        if( $goods_name ){
            $where['g.goods_name'] = ['like', "%{$goods_name}%"];
            $pageParam['query']['goods_name'] = $goods_name;
        }

        $start_time = input('start_time');
        if( $start_time ){
            $where['c.start_time'] = ['>', strtotime($start_time)];
        }

        $end_time = input('end_time');
        if( $end_time ){
            $where['c.end_time'] = ['<', strtotime($end_time)];
        }

        $list = Db::table('coupon')->alias('c')
                ->join('goods g','g.goods_id=c.goods_id','LEFT')
                ->field('c.*,g.goods_name')
                ->where($where)
                ->order('coupon_id DESC')
                ->paginate(10,false,$pageParam);

        return $this->fetch('',[
            'meta_title'    =>  '优惠券列表',
            'list'          =>  $list,
            'title'         =>  $title,
            'goods_name'    =>  $goods_name,
            'start_time'    =>  $start_time,
            'end_time'      =>  $end_time,
        ]);
    }

    public function coupon_add(){

        if( request()->isPost() ){
            $data = input('post.');

            //验证
            $validate = Loader::validate('Coupon');
            if(!$validate->scene('add')->check($data)){
                $this->error( $validate->getError() );
            }

            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            
            $res = Db::table('coupon')->insertGetId($data);

            if($res){
                //添加操作日志
                slog($res);
                $this->success('添加成功！',url('coupon/coupon_list'));
            }
        }

        return $this->fetch('',[
            'meta_title'    =>  '添加优惠券',
        ]);
    }

    public function coupon_edit(){

        $coupon_id = input('coupon_id');

        if( request()->isPost() ){
            $data = input('post.');

            //验证
            $validate = Loader::validate('Coupon');
            if(!$validate->scene('edit')->check($data)){
                $this->error( $validate->getError() );
            }

            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            
            $res = Db::table('coupon')->update($data);

            if($res){
                //添加操作日志
                slog($coupon_id);
                $this->success('修改成功！',url('coupon/coupon_list'));
            }
        }
        
        $info = Db::table('coupon')->find($coupon_id);
        $goods = Db::table('goods')->field('goods_id,goods_name')->find($info['goods_id']);

        return $this->fetch('',[
            'meta_title'    =>  '修改优惠券',
            'info'          =>  $info,
            'goods'          =>  $goods,
        ]);
    }

    public function del(){
        $id = input('coupon_id');
        if(!$id){
            jason([],'参数错误',0);
        }
        $info = Db::table('coupon')->find($id);
        if(!$info){
            jason([],'参数错误',0);
        }

        if( Db::table('coupon')->where('coupon_id',$id)->delete() ){
            //添加操作日志
            slog($id);
            jason([],'删除成功！');
        }
        jason([],'删除失败！',0);
    }
}
