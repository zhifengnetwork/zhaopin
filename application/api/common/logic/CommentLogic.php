<?php


namespace app\common\logic;

use think\Db;
use think\Model;

/**
 * 评论
 * Class CatsLogic
 * @package common\Logic
 */
class CommentLogic extends Model
{
	
	public function getCommentInfo($comment_id)
	{
		$comment_info = M('comment')->where(array('comment_id'=>$comment_id))->find();
		$reply = $this->getReplyPage($comment_id);
		return array('comment_info'=>$comment_info,'reply'=>$reply);
	}

    /**
     * 获取评论数
     * @param int $user_id
     * @return array
     */
    public function getAllTypeCommentNum($user_id)
    {
        //已评价
        $data['had'] = $this->getHadCommentNum($user_id);

        //待评价
        $data['no'] = $this->getWaitCommentNum($user_id);

        return $data;
    }
    /**
     * 获取已评论数
     * @param int $user_id
     * @return array
     */
    public function getHadCommentNum($user_id)
    {
        return $this->getCommentNum($user_id, 1);
    }

    /**
     * 获取未(待)评论数
     */
    public function getWaitCommentNum($user_id)
    {
        return $this->getCommentNum($user_id, 0);
    }


    /**
	 * 添加商品评论
	 * @param $order_id  订单id
	 * @param $goods_id  商品id
	 * @param $user_email用户邮箱地址
	 * @param $username  用户名
	 * @return bool
	 */
    public function addGoodsComment($add)
    {
        if (!$add['order_id'] || !$add['goods_id']) {
            return array('status'=>-1, 'msg'=>'非法操作');
        }

        //检查订单是否已完成
        $order = M('order')->where(['order_id' => $add['order_id'], 'user_id' => $add['user_id']])->find();
        if ($order['order_status'] != 2) {
            return ['status'=>-1, 'msg'=>'该笔订单还未完成'];
        }

        //检查是否已评论过
        $goods = M('comment')->where(['order_id' => $add['order_id'], 'goods_id' => $add['goods_id']])->find();
        if ($goods) {
            return ['status'=>-1, 'msg'=>'您已经评论过该商品'];
        }

        $row = M('comment')->add($add);
        if (!$row) {
            return ['status'=>-1,'msg'=>'评论失败'];
        }
        
        //更新订单商品表状态
        M('order_goods')->where(['goods_id'=>$add['goods_id'],'order_id'=>$add['order_id']])->save(['is_comment'=>1]);
        M('goods')->where(['goods_id'=>$add['goods_id']])->setInc('comment_count',1); // 评论数加一
        //
        // 查看这个订单是否全部已经评论,如果全部评论了 修改整个订单评论状态
        $comment_count = M('order_goods')->where(['order_id' => $add['order_id'], 'is_comment' => 0,'is_send'=>['>',3]])->count();
        if ($comment_count == 0) {
            // 如果所有的商品都已经评价了 订单状态改成已评价
            $rs = M('order')->where("order_id ='{$add['order_id']}'")->save(['order_status' => 4]);
            //已完成状态的订单处理判断是否可赠送积分      
            if($rs){
                $check = new \app\common\logic\UsersLogic();
                $check->receiveGoodsGiftIntegral($add);
            }                     
        }

        return ['status'=>1,'msg'=>'评论成功'];
    }

    /**
     * 添加评论
     */
    public function addComment($data)
    { 
        // 晒图片        
        $img = $this->uploadCommentImgFile('comment_img_file');
        if ($img['status'] !== 1) {
            return $img;
        }
        
        $user = M('users')->where("user_id", $data['user_id'])->find();
        
        $add['img']  = $img['result'] ? serialize($img['result']) : ($data['img'] ? serialize($data['img']) : ''); //兼顾小程序图片上传
        $add['email']       = $user['email'];
        $add['username']    = $user['nickname'];
        $add['goods_rank']  = $data['goods_rank'] ?: 1;
        $add['service_rank'] = $data['service_rank'] ?: 1;
        $add['deliver_rank'] = $data['deliver_rank'] ?: 1;
        $add['goods_id']    = $data['goods_id'] ?: 0;
        $add['order_id']    = $data['order_id'] ?: 0;
        $add['user_id']     = $data['user_id'] ?: 0;
        $add['parent_id']   = $data['parent_id'] ?: 0;
        $add['content']     = $data['content'] ?: '';
        $add['rec_id']       = $data['rec_id'] ;
        $add['is_anonymous'] = $data['is_anonymous'] ? 1 : 0;
        $add['add_time']    = time();
        $add['ip_address']  = \think\Request::instance()->ip();
        $add['zan_num']     = 0;
        $add['parent_id']   = 0;
        $add['is_show']     = 1;

        //添加评论
        return $this->addGoodsComment($add);
    }  

    /**
     * 把回复树状数组转换成二维数组
     * @param $comment_id 回复id
     * @param int $item_num 条数
     * @return array
     */
    public function getReplyListToArray($comment_id, $item_num = 0)
    {
        $reply_tree = $this->getReplyList($comment_id);
        if (empty($reply_tree)) {
            return $reply_tree;
        }
        $reply_flat_list = $this->treeToArray($reply_tree);
        if ($item_num == 0 || count($reply_flat_list) <= $item_num) {
            $res = $reply_flat_list;
        } else {
            $res = array_slice($reply_flat_list, 0, $item_num);
        }
        return $res;
    }

