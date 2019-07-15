<?php
namespace app\api\model;
use think\Model;
use think\Db;

class UserAddr extends Model
{
    protected $table = 'user_address';

    public function getAddressList($where = array())
    {   

        $result = $this->alias('ua')
            ->field('ua.address_id,ua.consignee,ua.mobile,ua.address,ua.is_default')
            ->field('p.area_name as p_cn,c.area_name as c_cn,d.area_name as d_cn,s.area_name as s_cn')
            ->join('region p', 'p.area_id = ua.province', 'left')
            ->join('region c', 'c.area_id = ua.city', 'left')
            ->join('region d', 'd.area_id = ua.district', 'left')
            ->join('region s', 's.area_id = ua.twon', 'left')
            ->where($where)
            ->order('ua.is_default desc, ua.address_id asc')
            ->select();
        $result = ota($result);
        return $result;
    }

    public function getAddressFind($where = array())
    {   
        $result = $this->alias('ua')
            ->field('user_id,province,city,district,twon,address,consignee,mobile')
            // ->field('ua.address_id,ua.consignee,ua.mobile,ua.address,ua.is_default')
            // ->field('p.area_name as p_cn,c.area_name as c_cn,d.area_name as d_cn,s.area_name as s_cn')
            // ->join('region p', 'p.area_id = ua.province', 'left')
            // ->join('region c', 'c.area_id = ua.city', 'left')
            // ->join('region d', 'd.area_id = ua.district', 'left')
            // ->join('region s', 's.area_id = ua.twon', 'left')
            ->where($where)
            ->order('ua.is_default desc, ua.address_id asc')
            ->find();
        $result = ota($result);
        return $result;
    }

    /**
     * 地址添加/编辑
     * @param $user_id 用户id
     * @param $user_id 地址id(编辑时需传入)
     * @return array
     */
    public function add_address($user_id,$address_id=0,$data){
        $post = $data;
        if($address_id == 0)
        {
            $c = $this->where(['user_id' => $user_id])->count();
            if($c >= 20)
                return array('status'=>-2,'msg'=>'最多只能添加20个收货地址','data'=>'');
        }
        //检查手机格式
        if($post['consignee'] == '')
            return array('status'=>-2,'msg'=>'收货人不能为空','data'=>'');
        if (!($post['district']>0))
            return array('status'=> -2,'msg'=>'所在地区不能为空','data'=>'');

        $district = Db::name('region')->where(['code' => $post['district']])->find();
        $post['district'] = $district['area_id'];
        $city     = Db::name('region')->where(['code' => $district['parent_id']])->find();
        $post['city']     = $city['area_id'];
        $province         = Db::name('region')->where(['code' => $city['parent_id']])->find();
        $post['province'] = $province['area_id'];


        if(empty($post['address']))
            return array('status'=>-2,'msg'=>'地址不能为空','data'=>'');
        if(!checkMobile($post['mobile']))
            return array('status'=>-2,'msg'=>'手机号码格式有误','data'=>'');
         unset($post['token']);
        //编辑模式
        if($address_id > 0){
            $address = $this->where(array('address_id'=>$address_id,'user_id'=> $user_id))->find();
            if($post['is_default'] == 1 && $address['is_default'] != 1)
                   $this->where(array('user_id'=>$user_id))->update(array('is_default'=>0));
            $row = $this->where(array('address_id'=>$address_id,'user_id'=> $user_id))->update($post);
            if($row !== false){
                return array('status'=>1,'msg'=>'编辑成功','data'=>$address_id);
            }else{
                return array('status'=>-2,'msg'=>'操作失败','data'=>$address_id);
            }

        }
        //添加模式
        $post['user_id'] = $user_id;
        
        // 如果目前只有一个收货地址则改为默认收货地址
        $c = $this->where(['user_id' => $user_id])->count();
        if($c == 0)  $post['is_default'] = 1;
        
        $insert_id = $this->insertGetId($post);
        if(!$insert_id)return array('status'=>-2,'msg'=>'添加失败','data'=>'');
        //如果设为默认地址
        $map['user_id']    = $user_id;
        $map['address_id'] = array('neq',$insert_id);
               
        if($post['is_default'] == 1)$this->where($map)->update(array('is_default'=>0));
        
        return array('status'=>1,'msg'=>'添加成功','data'=>'');
    }
}
