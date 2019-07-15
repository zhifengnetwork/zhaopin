<?php
namespace app\api\controller;
use app\common\model\GoodsChopper;
use Overtrue\Wechat\Js;
use think\Db;
use think\Config;

class Chopper extends ApiBase
{

    // public $wxconfig;

    // public function __construct()
    // {
    //     // parent::__construct();
    //     $appId  = Config::get('wx_config.appid');
    //     $secret = Config::get('wx_config.appsecret');
    //     $js             = new Js($appId, $secret);
    //     $this->wxconfig = $js->config(array('scanQRCode', 'onMenuShareTimeline', 'onMenuShareAppMessage'), false, false);
    // }
    /**
    * 砍一刀商品列表 //判断该用户是否砍过
    */
    public function goods_list(){    
         $user_id = 100;
        // if(!$user_id){
        //     $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        // }
        //砍价专区图片
        $picture = Db::table('category')->where('cat_name','like',"%砍价%")->value('img');
        $page = input('page');
        $where['gg.is_show']   = 1;
        $where['gg.is_delete'] = 0;
        $where['gg.status']    = 2;
        $where['g.is_del']     = 0;
        $where['g.is_show']    = 1;
        $where['gi.main']      = 1;
        //砍价商品
        $list = GoodsChopper::alias('gg')
                ->join('goods g','g.goods_id=gg.goods_id','LEFT')
                ->join('goods_img gi','gi.goods_id = g.goods_id','LEFT')
                ->where($where)
                ->field('gg.chopper_id,g.goods_id,gg.chopper_price,gg.surplus_amount,gg.start_time,gg.end_time,gg.sort,g.goods_name,g.desc,gi.picture img,gg.participants')
                ->paginate(6,false,['page'=>$page]);
        if($list){
            foreach($list as &$value){
                //拿出砍价商品规格价格最低的来显示 当前用户是否砍价
                $chopper = Db::name('chopper_random')->where(['user_id' => $user_id,'chopper_id' => $value['chopper_id']])->find();
                $value['div']            = empty($chopper['already_amount'])?0:round($chopper['already_amount']/$value['chopper_price'],2) * 100;
                $value['is_chopper']     = $chopper?1:0;
                $value['already_amount'] = $chopper['already_amount'];
                $value['price']          = Db::table('goods_sku')->where('goods_id',$value['goods_id'])->where('inventory','>',0)->min('groupon_price');
            }
            unset($value);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);
    }
    /**
     * 用户记录
     */
    public function chopper_user(){
        $user_id = 100;
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $chopper_state = input('chopper_state/d',0);//0:进行中,1:已完成，2已失效
        $page          = input('page/d',0);

        $where['c.user_id']       = $user_id;
        $where['c.chopper_state'] = $chopper_state;
        //砍价商品
        $list = Db::name('chopper_random')->alias('c')
                ->join('goods_chopper gg','c.chopper_id=gg.chopper_id','LEFT')
                ->join('goods g','g.goods_id=gg.goods_id','LEFT')
                ->join('goods_img gi','gi.goods_id = g.goods_id','LEFT')
                ->where($where)
                ->field('gg.chopper_id,g.goods_id,gg.surplus_amount,gg.start_time,gg.end_time,gg.sort,g.goods_name,g.desc,gi.picture as img,c.chopper_state,c.already_amount,gg.status')
                ->paginate(6,false,['page'=>$page]);
                   
        // 返回数据
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);

    }

    /**
     * 砍一刀详情
     */
    public function chopper_edit(){
        $user_id = 100;
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $chopper_id            = input('chopper_id/d',8);
        if(!$chopper_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        }
        $where['gg.is_show']   = 1;
        $where['gg.is_delete'] = 0;
        $where['gg.status']    = 2;
        $where['g.is_del']     = 0;
        $where['g.is_show']    = 1;
        $where['gi.main']      = 1;
        $where['m.chopper_id'] = $chopper_id;
        $where['m.user_id']    = $user_id;
        $info = Db::table('chopper_random')->alias('m')
                ->join('goods_chopper gg','m.chopper_id = gg.chopper_id','LEFT')
                ->join('goods g','g.goods_id = gg.goods_id','LEFT')
                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                ->where($where)
                ->field('gg.chopper_id,g.goods_id,m.goods_amount,gg.chopper_price,m.already_amount,gg.surplus_amount,gg.start_time,gg.end_time,gg.sort,g.goods_name,g.desc,gi.picture img')
                ->find();
        if(!$info){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        }        
        //百分比
        $info['div'] = round($info['already_amount']/$info['goods_amount'],2) * 100;
        //砍价用户
        $list = Db::name('user_chopper')->where(['user_id|invite_id' => $user_id,'chopper_id' => $chopper_id])->select();
        // 返回数据
        $signPackage = (wxJSSDK())->getSignPackage();//微信sdk
        $data = [
            'info'        => $info,
            'list'        => $list,
            'signPackage' => $signPackage,
        ];
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
    }


    /***
     * 砍一刀接口
     */

    public function chopper(){
        $user_id = 100;
        $invite_id  = input('invite_id',0);//被邀请人ID
        // if(!$user_id){
        //     $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        // }
        $chopper_id = input('chopper_id/d',8);
        if(!$chopper_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        }
        
        $userchopper = Db::name('user_chopper')->where(['user_id' => $user_id,'chopper_id' => $chopper_id])->select();
        
        // if(count($userchopper) >= 1){
        //      $this->ajaxReturn(['status' => -2 , 'msg'=>'每个商品当前只能砍一次,请邀请好友哟','data'=>'']);
        // }

        $chopper = GoodsChopper::get($chopper_id);

        $random  = Db::name('chopper_random')->where(['user_id' => $user_id,'chopper_id' => $chopper_id])->find();
        if(!$random){
            $amount      = $chopper['first_amount'];//第一刀
            $random_ins = [
                'user_id'       => $user_id,
                'chopper_id'    => $chopper_id,
                'amount'        => serialize(get_randMoney($chopper['end_price'],$chopper['end_num'])),
                'end_num'       => $chopper['end_num'],
                'chopper_state' => 0,
                'already_amount'=> $amount,
                'already_num'   => 1,
                'goods_amount'  => $chopper['goods_price'],
                'create_time'   => time(),
            ];
            
            $res1 = Db::name('chopper_random')->insert($random_ins); 
            if($res1 == false){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'砍价失败，请重试！','data'=>'']);
            }
        }else{
            $section     = unserialize($chopper['section']);
            $already_num =  $random['already_num']+1;
            if($already_num  == 2){
               $amount   = $chopper['second_amount']; //第二刀
            }elseif($already_num == 3){
               $amount   =  $chopper['third_amount']; //第三刀
            }elseif($already_num >= $section['start'] && $already_num <= $section['end']){
               $amount   =  $section['amount'];//区间刀
            }else{
               if($random['end_num'] == 0){
                   $amount = 0.01;//刀满
               }else{
                   $is_random    = 1;
                   $dt_end_price = unserialize($random['amount']);//随机刀
                   $amount       = $dt_end_price[$random['end_num']-1];
               }      
            }
        } 
        //用户砍价记录
        $insert = [
            'chopper_id'   => $chopper['chopper_id'],
            'user_id'      => $user_id,
            'goods_id'     => $chopper['goods_id'],
            'invite_id'    => $invite_id,
            'status'       => 1,
            'already_num'  => $random?$already_num:1,
            'create_time'  => time(),
            'amount'       => $amount
        ];
        // 启动事务
        Db::startTrans();
        //用户记录
        $perid = Db::name('user_chopper')->strict(false)->insertGetId($insert);
        if($perid !== false){
          $update = [
              'participants'   =>  Db::raw('participants+1'),
              'chopper_num'    =>  Db::raw('chopper_num+1'),
          ]; 
          //砍价主表统计
          $res = GoodsChopper::where(['chopper_id' => $chopper['chopper_id']])->update($update);
          if($res == false){
             Db::rollback();
             $this->ajaxReturn(['status' => -2 , 'msg'=>'砍价失败，请重试！','data'=>'']);
          }
          //砍价用户从表数据统计
          if($random){
                $update_random = [
                    'chopper_state' => $random['end_num']?0:1,
                    'already_amount'=> Db::raw('already_amount+'.$amount.''),
                    'already_num'   => Db::raw('already_num+1'),
                    'end_time'      => $random['end_num']?0:time(),
                ];
                //判断是否随机刀
                if(isset($is_random)){
                    $update_random['end_num'] = Db::raw('end_num-1');
                }
                $random_res1 = Db::name('chopper_random')->where(['chopper_id' => $chopper['chopper_id'],'user_id' => $user_id])->update($update_random);
                if($random_res1 == false){
                    Db::rollback();
                    $this->ajaxReturn(['status' => -2 , 'msg'=>'砍价失败，请重试！','data'=>'']);
                }
          }
            // 提交事务
            Db::commit();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功砍掉'.$amount.'元！','data'=>'']);
        }else{
            Db::rollback();
            $this->ajaxReturn(['status' => -2 , 'msg'=>'砍价失败，请重试！','data'=>'']);
        }

    }


    public function fenxiang(){
        $signPackage = (wxJSSDK())->getSignPackage();
        $this->assign('signPackage',$signPackage);
    }

    
}