    /**
     * 回复分页
     * @param $comment_id
     * @param int $page
     * @param int $item_num
     * @return mixed
     */
    public function getReplyPage($comment_id, $page = 0, $item_num = 20)
    {
        $reply_tree = $this->getReplyList($comment_id);
        $reply_flat_list = $this->treeToArray($reply_tree);
        $count = count($reply_flat_list);
        $list['list'] = array_slice($reply_flat_list, $page * $item_num, $item_num);
        $list['count'] = $count;
        return $list;
    }

    /**
     * 将树状数组转换二维数组
     * @param $tree
     * @return array
     */
    public function treeToArray($tree)
    {
        $list = array();
        foreach ($tree as $key) {
            $node = $key['children'];
            unset($key['children']);
            $list[] = $key;
            if ($node) $list = array_merge($list, $this->treeToArray($node));
        }
        return $list;
    }

    /**
     * 根据评论id获取评论下的所有回复
     * @param $comment_id
     * @param int $parent_id
     * @param array $result
     * @return array
     */
    private function getReplyList($comment_id, $parent_id = 0, &$result = array())
    {
        $reply_where = array(
            'comment_id' => $comment_id,
            'parent_id' => $parent_id
        );
        $arr = M('reply')->where($reply_where)->order('reply_time desc')->select();
        if (empty($arr)) {
            return array();
        }
        foreach ($arr as $cm) {
            $thisArr =& $result[];
            $cm['children'] = $this->getReplylist($comment_id, $cm['reply_id'], $thisArr);
            $thisArr = $cm;
        }
        return $result;
    }

    
    /**
     * 上传评论图片
     * @return type
     */
    public function uploadCommentImgFile($name)
    {
        $comment_img = [];
        //$comments = '';
        if ($_FILES[$name]['tmp_name']) {
            $files = request()->file($name);
            if (is_object($files)) {
                $files = [$files];
            }
            $image_upload_limit_size = config('image_upload_limit_size');
            $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
            $dir = UPLOAD_PATH.'comment/';
            if (!($_exists = file_exists($dir))) {
                mkdir($dir);
            }
            $parentDir = date('Ymd');
            
            $i = 0;
            foreach($files as $file){
                $i +=1;
                $info = $file->validate($validate)->move($dir, true); 
                if($info) {
                    $comment_img[] = '/'.$dir.$parentDir.'/'.$info->getFilename();
                } else {
                    return ['status' => -1, 'msg' => $file->getError()];
                }
            }
            //$comments = serialize($comment_img); // 上传的图片文件
        }

        return ['status' => 1, 'msg' => '上传成功', 'result' => $comment_img];
    }

    /**
     * 获取评论列表
     * @param $user_id 用户id
     * @param int $status 状态 0 未评论 1 已评论 ,其他 全部
     * @return mixed
     */
    public function getComment($user_id, $status = 2)
    {
        $comment_count = $this->getCommentNum($user_id, $status);
        $page = new \think\Page($comment_count,10);
        $comment_list = $this->getCommentList($user_id, $status, $page->firstRow, $page->listRows);

        $return['result'] = $comment_list;
        $return['page'] = $page; //分页
        return $return;
    }

    /**
     * 获取评论查询数
     * @param $user_id 用户id
     * @param int $status 状态 0 未评论 1 已评论 ,其他 全部
     * @return mixed
     */
    public function getCommentNum($user_id, $status = 2)
    {
        return $this->getCommentQuery(0, $user_id, $status);
    }

    public function getCommentList($user_id, $status = 2, $firstRow = 1, $listRows = 10)
    {
        return $this->getCommentQuery(1, $user_id, $status, $firstRow, $listRows);
    }

    /**
     * 获取评论查询结果
     * @param $user_id 用户id
     * @param $queryType: 0: 获取数量， 1:获取列表
     * @param int $status 状态 0 未评论 1 已评论 ,其他 全部
     * @return mixed
     */
    public function getCommentQuery($queryType, $user_id, $status = 2, $firstRow = 1, $listRows = 10)
    {
        $comment_where = ['o.user_id'=>$user_id,'is_send'=>['in','0,1,2']];//处理自提待评价订单，无此订单的bug
        switch($status){
            case 0: $comment_where['og.is_comment'] = 0;break;
            case 1: $comment_where['og.is_comment'] = 1;break;
        }

        $query = Db::name('order_goods')->alias('og')
            ->join('__ORDER__ o'," o.order_id = og.order_id AND o.user_id=$user_id AND o.deleted = 0 AND o.order_status IN (2,4)")
            ->join('__COMMENT__ c',"c.rec_id = og.rec_id", 'LEFT')  //要查看评论详情，得连表找出评论ID
            ->where($comment_where)
            ->where(function ($query) {
                $query->whereor('o.shop_id', 'gt', 0)->whereor('confirm_time','gt',0);
            });
        if ($queryType) {
            return $query->field('og.*,og.is_comment as goods_comment,o.*,c.comment_id')
                ->order('o.order_id', 'desc')
                ->limit($firstRow, $listRows)
                ->select();
        }

        return $query->count();
    }
}