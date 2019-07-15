<?php
namespace app\admin\controller;
use app\common\model\Goods;
use think\Loader;
use think\Db;

class Chopper extends Common
{
    function index(){
        //根据传过来的条件
        $where = [];
        $pageParam = ['query' => []];
        $where['is_delete'] =  0;
        $chopper_name = input('chopper_name');
        if(!empty($groupon_name)){
            $where['chopper_name'] =  ['like',"%{$chopper_name}%"];
            $pageParam['query']['chopper_name'] = $grouponchopper_name_name;
        }

        $is_show = input('is_show');
        if($is_show  != null){
            $where['is_show'] =  $is_show;
            $pageParam['query']['is_show'] = $is_show;
        }

        $list = Db::name('goods_chopper')->order('sort desc,chopper_id desc')->Where($where)->paginate(10,false);
        $page = $list->render();
        $list = $list->all();

        return $this->fetch("",[
            'chopper_name' => $chopper_name,
            'is_show'      => $is_show,
            'list'         => $list,
            'page'         => $page,
            'meta_title'   => '砍一刀列表',
        ]);

    }

    //添加砍一刀
    function add(){
        if(request()->isPost() ){
            $chopper_name   = input('chopper_name','');//砍一刀
            $surplus_amount = input('surplus_amount/f',0);//底价
            $first_amount   = input('first_amount/f',0);//第一刀
            $second_amount  = input('second_amount/f',0);//第二刀
            $third_amount   = input('third_amount/f',0);//第三刀
            $section        = input('section/a');//区间刀
            $end_num        = input('end_num/d');//区间刀
            $start_time     = input('start_time');//开始时间
            $end_time       = input('end_time');//结束时间
            $goods_id       = input('goods_id/d');
            $goods_cost_price = input('cost_price/f');
            $goods_price      = input('price/f');

            if($section['start'] > $section['end']){
                $this->error('区间刀填写错误!');
            }
            if($section['amount'] < 0){
                $this->error('区间刀价格不正确!');
            }
            if($end_num < 0){
                $this->error('剩余砍价次数不能小于0!');
            }
            if($goods_id <= 0){
                $this->error('请选择商品!');    
            }
            $good = Goods::get($goods_id);
            //判断砍价最低价不能小于成本价
            if($surplus_amount < $good['cost_price']){
                $this->error('最低价不能小于成本价!');
            }

            //区间刀总金额

            $qe_amount = ($section['end'] - $section['start'] + 1) * $section['amount'];

            //判断砍价总金额不能超过可砍金额
            $total_amount     = $first_amount + $second_amount + $third_amount + $qe_amount;
         
            $end_price        = $good['price'] - $surplus_amount - $total_amount;//随机砍的金额
            //用户需要砍的总金额
            $tamount = $total_amount + $end_price;
           
            //可砍金额
            $kekan_amount  = $good['price'] - $surplus_amount;


            if($tamount  > $kekan_amount){
                $this->error('设置的砍价金额超过商品可砍金额!');
            }


            $section      = serialize($section);
            $data = [
                'section'        => $section,
                'chopper_price'  => $tamount,
                'surplus_amount' => $surplus_amount,
                'chopper_name'   => $chopper_name,
                'first_amount'   => $first_amount,
                'second_amount'  => $second_amount,
                'third_amount'   => $third_amount,
                'end_num'        => $end_num,
                'end_price'      => $end_price,
                'start_time'     => strtotime($start_time),
                'end_time'       => strtotime($end_time),
                'goods_id'       => $goods_id,
                'goods_cost_price' => $goods_cost_price,
                'goods_price'      => $goods_price,
            ]; 
            //添加信息
            $chopper_id =  Db::name('goods_chopper')->insertGetId($data);
            if($chopper_id){
                //添加操作日志
                // slog($chopper_id);
                $this->success('添加砍一刀商品成功',url('chopper/index'));
            }else{
                $this->error('添加砍一刀商品失败!');
            }
        }

        return $this->fetch("",[
            'meta_title'    =>  '添加砍一刀',
        ]);
    }

