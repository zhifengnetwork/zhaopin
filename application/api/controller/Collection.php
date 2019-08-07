<?php
/**
 * 收藏API
 */
namespace app\api\controller;
use think\Db;
use app\common\model\Region;

class Collection extends ApiBase
{

    /**
     * 收藏列表
     */
    public function collection_list(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $regtype=input('regtype');
        if(!$regtype){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误','data'=>'']);
        }
//        $type=Db::name('member')->where('id',$user_id)->value('regtype');
        if($regtype==1){
            $list = Db::table('collection')->alias('c')
                ->join('recruit r','r.id=c.to_id','LEFT')
                ->join('company co','co.id=r.company_id','LEFT')
                ->join('member m','m.id=co.user_id','LEFT')
                ->join('category ca','ca.cat_id=r.type','LEFT')
                ->field('r.id,r.title,ca.cat_name,r.work_age,r.require_cert,r.salary,co.logo,m.regtype')
                ->where('c.type',1)
                ->where('c.user_id',$user_id)
                ->where('m.regtype',$regtype)
                ->select();
            foreach ($list as $key=>$value){
//                $list[$key]['city']=Region::getName($list[$key]['city']);
//                $list[$key]['district']=Region::getName($list[$key]['district']);
                $list[$key]['logo']=SITE_URL.$list[$key]['logo'];
            }
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$list]);
        }elseif ($regtype==2){
            $list = Db::table('collection')->alias('c')
                ->join('recruit r','r.id=c.to_id','LEFT')
                ->join('company co','co.id=r.company_id','LEFT')
                ->join('member m','m.id=co.user_id','LEFT')
                ->join('category ca','ca.cat_id=r.type','LEFT')
                ->field('r.id,r.title,ca.cat_name,r.work_age,r.require_cert,r.salary,co.logo,m.regtype')
                ->where('c.type',1)
                ->where('c.user_id',$user_id)
                ->where('m.regtype',$regtype)
                ->select();
            foreach ($list as $key=>$value){
//                $list[$key]['city']=Region::getName($list[$key]['city']);
//                $list[$key]['district']=Region::getName($list[$key]['district']);
                $list[$key]['logo']=SITE_URL.$list[$key]['logo'];
            }
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$list]);
        }elseif ($regtype==3){
            $list = Db::table('collection')->alias('c')
                ->join('person p','p.id=c.to_id','LEFT')
                ->join('member m','m.id=p.user_id','LEFT')
                ->join('category ca','ca.cat_id=p.job_type','LEFT')
                ->field('p.id,p.name,p.desc,ca.cat_name,p.work_age,p.images,p.avatar,m.regtype')
                ->where('c.type',2)
                ->where('c.user_id',$user_id)
                ->where('m.regtype',$regtype)
                ->select();
            foreach ($list as $key=>$value){
                $list[$key]['images']=$list[$key]['images']!='[]'?1:0;
                $list[$key]['avatar']=SITE_URL.$list[$key]['avatar'];
            }
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$list]);
        }
//        if($type==3){
//            $list = Db::table('collection')->alias('c')
//                ->join('recruit r','r.id=c.to_id','LEFT')
//                ->join('company co','co.id=r.company_id','LEFT')
//                ->join('member m','m.id=co.user_id','LEFT')
//                ->join('category ca','ca.cat_id=r.type','LEFT')
//                ->field('r.id,r.title,ca.cat_name,r.work_age,r.require_cert,r.salary,co.logo,m.regtype')
//                ->where('c.type',2)
//                ->where('c.user_id',$user_id)
//                ->where('m.regtype',$regtype)
//                ->select();
////            foreach ($list as $key=>$value){
////                $list[$key]['city']=Region::getName($list[$key]['city']);
////                $list[$key]['district']=Region::getName($list[$key]['district']);
////            }
//            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$list]);
//        }else{//公司类型收藏
//            $list = Db::table('collection')->alias('c')
//                ->join('person p','p.id=c.to_id','LEFT')
//                ->join('member m','m.id=p.user_id','LEFT')
//                ->join('category ca','ca.cat_id=p.job_type','LEFT')
//                ->field('p.id,p.name,p.desc,ca.cat_name,p.work_age,p.images,p.avatar,m.regtype')
//                ->where('c.type',1)
//                ->where('c.user_id',$user_id)
//                ->where('m.regtype',$regtype)
//                ->select();
//            foreach ($list as $key=>$value){
//                $list[$key]['images']=$list[$key]['images']?1:0;
//            }
//            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$list]);
//        }

    }

    /**
     * 收藏|取消收藏
     */
    public function collection(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $type = input('type',1);
        $to_id = input('to_id');
        if(!$to_id) $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);
        if($type==1){
            $res = Db::table('recruit')->where('id',$to_id)->find();
            if(!$res) $this->ajaxReturn(['status' => -2 , 'msg'=>'该职位不存在！','data'=>'']);
        }elseif ($type==2){
            $res = Db::table('person')->where('id',$to_id)->find();
            if(!$res) $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在！','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'类型不存在，拖出去打一顿！','data'=>'']);
        }

        $where['user_id'] = $user_id;
        $where['to_id'] = $to_id;
        $where['type'] = $type;

        $res = Db::table('collection')->where($where)->find();

        if($res){
            $res = Db::table('collection')->where($where)->delete();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'取消收藏！','data'=>'']);
        }else{
            $res = Db::table('collection')->insert($where);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'收藏成功！','data'=>'']);
        }
    }

}
