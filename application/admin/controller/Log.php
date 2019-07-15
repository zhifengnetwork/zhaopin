<?php
namespace app\admin\controller;

use think\Db;
use think\Config;

class Log extends Common
{   

    public function index()
    {

        $where = [];
        $pageParam = ['query' => []];
        
        $keyword = input('keyword');
        if( $keyword ){
            $where['mu.username'] = ['like', "%{$keyword}%"];
            $pageParam['query']['keyword'] = $keyword;
        }

        $start_time = input('start_time');
        if( $start_time ){
            $where['al.createtime'] = ['>', strtotime($start_time)];
        }

        $end_time = input('end_time');
        if( $end_time ){
            $where['al.createtime'] = array(array('gt', strtotime($start_time) ),array('lt', strtotime($end_time)+(60*60*24)-1 ));
        }

        $list = Db::table('admin_log')->alias('al')
                ->join('mg_user mu','mu.mgid=al.uid','LEFT')
                ->field('al.*,mu.username')
                ->where($where)
                ->order('al.id DESC')
                ->paginate(10,false,$pageParam);

        return $this->fetch('',[
            'meta_title'    =>  '系统操作日志',
            'list'          =>  $list,
            'keyword'       =>  $keyword,
            'start_time'    =>  $start_time,
            'end_time'      =>  $end_time,
        ]);
    }

    public function del(){
        $id = input('id');
        if(!$id){
            jason([],'参数错误',0);
        }
        $info = Db::table('admin_log')->find($id);
        if(!$info){
            jason([],'参数错误',0);
        }

        if( Db::table('admin_log')->where('id',$id)->delete() ){
            jason([],'删除成功！');
        }

    }
}
