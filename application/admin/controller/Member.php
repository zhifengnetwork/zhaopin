<?php


namespace app\admin\controller;
use app\common\model\Member as MemberModel;
use app\common\model\User as UserModel;
use think\Request;

use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\console\command\make\Model;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use app\common\logic\MessageTemplateLogic;
use app\common\logic\MessageFactory;
use app\common\model\Withdrawals;
use app\common\model\Users;
use app\common\model\AgentInfo;
use think\Loader;

class Member extends Common
{

    public function changelevel(){
        
        if(IS_POST){
            $post = I('post.');

            $is_agent = (int)$post['level'] > 0 ? 1 : 0;
            //大于0 是1，否则是0

            $is_distribut = (int)$post['is_distribut'];

            M('users')->where(['user_id'=>$post['user_id']])->update(['agent_user'=>$post['level'],'is_agent'=>$is_agent,'is_distribut'=> $is_distribut]);
          
            //修改  tp_agent_info
            $is_cun = M('agent_info')->where(['uid'=>$post['user_id']])->find();
            $head_id = M('users')->where(['user_id'=>$post['user_id']])->value('first_leader');

            if($is_cun){
                M('agent_info')->where(['uid'=>$post['user_id']])->update(['head_id'=>$head_id,'level_id'=>$post['level'],'update_time'=>time()]);
            }else{

                $model = new AgentInfo();
                $model->uid = $post['user_id'];
                $model->head_id = $head_id;
                $model->level_id = $post['level'];
                $model->create_time = time();
                $model->update_time = time();
                $model->save();

            }
              
            $this->success('修改成功');
            exit;
        }

        return $this->fetch();
    }
    /**
     * 会员列表
     */
    public function index()
    {
        
     

        $begin_time      = input('begin_time', '');
        $end_time        = input('end_time', '');
        $id              = input('mid','');
        $kw              = input('realname', '');
        $followed        = input('followed','');
        $isblack         = input('isblack', '');
        $level           = input('level','');
        $groupid         = input('groupid','');
        $where = [];
        if (!empty($id)) {
            $where['dm.id']    = $id;
        }
        if (!empty($followed)) {
            $where['f.state']   = $followed;
        }
        if(!empty($isblack)){
            $where['dm.isblack'] = $isblack;
        }
        if(!empty($level)){
            $where['dm.level'] = $level;
        }
        if(!empty($groupid)){
            $where['dm.groupid'] = $groupid;
        }

        if(!empty($kw)){
            is_numeric($kw)?$where['dm.mobile'] = $kw:$where['dm.realname'] = $kw;
        }
        if ($begin_time && $end_time) {
            $where['dm.createtime'] = [['EGT', strtotime($begin_time)], ['LT', strtotime($end_time)]];
        } elseif ($begin_time) {
            $where['dm.createtime'] = ['EGT', strtotime($begin_time)];
        } elseif ($end_time) {
            $where['dm.createtime'] = ['LT', strtotime($end_time)];
        }
        //携带参数
        $carryParameter = [
            'kw'               => $kw,
            'begin_time'       => $begin_time,
            'end_time'         => $end_time,
            'mid'              => $id,
            'followed'         => $followed,
            'isblack'          => $isblack,
            'level'            => $level,
            'groupid'          => $groupid,
        ];
        
        $list  = MemberModel::alias('dm')
                ->field('dm.*,l.levelname,g.groupname,dm.realname,dm.realname as username,dm.nickname as agentnickname,dm.avatar as agentavatar')
                ->join("member_group g",'dm.groupid=g.id','LEFT')
                ->join("member_level l",'dm.level =l.id','LEFT')
                ->join("user f",'f.openid=dm.openid','LEFT')
                ->where($where)
                ->order('createtime desc')
                ->paginate(10, false, ['query' => $carryParameter]);
                       
        foreach ($list as &$row) {
            $row['levelname']  = empty($row['levelname']) ?  '普通会员' : $row['levelname'];
            $order_info        = Db::table('order')->where(['user_id' =>$row['id'],'order_status' => 3])->field('count(order_id) as order_count,sum(goods_price) as ordermoney')->find();
            $row['ordercount'] = $order_info['order_count'];
            $row['ordermoney'] = empty($order_info['ordermoney'])?0:$order_info['ordermoney'];
            $row['followed']   = UserModel::followed($row['openid']);//是否关注;
            $row['balance']    = MemberModel::getBalance($row['id'],0);//余额
            $row['balance1']   = MemberModel::getBalance($row['id'],1);//积分
        }
        unset($row);

        // 导出
        $exportParam            = $carryParameter;
        $exportParam['tplType'] = 'export';
        $tplType                = input('tplType', '');
        if ($tplType == 'export') {
            $list  = MemberModel::alias('dm')
                ->field('dm.*,l.levelname,g.groupname,dm.realname,dm.realname as username,dm.nickname as agentnickname,dm.avatar as agentavatar')
                ->join("member_group g",'dm.groupid=g.id','LEFT')
                ->join("member_level l",'dm.level =l.id','LEFT')
                ->join("user f",'f.openid=dm.openid','LEFT')
                ->where($where)
                ->order('createtime desc')
                ->select();
            $str = "会员id,会员名称\n";

            foreach ($list as $key => $val) {
                $str .= $val['id'] . ',' . $val['username'] . ',' ;
                $str .= "\n";
            }
            export_to_csv($str, '用户列表', $exportParam);
        }
        return $this->fetch('',[ 
            'levels'         => MemberModel::getLevels(),
            'groups'         => MemberModel::getGroups(),
            'list'           => $list,
            'groupid'        => $groupid,
            'level'          => $level,
            'kw'             => $kw,
            'isblack'        => $isblack,
            'followed'       => $followed,
            'id'             => $id,
            'exportParam'    => $exportParam,
            'begin_time'     => empty($begin_time)?date('Y-m-d'):$begin_time,
            'end_time'       => empty($end_time)?date('Y-m-d'):$end_time,
            'meta_title'     => '会员管理',
        ]);
    }

