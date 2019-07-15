<?php


namespace app\common\logic;

use app\common\model\News;
use think\Model;

/**
 * Class
 * @package Home\Model
 */
class NewsLogic extends Model
{

    /**
     * 获取新闻列表
     * @param $data
     * @return array
     */
    public static function news_list($data)
    {
        $page = I('post.page/d', 1);//页数
        $limit = News::$LIMIT;//要显示的数量

        $list = M('news')
            ->alias('n')
            ->field('article_id,title,click,thumb,description,tags,cat_name,publish_time,link')
            ->join('__NEWS_CAT__ cat', 'cat.cat_id = n.cat_id', 'LEFT')
            ->where(['check_type' => News::$CHECK_PASS, 'is_open' => News::$STATUS_OPEN])
            ->limit(($page - 1) * $limit, $limit)
            ->order('publish_time desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['time'] = date('Y-m-d', $v['publish_time']);
        }
        $data = PageLogic::getPage($list, $page);
        return $data;


    }


    /**
     * 获取新闻详情
     * @param $data
     * @return array
     */
    public static function news_detail($data)
    {
        $list = M('news')
            ->alias('n')
            ->join('__NEWS_CAT__ cat', 'cat.cat_id = n.cat_id', 'LEFT')
            ->field('article_id,title,click,thumb,description,tags,cat_name,publish_time,content')
            ->where(['open_type' => News::$OPEN_TYPE, 'is_open' => News::$OPEN_STATUS, 'article_id' => $data['id']])
            ->find();
//      $list['addtime'] = date('Y-m-d',$list['addtime']);
        if ($list) {
            $list['content'] = htmlspecialchars_decode($list['content']);
            $list['time'] = date('Y-m-d', $list['publish_time']);
        }
        if ($list) {
            return array('status' => 1, 'msg' => '操作成功', 'result' => $list);
        }
        return array('status' => 1, 'msg' => '操作成功', 'result' => array());

    }


}