<?php
namespace app\admin\controller;

use app\common\model\PulsGoods;
use think\Db;
use think\Loader;
use think\Request;
use think\Config;
/*
 * 商品管理
 */
class Goods extends Common
{
    /*
     * 商品列表
     */
    public function index()
    {   

        $where = [];
        $pageParam = ['query' => []];

        $where['g.is_del'] = 0;
        // $pageParam['query']['is_del'] = 0;

        $is_show = input('is_show');
        if( $is_show ){
            $where['g.is_show'] = $is_show;
            $pageParam['query']['is_show'] = $is_show;
        }else if($is_show === '0'){
            $is_show = 0;
            $where['g.is_show'] = $is_show;
            $pageParam['query']['is_show'] = $is_show;
        }

        $goods_name = input('goods_name');
        if( $goods_name ){
            $where["g.goods_name"] = ['like', "%{$goods_name}%"];
            $pageParam['query']['goods_name'] = ['like', "%{$goods_name}%"];
        }

        $cat_id1 = input('cat_id1');
        if( $cat_id1 ){
            $where['g.cat_id1'] = $cat_id1;
            $pageParam['query']['cat_id1'] = $cat_id1;
        }
        
        $cat_id2 = input('cat_id2');
        if( $cat_id2 ){
            $where['g.cat_id2'] = $cat_id2;
            $pageParam['query']['cat_id2'] = $cat_id2;
        }

        $list  = Db::table('goods')->alias('g')
                ->join('category c1','c1.cat_id=g.cat_id1','LEFT')
                ->join('category c2','c2.cat_id=g.cat_id2','LEFT')
                ->order('goods_id DESC')
                ->field('g.*,c1.cat_name c1_name,c2.cat_name c2_name')
                ->where($where)
                ->paginate(10,false,$pageParam);

        //商品一级分类
        $cat_id11 = Db::table('category')->where('level',1)->select();
        //商品二级分类
        $cat_id22 = Db::table('category')->where('level',2)->select();

        return $this->fetch('goods/index',[
            'list'          =>  $list,
            'is_show'       =>  $is_show,
            'goods_name'    =>  $goods_name,
            'cat_id1'       =>  $cat_id1,
            'cat_id2'       =>  $cat_id2,
            'cat_id11'      =>  $cat_id11,
            'cat_id22'      =>  $cat_id22,
            
            'meta_title'    =>  '商品列表',
        ]);
    }

