<?php
namespace app\admin\controller;



use Payment\Config;
use think\Loader;
use think\Request;
use think\Db;

/**
 * 首页
 */
class Index extends Common
{
    public function index()
    {
        $where= [];
        $where['status'] = ['>=',0];
        $list       = Db::table('diy_ewei_shop')
                    ->where($where)
                    ->order('id')
                    ->paginate(10, false, ['page' => request()->param('page')]);
        $this->assign('list', $list);
        $this->assign('meta_title', '店铺装修');
        return $this->fetch();
    }


    public function page_edit(){
        $id  = request()->param('id',0,'intval');
        $this->assign('id',$id);
        $this->assign('meta_title', '页面编辑');
        return $this->fetch();
    }


    public function page_add(){
        $this->assign('meta_title', '页面新增');
        return $this->fetch();
    }

    public function status(){
        
    }

    public function page_enable () {
        $id = request()->param('id',0,'intval');
        $status = request()->param('status',0,'intval');
        if (!empty($id)){
            $getPage = model('DiyEweiShop')->where(['id'=>$id])->find();
            if (!empty($getPage)){
                if ($getPage['status'] == $status){
                    if ($status == 0){
                        return json(['code'=>0, 'msg'=>'该页面已经是禁用了','data'=>[]]);
                    }else{
                        return json(['code'=>0, 'msg'=>'该页面已经是启用了','data'=>[]]);
                    }
                }else{
                    if ($status == 1){
                        $getThisEnablePage = model('DiyEweiShop')->where(['status'=>1])->find();
                        if (!empty($getThisEnablePage)){
                            model('DiyEweiShop')->where(['id'=>$getThisEnablePage['id']])->update(['status'=>0]);
                        }
                    }
                    $updateThisPage = model('DiyEweiShop')->where(['id'=>$id])->update(['status'=>$status]);
                    if ($updateThisPage){
                        return json(['code'=>1, 'msg'=>'操作成功','data'=>[]]);
                    }else{
                        return json(['code'=>0, 'msg'=>'操作失败','data'=>[]]);
                    }
                }
            }else{
                return json(['code'=>0, 'msg'=>'页面不存在！','data'=>[]]);
            }
        }else{
            return json(['code'=>0, 'msg'=>'id不存在','data'=>[]]);
        }
    }

    public function page_delete () {
        $id = request()->param('id',0,'intval');
        if (!empty($id)) {
            $getPage = model('DiyEweiShop')->where(['id' => $id])->find();
            if (!empty($getPage)){
                $delete = model('DiyEweiShop')->where(['id'=>$id])->update(['status'=>-1]);
                if ($delete){
                    return json(['code'=>1, 'msg'=>'操作成功','data'=>[]]);
                }else{
                    return json(['code'=>0, 'msg'=>'操作失败','data'=>[]]);
                }
            }else{
                return json(['code'=>0, 'msg'=>'页面不存在！','data'=>[]]);
            }
        }else{
            return json(['code'=>0, 'msg'=>'id不存在','data'=>[]]);
        }
    }

    /***
     * 支付方式
     */
    public function pay_wechat(){
        $sysset = Db::table('sysset')->find();
        $set    = unserialize($sysset['sets']);
        if( Request::instance()->isPost() ){
            $data = input('post.');
            $set['pay']['weixin'] = $data['pay']['weixin'];
            $set['pay']['credit'] = $data['pay']['credit'];
            $set['pay']['cash']   = $data['pay']['cash'];
            $set['wechat']['account_name'] =  $data['wechat']['account_name'];
            $set['wechat']['appid']        =  $data['wechat']['appid'];
            $set['wechat']['secret']       =  $data['wechat']['secret'];
            $set['wechat']['key']          =  $data['wechat']['key'];
            $set['wechat']['mchid']        =  $data['wechat']['mchid'];
            $set['wechat']['apikey']       =  $data['wechat']['apikey'];
            $update['sets']    =   serialize($set);
            $res = Db::table('sysset')->where(['id' => $sysset['id']])->update($update);
            if($res){
                $this->success('编辑成功', url('index/pay_wechat'));
            }
            $this->error('编辑失败');
        }
        $this->assign('set', $set);
        $this->assign('meta_title', '微信支付');
        return $this->fetch();
    }

    /***
     * 支付宝
     */
    public function pay_alipay(){
        $sysset = Db::table('sysset')->find();
        $set    = unserialize($sysset['sets']);
        if(Request::instance()->isPost()){
            $data         = input('post.');
            $set['pay']['alipay'] = $data['pay']['alipay'];
            $set['pay']['credit'] = $data['pay']['credit'];
            $set['pay']['cash']   = $data['pay']['cash'];
            $set['alipay']['account_name'] =  $data['alipay']['account_name'];
            $set['alipay']['appid']        =  $data['alipay']['appid'];
            $set['alipay']['public_key']   =  $data['alipay']['public_key'];
            $set['alipay']['private_key']  =  $data['alipay']['private_key'];
            $set['alipay']['notify_url']   =  $data['alipay']['notify_url'];
            $update['sets']  = serialize($set);
            $res = Db::table('sysset')->where(['id' => $sysset['id']])->update($update);
            if($res !== false ){
                $this->success('编辑成功', url('index/pay_alipay'));
            }
            $this->error('编辑失败');
        }
        $this->assign('set', $set);
        $this->assign('meta_title', '支付宝');
        return $this->fetch();
    }
    /**
     * 支付交易设置
     */
    public function pay_py(){
            $sysset     = Db::table('sysset')->field('*')->find();
            $set        = unserialize($sysset['sets']);
        if(Request::instance()->isPost()){
            $trade = input('post.');
            
            $set['trade']   = $trade['trade'];
            
            $sysset['sets'] = serialize($set);
            
            $res = Db::table('sysset')->where(['uniacid' => 3])->update($sysset);
            if($res !== false ){
                $this->success('编辑成功', url('index/pay_py'));
            }
            $this->error('编辑失败');
        }
    
        $this->assign('set', $set);
        $this->assign('meta_title', '支付交易设置');
        return $this->fetch();

    }
    /**
     * 商城提醒
     */
    public function notice(){
        $sysset     = Db::table('sysset')->field('*')->find();
        $set        = unserialize($sysset['sets']);
      
        if(Request::instance()->isPost()){
            $notice          = input('post.');

            $set['notice']   = $notice['notice'];
            
            $sysset['sets'] = serialize($set);
            
            $res = Db::table('sysset')->where(['uniacid' => 3])->update($sysset);
            if($res !== false ){
                $this->success('编辑成功', url('index/notice'));
            }
            $this->error('编辑失败');
        }
        $this->assign('newtype', $set['notice']['newtype']);
        $this->assign('set', $set);
        $this->assign('meta_title', '支付交易设置');
        return $this->fetch();
    }


}
