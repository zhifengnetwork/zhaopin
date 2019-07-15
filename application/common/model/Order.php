<?php
namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class Order extends Model
{
    protected $updateTime = false;

    protected $autoWriteTimestamp = true;

    public function getAddressRegionAttr($value, $data)
    {
        $regions = Db::name('region')->where('id', 'IN', [$data['province'], $data['city'], $data['district'], $data['twon']])->order('level desc')->select();
        $address = '';
        if ($regions) {
            foreach ($regions as $regionKey => $regionVal) {
                $address = $regionVal['name'] . $address;
            }
        }
        return $address;
    }

    public function getPayStatusDetailAttr($value, $data)
    {
        $pay_status = config('PAY_STATUS');
        return $pay_status[$data['pay_status']];
    }

    public function getShippingStatusDetailAttr($value, $data)
    {
        $shipping_status = config('SHIPPING_STATUS');
        return $shipping_status[$data['shipping_status']];
    }


    public function shop()
    {
        return $this->hasOne('shop', 'shop_id', 'shop_id');
    }

    public function shopOrder()
    {
        return $this->hasOne('ShopOrder', 'order_id', 'order_id');
    }

    //获取所有订单商品
    public function OrderGoods()
    {
        return $this->hasMany('OrderGoods', 'order_id', 'order_id');
    }

    //订单商品总数
    public function countGoodsNum()
    {
        return $this->hasMany('OrderGoods', 'order_id', 'order_id')->sum('goods_num');
    }

    /**
     * 订单支付期限
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getFinallyPayTimeAttr($value, $data)
    {
        return $data['add_time'] + config('finally_pay_time');
    }


    /**
     * 订单发票
     * @return string
     */
    public function invoice()
    {
        return $this->hasOne('invoice', 'order_id', 'order_id');
    }

    public function getShippingStatusDescAttr($value, $data)
    {
        $config = config('SHIPPING_STATUS');
        return $config[$data['shipping_status']];
    }

     /**
     * 订单详细收货地址
     * @param $value
     * @param $data
     * @return string
     */
    public function getFullAddressAttr($value, $data)
    {
        $province = Db::name('region')->where(['area_id' => $data['province']])->value('area_name');
        $city     = Db::name('region')->where(['area_id' => $data['city']])->value('area_name');
        $district = Db::name('region')->where(['area_id' => $data['district']])->value('area_name');
        $address = $province . '，' . $city . '，' . $district . '，' . $data['address'];
        return $address;
    }

    /**
     *	处理发货单
     * @param array $data  查询数量
     * @return array
     * @throws \think\Exception
     */
    public function deliveryHandle($data){
       
        $orderObj    = $this->get($data['order_id']);
        $order       = $orderObj->append(['full_address','orderGoods'])->toArray();
        $orderGoods  = $order['orderGoods'];
		$selectgoods = $data['goods'];
        if($data['shipping'] == 1){
            if (!$this->updateOrderShipping($data,$order)){
                return array('status'=>0,'msg'=>'操作失败！！');
            }
        }
		$data['order_sn']    = $order['order_sn'];
		$data['delivery_sn'] = $this->get_delivery_sn();
		$data['zipcode']     = $order['zipcode'];
		$data['user_id']     = $order['user_id'];
		$data['admin_id']    = UID;
		$data['consignee']   = $order['consignee'];
		$data['mobile']      = $order['mobile'];
		$data['country']     = $order['country'];
		$data['province']    = $order['province'];
		$data['city']        = $order['city'];
		$data['district']    = $order['district'];
		$data['address']     = $order['address'];
		$data['shipping_price'] = $order['shipping_price'];
        $data['create_time']    = time();

        $insert = [
            'order_id'  => $data['order_id'],
            'order_sn'  => $order['order_sn'],
            'user_id'   => $order['user_id'],
            'admin_id'  => UID,
            'consignee' => $order['consignee'],
            'zipcode'   => $order['zipcode'],
            'mobile'    => $order['mobile'],
            'country'   => $order['country'],
            'province'  => $order['province'],
            'city'      => $order['city'],
            'district'  => $order['district'],
            'address'   => $order['address'],
            'shipping_code'   => $order['shipping_code'],
            'shipping_name'   => $order['shipping_name'],
            'shipping_price'  => $order['shipping_price'],
            'note'            => $data['note'],
            'create_time'     => time(),
            'send_type'       => $data['send_type'],
        ];
         // 启动事务
         Db::startTrans();
        
         $did = Db::table('delivery_doc')->insertGetId($insert);
         
         if($did == false){
             Db::rollback();
             return array('status'=>0,'msg'=>'发货失败');
         }
       
        //订单发货在线下单、电子面单
    	// if($data['send_type'] == 0 || $data['send_type'] == 3){
		// 	$did = Db::table('delivery_doc')->insertGetId($data);
		// }else{
		// 	$result = $this->submitOrderExpress($data,$orderGoods);
		// 	if($result['status'] == 1){
		// 		$did = $result['did'];
		// 	}else{
		// 		return array('status'=>0,'msg'=>$result['msg']);
		// 	}
        // }
		$is_delivery = 0;
		foreach ($orderGoods as $k=>$v){
			if($v['is_send'] >= 1){
				$is_delivery++;
            }	
			if($v['is_send'] == 0 && in_array($v['rec_id'],$selectgoods)){
				$res['is_send']     = 1;
				$res['delivery_id'] = $did;
				$r = Db::table('order_goods')->where("rec_id=".$v['rec_id'])->update($res);//改变订单商品发货状态
				$is_delivery++;
			}
        }
        //shipping_code or shipping_name 为null时报错
        $update['shipping_time'] = time();
        $update['shipping_code'] = isset($data['shipping_code'])? $data['shipping_code'] : '';
        $update['shipping_name'] = isset($data['shipping_name'] )? $data['shipping_name'] : '';
        if($is_delivery == count($orderGoods)){
            $update['shipping_status'] = 1;
        }else{
            $update['shipping_status'] = 2;
        }
        
        if($order['order_status'] == 0 && $order['shipping_status'] == 0){
            $update['order_status'] = 1;
        }
        
        $change_res  =  Db::table('order')->where("order_id=".$data['order_id'])->update($update);//改变订单状态

        if($change_res == false){
            Db::rollback();
            return array('status'=>0,'msg'=>'发货失败');
        }
        
		// $s = $this->orderActionLog($order['order_id'],'delivery',$data['note']);//操作日志
		
		//商家发货, 发送短信给客户
		// $res = checkEnableSendSms("5");
		// if ($res && $res['status'] ==1) {
		//     $user_id = $data['user_id'];
		//     $users = Db::table('member')->where('id', $user_id)->field('id , realname , mobile')->find();
		//     if($users){
		//         $realname = $users['realname'];
		//         $sender   = $users['mobile'];
		//         $params   = array('user_name' => $realname , 'consignee' => $data['consignee']);
		//         $resp     = sendSms("5", $sender, $params,'');
		//     }
		// }

      
        // 发送微信模板消息通知
        // $wechat = new WechatLogic;
        // $wechat->sendTemplateMsgOnDeliverNew($data);
        if(true){
        	// $order_arr = Db::name('order_goods')->where("order_id", $order['order_id'])->find();
        	// // 添加发消息通知
		    // $goods_original_img = Db::name('goods')->where("goods_id", $order_arr['goods_id'])->value('original_img');
		    // $send_data = [
		    //     'message_title' => '商品已发货',
		    //     'message_content' => $order_arr['goods_name'],
		    //     'img_uri' => $goods_original_img,
		    //     'order_sn' => $order['order_sn'],
		    //     'order_id' => $order['order_id'],
		    //     'mmt_code' => 'deliver_goods_logistics',
		    //     'type' => 2,
		    //     'users' => [$order['user_id']],
			// 	'category' => 2,
		    //     'message_val' => []
		    // ];
			// $messageFactory = new MessageFactory();
			// $messageLogic = $messageFactory->makeModule($send_data);
			// $messageLogic->sendMessage();
            // 提交事务
            Db::commit();
            return array('status'=>1,'msg' => '发货成功');
        }else{
            Db::rollback();
            return array('status'=>0,'msg' => '发货失败');
        }
     }

    /**
     * 修改订单发货信息
     * @param array $data
     * @param array $order
     * @return bool|mixed
     */
    public function updateOrderShipping($data=[],$order=[]){
        $updata['shipping_code'] = $data['shipping_code'];
        $updata['shipping_name'] = $data['shipping_name'];
        $this->where(['order_id'=>$data['order_id']])->save($updata);//改变物流信息
        $updata['invoice_no']    = $data['invoice_no'];
        $delivery_res = Db::name('delivery_doc')->where(['order_id'=>$data['order_id']])->save($updata);  //改变售后的信息
        //todo::待添加操作日志
        // if ($delivery_res){
        //     return $this->orderActionLog($order['order_id'],'订单修改发货信息',$data['note']);//操作日志
        // }else{
        //     return false;
        // }

    }
    
    /**
     * 得到发货单流水号
     */
    public function get_delivery_sn()
    {
    // /* 选择一个随机的方案 */send_http_status('310');
        mt_srand((double) microtime() * 1000000);
        return date('YmdHi') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }


    //订单发货在线下单、电子面单
	public function submitOrderExpress($data,$orderGoods){
		/*code_21快递鸟电子面单*/
		$eorder = [];
		$eorder["ShipperCode"] = $data['shipping_code'];//物流公司编码
		$eorder["OrderCode"] =  $data['order_sn'];//订单号
		$eorder["PayType"] = 1;
		$eorder["ExpType"] = 1;

		$shop_info  = tpCache('shop_info');
		$region_ids = array($shop_info['province'],$shop_info['city'],$shop_info['district'],$data['province'],$data['city'],$data['district']);
		$region     = Db::name('region')->where(array('id'=>array('in',$region_ids)))->getField('id,name');

		$sender = [];
		$sender["Name"] = $shop_info['contact'];
		$sender["Mobile"] = $shop_info['mobile'];
		$sender["ProvinceName"] = $region[$shop_info['province']];
		$sender["CityName"] = $region[$shop_info['city']];
		$sender["ExpAreaName"] = $region[$shop_info['district']];
		$sender["Address"] = $shop_info['address'];

		$receiver = [];
		$receiver["Name"] = $data['consignee'];
		$receiver["Mobile"] = $data['mobile'];
		$receiver['PostCode'] = $data['zipcode'];
		$receiver["ProvinceName"] = $region[$data['province']];
		$receiver["CityName"] = $region[$data['city']];
		$receiver["ExpAreaName"] = $region[$data['district']];
		$receiver["Address"] = $data['address'];

		$commodityOne = $commodity = [];
		foreach ($orderGoods as $val){
			if($val['is_send'] == 0 && in_array($val['rec_id'],$data['goods'])){
				$commodityOne["GoodsName"] = $val['goods_name'];
				$commodityOne['Goodsquantity'] = $val['goods_num'];
				$commodity[] = $commodityOne;
			}
		}

		$eorder["Sender"] = $sender;//收件人信息
		$eorder["Receiver"] = $receiver;//发件人信息
		$eorder["Commodity"] = $commodity;//发货商品信息
		$eorder['Remark'] = $data['note'];

		$jsonParam = json_encode($eorder, JSON_UNESCAPED_UNICODE);
		$jsonParam = str_replace("+","/",$jsonParam); // 电子面单时，商品带有+号，会提示非法参数
		require_once(PLUGIN_PATH . 'kdniao/kdniao.php');
		//1001预约取件接，1007电子面单
		$request_type = ($data['send_type']>1) ? 1007 : 1001;
		$kdniao = new \kdniao($request_type);
		$jsonResult = $kdniao->submitEOrder($jsonParam);
		$res = json_decode($jsonResult,true);
		if(!$res['Success']){
			return array('status'=>0,'msg'=>$res['Reason']);
		}else{
			$data['invoice_no'] = $res['Order']['LogisticCode'];
			$did = Db::name('delivery_doc')->add($data);
			$printhtml = empty($res['PrintTemplate']) ? '' : $res['PrintTemplate'];
			return array('status'=>1,'did'=>$did,'printhtml'=>$printhtml);
		}
		/*code_21快递鸟电子面单*/
    }
}
