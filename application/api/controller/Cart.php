<?php
/**
 * 购物车API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use app\common\logic\CartLogic;
use think\Request;
use think\Db;

class Cart extends ApiBase
{
    
    /*
     * 请求获取购物车列表
     */
    public function cartlist()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $cart_where['user_id'] = $user_id;
        $cartM = model('Cart');
        $cart_res = $cartM->cartList1($cart_where);
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$cart_res]);
    }

    /**
     * 购物车总数
     */
    public function cart_sum(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $cart_where['user_id'] = $user_id;
        $cart_where['groupon_id'] = 0;
        $num = Db::table('cart')->where($cart_where)->sum('goods_num');

        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$num]);
    }

    /**
     * 加入 | 修改 购物车
     */
    public function addCart()
    {   

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        // input('sku_id/d',0)

        $sku_id       = Request::instance()->param("sku_id", 0, 'intval');
        $groupon_id   = Request::instance()->param("groupon_id", 0, 'intval');
        $cart_number  = Request::instance()->param("cart_number", 1, 'intval');
        $act = Request::instance()->param('act');

        if( !$sku_id || !$cart_number ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'该商品不存在！','data'=>'']);
        }

        $sku_res = Db::name('goods_sku')->where('sku_id', $sku_id)->field('price,groupon_price,inventory,frozen_stock,goods_id')->find();

        if (empty($sku_res)) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'该商品不存在！','data'=>'']);
        }

        if ($cart_number > ($sku_res['inventory']-$sku_res['frozen_stock'])) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'该商品库存不足！','data'=>'']);
        }

        $goods = Db::table('goods')->where('goods_id',$sku_res['goods_id'])->field('single_number,most_buy_number')->find();

        if( $cart_number > $goods['single_number'] ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>"超过单次购买数量！同类商品单次只能购买{$goods['single_number']}个",'data'=>'']);
        }

        $order_goods_num = Db::table('order_goods')->alias('og')
                            ->join('order o','o.order_id=og.order_id')
                            ->where('o.order_status','neq',3)
                            ->where('og.goods_id',$sku_res['goods_id'])
                            ->where('og.user_id',$user_id)
                            ->sum('og.goods_num');
        
        $num =  $cart_number + $order_goods_num;
        if( $num > $goods['most_buy_number'] ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'超过最多购买量！','data'=>'']);
        }

        $cart_where = array();
        $cart_where['user_id'] = $user_id;
        $cart_where['goods_id'] = $sku_res['goods_id'];
        
        $act_where = [];
        if($act){
            $act_where['sku_id'] = ['not in',$sku_id];
        }

        $cart_goods_num = Db::table('cart')->where($cart_where)->where($act_where)->sum('goods_num');

        $num = $cart_number + $cart_goods_num;
        if( $num > $goods['single_number'] ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>"超过单次购买数量！同类商品单次只能购买{$goods['single_number']}个",'data'=>'']);
        }
        if( $num > $goods['most_buy_number'] ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'超过最多购买量！','data'=>'']);
        }

        if($groupon_id){
            $groupon = Db::table('goods_groupon')->where('groupon_id',$groupon_id)->where('goods_id',$sku_res['goods_id'])->where('is_show',1)->where('is_delete',0)->find();
            if($groupon){
                if( $groupon['status'] != 2 && $groupon['status'] != 0 ){
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>$sku_res['goods_id']]);
                }else if( $groupon['status'] == 2 ){
                    if( ($groupon['target_number'] - $groupon['sold_number']) <= 0 ){
                        $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>$sku_res['goods_id']]);
                    }
                    if( $groupon['end_time'] < time() ){
                        $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>$sku_res['goods_id']]);
                    }
                    $group_order = Db::table('order')->where('groupon_id',$groupon_id)->where('user_id',$user_id)->value('order_id');
                    if($group_order){
                        $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团您已参与，请勿重复参与！','data'=>'']);
                    }
                    $group_cart = Db::table('cart')->where('groupon_id',$groupon_id)->where('user_id',$user_id)->value('sku_id');
                }else{
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'该商品没有拼团！','data'=>'']);
                }
            }else{
                $this->ajaxReturn(['status' => -2 , 'msg'=>'该商品没有拼团！','data'=>'']);
            }
        }

        if($groupon_id){
            if($group_cart){
                if($sku_id != $group_cart){
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团商品已存在购物！','data'=>'']);
                }
            }
        }
        
        $cart_where['groupon_id'] = $groupon_id;
        $cart_where['sku_id'] = $sku_id;
        $cart_res = Db::table('cart')->where($cart_where)->field('id,goods_num')->find();

        if ($cart_res) {

            $new_number = $cart_res['goods_num'] + $cart_number;
            if($act){
                $new_number = $cart_number;
            }

            if ($new_number <= 0) {
                $result = Db::table('cart')->where('id',$cart_res['id'])->delete();
                $this->ajaxReturn(['status' => -2 , 'msg'=>'该购物车商品已删除！','data'=>'']);
            }

            if ($sku_res['inventory'] >= $new_number) {
                $update_data = array();
                $update_data['id'] = $cart_res['id'];
                $update_data['goods_num'] = $new_number;
                if($groupon_id){
                    $update_data['subtotal_price'] = $new_number * $sku_res['groupon_price'];
                }else{
                    $update_data['subtotal_price'] = $new_number * $sku_res['price'];
                }
                $result = Db::table('cart')->update($update_data);
                $cart_id = $cart_res['id'];
            } else {
                $this->ajaxReturn(['status' => -2 , 'msg'=>'该商品库存不足！','data'=>'']);
            }
        } else {
            $cartData = array();
            $goods_res = Db::name('goods')->where('goods_id',$sku_res['goods_id'])->field('goods_name,price,original_price')->find();
            $cartData['groupon_id'] = $groupon_id;
            $cartData['goods_id'] = $sku_res['goods_id'];
            $cartData['selected'] = 0;
            $cartData['goods_name'] = $goods_res['goods_name'];
            $cartData['sku_id'] = $sku_id;
            $cartData['user_id'] = $user_id;
            $cartData['market_price'] = $goods_res['original_price'];
            if($groupon_id){
                $cartData['goods_price'] = $sku_res['groupon_price'];
                $cartData['member_goods_price'] = $sku_res['groupon_price'];
                $cartData['subtotal_price'] = $cart_number * $sku_res['groupon_price'];
            }else{
                $cartData['goods_price'] = $sku_res['price'];
                $cartData['member_goods_price'] = $sku_res['price'];
                $cartData['subtotal_price'] = $cart_number * $sku_res['price'];
            }
            $cartData['goods_num'] = $cart_number;
            $cartData['add_time'] = time();
            $sku_attr = action('Goods/get_sku_str', $sku_id);
            $cartData['spec_key_name'] = $sku_attr;
            $cart_id = Db::table('cart')->insertGetId($cartData);
            $cart_id = intval($cart_id);
        }

        if($cart_id) {
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$cart_id]);
        } else {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'系统异常！','data'=>'']);
        }
    }

    /**
     * 删除购物车
     */
    public function delCart()
    {   
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $idStr = Request::instance()->param("cart_id", '', 'htmlspecialchars');
        
        $where['id'] = array('in', $idStr);
        $where['user_id'] = $user_id;
        $cart_res = Db::table('cart')->where($where)->column('id');
        if (empty($cart_res)) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'购物车不存在！','data'=>'']);
        }
        
        $res = Db::table('cart')->delete($cart_res);
        if ($res) {
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>'']);
        } else {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'系统异常！','data'=>'']);
        }
    }

    /**
     * 选中状态
     */
    public function selected(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $cart_id = Request::instance()->param("cart_id", '', 'htmlspecialchars');

        $selected = Db::table('cart')->where('id',$cart_id)->value('selected');
        if(!$selected && $selected != 0){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'购物车不存在！','data'=>'']);
        }

        if($selected){
            $res = Db::table('cart')->where('id',$cart_id)->update(['selected'=>0]);
        }else{
            $res = Db::table('cart')->where('id',$cart_id)->update(['selected'=>1]);
        }

        if ($res) {
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>'']);
        } else {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'系统异常！','data'=>'']);
        }

    }

}
