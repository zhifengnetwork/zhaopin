<?php
/**
 * +---------------------------------
 * 商品搜索API
 * +---------------------------------
*/
namespace app\api\controller;
use think\Db;

class Search extends ApiBase
{

    /**
     * +---------------------------------
     * 搜索列表页
     * +---------------------------------
    */
    public function get_search(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $hot = Db::table('search')->where('cat_id','>',0)->order('add_time DESC,number DESC')->field('keywords,cat_id')->limit(10)->select();
        $data['hot'] = array_unique($hot,SORT_REGULAR);
        $data['history'] = Db::table('search')->where('user_id',$user_id)->order('add_time DESC,number DESC')->field('keywords,cat_id')->limit(10)->select();

        $like = Db::table('search')->where('user_id',$user_id)->where('cat_id','>',0)->order('add_time DESC,number DESC')->field('keywords,cat_id')->limit(10)->select();
        $like = array_unique($like,SORT_REGULAR);
        if(isset($like['cat_id'])){
            $cat_ids = implode(',',$like['cat_id']);
            $cat = Db::table('category')->where('cat_id','in',$cat_ids)->field('cat_id,pid')->select();

            $pid = [];
            if($cat){
                foreach($cat as $key=>$value){
                    if($value['pid']){
                        $pid[] = Db::table('category')->where('cat_id',$value['pid'])->value('pid');
                    }else{
                        $pid[] = $value['cat_id'];
                    }
                }
            }
            $pid = implode(',',$pid);

            $like = Db::table('category')->where('pid','in',$pid)->field('cat_id,cat_name')->limit(10)->select();
            $data['like'] = shuffle($like);
        }else{
            $data['like'] = [];
        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$data]);
    }


    public function search(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $keywords = input('keywords');

        $keywords = trim($keywords);
        if(!$keywords){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'搜索关键字不能为空！','data'=>'']);
        }

        $cat_id = Db::table('category')->where('cat_name',"$keywords")->value('cat_id');
        $cat_id2 = 'cat_id1';
        $sort = input('sort');
        $goods_attr = input('goods_attr');
        $page = input('page',1);

        $where = [];
        $whereRaw = [];
        $pageParam = ['query' => []];
        if($cat_id){
            $cate_list = Db::name('category')->where('is_show',1)->where('cat_id',$cat_id)->value('pid');
            if($cate_list){
                $cate_list = Db::name('category')->where('is_show',1)->where('pid',$cate_list)->select();
                $cat_id2 = 'cat_id2';
            }else{
                $cate_list = Db::name('category')->where('is_show',1)->where('pid',$cat_id)->select();
            }
            $where[$cat_id2] = $cat_id;
            $pageParam['query'][$cat_id2] = $cat_id;

            $cate_list  = getTree1($cate_list);

            if($goods_attr){
                $whereRaw = "FIND_IN_SET($goods_attr,goods_attr)";
                $pageParam['query']['goods_attr'] = $goods_attr;
            }

            if($sort){
                $order['price'] = $sort;
            }else{
                $order['goods_id'] = 'DESC';
            }
            
            $goods_list = Db::name('goods')->alias('g')
                            ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                            ->where('gi.main',1)
                            ->where('is_show',1)
                            ->where($where)
                            ->where($whereRaw)
                            ->order($order)
                            ->field('g.goods_id,gi.picture img,goods_name,desc,price,original_price,g.goods_attr')
                            ->paginate(10,false,$pageParam)
                            ->toArray();
            if($goods_list['data']){
                foreach($goods_list['data'] as $key=>&$value){
                    $value['comment'] = Db::table('goods_comment')->where('goods_id',$value['goods_id'])->count();
                    $value['attr_name'] = Db::table('goods_attr')->where('attr_id','in',$value['goods_attr'])->column('attr_name');
                }
            }
            
            //添加搜索记录
            $where = [];
            $where['user_id']   =   $user_id;
            $where['keywords']  =   $keywords;
            $where['cat_id']    =   $cat_id;
            $id = Db::table('search')->where($where)->value('id');
            if($id){
                Db::table('search')->where('id',$id)->setInc('number',1);
                Db::table('search')->where('id',$id)->update(['add_time'=>time()]);
            }else{
                $where['number'] = 1;
                $where['add_time'] = time();
                Db::table('search')->insert($where);
            }

            $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>['cate_list'=>$cate_list,'goods_list'=>$goods_list['data']]]);
        }else{

            if($sort){
                $order['price'] = $sort;
            }else{
                $order['goods_id'] = 'DESC';
            }

            $goods_list = Db::table('goods')->alias('g')
                        ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                        ->where('gi.main',1)
                        ->where('is_show',1)
                        ->where('g.goods_name','like',"%{$keywords}%")
                        ->field('g.goods_id,gi.picture img,goods_name,desc,price,original_price,g.goods_attr')
                        ->order($order)
                        ->paginate(10,false,$pageParam)
                        ->toArray();
            if($goods_list['data']){
                foreach($goods_list['data'] as $key=>&$value){
                    $value['comment'] = Db::table('goods_comment')->where('goods_id',$value['goods_id'])->count();
                    $value['attr_name'] = Db::table('goods_attr')->where('attr_id','in',$value['goods_attr'])->column('attr_name');
                }
            }
            
            //添加搜索记录
            $where = [];
            $where['user_id']   =   $user_id;
            $where['keywords']  =   $keywords;
            $where['cat_id']    =   0;
            $id = Db::table('search')->where($where)->value('id');
            if($id){
                Db::table('search')->where('id',$id)->setInc('number',1);
            }else{
                $where['number'] = 1;
                $where['add_time'] = time();
                Db::table('search')->insert($where);
            }

            $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>['cate_list'=>[],'goods_list'=>$goods_list['data']]]);
        }
    }

    public function del_search(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $res = Db::table('search')->where('user_id',$user_id)->delete();

        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'清除成功！','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'清除失败！','data'=>'']);
        }
    }


    // public function Search()
    // {
    //     $filter_param = array();   // 筛选数组
    //     $id = I('get.id/d', 0);    // 当前分类id
    //     $brand_id = I('brand_id/d', 0);
    //     $sort = I('sort', 'sort'); // 排序
    //     $sort_asc = I('sort_asc', 'desc'); // 价格排序
    //     $price = I('price', '');   // 价钱
    //     $start_price = trim(I('start_price', '0')); // 输入框价钱
    //     $end_price = trim(I('end_price', '0'));     // 输入框价钱
    //     if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱
    //     $filter_param['id'] = $id; //加入筛选条件中
    //     $brand_id && ($filter_param['brand_id'] = $brand_id); //加入筛选条件中
    //     $price && ($filter_param['price'] = $price); //加入筛选条件中
    //     $q = urldecode(trim(I('q', ''))); // 关键字搜索
    //     $q && ($_GET['q'] = $filter_param['q'] = $q); //加入筛选条件中
    //     $qtype = I('qtype', '');
    //     $where = array('is_on_sale' => 1);
    //     if ($qtype) {
    //         $filter_param['qtype'] = $qtype;
    //         $where[$qtype] = 1;
    //     }
    //     if ($q) $where['goods_name'] = array('like', '%' . $q . '%');

    //     $goodsLogic = new GoodsLogic();
    //     $filter_goods_id = M('goods')->where($where)->cache(true)->getField("goods_id", true);

    //     // 过滤筛选的结果集里面找商品
    //     if ($brand_id || $price)// 品牌或者价格
    //     {
    //         $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
    //         $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个筛选条件的结果 的交集
    //     }

    //     //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
    //     $sel = I('sel');
    //     if ($sel) {
    //         $goods_id_4 = $goodsLogic->getFilterSelected($sel);
    //         $filter_goods_id = array_intersect($filter_goods_id, $goods_id_4);
    //     }

    //     $filter_menu  = $goodsLogic->get_filter_menu($filter_param, 'search'); // 获取显示的筛选菜单
    //     $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'search'); // 筛选的价格期间
    //     $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'search'); // 获取指定分类下的筛选品牌

    //     $count = count($filter_goods_id);
    //     $page = new Page($count, 12);
    //     if ($count > 0) {
    //         $sort_asc = $sort_asc == 'asc' ? 'asc' : 'desc';
    //         $sort_arr = ['sales_sum','shop_price','is_new','comment_count','sort'];
    //         if(!in_array($sort,$sort_arr)) $sort='sort';
    //         $goods_list = D('goods')->where("goods_id", "in", implode(',', $filter_goods_id))->order([$sort => $sort_asc])->limit($page->firstRow . ',' . $page->listRows)->field('goods_id,goods_name,comment_count,shop_price')->select();
    //         $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
    //         if ($filter_goods_id2)
    //             $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
    //     }
    //     $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
    //     C('TOKEN_ON', false);
    //     if($goods_list && $goods_images){
    //         foreach($goods_list as $k=>$v){
    //             foreach($goods_images as $k2=>$v2){
    //                 if($v['goods_id']==$v2['goods_id']){
    //                     $goods_list[$k]['goods_images'][] = $v2;
    //                 }
    //             }
    //         }
    //     }

    //     $data = [
    //         'goods_list'=> $goods_list,
    //         // 'goods_images'=> $goods_images,
    //         'filter_menu'=> $filter_menu,
    //         'filter_brand'=> $filter_brand,
    //         'filter_price'=> $filter_price,
    //         'filter_param'=> $filter_param,
    //         'sort_asc' => $sort_asc == 'asc' ? 'desc' : 'asc',
    //         'page' =>  $page
    //     ];

    //     $this->ajaxReturn(['status' => 0 , 'msg'=>'获取成功','data'=>$data]);
    // }

}