    private function &get_where()
    {
        $begin_time      = input('begin_time', '');
        $end_time        = input('end_time', '');
        $id              = input('mid','');
        $kw              = input('realname', '');
        $followed        = input('followed','');
        $isblack         = input('isblack', '');
        $level           = input('level','');
        $groupid         = input('groupid','');
        $where = [];
        if (!empty($id)) {
            $where['dm.id']    = $id;
        }
        if (!empty($followed)) {
            $where['f.state']   = $followed;
        }
        if(!empty($isblack)){
            $where['dm.isblack'] = $isblack;
        }
        if(!empty($level)){
            $where['dm.level'] = $level;
        }
        if(!empty($groupid)){
            $where['dm.groupid'] = $groupid;
        }

        if(!empty($kw)){
            is_numeric($kw)?$where['dm.mobile'] = $kw:$where['dm.realname'] = $kw;
        }
        if ($begin_time && $end_time) {
            $where['dm.createtime'] = [['EGT', $begin_time], ['LT', $end_time]];
        } elseif ($begin_time) {
            $where['dm.createtime'] = ['EGT', $begin_time];
        } elseif ($end_time) {
            $where['dm.createtime'] = ['LT', $end_time];
        }
        $this->assign('kw', $kw);
        $this->assign('id', $id);
        $this->assign('followed', $followed);
        $this->assign('isblack', $isblack);
        $this->assign('level', $level);
        $this->assign('groupid', $groupid);
        $this->assign('begin_time', empty($begin_time)?date('Y-m-d'):$begin_time);
        $this->assign('end_time', empty($end_time)?date('Y-m-d'):$end_time);
        return $where;
    }
    /***
     * 会员详情
     */
    public function member_edit(){
        $uid     = input('id');
        $member  = MemberModel::get($uid);
        if (Request::instance()->isPost()){
            $data = input('data/a');
            if( !empty(input('password')) && !empty($uid) ){
                //修改密码
                $data['pwd'] = md5(input('password'));
            }
            
            $res = MemberModel::where(['id' => $uid])->update($data);

            if($res !== false ){
                $this->success('编辑成功', url('member/index'));
            }
                $this->error('编辑失败');

        }
       
       
        $order_info        = Db::table('order')->where(['user_id' =>$member['id'],'order_status' => 3])->field('count(order_id) as order_count,sum(goods_price) as ordermoney')->find();
        $member['self_ordercount'] = $order_info['order_count'];
        $member['self_ordermoney'] = empty($order_info['ordermoney'])?0:$order_info['ordermoney'];
        $member['balance']         = MemberModel::getBalance($member['id'],0);//余额
        $member['balance1']        = MemberModel::getBalance($member['id'],1);//积分
        // //更新数据
        // $member && $this->dataupdate($uid);
        $groups  =  MemberModel::getGroups();
        $levels  =  MemberModel::getLevels();
        $this->assign('followed', 1);
        $this->assign('groups', $groups);
        $this->assign('levels', $levels);
        $this->assign('member', $member);
        $this->assign('meta_title', '会员详情');
        return $this->fetch();

    }

    /***
     * 会员详情
     */
    public function member_isblack(){
        $uid     = input('id');
        $isblack = input('isblack');
        $member  = MemberModel::get($uid);
        if(empty($member)){
            $this->error('会员不存在，无法设置黑名单!');
        }
        if($member['isblack']){
             $update['isblack'] = 0;
        }else{
             $update['isblack'] = 1;
        }
        $res = MemberModel::where(['id' => $uid])->update($update);
        if($res !== false){
            $this->success('设置成功');
        }
            $this->error('设置失败!');
    }


