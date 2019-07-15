<?php
namespace app\admin\controller;
use think\Loader;
use think\Db;

class Groupon extends Common
{
    function index(){
        //根据传过来的条件
        $where = [];
        $pageParam = ['query' => []];
        $where['is_delete'] =  0;
        $groupon_name = input('groupon_name');
        if(!empty($groupon_name)){
            $where['groupon_name'] =  ['like',"%{$groupon_name}%"];
            $pageParam['query']['groupon_name'] = $groupon_name;
        }

        $is_show = input('is_show');
        if($is_show  != null){
            $where['is_show'] =  $is_show;
            $pageParam['query']['is_show'] = $is_show;
        }

        $list=Db::name('goods_groupon')->order('sort desc,groupon_id desc')->Where($where)->paginate(10,false);

        $page=$list->render();
        $list = $list->all();
        
        return $this->fetch("",[
            'groupon_name' => $groupon_name,
            'is_show'    => $is_show,
            'list'=>$list,
            'page'=>$page,
            'meta_title'    =>  '团购列表',
        ]);

    }

    //添加团购
    function add(){
        if( request()->isPost() ){
            $data = input('post.');
            
            //求出有没有这个团购有则求出最大值
            $period =  Db::name('goods_groupon')->where('goods_id','=',$data['goods_id'])->max('period');
            if ($period){
                $data['period'] = $period +1 ;
            }else{
                $data['period'] = 1 ;
            }

            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $data['groupon_name'] = $data['groupon_name'] . '_第' .$data['period'] .'期';
            
            //添加信息
            $groupon_id =  Db::name('goods_groupon')->insertGetId($data);
            if($groupon_id){
                //redis
                $redis = $this->getRedis();
                for($i=1;$i<=$data['target_number'];$i++){
                    $redis->rpush("GOODS_GROUP_{$groupon_id}",1);
                }

                //添加操作日志
                slog($groupon_id);
                $this->success('添加团购商品成功',url('Groupon/index'));
            }else{
                $this->error('添加团购商品失败!');
            }
        }

        return $this->fetch("",[
            'meta_title'    =>  '添加团购',
        ]);
    }

    //修改团购
    function edit(){
        $groupon_id = input('groupon_id');
        $info = Db::name('goods_groupon')->where('groupon_id',$groupon_id)->find();
        if(request()->isPost()){
            $data=input('post.');
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $res = Db::name('goods_groupon')->where('groupon_id',$data['groupon_id'])->update($data);
            if($res !== false){
                //redis
                $redis = $this->getRedis();
                $num = $data['target_number'] - $redis->llen("GOODS_GROUP_{$data['groupon_id']}");
                for($i=1;$i<=$num;$i++){
                    $redis->rpush("GOODS_GROUP_{$groupon_id}",1);
                }

                //添加操作日志
                slog($groupon_id);
                $this->success('修改团购商品成功',url('Groupon/index'));
            }else{
                $this->error('修改团购商品失败!');
            }
        }

        

        return $this->fetch("",[
            'info'=>$info,
            'meta_title'    =>  '修改团购',
        ]);
    }

    //团购删除
    function del(){

        $groupon_id = input('groupon_id');
        if(!$groupon_id){
            jason([],'参数错误',0);
        }
        $info = Db::table('goods_groupon')->find($groupon_id);
        if(!$info){
            jason([],'参数错误',0);
        }

        $res = Db::table('goods_groupon')->where('groupon_id',$groupon_id)->update(['is_delete'=>1]);
        if($res){
            //添加操作日志
            slog($groupon_id);
            jason([],'删除成功！');
        }else{
            jason([],'删除失败！',0);
        }
    }

    /*
     * ajax 上架/下架
     */
    public function is_show(){
        $groupon_id = input('groupon_id');
        $is_show  = input('is_show');
        if(!$groupon_id){
            jason([],'参数错误',0);
        }
        $info = Db::table('goods_groupon')->find($groupon_id);
        if(!$info){
            jason([],'参数错误',0);
        }

        if( Db::table('goods_groupon')->where('groupon_id',$groupon_id)->update(['is_show'=>$is_show]) ){
            //添加操作日志
            slog($groupon_id);
            jason([]);
        }
        jason([],'失败',0);

    }

}
