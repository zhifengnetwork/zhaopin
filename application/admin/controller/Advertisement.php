<?php
namespace app\admin\controller;
use app\common\model\Advertisement as Advertise;
use think\Db;

/**
 * 广告图管理
 */
class Advertisement extends Common
{
    /**
     * 广告图列表
     */
    public function index()
    {
        $list = Db::table('page_advertisement')->where(['status'=>['<>',-1]])->select();
//        $list = Advertise::order('sort')->select();
        $this->assign('list', $list);
        $this->assign('meta_title', '页面');
        return $this->fetch();
    }

    /**
     *  页面广告轮播列表
     */
    public function list() {
        $page_id = request()->param('page_id',0);
        $list = Db::table('advertisement')->where(['state'=>['<>',-1],'page_id'=>$page_id])->order('type asc sort asc')->select();
        $this->assign('list',$list);
        $this->assign('page_id',$page_id);
        return $this->fetch();
    }

    /**
     * 编辑页面的广告和轮播
     */
    public function edit()
    {
        $id = input('id', 0);

        if (request()->isPost()) {
            $id    = input('id', 0);
            $title = input('title', '');
            $sort  = input('sort/d', 0);
            $state = input('state/d', 0);
            $url = input('url', '');
            $page_id = input('page_id', 0);
            $type = input('type', 0);
            $data  = [
                'title' => $title,
                'sort'  => $sort,
                'url'  => $url,
                'page_id'  => $page_id,
                'type'  => $type,
            ];
            $data['state']  = $state;
            
            !$title && $this->error('标题不能为空');
            !$sort && $this->error('排序不能为空');
            ($sort < 0 || $sort > 10) && $this->error('排序在0和10之间');
            // 图片验证
            $res = Advertise::pictureUpload('fixed_picture', 0);
            if ($res[0] == 1) {
                $this->error($res[0]);
            } else {
                $pictureName                             = $res[1];
                !empty($pictureName) && $data['picture'] = $pictureName;
            }
            if ($id) {
                $Advertise = new Advertise;
                if ($Advertise->save($data, ['id' => $id]) !== false) {
                    $this->success('编辑成功', url('advertisement/list', ['page_id' => $page_id]));
                }
                $this->error('编辑失败');
            }

            $file = request()->file('file');
            !$file && $this->error('图片不能为空');
            $Advertise = new Advertise($data);
            if ($Advertise->save()) {
                $this->success('添加成功', url('advertisement/list', ['page_id' => $page_id]));
            }
            $this->error('添加失败');
        }
        $info = $id ? Advertise::where('id', $id)->find()->getdata() : [];
        $this->assign('info', $info);
        $this->assign('id', $id);
        $this->assign('page_id', request()->param('page_id',0));
        $this->assign('meta_title', $id ? '编辑广告' : '新增广告');
        return $this->fetch();
    }

    /**
     * 删除
     */
    public function set_status()
    {
        $id = input('id', 0);
        if (Db::table('advertisement')->where('id', $id)->delete()) {
            $this->success('删除成功！');
        }
        $this->error('删除失败！');
    }

    /**
     *  添加页面
     */
    public function page_edit () {
        $id = request()->param('id',0);
        $get_page = Db::table('page_advertisement')->where('id',$id)->find();
        $page_name = request()->param('page_name','');
        $only_logo = request()->param('only_logo','');
        $status = request()->param('status',0);
        if (request()->isPost()){
            if (empty($only_logo)){
                $this->error('页面唯一标识不能为空！');
            }
            $old_page =  Db::table('page_advertisement')->where(['status'=>['<>',-1],'only_logo'=>$only_logo])->value('id');
            if ($id){
                if ($old_page != $id){
                    $this->error('该页面标识已经存在！');
                }
                //编辑
                $res = Db::table('page_advertisement')->where('id',$id)
                    ->update(['page_name'=>$page_name,'only_logo'=>$only_logo,'status'=>$status]);
            }else{
                if (!empty($old_page)){
                    $this->error('该页面标识已经存在！');
                }
                //添加
                $res = Db::table('page_advertisement')
                    ->insert(['page_name'=>$page_name,'only_logo'=>$only_logo,'status'=>$status]);
            }
            if ($res){
                $this->success('添加成功', url('advertisement/index'));
            }else{
                $this->error('添加失败');
            }
        }else{
            $this->assign('id',$id);
            $this->assign('get_page',$get_page);
            $this->assign('meta_title','页面编辑');
            return $this->fetch();
        }
    }

    /**
     *  修改页面状态
     */
    public function page_status () {
        $id = request()->param('id',0);
        $status = request()->param('status',0);
        if ($id){
            $update = Db::table('page_advertisement')->where('id',$id)->update(['status'=>$status]);
            if ($update){
                return json(['code'=>1,'msg'=>'操作成功！','data'=>[]]);
            }else{
                json(['code'=>0,'msg'=>'修改失败！','data'=>[]]);
            }
        }else{
            return json(['code'=>0,'msg'=>'id不存在！','data'=>[]]);
        }
    }

    /**
     *  修改页面广告轮播状态
     */
    public function update_status () {
        $id = request()->param('id',0);
        $status = request()->param('status',0);
        if ($id){
            $update = Db::table('advertisement')->where('id',$id)->update(['state'=>$status]);
            if ($update){
                return json(['code'=>1,'msg'=>'操作成功！','data'=>[]]);
            }else{
                json(['code'=>0,'msg'=>'修改失败！','data'=>[]]);
            }
        }else{
            return json(['code'=>0,'msg'=>'id不存在！','data'=>[]]);
        }
    }
    

}