    /*
     * 添加商品
     */
    public function add()
    {   

        if( Request::instance()->isPost() ){
            $data = input('post.');

            //验证
            $validate = Loader::validate('Goods');
            if(!$validate->scene('add')->check($data)){
                $this->error( $validate->getError() );
            }
            
            // $data['img_td'] = $_FILES['img_td'];

            // $img = [];
            // foreach($data['img_td'] as $key=>$value){
            //     foreach($value['img'] as $k=>$v){
            //         $img[$k][$key] = $v;
            //     }
            // }

            // foreach($img as $key=>$value){
            //     if($value['error']==0){

            //         $file_name = 'sku_img/';
            //         $name = $file_name . request()->time().rand(0,99999) . '.png';
            //         $names = ROOT_PATH .Config('c_pub.img');

            //         //防止文件名重复
            //         $filename = $names . $name;
            //         //转码，把utf-8转成gb2312,返回转换后的字符串， 或者在失败时返回 FALSE。
            //         $filename =iconv("UTF-8","gb2312",$filename);
                    
            //         if (!file_exists($names . $file_name)){
            //             mkdir($names . $file_name,0777,true);
            //         }
                    
            //         //保存文件,   move_uploaded_file 将上传的文件移动到新位置
            //         move_uploaded_file($value["tmp_name"],$filename);//将临时地址移动到指定地址

            //         $data_spec[$key]['img'] = ['key' => 'img', 'value' => $name];
            //     }else{
            //         $data_spec[$key]['img'] = ['key' => 'img', 'value' => ''];
            //     }
            // }

            // 本店售价
            $pri = $data['pri_td']['pri'];
            $group_pri = $data['pri_td']['group_pri'];
            $pri_count = count($pri);
            for ($m = 0; $m < $pri_count; $m++) {
                $data_spec[$m]['pri'] = ['key' => 'pri', 'value' => $pri[$m]];
                $data_spec[$m]['group_pri'] = ['key' => 'group_pri', 'value' => $group_pri[$m]];
            }

            // 初始化规格数据格式
            $count = count($data['goods_td'][1]);
            for ($i = 0; $i < $count; $i++) {
                foreach ($data['goods_th'] as $key => $val) {
                    $value = $data['goods_td'][$key][$i];
                    if (isset($value) && $value !== '') {
                        if ($key == 'pri' || $key == 'num') {
                            if (!is_numeric($value)) {
                                $this->error( $val[0] . '不能为非数字' );
                            }
                        }
                        $data_spec[$i][] = ['key' => $val[0], 'value' => $value];
                    }
                }
            }
            if (is_string($data_spec)) {
                $this->error('规格错误！');
            }
            
            foreach ($data['goods_th'] as $key => $val) {
                if ($key !== 'num' && $key !== 'pri') {
                    if (!empty($data['goods_td'][$key])) {
                        $spec_str = implode(';', array_unique($data['goods_td'][$key]));
                    } else {
                        $spec_str = '';
                    }
                    $default_spec[] = json_encode(['key'=>$val[0], 'value'=>$spec_str], JSON_UNESCAPED_UNICODE);
                }
            }
            $default_spec_str = implode(',', $default_spec);
            $current_spec = $data['goods_th'];
            unset($current_spec['num'],$current_spec['pri']);
            
            $all_spec = Db::name('goods_spec')->column('spec_name','spec_id');
            foreach ($current_spec as $key => $val) {
                if (!in_array(trim($val[0]), $all_spec)) {
                    $new_spec['spec_name'] = $val[0];
                    $rst = Db::table('goods_spec')->insert($new_spec);
                    if (!$rst) {
                        $this->error('添加新商品规格类型出错!');
                    }
                }
            }
            $data['goods_spec'] = '[' . $default_spec_str . ']';

            if( isset( $data['goods_attr'] ) ){
                if( in_array( 6 , $data['goods_attr']  ) ){
                    $data['stock1'] = array_sum($data['goods_td']['num']);
                    $data['limited_start'] = strtotime( $data['limited_start'] );
                    $data['limited_end'] = strtotime( $data['limited_end'] );
                }
                $data['goods_attr'] = implode( ',' , $data['goods_attr'] );
            }
            
            
            $data['add_time'] = strtotime( $data['add_time'] );
            $goods_id = Db::table('goods')->strict(false)->insertGetId($data);
            
            if ( $goods_id ) {
                //限时购redis
                if(isset($data['stock1'])){
                    $redis = $this->getRedis();
                    for($i=1;$i<=$data['stock1'];$i++){
                        $redis->rpush("GOODS_LIMITED_{$goods_id}",1);
                    }
                }

                //添加操作日志
                slog($goods_id);

                //图片处理
                if( isset($data['img']) && !empty($data['img'][0])){
                    foreach ($data['img'] as $key => $value) {

                        $saveName = request()->time().rand(0,99999) . '.png';

                        $img=base64_decode($value);
                        //生成文件夹
                        $names = "goods" ;
                        $name = "goods/" .date('Ymd',time()) ;
                        if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){ 
                            mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
                        }
                        //保存图片到本地
                        file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$img);

                        unset($data['img'][$key]);
                        $data['img'][] = $name.$saveName;
                    }
                    $data['img'] = array_values($data['img']);
                    
                    foreach ($data['img'] as $key => $value) {
                        if(!$key){
                            $datas[$key]['main'] = 1;
                        }else{
                            $datas[$key]['main'] = 0;
                        }
                        $datas[$key]['picture'] = $value;
                        $datas[$key]['goods_id'] = $goods_id;
                    }

                    Db::table('goods_img')->insertAll($datas);
                }
                $skuRes = setSukMore($goods_id, $data_spec);

                if ($skuRes) {
                    $this->success('添加商品成功',url('goods/add'));
                }else{
                    $this->error('添加商品失败');
                }
            } else {
                $this->error('添加失败');
            }

        }

        //商品属性
        $goods_attr = Db::table('goods_attr')->select();
        //商品一级分类
        $cat_id1 = Db::table('category')->where('level',1)->select();
        //商品二级分类
        $cat_id2 = Db::table('category')->where('level',2)->select();
        //配送方式
        $delivery = Db::table('goods_delivery')->field('delivery_id,name')->where('is_show',1)->select();

        return $this->fetch('goods/add',[
            'meta_title'    =>  '添加商品',
            'goods_attr'    =>  $goods_attr,
            'cat_id1'       =>  $cat_id1,
            'cat_id2'       =>  $cat_id2,
            'delivery'      =>  $delivery,
        ]);
    }

    /*
     * 修改商品
     */
    public function edit(){
        $goods_id = input('goods_id');

        if(!$goods_id){
            $this->error('参数错误！');
        }
        $info = Db::table('goods')->find($goods_id);

//        dump($info);die;
        if($info['goods_attr']){
            $info['goods_attr'] = explode(',',$info['goods_attr']);
        }
        
        if( Request::instance()->isPost() ){
            $data = input('post.');
            
            //验证
            $validate = Loader::validate('Goods');
            if(!$validate->scene('edit')->check($data)){
                $this->error( $validate->getError() );
            }

            // 规格id
            $sku_id_arr = $data['sku_id'];
            for ($n = 0; $n < count($sku_id_arr); $n++) {
                $data_spec[$n]['sku_id'] = $data['sku_id'][$n];
            }

            // 本店售价
            $pri = $data['pri_td']['pri'];
            $group_pri = $data['pri_td']['group_pri'];
            $pri_count = count($pri);
            for ($m = 0; $m < $pri_count; $m++) {
                $data_spec[$m]['pri'] = ['key' => 'pri', 'value' => $pri[$m]];
                $data_spec[$m]['group_pri'] = ['key' => 'group_pri', 'value' => $group_pri[$m]];
            }
            // 初始化规格数据格式
            $count = count($data['goods_td'][1]);
            for ($i = 0; $i < $count; $i++) {
                foreach ($data['goods_th'] as $key => $val) {
                    $value = $data['goods_td'][$key][$i];
                    if (isset($value) && $value !== '') {
                        if ($key == 'pri' || $key == 'num') {
                            if (!is_numeric($value)) {
                                $this->error( $val[0] . '不能为非数字' );
                            }
                        }
                        $data_spec[$i][] = ['key' => $val[0], 'value' => $value];
                    }
                }
            }
            if (is_string($data_spec)) {
                $this->error('规格错误！');
            }

            foreach ($data['goods_th'] as $key => $val) {
                if ($key !== 'num' && $key !== 'pri') {
                    if (!empty($data['goods_td'][$key])) {
                        $spec_str = implode(';', array_unique($data['goods_td'][$key]));
                    } else {
                        $spec_str = '';
                    }
                    $default_spec[] = json_encode(['key'=>$val[0], 'value'=>$spec_str], JSON_UNESCAPED_UNICODE);
                }
            }
            $default_spec_str = implode(',', $default_spec);

            // 判断是否新的规格名称gl_goods_spec
            $current_spec = $data['goods_th'];
            unset($current_spec['num'],$current_spec['pri']);
            $all_spec = Db::name('goods_spec')->column('spec_name','spec_id');
            
            foreach ($current_spec as $key => $val) {
                if (!in_array($val[0],$all_spec)) {
                    $new_spec['spec_name'] = $val[0];
                    $rst = Db::name('goods_spec')->insert($new_spec);
                    if (!$rst) {
                        $this->error('添加新商品规格类型出错!');
                    }
                }
            }
            
            $data['goods_spec'] = '[' . $default_spec_str . ']';
            
            $skuRes = setSukMore2($goods_id, $data_spec);
            
            if( isset( $data['goods_attr'] ) ){
                if( in_array( 6 , $data['goods_attr']  ) ){
                    if( !in_array( 6 , $info['goods_attr']  ) ){
                        //限时购redis
                        foreach($data_spec as $key=>$value){
                            foreach($value as $k=>$v){
                                if($k == 'sku_id'){
                                    $sku_id = $value['sku_id'];
                                }
                                if(isset($v['key']) && $v['key'] == '库存' ){
                                    $redis = $this->getRedis();
                                    $redis->del("GOODS_LIMITED_{$sku_id}");
                                    for($i=0;$i<$v['value'];$i++){
                                        $redis->rpush("GOODS_LIMITED_{$sku_id}",1);
                                    }
                                }
                            }
                        }
                        $data['stock1'] = array_sum($data['goods_td']['num']);
                    }
                    $data['limited_start'] = strtotime( $data['limited_start'] );
                    $data['limited_end'] = strtotime( $data['limited_end'] );
                }
                $data['goods_attr'] = implode( ',' , $data['goods_attr'] );
            }

            //图片处理
            if( isset($data['img']) && !empty($data['img'][0])){
                foreach ($data['img'] as $key => $value) {

                    $saveName = request()->time().rand(0,99999) . '.png';

                    $img=base64_decode($value);
                    //生成文件夹
                    $names = "goods" ;
                    $name = "goods/" .date('Ymd',time()) ;
                    if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){ 
                        mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
                    }
                    //保存图片到本地
                    file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$img);

                    unset($data['img'][$key]);
                    $data['img'][] = $name.$saveName;
                }
                $data['img'] = array_values($data['img']);
                $img = Db::table('goods_img')->where('goods_id',$goods_id)->find();
                foreach ($data['img'] as $key => $value) {
                    if(!$img){
                        if(!$key){
                            $datas[$key]['main'] = 1;
                        }else{
                            $datas[$key]['main'] = 0;
                        }
                    }
                    
                    $datas[$key]['picture'] = $value;
                    $datas[$key]['goods_id'] = $data['goods_id'];
                }

                Db::table('goods_img')->insertAll($datas);
            }
            
            if ( Db::table('goods')->strict(false)->update($data) !== false ) {
                //添加操作日志
                slog($goods_id);
                $this->success('修改成功', url('goods/index'));
            } else {
                $this->error('修改失败');
            }
        }

        $rsts = $this->get_spec_info($goods_id);
        
        //商品属性
        $goods_attr = Db::table('goods_attr')->select();
        //商品一级分类
        $cat_id1 = Db::table('category')->where('level',1)->select();
        //商品二级分类
        $cat_id2 = Db::table('category')->where('level',2)->select();
        //商品组图
        $img = Db::table('goods_img')->where('goods_id','=',$goods_id)->select();
        //配送方式
        $delivery = Db::table('goods_delivery')->field('delivery_id,name')->where('is_show',1)->select();

        return $this->fetch('goods/edit',[
            'meta_title'  =>  '编辑商品',
            'info'        =>  $info,
            'goods_attr'  =>  $goods_attr,
            'cat_id1'     =>  $cat_id1,
            'cat_id2'     =>  $cat_id2,
            'delivery'    =>  $delivery,
            'img'         =>  $img,
            'rsts'        =>  $rsts,
        ]);
    }
    
    // 获取规格详细信息(不要改变添加数组值的位置)
    public function get_spec_info($goods_id){
        $sku_info = Db::table('goods_sku')->where('goods_id',$goods_id)->select();
        $spec_arr = Db::table('goods_spec')->column('spec_name', 'spec_id');
        $spec_attr_arr = Db::name('goods_spec_attr')->where('goods_id', $goods_id)->column('attr_name', 'attr_id');

        $spec_info = [];
        $spec_th = [];
        foreach ($sku_info as $key => $val) {
            $sku_attr = explode(',', trim(trim($val['sku_attr'], '{'), '}'));
            $spec_info[$key]['sku_id'] = $val['sku_id'];
            
            foreach ($sku_attr as $k => $v) {
                $sku_attr_arr = explode(':', $v);
                $spec_th[$k] = $spec_arr[$sku_attr_arr[0]];
                $spec_info[$key][] = $spec_attr_arr[$sku_attr_arr[1]];
            }
            
            if ($sku_info[$key]['price'] !== '') {
                // $spec_info[$key]['com_price'] = '1|'.$val['price'];
                $spec_info[$key]['com_price'] = $val['price'];
            }
            
            $spec_info[$key]['groupon_price'] = $val['groupon_price'];

            $spec_info[$key]['inventory'] = $val['inventory'];
            $spec_info[$key]['frozen_stock'] = $val['frozen_stock'];
        }
        
        $spec_th[] = '价格';
        $spec_th[] = '拼团价格';
        $spec_th[] = '库存';
        $spec_th[] = '冻结库存';
        $info['th'] = $spec_th;
        $info['td'] = $spec_info;
        return $info;
    }

    /**
     * ajax设为主图
     */
    public function ImgMain(){
        $id = input('id');
        if(!$id){
            return 0;
        }

        $res = Db::table('goods_img')->where('id','=',$id)->field('goods_id')->find();
        if(!$res['goods_id']){
            return 0;
        }

        Db::table('goods_img')->update(['id'=>$id,'main'=>1]);
        //添加操作日志
        slog($id);
        return Db::table('goods_img')->where('goods_id',$res['goods_id'])->where('id','neq',$id)->update(['main'=>0]);
    }

    /**
     * ajax删除图片
     */
    public function del_img(){
        if( request()->isAjax() ){
            $data['id'] = input('id');
            
            $info = Db::table('goods_img')->find($data['id']);
            if( !$info ){
                return 0;
            }

            @unlink(ROOT_PATH .Config('c_pub.img') . $info['picture']);
            //添加操作日志
            slog($data['id']);
            return Db::table('goods_img')->where('id','=',$data['id'])->delete();
        }
    }

    /*
     * ajax 删除商品
     */
    public function del(){
        $goods_id = input('goods_id');
        if(!$goods_id){
            jason([],'参数错误',0);
        }
        $info = Db::table('goods')->find($goods_id);
        if(!$info){
            jason([],'参数错误',0);
        }

        if( Db::table('goods')->where('goods_id',$goods_id)->update(['is_del'=>1]) ){
            //添加操作日志
            slog($goods_id);
            jason([],'删除商品成功！');
        }else{
            jason([],'删除商品失败！',0);
        }

    }

    /*
     * ajax 上架/下架
     */
    public function is_show(){
        $goods_id = input('goods_id');
        $is_show  = input('is_show');
        if(!$goods_id){
            jason([],'参数错误',0);
        }
        $info = Db::table('goods')->find($goods_id);
        if(!$info){
            jason([],'参数错误',0);
        }

        if( Db::table('goods')->where('goods_id',$goods_id)->update(['is_show'=>$is_show]) ){
            //添加操作日志
            slog($goods_id);
            jason(200);
        }
        jason([],'失败',0);

    }

    /*
     * ajax 批量上架/批量下架
     */
    public function is_show_all(){
        $goods_id = input('goods_id');
        $is_show  = input('is_show');
        if(!$goods_id){
            jason([],'参数错误',0);
        }

        if( Db::table('goods')->where('goods_id','in',$goods_id)->update(['is_show'=>$is_show]) ){
            //添加操作日志
            slog($goods_id);
            jason([]);
        }
        jason([],'失败',0);

    }
    
    /*
     * ajax 批量删除
     */
    public function del_all(){
        $goods_id = input('goods_id');
        if(!$goods_id){
            jason([],'参数错误',0);
        }

        if( Db::table('goods')->where('goods_id','in',$goods_id)->update(['is_del'=>1]) ){
            //添加操作日志
            slog($goods_id);
            jason([]);
        }
        jason([],'失败',0);

    }

    public function goods_type_list(){

        $where = [];
        $pageParam = ['query' => []];

        $type_name = input('type_name');
        if( $type_name ){
            $where["type_name"] = ['like', "%{$type_name}%"];
            $pageParam['query']['type_name'] = ['like', "%{$type_name}%"];
        }

        $list  = Db::table('goods_type')->order('type_id DESC')->where($where)->paginate(10,false,$pageParam);

        return $this->fetch('',[
            'list'          =>  $list,
            'type_name'      =>  $type_name,
            'meta_title'    =>  '商品规格管理',
        ]);
    }

    public function goods_type_add(){

        if( Request::instance()->isPost() ){
            $data = input('post.');
            
            if( !$data['type_name'] ){
                $this->error('请填写商品类型名称！');
            }

            if ( Db::table('goods_type')->insert($data) ) {
                $this->success('添加成功', url('goods/goods_type_list'));
            } else {
                $this->error('添加失败');
            }
        }

        return $this->fetch('',[
            'meta_title'    =>  '添加商品类型',
        ]);
    }

    public function goods_type_edit(){
        $type_id = input('type_id');
        
        if(!$type_id){
            $this->error('参数错误！');
        }

        if( Request::instance()->isPost() ){
            $data = input('post.');
            
            if( !$data['type_name'] ){
                $this->error('请填写商品类型名称！');
            }

            if ( Db::table('goods_type')->update($data) ) {
                $this->success('修改成功', url('goods/goods_type_list'));
            } else {
                $this->error('修改失败');
            }

        }

        $info = Db::table('goods_type')->find($type_id);
        
        return $this->fetch('',[
            'info'          =>  $info,
            'meta_title'    =>  '修改商品类型',
        ]);
    }

    
    public function goods_type_del(){
        $type_id = input('type_id');
        if(!$type_id){
            jason([],'参数错误',0);
        }
        $info = Db::table('goods_type')->find($type_id);
        if(!$info){
            jason([],'参数错误',0);
        }
        $spec = Db::name('goods_spec')->where('type_id','=',$type_id)->find();
        if($spec){
            jason([],'该类型含有规格，不能删除！',0);
        }

        if( Db::table('goods_type')->where('type_id',$type_id)->delete() ){
            jason([],'删除商品规格成功！');
        }else{
            jason([],'删除商品规格失败！',0);
        }

    }

    public function goods_spec_list(){
        $where = [];
        $pageParam = ['query' => []];

        $type_id = input('type_id');
        if(!$type_id){
            $this->error('参数错误！');
        }

        $where["type_id"] = ['eq', "{$type_id}"];
        $pageParam['query']['type_id'] = ['like', "%{$type_id}%"];

        $spec_name = input('spec_name');
        if( $spec_name ){
            $where["spec_name"] = ['like', "%{$spec_name}%"];
            $pageParam['query']['spec_name'] = ['like', "%{$spec_name}%"];
        }

        $list  = Db::table('goods_spec')->order('spec_id DESC')->where($where)->paginate(10,false,$pageParam);

        return $this->fetch('',[
            'list'          =>  $list,
            'spec_name'      =>  $spec_name,
            'meta_title'    =>  '商品规格管理',
        ]);
    }

    public function goods_spec_add(){

        if( Request::instance()->isPost() ){
            $data = input('post.');

            $data['type_id'] = input('type_id');
            if(!$data['type_id']){
                $this->error('参数错误！');
            }
            
            if( !$data['spec_name'] ){
                $this->error('请填写商品规格名称！');
            }
            
            if ( Db::table('goods_spec')->insert($data) ) {
                $this->success('添加成功', url('goods/goods_spec_list',['type_id'=>$data['type_id']],false));
            } else {
                $this->error('添加失败');
            }
        }

        return $this->fetch('',[
            'meta_title'    =>  '添加商品规格',
        ]);
    }

    public function goods_spec_edit(){
        $spec_id = input('spec_id');
        
        if(!$spec_id){
            $this->error('参数错误！');
        }
        
        $info = Db::table('goods_spec')->find($spec_id);

        if( Request::instance()->isPost() ){
            $data = input('post.');
            
            if( !$data['spec_name'] ){
                $this->error('请填写商品规格名称！');
            }

            if ( Db::table('goods_spec')->update($data) ) {
                $this->success('修改成功', url('goods/goods_spec_list',['type_id'=>$info['type_id']],false));
            } else {
                $this->error('修改失败');
            }

        }
        
        return $this->fetch('',[
            'info'          =>  $info,
            'meta_title'    =>  '修改商品规格',
        ]);
    }

    
    public function goods_spec_del(){
        $spec_id = input('spec_id');
        if(!$spec_id){
            jason([],'参数错误',0);
        }
        $info = Db::table('goods_spec')->find($spec_id);
        if(!$info){
            jason([],'参数错误',0);
        }
        $spec = Db::name('goods_spec_val')->where('spec_id','=',$spec_id)->find();
        if($spec){
            jason([],'该规格含有规格值，不能删除！',0);
        }

        if( Db::table('goods_spec')->where('spec_id',$spec_id)->delete() ){
            jason([],'删除商品规格成功！');
        }else{
            jason([],'删除商品规格失败！',0);
        }
    }

    public function goods_spec_val_list(){
        $where = [];
        $pageParam = ['query' => []];

        $spec_id = input('spec_id');
        if(!$spec_id){
            $this->error('参数错误！');
        }

        $where["spec_id"] = ['eq', "{$spec_id}"];
        $pageParam['query']['spec_id'] = ['like', "%{$spec_id}%"];

        $val_name = input('val_name');
        if( $val_name ){
            $where["val_name"] = ['like', "%{$val_name}%"];
            $pageParam['query']['val_name'] = ['like', "%{$val_name}%"];
        }

        $list  = Db::table('goods_spec_val')->order('val_id DESC')->where($where)->paginate(10,false,$pageParam);

        return $this->fetch('',[
            'list'          =>  $list,
            'val_name'      =>  $val_name,
            'meta_title'    =>  '商品规格值管理',
        ]);
    }

    public function goods_spec_val_add(){

        if( Request::instance()->isPost() ){
            $data = input('post.');

            $data['spec_id'] = input('spec_id');
            if(!$data['spec_id']){
                $this->error('参数错误！');
            }
            
            if( !$data['val_name'] ){
                $this->error('请填写商品规格值名称！');
            }
            
            if ( Db::table('goods_spec_val')->insert($data) ) {
                $this->success('添加成功', url('goods/goods_spec_val_list',['spec_id'=>$data['spec_id']],false));
            } else {
                $this->error('添加失败');
            }
        }

        return $this->fetch('',[
            'meta_title'    =>  '添加商品规格值',
        ]);
    }

    public function goods_spec_val_edit(){
        $val_id = input('val_id');
        
        if(!$val_id){
            $this->error('参数错误！');
        }
        
        $info = Db::table('goods_spec_val')->find($val_id);

        if( Request::instance()->isPost() ){
            $data = input('post.');
            
            if( !$data['val_name'] ){
                $this->error('请填写商品规格值名称！');
            }

            if ( Db::table('goods_spec_val')->update($data) ) {
                $this->success('修改成功', url('goods/goods_spec_val_list',['spec_id'=>$info['spec_id']],false));
            } else {
                $this->error('修改失败');
            }

        }
        
        return $this->fetch('',[
            'info'          =>  $info,
            'meta_title'    =>  '修改商品规格值',
        ]);
    }

    
    public function goods_spec_val_del(){
        $val_id = input('val_id');
        if(!$val_id){
            jason([],'参数错误',0);
        }
        $info = Db::table('goods_spec_val')->find($val_id);
        if(!$info){
            jason([],'参数错误',0);
        }

        if( Db::table('goods_spec_val')->where('val_id',$val_id)->delete() ){
            jason([],'删除商品规格成功！');
        }else{
            jason([],'删除商品规格失败！',0);
        }
    }


    /**
     * ajax规格
     */
    public function spec(){
        $type_id = input('type_id','1');

        if(!$type_id){
            return false;
        }

        $res = Db::table('goods_spec')->where('type_id','=',$type_id)->select();

        foreach ($res as $key => $value) {
            $res[$key]['spec_value'] = Db::table('goods_spec_val')->where('spec_id','=',$value['spec_id'])->select();
        }

        return json($res);
    }

    /**
     * ajax删除sku
     */
    public function del_sku(){
        if( request()->isAjax() ){
            $sku_id = input('sku_id');
            if( Db::table('goods_sku')->where('sku_id','=',$sku_id)->delete() ){
                jason([],'删除商品规格成功！');
            }else{
                jason([],'删除商品规格成功！',0);
            }
        }
    }

    /**
     * 配送方式列表
     */
    public function goods_delivery_list(){

        $where = [];
        $pageParam = ['query' => []];

        $name = input('name');
        if( $name ){
            $where["name"] = ['like', "%{$name}%"];
            $pageParam['query']['name'] = ['like', "%{$name}%"];
        }

        $list = Db::table('goods_delivery')->order('delivery_id DESC')->where($where)->paginate(10,false,$pageParam);

        return $this->fetch('',[
            'name'          =>  $name,
            'list'          =>  $list,
            'meta_title'    =>  '配送方式列表',
        ]);
    }

    /**
     * 添加配送方式
     */
    public function goods_delivery_add(){

        if( Request::instance()->isPost() ){
            $data = input('post.');
            
            //验证
            $validate = Loader::validate('Delivery');
            if(!$validate->scene('add')->check($data)){
                $this->error( $validate->getError() );
            }

            $data['areas'] = array();
            if(isset($data['citys'])){
                foreach($data['citys'] as $key=>$value){
                    $data['areas']['citys'][$key]            = $data['citys'][$key];
                    $data['areas']['firstweight_qt'][$key]   = $data['firstweight_qt'][$key];
                    $data['areas']['firstprice_qt'][$key]    = $data['firstprice_qt'][$key];
                    $data['areas']['secondweight_qt'][$key]  = $data['secondweight_qt'][$key];
                    $data['areas']['secondprice_qt'][$key]   = $data['secondprice_qt'][$key];
                }
            }
            $data['areas'] = serialize($data['areas']);

            if($data['is_default']){
                Db::table('goods_delivery')->where('delivery_id','neq',0)->update(['is_default'=>0]);
            }
            $delivery_id = Db::table('goods_delivery')->strict(false)->insertGetId($data);
            if ( $delivery_id ) {
                //添加操作日志
                slog($delivery_id);
                $this->success('添加成功', url('goods/goods_delivery_list','',false));
            } else {
                $this->error('添加失败');
            }
        }

        $areas = file_get_contents(ROOT_PATH . 'public/upload/areas');
        $areas = unserialize($areas);

        return $this->fetch('',[
            'meta_title'    =>  '添加配送方式',
            'areas'         =>  $areas,
        ]);
    }

    /**
     * 修改配送方式
     */
    public function goods_delivery_edit(){

        $delivery_id = input('delivery_id');
        if(!$delivery_id) $this->error('参数错误！');
        $info = Db::table('goods_delivery')->find($delivery_id);

        if( Request::instance()->isPost() ){
            $data = input('post.');
            
            //验证
            $validate = Loader::validate('Delivery');
            if(!$validate->scene('edit')->check($data)){
                $this->error( $validate->getError() );
            }

            $data['areas'] = array();
            if(isset($data['citys'])){
                foreach($data['citys'] as $key=>$value){
                    $data['areas']['citys'][$key]            = $data['citys'][$key];
                    $data['areas']['firstweight_qt'][$key]   = $data['firstweight_qt'][$key];
                    $data['areas']['firstprice_qt'][$key]    = $data['firstprice_qt'][$key];
                    $data['areas']['secondweight_qt'][$key]  = $data['secondweight_qt'][$key];
                    $data['areas']['secondprice_qt'][$key]   = $data['secondprice_qt'][$key];
                }
            }
            $data['areas'] = serialize($data['areas']);

            if($data['is_default']){
                Db::table('goods_delivery')->where('delivery_id','neq',0)->update(['is_default'=>0]);
            }
            
            if ( Db::table('goods_delivery')->strict(false)->update($data) ) {
                //添加操作日志
                slog($delivery_id);
                $this->success('修改成功', url('goods/goods_delivery_list','',false));
            } else {
                $this->error('修改失败');
            }
        }

        $info['areas'] = unserialize($info['areas']);

        $areas = file_get_contents(ROOT_PATH . 'public/upload/areas');
        $areas = unserialize($areas);

        return $this->fetch('',[
            'meta_title'    =>  '修改配送方式',
            'info'          =>  $info,
            'areas'         =>  $areas,
        ]);
    }

    /**
     * 删除配送方式
     */
    public function goods_delivery_del(){
        if( request()->isAjax()){
            $delivery_id = input('delivery_id');
            if( Db::table('goods_delivery')->where('delivery_id','=',$delivery_id)->delete()){
                //添加操作日志
                slog($delivery_id);
                jason([],'删除配送方式成功！');
            }else{
                jason([],'删除配送方式成功！',0);
            }
        }
    }

    /**
     * 虚拟物品模版列表
     */
    public function virtual_goods_list(){

        $where = [];
        $pageParam = ['query' => []];

        $title = input('title');
        if( $title ){
            $where["title"] = ['like', "%{$title}%"];
            $pageParam['query']['title'] = ['like', "%{$title}%"];
        }

        $list = Db::table('virtual_goods')->where($where)->paginate(10,false,$pageParam);
        $page = $list->render();
        $list = $list->toArray();
        if($list['data']){
            foreach($list['data'] as $key=>$value){
                $list['data'][$key]['use'] = Db::table('virtual_data')->where('type_id',$value['id'])->where('user_id','>',0)->count();
                $list['data'][$key]['count'] = Db::table('virtual_data')->where('type_id',$value['id'])->count();
            }
        }
        return $this->fetch('',[
            'meta_title'    =>  '虚拟物品模版列表',
            'list'          =>  $list,
            'title'         =>  $title,
            'page'          =>  $page,
        ]);
    }

    /**
     * 添加新模板
     */
    public function virtual_goods_add(){

        $id = input('id');
        
        if( Request::instance()->isPost() ){
            $data = input('post.');
            $fields = array();
            foreach($data['fields'] as $key=>$value){
                $fields[$value] = $data['fields_name'][$key];
            }
            $data['fields'] = serialize($fields);
            if($id){
                //添加操作日志
                slog($id,'edit');
                Db::table('virtual_goods')->strict(false)->update($data);
                $this->success('修改成功！',url('goods/virtual_goods_list'));
            }else{
                $id = Db::table('virtual_goods')->strict(false)->insertGetId($data);
                //添加操作日志
                slog($id);
                $this->success('添加成功！',url('goods/virtual_goods_list'));
            }
        }

        $info = Db::table('virtual_goods')->where('id',$id)->find();
        if($info['fields']) $info['fields'] = unserialize($info['fields']);
        
        $cate = Db::table('virtual_category')->select();

        return $this->fetch('',[
            'meta_title'    =>  '添加新模板',
            'cate'          =>  $cate,
            'info'          =>  $info,
        ]);
    }

    /**
     * 删除虚拟商品
     */
    public function virtual_goods_del(){
        if( request()->isAjax()){
            $id = input('id');
            if( Db::table('virtual_goods')->where('id','=',$id)->delete()){
                //添加操作日志
                slog($id);
                jason([],'删除虚拟商品成功！');
            }else{
                jason([],'删除虚拟商品成功！',0);
            }
        }
    }

    /**
     * 数据列表
     */
    public function virtual_data_list(){
        $type_id = input('type_id');
        if(!$type_id) $this->error('参数错误！');

        $where['d.type_id'] = $type_id;
        $pageParam['query']['type_id'] = $type_id;

        $pvalue = input('pvalue');
        if( $pvalue ){
            $where["d.pvalue"] = ['like', "%{$pvalue}%"];
            $pageParam['query']['pvalue'] = ['like', "%{$pvalue}%"];
        }

        $list = Db::table('virtual_data')->alias('d')
                ->join('users u','u.user_id=d.user_id','LEFT')
                ->join('order o','o.order_id=d.order_id','LEFT')
                ->where($where)
                ->field('d.*,u.realname,u.mobile,o.total_amount')
                ->paginate(10,false,$pageParam);
        
        $key_title = Db::name('virtual_goods')->where('id',$type_id)->value('fields');
        $key_title = unserialize($key_title);
        
        return $this->fetch('',[
            'meta_title'    =>  '数据列表',
            'list'          =>  $list,
            'pvalue'        =>  $pvalue,
            'key_title'     =>  $key_title,
        ]);
    }

    /**
     * 添加数据
     */
    public function virtual_data_add(){

        $id = input('id');
        $type_id = input('type_id');
        
        if(!$type_id) $this->error('参数错误！');

        $key_title = Db::name('virtual_goods')->where('id',$type_id)->value('fields');
        $key_title = unserialize($key_title);
        
        if( Request::instance()->isPost() ){
            $data = input('post.');


            $arr = [];
            foreach ($data['tp_id'] as $index => $type_id) {
                $values = array();
                foreach ($key_title as $key => $name) {
                    $values[$key] = $data['tp_value_' . $key][$index];
                }
                $arr[$index]['type_id'] = $type_id;
                $arr[$index]['pvalue'] = $values['key'];
                $arr[$index]['fields'] = serialize($values);
                $arr[$index]['id'] = $id ? $id : '';
            }

            if($id){
                Db::table('virtual_data')->strict(false)->update($arr[0]);
                //添加操作日志
                slog($id,'edit');
                $this->success('修改成功！',url('goods/virtual_data_list',['type_id'=>$type_id],false));
            }else{
                $ids = Db::table('virtual_data')->strict(false)->insertAll($arr);
                $this->success('添加成功！',url('goods/virtual_data_list',['type_id'=>$type_id],false));
            }
        }

        $info = Db::table('virtual_data')->where('id',$id)->find();
        if($info) $info['fields'] = unserialize($info['fields']);
        
        return $this->fetch('',[
            'meta_title'    =>  '添加数据',
            'key_title'     =>  $key_title,
            'info'          =>  $info,
        ]);
    }

    /**
     * 删除数据
     */
    public function virtual_data_del(){
        if( request()->isAjax()){
            $id = input('id');
            if( Db::table('virtual_data')->where('id','=',$id)->delete()){
                //添加操作日志
                slog($id);
                jason([],'删除数据成功！');
            }else{
                jason([],'删除数据成功！',0);
            }
        }
    }

    /**
     * 虚拟分类列表
     */
    public function virtual_category_list(){

        $where = [];
        $pageParam = ['query' => []];

        $name = input('name');
        if( $name ){
            $where["name"] = ['like', "%{$name}%"];
            $pageParam['query']['name'] = ['like', "%{$name}%"];
        }

        $list = Db::table('virtual_category')->where($where)->paginate(10,false,$pageParam);

        return $this->fetch('',[
            'meta_title'    =>  '虚拟分类列表',
            'list'          =>  $list,
            'name'          =>  $name,
        ]);
    }

    /**
     * 添加|修改 虚拟分类
     */
    public function virtual_category_add(){

        $id = input('id');
        $info = Db::name('virtual_category')->where('id',$id)->find();

        if( Request::instance()->isPost() ){
            $data = input('post.');
            if(!$data['name']){
                $this->error('分类名称不能为空！');
            }
            if($data['id']){
                //添加操作日志
                slog($id,'edit');
                Db::table('virtual_category')->update($data);
                $this->success('修改成功！',url('virtual_category_list'));
            }else{
                $id = Db::table('virtual_category')->insertGetId($data);
                //添加操作日志
                slog($id);
                $this->success('添加成功！',url('virtual_category_list'));
            }
        }

        return $this->fetch('',[
            'meta_title'    =>  '添加虚拟分类',
            'info'          =>  $info,
        ]);
    }

    /**
     * 删除虚拟分类
     */
    public function virtual_category_del(){
        if( request()->isAjax()){
            $id = input('id');
            if( Db::table('virtual_category')->where('id','=',$id)->delete()){
                //添加操作日志
                slog($id);
                jason([],'删除虚拟分类成功！');
            }else{
                jason([],'删除虚拟分类失败！',0);
            }
        }
    }

    /**
     * 商品评论列表
     */
    public function comment_list(){
        $goods_id = input('goods_id');

        $where['goods_id'] = $goods_id;
        $pageParam['query']['goods_id'] = $goods_id;
        

        $list = Db::table('goods_comment')->alias('gc')
                ->join('member m','m.id=gc.user_id','LEFT')
                ->field('gc.*,m.mobile')
                ->where($where)
                ->paginate(10,false,$pageParam);


        return $this->fetch('',[
            'list'          =>  $list,
            'meta_title'    =>  '商品评论列表',
        ]);
    }

    /**
     * 删除商品评论
     */
    public function comment_del(){
        $goods_id = input('goods_id');

        if( Db::table('goods_comment')->where('id','=',$id)->delete()){
            //添加操作日志
            slog($id);
            jason([],'删除成功！');
        }else{
            jason([],'删除失败！',0);
        }
    }

    /**
     * 商品评论回复
     */
    public function comment_replies(){
        $id = input('id');

        if(!$id) $this->error('参数错误！');

        $info = Db::table('goods_comment')->find($id);

        if( request()->isPost() ){
            $data = input('post.');

            if( Db::table('goods_comment')->update($data) !== false ){
                slog($id);
                $this->success('修改成功！',url('goods/comment_list',['goods_id'=>$info['goods_id']],false));
            }
            $this->error('修改失败！');
        }

        return $this->fetch('',[
            'info'          =>  $info,
            'meta_title'    =>  '商品评论回复',
        ]);
    }


    /**
     * 升级PULS会员商品
     */
