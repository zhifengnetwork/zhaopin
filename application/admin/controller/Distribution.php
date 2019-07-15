<?php
namespace app\admin\controller;

use think\Db;
use think\Loader;
use think\Request;

/*
 * 分销管理
 */
class Distribution extends Common
{

    /*
     * 分销中心入口
     */
    public function index(){



        return $this->fetch('',[
            'meta_title'    =>  '分销列表',
            // 'info'  =>  $info,
        ]);
    }

    /*
     * 分销中心入口
     */
    public function distribution_center(){
        
        $shop_id = session('admin_user_auth.mgid');
        $info = Db::table('distribution_set')->where('shop_id',$shop_id)->find();

        if( request()->isPost() ){
            $data = input('post.');
            
            if( isset($data['cover_img']) ) $data['cover_img'] = $this->base_img($data['cover_img'],'distribution_set','cover_img',$info['cover_img']);

            if($info){
                $data['id'] = $info['id'];
                //添加操作日志
                slog($data['id'],'edit');
                $res = Db::name('distribution_set')->update($data);
            }else{
                $data['shop_id'] = $shop_id;
                $res = Db::name('distribution_set')->insertGetId($data);
                //添加操作日志
                slog($res);
            }

            if( $res !== false ){
                $this->success('修改成功！');
            }
            $this->success('修改失败！');
        }

        return $this->fetch('',[
            'meta_title'    =>  '分销中心入口',
            'info'  =>  $info,
        ]);
    }

    /*
     * 分销设置
     */
    public function distribution_set(){
        
        $shop_id = session('admin_user_auth.mgid');
        $info = Db::table('distribution_set')->where('shop_id',$shop_id)->find();

        if( request()->isPost() ){
            $data = input('post.');
            
            if( isset($data['cover_img']) ) $data['cover_img'] = $this->base_img($data['cover_img'],'distribution_set','cover_img',$info['cover_img']);

            if($info){
                $data['id'] = $info['id'];
                //添加操作日志
                slog($data['id'],'edit');
                $res = Db::name('distribution_set')->update($data);
            }else{
                $data['shop_id'] = $shop_id;
                $res = Db::name('distribution_set')->insertGetId($data);
                //添加操作日志
                slog($res);
            }

            if( $res !== false ){
                $this->success('修改成功！');
            }
            $this->success('修改失败！');
        }

        return $this->fetch('',[
            'meta_title'    =>  '分销设置',
            'info'  =>  $info,
        ]);
    }

    /*
     * 分销关系
     */
    public function distribution_relations(){
        $shop_id = session('admin_user_auth.mgid');
        $info = Db::table('distribution_set')->where('shop_id',$shop_id)->find();

        if( request()->isPost() ){
            $data = input('post.');
            
            if($info){
                $data['id'] = $info['id'];
                //添加操作日志
                slog($data['id'],'edit');
                $res = Db::name('distribution_set')->update($data);
            }else{
                $data['shop_id'] = $shop_id;
                $res = Db::name('distribution_set')->insertGetId($data);
                //添加操作日志
                slog($res);
            }

            if( $res !== false ){
                $this->success('修改成功！');
            }
            $this->success('修改失败！');
        }

        return $this->fetch('',[
            'meta_title'    =>  '分销关系',
            'info'  =>  $info,
        ]);
    }

