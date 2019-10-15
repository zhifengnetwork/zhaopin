<?php
namespace app\api\controller;
use Payment\Notify\PayNotifyInterface;
use Payment\Config;
use think\Loader;
use think\Db;

/**
 * @author: helei
 * @createTime: 2016-07-20 18:31
 * @description:
 */

/**
 * 客户端需要继承该接口，并实现这个方法，在其中实现对应的业务逻辑
 * Class TestNotify
 * anthor helei
 */
class TestNotify implements PayNotifyInterface
{
    public function notifyProcess(array $data)
    {
        $channel = $data['channel'];
        if ($channel === Config::ALI_CHARGE){// 支付宝支付
            // array (
            //     'notify_time' => '2019-05-16 19:40:33',
            //     'notify_type' => 'trade_status_sync',
            //     'notify_id' => '2019051600222194032091281017974707',
            //     'app_id' => '2019050264367537',
            //     'transaction_id' => '2019051622001491281034488845',
            //     'order_no' => '20190515213016563588',
            //     'out_biz_no' => '',
            //     'buyer_id' => '2088022531091287',
            //     'buyer_account' => '151****2455',
            //     'seller_id' => '2088531154918656',
            //     'seller_email' => 'gzyx5558@163.com',
            //     'trade_state' => 'success',
            //     'amount' => '0.01',
            //     'receipt_amount' => '0.01',
            //     'invoice_amount' => '0.01',
            //     'pay_amount' => '0.01',
            //     'point_amount' => '0.00',
            //     'refund_fee' => '',
            //     'subject' => '支付宝支付',
            //     'body' => 'ADS大声地说',
            //     'trade_create_time' => '2019-05-16 19:40:31',
            //     'pay_time' => '2019-05-16 19:40:32',
            //     'trade_refund_time' => '',
            //     'trade_close_time' => '',
            //     'channel' => 'ali_charge',
            //     'fund_bill_list' => 
            //     array (
            //       0 => 
            //       array (
            //         'amount' => '0.01',
            //         'fundChannel' => 'ALIPAYACCOUNT',
            //       ),
            //     ),
            //   )
            //修改订单状态
            $update = [
                'seller_id'      => $data['seller_id'],
                'transaction_id' => $data['transaction_id'],
                'order_status'   => 1,
                'pay_status'     => 1,
                'pay_time'       => strtotime($data['pay_time']),
            ];

            Db::startTrans();

            Db::name('order')->where(['order_sn' => $data['order_no']])->update($update);

            $order = Db::table('order')->where(['order_sn' => $data['order_no']])->field('order_id,groupon_id,user_id')->find();

            $goods_res = Db::table('order_goods')->field('goods_id,goods_name,goods_num,spec_key_name,goods_price,sku_id')->where('order_id',$order['order_id'])->select();
            $jifen = 0;
            foreach($goods_res as $key=>$value){

                // $sku = Db::table('goods_sku')->where('sku_id',$value['sku_id'])->field('inventory,frozen_stock')->find();
                // $sku_num = $sku['inventory'] - $sku['frozen_stock'];
                // if( $value['goods_num'] > $sku_num ){
                //     Db::rollback();
                //     $this->ajaxReturn(['status' => -2 , 'msg'=>"商品：{$value['goods_name']}，规格：{$value['spec_key_name']}，数量：剩余{$sku_num}件可购买！",'data'=>'']);
                // }

                $goods = Db::table('goods')->where('goods_id',$value['goods_id'])->field('less_stock_type,gift_points')->find();
                //付款减库存
                if($goods['less_stock_type']==2){
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('frozen_stock',$value['goods_num']);
                    Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
                }
                $baifenbi = strpos($goods['gift_points'] ,'%');
                if($baifenbi){
                    $goods['gift_points'] = substr($goods['gift_points'],0,strlen($goods['gift_points'])-1); 
                    $goods['gift_points'] = $goods['gift_points'] / 100;
                    $jg = sprintf("%.2f",$value['goods_price'] * $value['goods_num']);
                    $jifen = sprintf("%.2f",$jifen + ($jg * $goods['gift_points']));
                }else{
                    $goods['gift_points'] = $goods['gift_points'] ? $goods['gift_points'] : 0;
                    $jifen = sprintf("%.2f",$jifen + ($value['goods_num'] * $goods['gift_points']));
                }
            }
            //团购
            Db::table('goods_groupon')->where('groupon_id',$order['groupon_id'])->setInc('sold_number',1);

            $res = Db::table('member')->update(['id'=>$order['user_id'],'gouwujifen'=>$jifen]);

            //判断用户是否是puls会员
            $is_puls = model('Member')->is_puls($order['user_id']);
            if (empty($is_puls)){
                //不是puls会员
                $update_ispuls = model('Member')->create_puls($order['user_id'],$order['order_id'],1);
            }else{
                $update_ispuls = 1;
            }
            
            if($order['order_id'] && $update_ispuls){
                Db::commit();
                return true;
            }else{
                Db::rollback();
                return false;
            }


            
        } elseif ($channel === Config::WX_CHARGE) {// 微信支付
            $amount=$data['amount'];
            $order_sn=$data['order_no'];
            $is_who=substr($order_sn,0,1);
            $recharge=Db::name('recharge')->where(['recharge_sn'=>$order_sn])->find();
            if(!$recharge||$recharge['status']==1){
                return false;
            }
            $user_id=$recharge['user_id'];
            $member=Db::name('member')->where(['user_id'=>$user_id])->find();
            if($is_who=='R'){//充值
                Db::startTrans();
                $update = [
//                    'seller_id'      => $data['seller_id'],
//                    'transaction_id' => $data['transaction_id'],
                    'status'     => 1,
                    'pay_time'       => strtotime($data['pay_time']),
                ];
                Db::name('recharge')->where(['recharge_sn'=>$order_sn])->update($update);
                $res=Db::table('member')->where('id',$user_id)->setInc('balance',$amount);
                if(!$res){
                    Db::rollback();
                    return false;
                }
                $data=[];
                $data['user_id']=$user_id;
                $data['money']=$amount;
                $data['old_balance']=$member['balance'];
                $data['balance']=sprintf("%.2f",$member['balance']+$amount);
                $data['balance_type']='充值';
                $data['source_type']=2;
                $data['log_type']=0;
                $data['source_id']=$user_id;
                $data['create_time']=time();
                Db::name('member_balance_log')->insertGetId($data);
                Db::commit();
                return true;
            }elseif ($is_who=='V'){//VIP充值
                $vip_type=$recharge['for_id'];
                $sysset = Db::table('sysset')->field('*')->find();
                $set =json_decode($sysset['vip'], true);
                $member['month_money']=$set['month_money'];
                $member['quarter_money']=$set['quarter_money'];
                $member['year_money']=$set['year_money'];
                $company=Db::name('company')->where(['user_id'=>$user_id])->find();
                if(!$company){
                    return false;
                }
                $vip_time=$company['vip_time'];
                $money=0;
                if($vip_time<time()){
                    $vip_time=time();
                }
                $num=0;
                switch ($vip_type){
                    case 1:
                        $money=$set['month_money'];
                        $num=$set['month'];
                        $vip_time=strtotime("+1 month",$vip_time);
                        break;
                    case 2:
                        $money=$set['quarter_money'];
                        $num=$set['quarter'];
                        $vip_time=strtotime("+3 month",$vip_time);
                        break;
                    case 3:
                        $money=$set['year_money'];
                        $num=$set['year'];
                        $vip_time=strtotime("+12 month",$vip_time);
                        break;
                    default:
                        $this->ajaxReturn(['status' => -2, 'msg' => '会员类型不存在','data'=>[]]);
                        break;
                }
                Db::startTrans();
                Db::table('member')->where('id',$user_id)->setDec('balance',$money);
                $data['is_vip']=1;
                $data['vip_type']=$vip_type;
                $data['vip_time']=$vip_time;
                $data['reserve_num']=$company['reserve_num']+$num;
                $data['reserve_num_all']=$company['reserve_num_all']+$num;
                $res=Db::name('company')->where(['user_id'=>$user_id])->update($data);
                if(!$res){
                    Db::rollback();
                    return false;
                }
                $update = [
//                    'seller_id'      => $data['seller_id'],
//                    'transaction_id' => $data['transaction_id'],
                    'status'     => 1,
                    'pay_time'       => strtotime($data['pay_time']),
                ];
                Db::name('recharge')->where(['recharge_sn'=>$order_sn])->update($update);
                $data=[];
                $data['user_id']=$user_id;
                $data['money']=$money;
                $data['old_balance']=$member['balance'];
                $data['balance']=sprintf("%.2f",$member['balance']-$money);
                $data['balance_type']='VIP购买';
                $data['source_type']=4;
                $data['log_type']=0;
                $data['source_id']=$user_id;
                $data['create_time']=time();
                $m_id=Db::name('member_balance_log')->insertGetId($data);
                if(!$m_id){
                    Db::rollback();
                    return false;
                }
                Db::commit();
                return true;
            }elseif ($is_who=='Y'){//预约
//                $reserve_money = Db::name('config')->where(['name'=>'reserve_money'])->value('value');
                Db::startTrans();
                $update = [
//                    'seller_id'      => $data['seller_id'],
//                    'transaction_id' => $data['transaction_id'],
                    'status'     => 1,
                    'pay_time'       => strtotime($data['pay_time']),
                ];
                Db::name('recharge')->where(['recharge_sn'=>$order_sn])->update($update);
                if (Db::name('person')->where(['id' => $recharge['to_id']])->update(['reserve_c' =>$recharge['for_id']])) {
                    //删除收藏该应聘者的数据，除了预约的第三方或公司
                    Db::name('collection')->where(['type' => 2, 'to_id' => $recharge['to_id'], 'user_id' => ['neq', $recharge['user_id']]])->delete();
                    Db::commit();
                    return true;
                }else{
                    Db::rollback();
                    return false;
                }
            }else{
                return false;
            }
        } elseif ($channel === Config::CMB_CHARGE) {// 招商支付
        } elseif ($channel === Config::CMB_BIND) {// 招商签约
        } else {
            // 其它类型的通知
        }
        // 执行业务逻辑，成功后返回true
        return true;
    }
}