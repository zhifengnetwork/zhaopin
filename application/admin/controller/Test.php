<?php
namespace app\admin\controller;
use think\Db;
class Test
{
    //测试分销
    public function index()
    {
        $retrun=$this->getTree(1);  //获取初始数据
        $retrun=$this->orderTree($retrun);  //根据等级排序
        dump($retrun);exit;
    }

    //写入数据库 测试
    public function insert_into_sql(){

    }


    //测试 接下来，后面还应有个排序
    function getTree($pid = 1,&$treeList=array(),$level = 0) {
        //$link为数据库连接，&$treeList为输出数组，因为需要累积结果，所以加上引用
        $level+=1; //count为识别分级深度的标识
        $result=Db::table('Test_tree')->where("pid=".$pid )->field('*')->order('user_id ASC')->select();
        foreach($result as $k=>$v){
            $arr=array();
//            $arr['id']=$v['id'];
            $arr['level'] = $level;
            $arr['user_id']=$v['user_id'];
            $treeList[]=$arr;
            $this->getTree($v['user_id'],$treeList,$level); //再次调用自身，这时的pid为上一条数据的id从而找到上一条数据的子分类;
        }
//        dump($treeList);exit;
        return $treeList; //输出结果
    }

    //排序
    function orderTree($treeList=array()){
        if(is_array($treeList)){
            $arr=$treeList;
            for($i=0;$i<count($arr)-1;$i++){
                for($j=0;$j<count($arr)-1-$i;$j++){
                    /**
                     * 比较第j位和j+1的initial值
                     * 如果j位的initial值比j+1位的initial值大，那么他们的位置发生交换
                     * 如果j位的initial值比j+1位的initial值小，那么位置不变
                     */
                    if($arr[$j]['level'] > $arr[($j+1)]['level']){
                        $temp=$arr[$j];
                        $arr[$j]=$arr[($j+1)];
                        $arr[($j+1)]=$temp;
                    }
                }
            }
            return $arr;
        }
    }




}