    //修改砍一刀
    function edit(){
        $chopper_id = input('chopper_id');
        if(request()->isPost()){
            $is_show = Db::name('goods_chopper')->where(['chopper_id' => $chopper_id])->value('is_show');
            //todo 防止砍价错乱
            // if($is_show == 1 && ){
                
            // }

            $chopper_name   = input('chopper_name','');//砍一刀
            $surplus_amount = input('surplus_amount/f',0);//底价
            $first_amount   = input('first_amount/f',0);//第一刀
            $second_amount  = input('second_amount/f',0);//第二刀
            $third_amount   = input('third_amount/f',0);//第三刀
            $section        = input('section/a');//区间刀
            $end_num        = input('end_num/d');//区间刀
            $start_time     = input('start_time');//区间刀
            $end_time       = input('end_time');//区间刀
            $goods_id       = input('goods_id/d');
            $goods_cost_price = input('cost_price/f');
            $goods_price      = input('price/f');

            if($section['start'] > $section['end']){
                $this->error('区间刀填写错误!');
            }
            if($section['amount'] < 0){
                $this->error('区间刀价格不正确!');
            }
            if($end_num < 0){
                $this->error('剩余砍价次数不能小于0!');
            }
            $good = Goods::get($goods_id);
            if($surplus_amount > $good['cost_price']){
                $this->error('最低价不能小于成本价!');
            }


            $section = serialize($section);
            $data = [
                'section'      => $section,
                'surplus_amount' => $surplus_amount,
                'chopper_name' => $chopper_name,
                'first_amount' => $first_amount,
                'second_amount'=> $second_amount,
                'third_amount' => $third_amount,
                'end_num'      => $end_num,
                'start_time'   => strtotime($start_time),
                'end_time'     => strtotime($end_time),
                'goods_id'     => $goods_id,
                'goods_cost_price' => $goods_cost_price,
                'goods_price'      => $goods_price,
            ]; 
            $res = Db::name('goods_chopper')->where(['chopper_id' => $chopper_id])->update($data);

            if($res !== false){
                // //添加操作日志
                // slog($chopper_id);
                $this->success('修改砍一刀商品成功',url('chopper/index'));
            }else{
                $this->error('修改砍一刀商品失败!');
            }
        }

        $info   = Db::name('goods_chopper')->where('chopper_id',$chopper_id)->find();
        $section = unserialize($info['section']);
        return $this->fetch("",[
            'section'       =>  $section,
            'info'          =>  $info,
            'meta_title'    =>  '修改砍一刀',
        ]);
    }

    //砍一刀删除
    function del(){

        $chopper_id = input('chopper_id');
        if(!$chopper_id){
            jason([],'参数错误',0);
        }
        $info = Db::table('goods_chopper')->find($chopper_id);
        if(!$info){
            jason([],'参数错误',0);
        }

        $res = Db::table('goods_chopper')->where('chopper_id',$chopper_id)->update(['is_delete'=>1]);
        if($res){
            //添加操作日志
            slog($chopper_id);
            jason([],'删除成功！');
        }else{
            jason([],'删除失败！',0);
        }
    }

    /*
     * ajax 上架/下架
     */
    public function is_show(){
        $chopper_id = input('chopper_id');
        $is_show  = input('is_show');
        if(!$chopper_id){
            jason([],'参数错误',0);
        }
        $info = Db::table('goods_chopper')->find($chopper_id);
        if(!$info){
            jason([],'参数错误',0);
        }

        if( Db::table('goods_chopper')->where('chopper_id',$chopper_id)->update(['is_show'=>$is_show]) ){
            //添加操作日志
            slog($chopper_id);
            jason([]);
        }
        jason([],'失败',0);

    }

}