    /*
     * 分销等级列表
     */
    public function distribution_grade(){

        $where = [];
        $pageParam = ['query' => []];

        $list = Db::table('distribution_level')->where($where)->order('id DESC')->paginate(10,false,$pageParam);
        $page = $list->render();
        $list = $list->toArray();
        if($list['data']){
            foreach($list['data'] as $key=>$value){
                $value['authority'] = unserialize($value['authority']);
                if( isset($value['authority']['is_withdraw']) && $value['authority']['is_withdraw'] == 1 ){   $list['data'][$key]['authority_item'] = ',佣金提现'; }
                if( isset($value['authority']['is_qrcode']) && $value['authority']['is_qrcode'] == 1 ){     $list['data'][$key]['authority_item'] .= ',推广二维码'; } 
                if( isset($value['authority']['is_optional']) && $value['authority']['is_optional'] == 1 ){   $list['data'][$key]['authority_item'] .= ',自选商品'; }
                if( isset($value['authority']['is_shop']) && $value['authority']['is_shop'] == 0 ){       $list['data'][$key]['authority_item'] .= ',我的小店'; }
                if( isset($value['authority']['show_goods']) && $value['authority']['show_goods'] == 1 ){    $list['data'][$key]['authority_item'] .= ',订单商品详情'; }
                if( isset($value['authority']['show_customer']) && $value['authority']['show_customer'] == 1 ){ $list['data'][$key]['authority_item'] .= ',订单购买者详情'; }
                if( isset($value['authority']['is_remind']) && $value['authority']['is_remind'] == 1 ){     $list['data'][$key]['authority_item'] .= ',消息提醒'; }
                if( isset($value['authority']['is_message']) && $value['authority']['is_message'] == 1 ){    $list['data'][$key]['authority_item'] .= ',开启留言'; }
                if( isset($value['authority']['is_rank']) && $value['authority']['is_rank'] == 1 ){       $list['data'][$key]['authority_item'] .= ',开启权重'; }
                if( empty($list['data'][$key]['authority_item'])){
                    $list['data'][$key]['authority_item'] = '无';
                }else{
                    $list['data'][$key]['authority_item'] = ltrim($list['data'][$key]['authority_item'],',');
                }
            }
        }
        
        return $this->fetch('',[
            'meta_title'    =>  '分销等级列表',
            'list'          =>  $list,
            'page'          =>  $page,
        ]);
    }

    /*
     * 添加新等级
     */
    public function distribution_grade_add(){

        $id = input('id');

        $info = Db::table('distribution_level')->where('id',$id)->find();
        
        if($info) $info['updatelevel'] = @json_decode($info['updatelevel'], true);
        if($info) $info['authority'] = unserialize($info['authority']);

        if( request()->isPost() ){
            $data = input('post.');

            $data['updatelevel'] = json_encode($data['updatelevel']);
            $data['authority']   = isset($data['authority']) ? serialize($data['authority']) : serialize(array());

            if($id){
                //添加操作日志
                slog($id,'edit');
                $res = Db::table('distribution_level')->update($data);
            }else{
                $res = Db::table('distribution_level')->insertGetId($data);
                //添加操作日志
                slog($res);
            }

            if($res !== false){
                $this->success('成功！',url('distribution/distribution_grade'));
            }else{
                $this->error('失败！');
            }

        }
        
        if($id){
            $grade = Db::table('distribution_level')->field('id,levelname')->where('id','<>',$id)->select();
        }else{
            $grade = Db::table('distribution_level')->field('id,levelname')->select();
        }
        
        return $this->fetch('',[
            'meta_title'    =>  '添加新等级',
            'info'          =>  $info,
            'grade'         =>  $grade,
        ]);
    }

    /*
     * 删除等级
     */
    public function distribution_grade_del(){
        $id = input('id');

        if(!$id){
            jason([],'参数错误',0);
        }

        $info = Db::table('distribution_level')->find($id);
        if(!$info){
            jason([],'参数错误',0);
        }

        if( Db::table('distribution_level')->where('id',$id)->delete() ){
            //添加操作日志
            slog($id);
            jason([],'删除成功！');
        }
    }


    /*
     * 通知设置
     */
    public function distribution_notify(){

        $shop_id = session('admin_user_auth.mgid');
        $info = Db::table('distribution_set')->field('id,tm')->where('shop_id',$shop_id)->find();

        if( request()->isPost() ){
            $data = input('post.');
            $data['tm'] = serialize($data['tm']);
            if($data['id']){
                //添加操作日志
                slog($data['id']);
                Db::table('distribution_set')->update($data);
            }else{
                $id = Db::table('distribution_set')->insertGetId($data);
                //添加操作日志
                slog($id);
            }
            $this->success('成功！');
        }

        if($info['tm']) $info['tm'] = unserialize($info['tm']);

        return $this->fetch('',[
            'meta_title'    =>  '通知设置',
            'info'          =>  $info,
        ]);
    }



}
