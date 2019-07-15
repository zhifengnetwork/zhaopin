<?php
/**
 * 订单API
 */
namespace app\api\controller;
use think\Db;

class Order extends ApiBase
{


    /**
     * 购物车提交订单
     */
    public function temporary()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }

        //购物车商品
        $idStr = input('cart_id');
        
        $cart_where['id'] = array('in',$idStr);
        $cart_where['user_id'] = $user_id;
        $cartM = model('Cart');
        $cart_res = $cartM->cartList1($cart_where);
        if(!$cart_res){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'购物车商品不存在！','data'=>'']);
        }
        
        // 查询地址
        $addr_data['ua.user_id'] = $user_id;
        $addressM = Model('UserAddr');
        $addr_res = $addressM->getAddressList($addr_data);
        if($addr_res){
            foreach($addr_res as $key=>$value){
                $addr = $value['p_cn'] . $value['c_cn'] . $value['d_cn'] . $value['s_cn'];
                $addr_res[$key]['address'] = $addr . $addr_res[$key]['address'];
                unset($addr_res[$key]['p_cn'],$addr_res[$key]['c_cn'],$addr_res[$key]['d_cn'],$addr_res[$key]['s_cn']);
            }
        }
        
        $data['goods_res'] = $cart_res;
        $data['addr_res'] = $addr_res;
        
        $pay = Db::table('sysset')->value('sets');
        $pay = unserialize($pay)['pay'];

        $pay_type = config('PAY_TYPE');
        $arr = [];
        $i = 0;
        foreach($pay as $key=>$value){
            if($value){
                $arr[$i]['pay_type'] = $pay_type[$key]['pay_type'];
                $arr[$i]['pay_name'] = $pay_type[$key]['pay_name'];
                $i++;
            }
        }

        $data['pay_type'] = $arr;
        
        $order_amount = '0'; //订单价格
        $shipping_price = 0;
        $goods_ids = '';
        $goods_coupon = [];
        $cart_goods_arr = [];
        $data['groupon'] = [];
        foreach($data['goods_res'] as $key=>$value){

            if($value['groupon_id']){
                $groupon = Db::table('goods_groupon')->where('groupon_id',$value['groupon_id'])->where('goods_id',$value['goods_id'])->where('is_show',1)->where('is_delete',0)->where('status',2)->find();
                if(!$groupon){
                    Db::table('cart')->where('id',$value['id'])->delete();
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>$value['goods_id']]);
                    unset($data['goods_res'][$key]);
                }
                if($groupon['end_time'] < time()){
                    Db::table('cart')->where('id',$value['id'])->delete();
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>$value['goods_id']]);
                    unset($data['goods_res'][$key]);
                }
                $count = count($cart_res);
                if($count > 1){
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'不能和其他拼团一起下单！','data'=>'']);
                }
                $data['groupon'] = $groupon;
            }

            if( !in_array($value['goods_id'],$cart_goods_arr) ){
                $cart_goods_arr[] = $value['goods_id'];
            
                //处理运费
                $goods_res = Db::table('goods')->field('shipping_setting,shipping_price,delivery_id,less_stock_type')->where('goods_id',$value['goods_id'])->find();
                if($goods_res['shipping_setting'] == 1){
                    $shipping_price = sprintf("%.2f",$shipping_price + $goods_res['shipping_price']);   //计算该订单的物流费用
                }else if($goods_res['shipping_setting'] == 2){
                    if( !$goods_res['delivery_id'] ){
                        $deliveryWhere['is_default'] = 1;
                    }else{
                        $deliveryWhere['delivery_id'] = $goods_res['delivery_id'];
                    }
                    $delivery = Db::table('goods_delivery')->where($deliveryWhere)->find();
                    if( $delivery ){
                        if($delivery['type'] == 2){
                            $shipping_price = sprintf("%.2f",$shipping_price + $delivery['firstprice']);   //计算该订单的物流费用
                            $number = $value['goods_num'] - $delivery['firstweight'];
                            if($number > 0){
                                $number = ceil( $number / $delivery['secondweight'] );  //向上取整
                                $xu = sprintf("%.2f",$delivery['secondprice'] * $number );   //续价
                                $shipping_price = sprintf("%.2f",$shipping_price + $xu);   //计算该订单的物流费用
                            }
                        }
                    }
                }

                $order_amount = sprintf("%.2f",$order_amount + $value['subtotal_price']);   //计算该订单的总价

                $goods_coupon[$value['goods_id']]['subtotal_price'] =  $value['subtotal_price'];

                $goods_ids .= $value['goods_id'] . ',';
            }
        }
        $goods_ids = $goods_ids . 0;

        $data['goods_res'] = array_values($data['goods_res']);

        $data['shipping_price'] = $shipping_price;  //该订单的物流费用

        $coupon = Db::table('coupon_get')->alias('cg')
                    ->join('coupon c','c.coupon_id=cg.coupon_id','LEFT')
                    ->field('c.coupon_id,c.title,c.threshold,c.price,c.start_time,c.end_time,c.goods_id')
                    ->where('c.goods_id','in',$goods_ids)
                    ->where('cg.user_id',$user_id)
                    ->where('cg.is_use',0)
                    ->where('c.start_time','<',time())
                    ->where('c.end_time','>',time())
                    ->select();

        $coupon_arr = [];
        foreach($coupon as $key=>$value){
            if(isset($goods_coupon[$value['goods_id']])){
                if( $goods_coupon[$value['goods_id']]['subtotal_price'] >= $value['threshold'] ){
                    $coupon_arr[] = $value;
                }
            }

            if($value['goods_id']==0){
                if( $order_amount >= $value['threshold'] ){
                    $coupon_arr[] = $value;
                }
            }
        }

        $data['coupon'] = $coupon_arr;
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$data]);
    }


    /**
     * 提交订单
     */
    public function submitOrder()
    {   
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $cart_str = input("cart_id");
        $addr_id = input("address_id");
        $coupon_id = input("coupon_id");
        $pay_type = input("pay_type");
        $user_note = input("user_note", '', 'htmlspecialchars');

        // 查询地址是否存在
        $AddressM = model('UserAddr');

        $addrWhere = array();
        $addrWhere['address_id'] = $addr_id;
        $addrWhere['user_id'] = $user_id;
        $addr_res = $AddressM->getAddressFind($addrWhere);
        
        if (empty($addr_res)) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'该地址不存在！','data'=>'']);
        }
        
        //购物车商品
        $cart_where['id'] = array('in',$cart_str);
        $cart_where['user_id'] = $user_id;
        $cartM = model('Cart');
        $cart_res = $cartM->cartList($cart_where);
        if(!$cart_res){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'购物车商品不存在！','data'=>'']);
        }
        
        $order_amount = '0'; //订单价格
        $order_goods = [];  //订单商品
        $sku_goods = [];  //去库存
        $shipping_price = '0'; //订单运费
        $i = 0;
        $cart_ids = ''; //提交成功后删掉购物车
        $goods_ids = '';//商品IDS
        $goods_coupon = [];
        $groupon_id = 0;
        foreach($cart_res as $key=>$value){

            if($value['groupon_id']){
                $groupon = Db::table('goods_groupon')->where('groupon_id',$value['groupon_id'])->where('goods_id',$value['goods_id'])->where('is_show',1)->where('is_delete',0)->where('status',2)->find();
                if(!$groupon){
                    Db::table('cart')->where('id',$value['id'])->delete();
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>$value['goods_id']]);
                    unset($data['goods_res'][$key]);
                }
                if($groupon['end_time'] < time()){
                    Db::table('cart')->where('id',$value['id'])->delete();
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>$value['goods_id']]);
                    unset($data['goods_res'][$key]);
                }
                $count = count($cart_res);
                if($count > 1){
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'不能和其他拼团一起下单！','data'=>'']);
                }
                $groupon_id = $value['groupon_id'];
                //redis团购队列
                $redis = $this->getRedis();
                if( !$redis->lpop("GROUP_GOODS_{$groupon_id}") ){
                    Db::table('cart')->where('id',$value['id'])->delete();
                    Db::table('goods_groupon')->where('groupon_id',$groupon_id)->update(['is_show'=>0,'status'=>1]);
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>$value['goods_id']]);
                }

            }
            
            $goods_ids .= $value['goods_id'] . ',';
            $goods_coupon[$value['goods_id']]['subtotal_price'] =  $value['subtotal_price'];

            //处理运费
            $goods_res = Db::table('goods')->field('shipping_setting,shipping_price,delivery_id,less_stock_type,goods_attr')->where('goods_id',$value['goods_id'])->find();
            if($goods_res['goods_attr']){
                $goods_attr = explode(',',$goods_res['goods_attr']);
                if( in_array(6,$goods_attr) ){
                    $is_limited = 1;
                }else{
                    $is_limited = 0;
                }
            }

            if($goods_res['shipping_setting'] == 1){
                $shipping_price = sprintf("%.2f",$shipping_price + $goods_res['shipping_price']);   //计算该订单的物流费用
            }else if($goods_res['shipping_setting'] == 2){
                if( !$goods_res['delivery_id'] ){
                    $deliveryWhere['is_default'] = 1;
                }else{
                    $deliveryWhere['delivery_id'] = $goods_res['delivery_id'];
                }
                $delivery = Db::table('goods_delivery')->where($deliveryWhere)->find();
                if( $delivery ){
                    if($delivery['type'] == 2){
                        //件数
                        $shipping_price = sprintf("%.2f",$shipping_price + $delivery['firstprice']);   //计算该订单的物流费用
                        $number = $value['goods_num'] - $delivery['firstweight'];
                        if($number > 0){
                            $number = ceil( $number / $delivery['secondweight'] );  //向上取整
                            $xu = sprintf("%.2f",$delivery['secondprice'] * $number );   //续价
                            $shipping_price = sprintf("%.2f",$shipping_price + $xu);   //计算该订单的物流费用
                        }
                    }else{
                        //重量的待处理
                    }
                }

            }

            $cart_ids .= ',' . $value['cart_id'];
            $order_amount = sprintf("%.2f",$order_amount + $value['subtotal_price']);   //计算该订单的总价
            $cat_id = Db::table('goods')->where('goods_id',$value['goods_id'])->value('cat_id1');
            foreach($value['spec'] as $k=>$v){

                if($is_limited){
                    //限时购redis
                    $redis = $this->getRedis();
                    for($i=0;$i<$v['goods_num'];$i++){
                        if( !$redis->lpop("GOODS_LIMITED_{$v['sku_id']}") ){
                            for($j=1;$j<=$i;$j++){
                                $redis->rpush("GOODS_LIMITED_{$v['sku_id']}",1);
                                continue;
                            }
                            $this->ajaxReturn(['status' => -2 , 'msg'=>"商品：{$v['goods_name']}，规格：{$v['spec_key_name']}，数量：剩余{$i}件可购买！",'data'=>'']);
                            continue;
                        }
                    }
                }else{
                    $sku = Db::table('goods_sku')->where('sku_id',$v['sku_id'])->field('inventory,frozen_stock')->find();
                    $sku_num = $sku['inventory'] - $sku['frozen_stock'];
                    if( $v['goods_num'] > $sku_num ){
                        $this->ajaxReturn(['status' => -2 , 'msg'=>"商品：{$v['goods_name']}，规格：{$v['spec_key_name']}，数量：剩余{$sku_num}件可购买！",'data'=>'']);
                    }
                }

                $order_goods[$i]['goods_id'] = $v['goods_id'];
                $order_goods[$i]['user_id'] = $v['user_id'];
                $order_goods[$i]['less_stock_type'] = $goods_res['less_stock_type'];
                $order_goods[$i]['cat_id'] = $cat_id;
                $order_goods[$i]['goods_name'] = $v['goods_name'];
                $order_goods[$i]['goods_sn'] = $v['goods_sn'];
                $order_goods[$i]['goods_num'] = $v['goods_num'];
                $order_goods[$i]['final_price'] = $v['goods_price'];
                $order_goods[$i]['goods_price'] = $v['goods_price'];
                $order_goods[$i]['member_goods_price'] = $v['member_goods_price'];
                $order_goods[$i]['sku_id'] = $v['sku_id'];
                $order_goods[$i]['spec_key_name'] = $v['spec_key_name'];
                $order_goods[$i]['delivery_id'] = $goods_res['delivery_id'];
                $i++;
            }
        }
        $coupon_price = 0;
        $goods_ids = $goods_ids . 0;
        if($coupon_id){
            $couponRes = Db::table('coupon_get')->alias('cg')
                    ->join('coupon c','c.coupon_id=cg.coupon_id','LEFT')
                    ->field('c.coupon_id,c.title,c.threshold,c.price,c.start_time,c.end_time,c.goods_id')
                    ->where('c.goods_id','in',$goods_ids)
                    ->where('cg.user_id',$user_id)
                    ->where('cg.is_use',0)
                    ->where('c.start_time','<',time())
                    ->where('c.end_time','>',time())
                    ->where('c.coupon_id','=',$coupon_id)
                    ->find();
            if($couponRes){
                if(isset($goods_coupon[$couponRes['goods_id']])){
                    if( $goods_coupon[$couponRes['goods_id']]['subtotal_price'] >= $couponRes['threshold'] ){
                        $coupon_price = $couponRes['price'];
                    }
                }
                if($couponRes['goods_id']==0){
                    if( $order_amount >= $couponRes['threshold'] ){
                        $coupon_price = $couponRes['price'];
                    }
                }
            }
        }
        
        $cart_ids = ltrim($cart_ids,',');
        
        Db::startTrans();
        $goods_price = $order_amount;
        $order_amount = sprintf("%.2f",$order_amount + $shipping_price);    //商品价格+物流价格=订单金额

        $orderInfoData['order_sn'] = date('YmdHis',time()) . mt_rand(10000000,99999999);
        $orderInfoData['user_id'] = $user_id;
        $orderInfoData['groupon_id'] = $groupon_id;
        $orderInfoData['order_status'] = 1;         //订单状态 0:待确认,1:已确认,2:已收货,3:已取消,4:已完成,5:已作废,6:申请退款,7:已退款,8:拒绝退款
        $orderInfoData['pay_status'] = 0;       //支付状态 0:未支付,1:已支付,2:部分支付
        $orderInfoData['shipping_status'] = 0;       //商品配送情况;0:未发货,1:已发货,2:部分发货,3:已收货
        $orderInfoData['pay_type'] = $pay_type;    //支付方式 1:余额支付,2:微信支付,3:支付宝支付,4:货到付款
        $orderInfoData['consignee'] = $addr_res['consignee'];       //收货人
        $orderInfoData['province'] = $addr_res['province'];
        $orderInfoData['city'] = $addr_res['city'];
        $orderInfoData['district'] = $addr_res['district'];
        $orderInfoData['twon'] = $addr_res['twon'];
        $orderInfoData['address'] = $addr_res['address'];
        $orderInfoData['mobile'] = $addr_res['mobile'];
        $orderInfoData['user_note'] = $user_note;       //备注
        $orderInfoData['add_time'] = time();
        $orderInfoData['coupon_price'] = $coupon_price;     //优惠金额
        $orderInfoData['shipping_price'] = $shipping_price;     //物流费(待完善)
        $orderInfoData['goods_price'] = $goods_price;     //商品价格
        $orderInfoData['total_amount'] = $order_amount;     //订单金额
        
        if($coupon_price){
            $orderInfoData['coupon_id'] = $coupon_id;
            $orderInfoData['order_amount'] = sprintf("%.2f",$order_amount - $coupon_price);       //总金额(实付金额)
        }else{
            $orderInfoData['order_amount'] = $order_amount;       //总金额(实付金额)
        }

        $order_id = Db::table('order')->insertGetId($orderInfoData);
        
        // 添加订单商品
        foreach($order_goods as $key=>$value){

            $order_goods[$key]['order_id'] = $order_id;
            //拍下减库存
            if($value['less_stock_type']==1){
                Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
                Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
            }else if($value['less_stock_type']==2){
                //冻结库存
                Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setInc('frozen_stock',$value['goods_num']);
            }
            unset($order_goods[$key]['less_stock_type']);
        }

        //添加使用优惠券记录
        if($coupon_price){
            Db::table('coupon_get')->where('user_id',$user_id)->where('coupon_id',$coupon_id)->update(['is_use'=>1,'use_time'=>time()]);
        }
        
        $res = Db::table('order_goods')->insertAll($order_goods);
        if (!empty($res)) {
            //将商品从购物车删除
            Db::table('cart')->where('id','in',$cart_str)->delete();
            
            Db::commit();
            $this->ajaxReturn(['status' => 1 ,'msg'=>'提交成功！','data'=>$order_id]);
        } else {
            Db::rollback();
            $this->ajaxReturn(['status' => -2 , 'msg'=>'提交订单失败！','data'=>'']);
        }
    }

   /**
    * 订单列表
    */
    public function order_list()
    {   
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $type = input('type');
        if(!$type) $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        
        $page = input('page',1);
        
        $where = [];
        $pageParam = ['query' => []];

        if ($type=='dfk'){
            $where = array('order_status' => 1 ,'pay_status'=>0 ,'shipping_status' =>0); //待付款
            $pageParam['query']['order_status'] = 1;
            $pageParam['query']['pay_status'] = 0;
            $pageParam['query']['shipping_status'] = 0;
        }
        if ($type=='dfh'){
            $where = array('order_status' => 1 ,'pay_status'=>1 ,'shipping_status' =>0); //待发货
            $pageParam['query']['order_status'] = 1;
            $pageParam['query']['pay_status'] = 1;
            $pageParam['query']['shipping_status'] = 0;
        }
        if ($type=='dsh'){
            $where = array('order_status' => 1 ,'pay_status'=>1 ,'shipping_status' =>1); //待收货
            $pageParam['query']['order_status'] = 1;
            $pageParam['query']['pay_status'] = 1;
            $pageParam['query']['shipping_status'] = 1;
        }
        if ($type=='dpj'){
            $where = array('order_status' => 4 ,'pay_status'=>1 ,'shipping_status' =>3); //待评价
            $pageParam['query']['order_status'] = 4;
            $pageParam['query']['pay_status'] = 1;
            $pageParam['query']['shipping_status'] = 3;
        }
        if ($type=='tk'){
            $where = array('order_status' => [['=',6],['=',7],['=',8],'or'] ,'pay_status'=>1); //退款/售后
            $pageParam['query']['order_status'] = [['=',6],['=',7],['=',8],'or'];
            $pageParam['query']['pay_status'] = 1;
        }
        if ($type=='yqx'){
            $where = array('order_status' => 3); //已取消
            $pageParam['query']['order_status'] = 3;
        }

        $where['o.user_id'] = $user_id;
        $where['gi.main'] = 1;
        $where['o.deleted'] = 0;

        $order_list = Db::table('order')->alias('o')
                        ->join('order_goods og','og.order_id=o.order_id','LEFT')
                        ->join('goods_img gi','gi.goods_id=og.goods_id','LEFT')
                        ->join('goods g','g.goods_id=og.goods_id','LEFT')
                        ->where($where)
                        ->group('og.order_id')
                        ->order('o.order_id DESC')
                        ->field('o.order_id,o.order_sn,og.goods_name,gi.picture img,og.spec_key_name,og.goods_price,g.original_price,og.goods_num,o.order_status,o.pay_status,o.shipping_status,pay_type')
                        ->paginate(10,false,$pageParam)
                        ->toArray();
                        
        if($order_list['data']){
            foreach($order_list['data'] as $key=>&$value){

                $value['comment'] = 0; 
                if( $value['order_status'] == 1 && $value['pay_status'] == 0 && $value['shipping_status'] == 0 ){
                    $value['status'] = 1;   //待付款
                }else if( $value['order_status'] == 1 && $value['pay_status'] == 1 && $value['shipping_status'] == 0 ){
                    $value['status'] = 2;   //待发货
                }else if( $value['order_status'] == 1 && $value['pay_status'] == 1 && $value['shipping_status'] == 1 ){
                    $value['status'] = 3;   //待收货
                }else if( $value['order_status'] == 4 && $value['pay_status'] == 1 && $value['shipping_status'] == 3 ){
                    $value['status'] = 4;   //待评价
                    
                    //是否评价
                    $comment = Db::table('goods_comment')->where('order_id',$value['order_id'])->find();
                    if($comment){
                        $value['comment'] = 1;
                    }else{
                        $value['comment'] = 0; 
                    }

                }else if( $value['order_status'] == 3 && $value['pay_status'] == 0 && $value['shipping_status'] == 0 ){
                    $value['status'] = 5;   //已取消
                }else if( $value['order_status'] == 6 ){
                    $value['status'] = 6;   //待退款
                }else if( $value['order_status'] == 7 ){
                    $value['status'] = 7;   //已退款
                }else if( $value['order_status'] == 8 ){
                    $value['status'] = 8;   //拒绝退款
                }
            }
        }
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$order_list['data']]);
    }


    /**
    * 订单详情
    */
    public function order_detail()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $order_id = input('order_id');

        $where['o.user_id'] = $user_id;
        $where['o.order_id'] = $order_id;

        $order = Db::name('order')->alias('o')->where($where)->where('deleted',0)->find();
        if(!$order){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'订单不存在','data'=>'']);
        }

        $field = array(
            'o.order_id',//订单ID
            'o.order_sn',//订单编号
            'o.order_status',//订单状态
            'o.pay_status',//支付状态
            'o.shipping_status',//商品配送情况
            'o.pay_type',//支付类型
            'o.consignee',//收货人
            'o.mobile',//收货人手机号
            'o.province',//省
            'o.city',//市
            'o.district',//区
            'o.twon',//街道
            'o.address',//地址
            'o.coupon_price',//优惠券抵扣
            'o.order_amount',//订单总价
            'o.total_amount',//应付款金额
            'o.add_time',//下单时间
            'o.shipping_name',//物流名称
            'o.shipping_price',//物流费用
            'o.user_note',//订单备注
            'o.pay_time',//支付时间
            'o.user_money',//使用余额
        );

        $order = Db::table('order')->alias('o')->where($where)->field($field)->find();

        $pay_type = config('PAY_TYPE');
        foreach($pay_type as $key=>$value){
            if($value['pay_type'] == $order['pay_type']){
                $order['pay_type'] = $value;
            }
        }
        
        $order_refund = 0;
        $data['order_refund'] = [];
        if( $order['order_status'] == 1 && $order['pay_status'] == 0 && $order['shipping_status'] == 0 ){
            $order['status'] = 1;   //待付款
        }else if( $order['order_status'] == 1 && $order['pay_status'] == 1 && $order['shipping_status'] == 0 ){
            $order['status'] = 2;   //待发货
        }else if( $order['order_status'] == 1 && $order['pay_status'] == 1 && $order['shipping_status'] == 1 ){
            $order['status'] = 3;   //待收货
        }else if( $order['order_status'] == 4 && $order['pay_status'] == 1 && $order['shipping_status'] == 3 ){
            $order['status'] = 4;   //待评价
        }else if( $order['order_status'] == 3 && $order['pay_status'] == 0 && $order['shipping_status'] == 0 ){
            $order['status'] = 5;   //已取消
        }else if( $order['order_status'] == 6 ){
            $order['status'] = 6;   //待退款
            $order_refund = 1;
        }else if( $order['order_status'] == 7 ){
            $order['status'] = 7;   //已退款
            $order_refund = 1;
        }else if( $order['order_status'] == 8 ){
            $order['status'] = 8;   //拒绝退款
            $order_refund = 1;
        }

        if($order_refund){
            $order['order_refund'] = Db::table('order_refund')->where('order_id',$order_id)->find();
        }
        $order['order_refund']['count_num'] = 0;
        $order['goods_res'] = Db::table('order_goods')->field('goods_id,goods_name,goods_num,spec_key_name,goods_price')->where('order_id',$order['order_id'])->select();
        foreach($order['goods_res'] as $key=>$value){
            $order['order_refund']['count_num'] += $value['goods_num'];
            $order['goods_res'][$key]['original_price'] = Db::table('goods')->where('goods_id',$value['goods_id'])->value('original_price');
            $order['goods_res'][$key]['img'] = Db::table('goods_img')->where('goods_id',$value['goods_id'])->where('main',1)->value('picture');
        }

        $order['province'] = Db::table('region')->where('area_id',$order['province'])->value('area_name');
        $order['city'] = Db::table('region')->where('area_id',$order['city'])->value('area_name');
        $order['district'] = Db::table('region')->where('area_id',$order['district'])->value('area_name');
        $order['twon'] = Db::table('region')->where('area_id',$order['twon'])->value('area_name');

        $order['address'] = $order['province'].$order['city'].$order['district'].$order['twon'].$order['address'];
        unset($order['province'],$order['city'],$order['district'],$order['twon']);
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$order]);
    }

    /**
    * 修改状态
    */
    public function edit_status(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id');
        $status = input('status');

        if($status != 1 && $status != 3 && $status != 4 && $status != 5){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        }

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_status,groupon_id,pay_status,shipping_status')->find();
        if(!$order) $this->ajaxReturn(['status' => -2 , 'msg'=>'订单不存在！','data'=>'']);

        if( $order['order_status'] == 1 && $order['pay_status'] == 0 && $order['shipping_status'] == 0 ){
            //取消订单
            if($status != 1) $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
            Db::startTrans();
            $res = Db::table('order')->update(['order_id'=>$order_id,'order_status'=>3]);

            $order_goods = Db::table('order_goods')->where('order_id',$order_id)->field('goods_id,sku_id,goods_num')->select();
            foreach($order_goods as $key=>$value){
                $goods = Db::table('goods')->where('goods_id',$value['goods_id'])->field('goods_attr,less_stock_type')->find();
                if($goods['less_stock_type'] == 1){
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setInc('inventory',$value['goods_num']);
                    Db::table('goods')->where('goods_id',$value['goods_id'])->setInc('stock',$value['goods_num']);
                }else if($goods['less_stock_type'] == 2){
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('frozen_stock',$value['goods_num']);
                }
                //团购
                if( $order['groupon_id'] ){
                    $redis = getRedis();
                    $redis->rpush("GOODS_GROUP_{$order['groupon_id']}",1);
                }
                //限时购
                if($goods['goods_attr']){
                    $attr = explode(',',$goods['goods_attr']);
                    if(in_array(6,$attr)){
                        $redis = getRedis();
                        for($i=0;$i<$value['goods_num'];$i++){
                            $redis->rpush("GOODS_LIMITED_{$value['sku_id']}",1);
                        }
                    }
                }
            }
            if($res){
                Db::commit();
            }else{
                Db::rollback();
            }
        }else if( $order['order_status'] == 1 && $order['pay_status'] == 1 && $order['shipping_status'] == 1 ){
            //确认收货
            if($status != 3) $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
            $res = Db::table('order')->update(['order_id'=>$order_id,'order_status'=>4,'shipping_status'=>3]);
        }else if( ($order['order_status'] == 4 && $order['pay_status'] == 1 && $order['shipping_status'] == 3) || $order['order_status'] == 3 ){
            //删除订单
            if($status != 4 && $status != 5) $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
            $res = Db::table('order')->update(['order_id'=>$order_id,'deleted'=>1]);
        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>'']);
    }

    /**
    * 订单商品评论
    */
    public function order_comment(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $comments = input('comments');
        $comments = json_decode($comments ,true);

        $order_id = $comments[0]['order_id'];

        $res = Db::table('goods_comment')->where('order_id',$order_id)->find();
        if($res) $this->ajaxReturn(['status' => -2 , 'msg'=>'此订单您已评论过！','data'=>'']);

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_status,pay_status,shipping_status')->find();
        if(!$order) $this->ajaxReturn(['status' => -2 , 'msg'=>'订单不存在！','data'=>'']);
        
        if( $order['order_status'] != 4 && $order['pay_status'] != 1 && $order['shipping_status'] != 3 ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        }

        $order_goods = Db::table('order_goods')
                            ->where('order_id',$order_id)
                            ->field('goods_id,sku_id')
                            ->select();
        $time = time();        
        foreach($order_goods as $key=>$value){

            if($order_goods[$key]['goods_id'] == $comments[$key]['goods_id'] && $order_goods[$key]['sku_id'] == $comments[$key]['sku_id']){
                if(!empty($comments[$key]['img'])){
                    foreach ($comments[$key]['img'] as $k => $val) {
                        $val = explode(',',$val)[1];
                        $saveName = request()->time().rand(0,99999) . '.png';

                        $img=base64_decode($val);
                        //生成文件夹
                        $names = "comment" ;
                        $name = "comment/" .date('Ymd',time()) ;
                        if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){ 
                            mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
                        }
                        //保存图片到本地
                        file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$img);

                        // unset($comments[$key]['img'][$k]);
                        $comments[$key]['img'][$k] = $name.$saveName;
                    }
                    $comments[$key]['img'] = implode(',',$comments[$key]['img']);
                }
            }else{
                $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
            }
            $comments[$key]['add_time'] = $time;
        }

        $res = Db::table('goods_comment')->insertAll($comments);

        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>'']);
        }

        $this->ajaxReturn(['status' => -2 , 'msg'=>'提交失败！','data'=>'']);
    }

    /**
    * 获取订单商品评论列表
    */
    public function order_comment_list(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id');

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_status,pay_status,shipping_status')->find();
        if(!$order) $this->ajaxReturn(['status' => -2 , 'msg'=>'订单不存在！','data'=>'']);

        if( $order['order_status'] == 4 && $order['pay_status'] == 1 && $order['shipping_status'] == 3 ){
            $order_goods = Db::table('order_goods')->alias('og')
                            ->join('goods_img gi','gi.goods_id=og.goods_id')
                            ->where('gi.main',1)
                            ->where('og.order_id',$order_id)
                            ->field('og.goods_id,og.sku_id,og.goods_name,og.goods_num,og.spec_key_name,gi.picture img')
                            ->select();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$order_goods]);
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        }
    }

    /**
    * 获取退款信息
    */
    public function get_refund(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id');
        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_id,order_status,pay_status,shipping_status,consignee,mobile')->find();
        if(!$order) $this->ajaxReturn(['status' => -2 , 'msg'=>'订单不存在！','data'=>'']);
        if($order['pay_status'] == 0){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
            // $this->ajaxReturn(['status' => -2 , 'msg'=>'该订单还未付款！','data'=>'']);
        }
        if( $order['order_status'] > 3 && $order['shipping_status'] > 4 ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        }

        $data['consignee'] = $order['consignee'];
        $data['mobile'] = $order['mobile'];
        $data['refund_reason'] = config('REFUND_REASON');
        $data['refund_type'] = config('REFUND_TYPE');

        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$data]);
    }

    /**
    * 申请退款
    */
    public function apply_refund(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id');
        $refund_type = input('refund_type');
        $refund_reason = input('refund_reason');
        $cancel_remark = input('cancel_remark');
        $create_time = time();
        $img = input('img');

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_id,order_status,pay_status,shipping_status')->find();
        if(!$order) $this->ajaxReturn(['status' => -2 , 'msg'=>'订单不存在！','data'=>'']);

        if($order['pay_status'] == 0){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
            // $this->ajaxReturn(['status' => -2 , 'msg'=>'该订单还未付款！','data'=>'']);
        }

        if( $order['order_status'] > 3 ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        }

        $refund = Db::table('order_refund')->where('order_id',$order['order_id'])->find();
        if($refund){
            if($refund['refund_status'] == 0){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'此订单已被拒绝退款！','data'=>'']);
            }else if($refund['refund_status'] == 1){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'您已申请退款，待审核中！','data'=>'']);
            }else if($refund['refund_status'] == 2){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'该订单已退款！','data'=>'']);
            }
        }

        if(!empty($img)){
            $img = json_decode($img,true);
            foreach ($img as $k => $val) {
                $val = explode(',',$val)[1];
                $saveName = request()->time().rand(0,99999) . '.png';

                $imga=base64_decode($val);
                //生成文件夹
                $names = "refund" ;
                $name = "refund/" .date('Ymd',time()) ;
                if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){ 
                    mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
                }
                //保存图片到本地
                file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$imga);

                // unset($img[$k]);
                $img[$k] = $name.$saveName;
            }
            $img = implode(',',$img);
        }

        $data['order_id']  = $order_id;
        $data['refund_sn'] = 'ZF' . date('YmdHis',time()) . mt_rand(100000,999999);
        $data['refund_type']   = $refund_type;
        $data['refund_reason'] = $refund_reason;
        $data['cancel_remark'] = $cancel_remark;
        $data['create_time']   = $create_time;
        $data['img']   = $img;
        $data['refund_status'] = 1;
        Db::startTrans();
        $res = Db::table('order_refund')->insert($data);

        Db::table('order')->update(['order_id'=>$order_id,'order_status'=>6]);

        if($res){
            Db::commit();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>'']);
        }else{
            Db::rollback();
            $this->ajaxReturn(['status' => -2 , 'msg'=>'申请退款失败！','data'=>'']);
        }
    }

    /**
    * 取消申请退款
    */
    public function cancel_refund(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id');

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_id,order_status,pay_status,shipping_status')->find();
        if(!$order) $this->ajaxReturn(['status' => -2 , 'msg'=>'订单不存在！','data'=>'']);

        if($order['order_status'] != 6){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        }
        
        if($order['shipping_status'] == 0 || $order['shipping_status'] == 1){
            $res = Db::table('order')->update(['order_id'=>$order_id,'order_status'=>1]);
        }else if($order['shipping_status'] == 3){
            $res = Db::table('order')->update(['order_id'=>$order_id,'order_status'=>4]);
        }

        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'取消申请退款成功！','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'取消申请退款失败！','data'=>'']);
        }
    }
}