     /***
     * 会员删除
     */
    public function member_delete(){
        $uid     = input('id');
        $member  = MemberModel::get($uid);
        if(empty($member)){
            $this->error('会员不存在，无法删除!');
        }
        $agentcount = MemberModel::where(['agentid' => $uid])->count();

        if ($agentcount > 0) {
            $this->error('此会员有下线存在，无法删除!');
        }

        $res = MemberModel::where(['id' => $uid])->delete();

        if($res !== false){
            $this->success('删除成功');
        }
            $this->error('删除失败!');
    }







    /**
     * 更新用户数据
     */

    public function dataupdate($id){

        $account_wechats = pdo_fetch("select `key`,secret  from " . tablename('account_wechats')  . " where uniacid=' ".$_W['uniacid']." ' ");
    
        $appid = $account_wechats['key'];
    
        $appsecret =  $account_wechats['secret'];
    
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
    
        $ch = curl_init();
    
        curl_setopt($ch, CURLOPT_URL, $url);
    
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
    
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
        $output = curl_exec($ch);
    
        curl_close($ch);
    
        $jsoninfo = json_decode($output, true);
    
        $access_token = $jsoninfo["access_token"];
    
        $member = m('member')->getMember($id);
    
        load()->model('account');
    
        $acc = WeAccount::create($_W['acid']);
    
        $token = $acc->getAccessToken();
    
        $url3="https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$member['openid']."&lang=zh_CN";
    
        
    
        $ch1 = curl_init();
    
        curl_setopt($ch1, CURLOPT_URL, $url3);
    
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, FALSE); 
    
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, FALSE); 
    
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
    
        $output1 = curl_exec($ch1);
    
        curl_close($ch1);
    
        if(!is_error($output1)){
    
            $jsoninfo1 = json_decode($output1, true);
    
            $_var_0 = filterEmoji($jsoninfo1['nickname']);
    
    
    
            if ($jsoninfo1['headimgurl'] && $_var_0) {
    
                pdo_update('sz_yi_member',array('nickname'=>$_var_0,'avatar'=>$jsoninfo1['headimgurl']),array('openid'=>$jsoninfo1['openid']));
    
            } elseif ($jsoninfo1['headimgurl'] && empty($_var_0)) {
    
                $_var_0 = '(非法昵称)';
    
                pdo_update('sz_yi_member',array('nickname'=>$_var_0,'avatar'=>$jsoninfo1['headimgurl']),array('openid'=>$jsoninfo1['openid']));
    
            }
    
        }
    
    }










    public function level(){
        $where      = array();
        $list       = Db::table('puls_level')
                        ->field('*')
                        ->where($where)
                        ->order('id')
                        ->paginate(10, false, ['query' => $where]);
        $this->assign('list', $list);
        $this->assign('meta_title', '会员等级');
        return $this->fetch();              
    }


    public function level_add(){
        if (request()->isPost()){
            $name = request()->param('name','');
            $level = request()->param('level','');
            $children_num = request()->param('children_num','');
            $team_num = request()->param('team_num','');
            $reward = request()->param('reward','');
            if (empty($name)){
                $this->error('等级名称不能为空！');
            }
            $data = [
                'name'  =>  $name,
                'level'  =>  $level,
                'children_num'  =>  $children_num,
                'team_num'  =>  $team_num,
                'reward'  =>  $reward
            ];
            $inser = Db::table('puls_level')->insert($data);
            if (!empty($inser)){
                $this->success('添加成功！', url('member/level'));
            }else{
                $this->error('添加失败！');
            }
        }
        $this->assign('meta_title', '会员等级设置');
        return $this->fetch();              
    }


    public function level_edit(){
        $id      = input('id');
        $level   = Db::table('puls_level')->where(['id' => $id])->find();
        if (Request::instance()->isPost()){
            $name = request()->param('name','');
            $level = request()->param('level','');
            $children_num = request()->param('children_num','');
            $team_num = request()->param('team_num','');
            $reward = request()->param('reward','');
            if (empty($name)){
                $this->error('等级名称不能为空！');
            }
            $data = [
                'name'  =>  $name,
                'level'  =>  $level,
                'children_num'  =>  $children_num,
                'team_num'  =>  $team_num,
                'reward'  =>  $reward
            ];
           $res = Db::table('member_level')->where(['id' => $id])->update($data);

           if($res !== false ){
              $this->success('编辑成功', url('member/level'));
           }else{
               $this->error('编辑失败');
           }
        }
        $this->assign('level', $level);
        $this->assign('meta_title', '会员等级设置');
        return $this->fetch();
    }


    public function group(){
        $where       = array();
        $membercount = Db::table('member')->where(['groupid' => 0])->count();
        $list        =  [
            [
                'id'          => 0,
                'groupname'   => '无分组',
                'membercount' => $membercount
            ]
        ];
        $alllist  = Db::table('member_group')
                        ->field('*')
                        ->where($where)
                        ->order('id')
                        ->select();
        foreach ($alllist as &$row) {
            $row['membercount'] = Db::table('member')->where(['groupid' => $row['id']])->count();
        }
        unset($row);
        $list = array_merge($list, $alllist);
        $this->assign('list', $list);
        $this->assign('meta_title', '会员分组设置');
        return $this->fetch();              
    }

    public function group_add(){
    
        if (Request::instance()->isPost()){
            $groupname = input('groupname','');
            if(empty($groupname)){
                $this->error('分组名称不能为空');
            }
            $add['groupname'] = $groupname;
           $res = Db::table('member_group')->insert($add);
           if($res !== false ){
               $this->success('新增成功', url('member/group'));
           }
                $this->error('新增失败');

         }
        $this->assign('meta_title', '会员分组新增');
        return $this->fetch();              
    }

    public function group_edit(){
          $id = input('id');
        if (Request::instance()->isPost()){
            $groupname = input('groupname','');
            if(empty($groupname)){
                $this->error('分组名称不能为空');
            }
            $uodate['groupname'] = $groupname;
            $res = Db::table('member_group')->where(['id' => $id])->update($uodate);
            if($res !== false ){
                $this->success('编辑成功', url('member/group'));
              }
                $this->error('编辑失败');
        }
        $info = Db::table('member_group')->where(['id' => $id])->find();
        $this->assign('info', $info);
        $this->assign('meta_title', '会员等级设置');
        return $this->fetch();              
    }

    public function set(){
        $setdata = Db::table('sysset')->find();
        $set = unserialize($setdata['sets']);
        if (Request::instance()->isPost()){
            $patdata = input('post.');
            $set['shop'] = $patdata['shop'];
            $update['sets']    = serialize($set);
            $res = Db::table('sysset')->where(['id' => 1])->update($update);
            if($res !== false ){
                $this->success('编辑成功', url('member/set'));
            }
            $this->error('编辑失败');
        }
        $this->assign('set', $set);
        $this->assign('meta_title', '会员设置');
        return $this->fetch();              
    }




    /**
     * 会员详细信息查看
     */
     public function detail()
     {
 
         $uid = I('get.id');
         $user = D('users')->where(array('user_id' => $uid))->find();
         if (!$user)
             exit($this->error('会员不存在'));
         if (IS_POST) {
             //  会员信息编辑
             $password = I('post.password');
             $password2 = I('post.password2');
             if ($password != '' && $password != $password2) {
                 exit($this->error('两次输入密码不同'));
             }
             if ($password == '' && $password2 == '') {
                 unset($_POST['password']);
             } else {
                 $_POST['password'] = encrypt($_POST['password']);
             }
 
             if (!empty($_POST['email'])) {
                 $email = trim($_POST['email']);
                 $c = M('users')->where("user_id != $uid and email = '$email'")->count();
                 $c && exit($this->error('邮箱不得和已有用户重复'));
             }
 
             if (!empty($_POST['mobile'])) {
                 $mobile = trim($_POST['mobile']);
                 $c = M('users')->where("user_id != $uid and mobile = '$mobile'")->count();
                 $c && exit($this->error('手机号不得和已有用户重复'));
             }
             if(!empty($_POST['level'])){
                 $userLevel = D('user_level')->where('level_id=' . $_POST['level'])->value('level');
                 $_POST['agent_user'] = $userLevel;
             }
         
             $agent = M('agent_info')->where(['uid'=>$uid])->find();
            
             if ($agent) {
                 $data = array('level_id' => (int)$userLevel);
                 M('agent_info')->where(['uid'=>$uid])->save($data);
             }else{
                 $this->agent_add($user['user_id'],$user['first_leader'],(int)$userLevel);
                 $_POST['is_agent'] = 1;
             }
            
             $row = M('users')->where(array('user_id' => $uid))->save($_POST);
             if ($row){
                 exit($this->success('修改成功'));
             }else{
                exit($this->error('未作内容修改或修改失败'));
             }
         }
 
         $user['first_lower'] = M('users')->where("first_leader = {$user['user_id']}")->count();
         $user['second_lower'] = M('users')->where("second_leader = {$user['user_id']}")->count();
         $user['third_lower'] = M('users')->where("third_leader = {$user['user_id']}")->count();
 
         //一级是分销商数量
         $first_leader_distribut = M('users')->where("first_leader = {$user['user_id']}")->field('user_id')->select();
         $first_leader_distribut_num = 0;
         foreach($first_leader_distribut as $key => $val){
            $is_distribut = M('users')->where(["user_id"=>$val['user_id']])->value('is_distribut');
            if($is_distribut == 1){
                $first_leader_distribut_num += 1;
            }
         }
         $this->assign('first_leader_distribut_num', $first_leader_distribut_num);
        
         $this->assign('user', $user);
         return $this->fetch();
     }
 
     private function agent_add($user_id,$head_id,$level_id)
     {
         $data = array(
             'uid'=>$user_id,
             'head_id'=>$head_id,
             'level_id'=>$level_id,
             'create_time'=>time(),
             'update_time'=>time(),
             'note'=>"后台增加等级"
         );
         M('agent_info')->add($data);
     }

    public function add_user()
    {
        if (IS_POST) {
            $data = I('post.');
            $user_obj = new UsersLogic();
            $res = $user_obj->addUser($data);
            if ($res['status'] == 1) {
                $this->success('添加成功', U('User/index'));
                exit;
            } else {
                $this->error('添加失败,' . $res['msg'], U('User/index'));
            }
        }
        return $this->fetch();
    }

    public function export_user()
    {
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">邮箱</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
        $strTable .= '</tr>';
        $user_ids = I('user_ids');
        if ($user_ids) {
            $condition['user_id'] = ['in', $user_ids];
        } else {
            $mobile = I('mobile');
            $email = I('email');
            $mobile ? $condition['mobile'] = $mobile : false;
            $email ? $condition['email'] = $email : false;
        };
        $count = DB::name('users')->where($condition)->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $userList = M('users')->where($condition)->order('user_id')->limit($start,5000)->select();
            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['nickname'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['level'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['email'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['reg_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['last_login']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_points'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['total_amount'] . ' </td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'users_' . $i);
        exit();
    }

    /**
     * 用户收货地址查看
     */
    public function address()
    {
        $uid = I('get.id');
        $lists = D('user_address')->where(array('user_id' => $uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList', $regionList);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete()
    {
        $uid = I('get.id');

        //先删除ouath_users表的关联数据
        M('OuathUsers')->where(array('user_id' => $uid))->delete();
        $row = M('users')->where(array('user_id' => $uid))->delete();
        if ($row) {
            $this->success('成功删除会员');
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 删除会员
     */
    public function ajax_delete()
    {
        $uid = I('id');
        if ($uid) {
            $row = M('users')->where(array('user_id' => $uid))->delete();
            if ($row !== false) {
                //把关联的第三方账号删除
                M('OauthUsers')->where(array('user_id' => $uid))->delete();
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            } else {
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    /**
     * 账户资金记录
     */
    public function account_log()
    {
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('account_log')->where(array('user_id' => $user_id))->count();
        $page = new Page($count);
        $lists = M('account_log')->where(array('user_id' => $user_id))->order('change_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('user_id', $user_id);
        $this->assign('page', $page->show());
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit()
    {
        $user_id = I('user_id');
        if (!$user_id > 0) $this->ajaxReturn(['status' => 0, 'msg' => "参数有误"]);
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock')->where('user_id', $user_id)->find();
        if (IS_POST) {
            $desc = I('post.desc');
            if (!$desc)
                $this->ajaxReturn(['status' => 0, 'msg' => "请填写操作说明"]);
            //加减用户资金
            $m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
            $user_money = $m_op_type ? $user_money : 0 - $user_money;
            if (($user['user_money'] + $user_money) < 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
            }
            //加减用户积分
            $p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points = $p_op_type ? $pay_points : 0 - $pay_points;
            if (($pay_points + $user['pay_points']) < 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户剩余积分不足！！']);
            }
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');
            if ($revision_frozen_money != 0) {    //有加减冻结资金的时候
                $frozen_money = $f_op_type ? $revision_frozen_money : 0 - $revision_frozen_money;
                $frozen_money = $user['frozen_money'] + $frozen_money;    //计算用户被冻结的资金
                if ($f_op_type == 1 && $revision_frozen_money > $user['user_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
                }
                if ($f_op_type == 0 && $revision_frozen_money > $user['frozen_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "冻结的资金不足！！"]);
                }
                $user_money = $f_op_type ? 0 - $revision_frozen_money : $revision_frozen_money;    //计算用户剩余资金
                M('users')->where('user_id', $user_id)->update(['frozen_money' => $frozen_money]);
            }
            if (accountLog($user_id, $user_money, $pay_points, $desc, 0)) {
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/User/account_log", array('id' => $user_id))]);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => "操作失败"]);
            }
            exit;
        }
        $this->assign('user_id', $user_id);
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function recharge()
    {
        $timegap = urldecode(I('timegap'));
        $nickname = I('nickname');
        $map = array();
        if ($timegap) {
            $gap = explode(',', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['ctime'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        if ($nickname) {
            $map['nickname'] = array('like', "%$nickname%");
            $this->assign('nickname', $nickname);
        }
        $count = M('recharge')->where($map)->count();
        $page = new Page($count);
        $lists = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    // public function level()
    // {
    //     $act = I('get.act', 'add');
    //     $this->assign('act', $act);
    //     $level_id = I('get.level_id');
    //     if ($level_id) {
    //         $level_info = D('user_level')->where('level_id=' . $level_id)->find();
    //         $this->assign('info', $level_info);
    //     }
    //     return $this->fetch();
    // }

    public function levelList()
    {
        $Ad = M('user_level');
        $p = $this->request->param('p');
        $res = $Ad->order('level_id')->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->add($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->where('level_id=' . $data['level_id'])->save($data);
                if ($r !== false) {
                    $discount = $data['discount'] / 100;
                    D('users')->where(['level' => $data['level_id']])->save(['discount' => $discount]);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('user_level')->where('level_id=' . $data['level_id'])->delete();
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));
        if ($search_key == '') $this->ajaxReturn(['status' => -1, 'msg' => '请按要求输入！！']);
        $list = M('users')->where(['nickname' => ['like', "%$search_key%"]])->select();
        if ($list) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'result' => $list]);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '未查询到相应数据！！']);
    }

    /**
     * 分销树状关系
     */
    public function ajax_distribut_tree()
    {
        $list = M('users')->where("first_leader = 1")->select();
        return $this->fetch();
    }

    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送系统通知消息
     * @author yhj
     * @time  2018/07/10
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $message_content = I('post.text', '');//内容
        $message_title = I('post.title', '');//标题
        $message_type = I('post.type', 0);//个体or全体
        $users = I('post.user/a');//个体id
        $message_val = ['name' => ''];
        $send_data = array(
            'message_title' => $message_title,
            'message_content' => $message_content,
            'message_type' => $message_type,
            'users' => $users,
            'type' => 0, //0系统消息
            'message_val' => $message_val,
            'category' => 0,
            'mmt_code' => 'message_notice'
        );

        $messageFactory = new MessageFactory();
        $messageLogic = $messageFactory->makeModule($send_data);
        $messageLogic->sendMessage();

        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $user_where = array(
                'user_id' => array('IN', $user_id_array),
                'email' => array('neq', '')
            );
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $users = I('post.user/a');
        $email = I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(array('user_id' => array('IN', $user_id_array)))->select();
            $to = array();
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if ($email) {
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     *  转账汇款记录
     */
    public function remittance()
    {
        $status = I('status', 1);
        $realname = I('realname');
        $bank_card = I('bank_card');
        $where['status'] = $status;
        $realname && $where['realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['bank_card'] = array('like', '%' . $bank_card . '%');

        $create_time = urldecode(I('create_time'));
        // echo urldecode($create_time);
        // echo $create_time;exit;
        // $create_time = str_replace('+', '', $create_time);

        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        if ($status == 2) {
            $time_name = 'pay_time';
            $export_time_name = '转账时间';
            $export_status = '已转账';
        } else {
            $time_name = 'check_time';
            $export_time_name = '审核时间';
            $export_status = '待转账';
        }
        $where[$time_name] = array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));
        $withdrawalsModel = new Withdrawals();
        $count = $withdrawalsModel->where($where)->count();
        $Page = new page($count, C('PAGESIZE'));
        $list = $withdrawalsModel->where($where)->limit($Page->firstRow, $Page->listRows)->order("id desc")->select();
        if (I('export') == 1) {
            # code...导出记录
            $selected = I('selected');
            if (!empty($selected)) {
                $selected_arr = explode(',', $selected);
                $where['id'] = array('in', $selected_arr);
            }
            $list = $withdrawalsModel->where($where)->order("id desc")->select();
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">用户昵称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">银行机构名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">账户号码</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">账户开户名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">' . $export_time_name . '</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">备注</td>';
            $strTable .= '</tr>';
            if (is_array($list)) {
                foreach ($list as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['users']['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $export_status . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val[$time_name]) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }

        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('status', $status);
        $this->assign('Page', $Page);
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $this->get_withdrawals_list();
        $this->assign('withdraw_status', C('WITHDRAW_STATUS')); 
        return $this->fetch();
    }

    public function get_withdrawals_list($status = '')
    {
        $id = I('selected/a');
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');

        $create_time = urldecode(I('create_time'));
        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        $where['w.create_time'] = array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));

        $status = empty($status) ? I('status') : $status;
        if ($status !== '') {
            $where['w.status'] = $status;
        } else {
            $where['w.status'] = ['lt', 2];
        }
        if ($id) {
            $where['w.id'] = ['in', $id];
        }

        //会员信息搜索
        $search_type = I('search_type');
        $search_value = I('search_value');
        if($search_type == 'mobile'){
            $where['u.mobile'] = array('like', "%$search_value%");
        }else if($search_type == 'user_id'){
            $where['w.user_id'] = $search_value ? $search_value : array('like', "%$search_value%");                
        }else if($search_type == 'nickname'){
            $where['u.nickname'] = array('like', "%$search_value%");
        }

        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['w.bank_card'] = array('like', '%' . $bank_card . '%');
        $export = I('export');
        if ($export == 1) {
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">ID</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">审核时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
            $strTable .= '</tr>';
            $remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->select();
            if (is_array($remittanceList)) {
                foreach ($remittanceList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['id'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['check_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }
        $count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page = new Page($count, 10);
        $list = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //$this->assign('create_time',$create_time2);
    
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        C('TOKEN_ON', false);
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $id = I('del_id/d');
        $res = Db::name("withdrawals")->where(['id' => $id])->delete();
        if ($res !== false) {
            $return_arr = ['status' => 1, 'msg' => '操作成功', 'data' => '',];
        } else {
            $return_arr = ['status' => -1, 'msg' => '删除失败', 'data' => '',];
        }
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public function editWithdrawals()
    {
        $id = I('id');
        $withdrawals = Db::name("withdrawals")->find($id);
        $user = M('users')->where(['user_id' => $withdrawals['user_id']])->find();
        if ($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif ($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif ($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];
        $status = $withdrawals['status'];
        $withdrawals['status_code'] = C('WITHDRAW_STATUS')["$status"];
        $this->assign('user', $user);
        $this->assign('data', $withdrawals);
        return $this->fetch();
    }

    /**
     *  处理会员提现申请
     */
    public function withdrawals_update()
    {
        $id_arr = I('id/a');
       
        if(count($id_arr) > 1){
            $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败，请单选"), 'JSON');
            exit;
        }

        $data['status'] = $status = I('status');
        $data['remark'] = I('remark');

        $ids = implode(',', $id_arr);

        $falg = M('withdrawals')->where(['id'=>$ids])->find();
        $user_find = M('users')->where(['user_id'=>$falg['user_id']])->find();

        if ($status == 1){
            $data['check_time'] = time();
            $wx_content = "您提交的提现申请已通过审核\n将在24小时内到账，请注意查收！\n备注：{$data['remark']}";

        }
        if ($status != 1){
            //审核未通过退还金额
            accountLog($falg['user_id'], $falg['money'] , 0, '提现未通过退款',  0, 0, '');
            $wx_content = "您提交的提现申请未通过审核！\n备注：{$data['remark']}";
            $data['refuse_time'] = time();
            // 发送公众号消息给用户
            $wechat = new \app\common\logic\wechat\WechatUtil();
            $wechat->sendMsg($user_find['openid'], 'text', $wx_content);

            Db::name('withdrawals')->whereIn('id', $ids)->update($data);

            $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');
            exit;
        }

        // $falg = M('withdrawals')->where(['id'=>$ids])->find();
        // $user_find = M('users')->where(['user_id'=>$falg['user_id']])->find();
        // if($user_find['user_money'] < $falg['money'])
        // {
        //     $this->ajaxReturn(array('status' => 0, 'msg' => "当前用户余额不足"), 'JSON');
        //     exit;
        // }
        $user_arr = array(
            'user_money' => $user_find['user_money'] - $falg['money']
        );

        if($ids == ''){
            $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败，ID不能为空"), 'JSON');
            exit;
        }

        // //写记录（扣钱）
        // $rr = accountLog($falg['user_id'], -$falg['money'], 0, '提现编号:'.$ids,0, 0 , 0);
        // if($rr == false){
        //     // 发送公众号消息给用户
        //     $wechat = new \app\common\logic\wechat\WechatUtil();
        //     $wechat->sendMsg($user_find['openid'], 'text', '您提交的提现申请操作失败！');
        //     $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
        //     exit;
        // }
    
        if($falg['bank_name'] == '微信'){  
            //微信
            $result = $this->withdrawals_weixin($falg['id']);
            if(isset($result['status'])){
                // 发送公众号消息给用户
                $wechat = new \app\common\logic\wechat\WechatUtil();
                $wechat->sendMsg($user_find['openid'], 'text', '您提交的提现申请操作失败！');
                $this->ajaxReturn(array('status' => 0, 'msg' => $result['msg']), 'JSON');
                exit;
            }else{
                $result['payment_time'] = strtotime($result['payment_time']);
                $result['money'] = $falg['money'];
                $result['user_id'] = $falg['user_id'];
                $flag = M('withdrawals_weixin')->insert($result);
            } 
            // ["mch_appid"] => string(18) "wxaef006dc718188f7"
            // ["mchid"] => string(10) "1507131181"
            // ["device_info"] => string(0) ""
            // ["nonce_str"] => string(32) "d2ad6d55fd8329342da107ea105fcfaa"
            // ["result_code"] => string(7) "SUCCESS"
            // ["partner_trade_no"] => string(8) "15357032"
            // ["payment_no"] => string(28) "1507131181201903296764930713"
            // ["payment_time"] => string(19) "2019-03-29 15:20:40"
        }

        $r = Db::name('withdrawals')->whereIn('id', $ids)->update($data);
        if ($r !== false) {
         
            // 发送公众号消息给用户
            $wechat = new \app\common\logic\wechat\WechatUtil();
            $wechat->sendMsg($user_find['openid'], 'text', $wx_content);

            $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');

        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
        }
    }

    //用户微信提现
    private function withdrawals_weixin($id){
        $falg = M('withdrawals')->where(['id'=>$id])->find();
        $openid = M('users')->where('user_id', $falg['user_id'])->value('openid');
        $data['openid'] = $openid;
        $data['pay_code'] = $falg['id'].$falg['user_id'];
        $data['desc'] = '提现ID'.$falg['id'];
        if($falg['taxfee'] >= $falg['money']){
            return array('status'=>1, 'msg'=>"提现额度必须大于手续费！" );
        }else{
            $data['money'] = bcsub($falg['money'], $falg['taxfee'], 2);
        }
        include_once PLUGIN_PATH . "payment/weixin/weixin.class.php";
        $weixin_obj = new \weixin();
        $result = $weixin_obj->transfer($data);
        // if($result){
        //     $result['payment_time'] = strtotime($result['payment_time']);
        //     $result['money'] = $falg['money'];
        //     $result['user_id'] = $falg['user_id'];
        // }
        return $result;
    }

    // 用户申请提现
    public function transfer()
    {
        $id = I('selected/a');
        if (empty($id)) $this->error('请至少选择一条记录');
        $atype = I('atype');
        if (is_array($id)) {
            $withdrawals = M('withdrawals')->where('id in (' . implode(',', $id) . ')')->select();
        } else {
            $withdrawals = M('withdrawals')->where(array('id' => $id))->select();
        }


        $messageFactory = new \app\common\logic\MessageFactory();
        $messageLogic = $messageFactory->makeModule(['category' => 0]);

        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach ($withdrawals as $val) {
            $user = M('users')->where(array('user_id' => $val['user_id']))->find();
            //$oauthUsers = M("OauthUsers")->where(['user_id'=>$user['user_id'] , 'oauth_child'=>'mp'])->find();
            $oauthUsers = M("OauthUsers")->where(['user_id' => $user['user_id'], 'oauth' => 'weixin'])->find();
            //获取用户绑定openId
            $user['openid'] = $oauthUsers['openid'];
            if ($user['user_money'] < $val['money']) {
                $data = array('status' => -2, 'remark' => '账户余额不足');
                M('withdrawals')->where(array('id' => $val['id']))->save($data);
                $this->error('账户余额不足');
            } else {
                $rdata = array('type' => 1, 'money' => $val['money'], 'log_type_id' => $val['id'], 'user_id' => $val['user_id']);
                if ($atype == 'online') {
                    header("Content-type: text/html; charset=utf-8");
exit("请联系DC环球直供网络客服购买高级版支持此功能");
                } else {
                    accountLog($val['user_id'], ($val['money'] * -1), 0, "管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('withdrawals')->where(array('id' => $val['id']))->save(array('status' => 2, 'pay_time' => time()));
                    expenseLog($rdata);//支出记录日志
                    // 提现通知
                    $messageLogic->withdrawalsNotice($val['id'], $val['user_id'], $val['money'] - $val['taxfee']);

                }
            }
        }
        if ($alipay['batch_num'] > 0) {
            //支付宝在线批量付款
            include_once PLUGIN_PATH . "payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!", U('remittance'), 3);
    }


    /**
     * 会员标签列表
     */
    public function labels()
    {
        $p = input('p/d');
        $Label = new UserLabel();
        $label_list = $Label->order('label_order')->page($p, 10)->select();
        $this->assign('label_list', $label_list);
        $Page = new Page($Label->count(), 10);
        $this->assign('page', $Page);
        return $this->fetch();
    }

    /**
     * 添加、编辑页面
     */
    public function labelEdit()
    {
        $label_id = input('id/d');
        if ($label_id) {
            $Label = new UserLabel();
            $label = $Label->where('id', $label_id)->find();
            $this->assign('label', $label);
        }
        return $this->fetch();
    }

    /**
     * 会员标签添加编辑删除
     */
    public function label()
    {
        $label_info = input();
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        $userLabelValidate = Loader::validate('UserLabel');
        $UserLabel = new UserLabel();
        if (request()->isPost()) {
            if ($label_info['label_id']) {
                if (!$userLabelValidate->scene('edit')->batch()->check($label_info)) {
                    $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLabelValidate->getError()];
                }else {
                    $UserLabel->where('id', $label_info['label_id'])->save($label_info);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => ''];
                }
            }else{
                if (!$userLabelValidate->batch()->check($label_info)) {
                    $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLabelValidate->getError()];
                } else {
                    $UserLabel->insert($label_info);
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => ''];
                }
            }
        }
        if (request()->isDelete()) {
            $UserLabel->where('id', $label_info['label_id'])->delete();
            $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
        }
        $this->ajaxReturn($return);
    }

}