//    public function puls_goods_list () {
//        $name = request()->param('name');
//        $where = [];
//        if (!empty($name)){
//            $where['b.goods_name'] = ['like',"%$name%"];
//        }
//        $where['a.status'] = ['>',-1];
//        $field = 'a.*,b.goods_name,b.price,b.limited_start,b.limited_end,b.stock,b.is_show,b.is_del';
//        $list = model('PulsGoods')->alias('a')
//            ->join('goods b','a.goods_id = b.goods_id','left')
//            ->where($where)
//            ->order('a.id desc')
//            ->field($field)
//            ->paginate(15,'',['query'=>request()->param()]);
//        $this->assign('list',$list);
//        $this->assign('name',$name);
//        $this->assign('meta_title','升级PULS会员商品列表');
//        return $this->fetch('goods/puls_goods_list');
//
//    }
//
//    /**
//     * 添加升级PULS会员商品
//     */
//    public function puls_goods_add () {
//        $goods_id = request()->param('goods_id',0,'intval');
//        $status = request()->param('status',0,'intval');
//        if (request()->isPost()){
//            if (empty($goods_id)){
//                $this->error('请选择商品',url('puls_goods_add'));
//            }
//            $insert = model('PulsGoods')->insert(['goods_id'=>$goods_id,'status'=>$status]);
//            if ($insert){
//                $this->success('添加成功！',url('goods/puls_goods_list'));
//            }else{
//                $this->error('添加失败！');
//            }
//        }
//
//        return $this->fetch('goods/puls_goods_add');
//    }
////dddd
//    /**
//     *AJAX搜索商品
//     */
//    public function search_goods () {
//        $name = request()->param('name','');
//        $where = [];
//        if (!empty($name)){
//            $id_arr = model('PulsGoods')->where(['status'=>['>',-1]])->column('goods_id');
//            $where['goods_id'] = ['notin',$id_arr];
//            $where['goods_name'] = ["like","%$name%"];
//            $list = model('Goods')->where($where)->field('goods_id,goods_name')->select();
//            return json(['code'=>1,'msg'=>'','data'=>$list]);
//        }else{
//            return json(['code'=>0,'msg'=>'没有商品名称','data'=>[]]);
//        }
//
//
//    }
//
//    /**
//     * 修改升级PULS会员商品状态
//     */
//    public function puls_goods_update () {
//        $id = request()->param('id',0,'intval');
//        $status = request()->param('status',0,'intval');
//        if (empty($id)){
//            return json(['code'=>0,'msg'=>'id不存在！','data'=>[]]);
//        }
//        $update = model('PulsGoods')->update(['id'=>$id,'status'=>$status]);
//        if ($update){
//            return json(['code'=>1,'msg'=>'操作成功！','data'=>[]]);
//        }else{
//            return json(['code'=>0,'msg'=>'操作失败！','data'=>[]]);
//
//        }
//    }

}
