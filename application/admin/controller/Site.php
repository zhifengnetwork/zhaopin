<?php
namespace app\admin\controller;

use think\Db;
use think\Config;

class Site extends Common
{   
    public function _initialize()
    {   
        parent::_initialize();
        $this->info = Db::table('site')->find();
    }

    public function index()
    {

        if( request()->isPost() ){
            $data = input('post.');

            if( isset($data['logo']) ) $data['logo'] = $this->base_img($data['logo'],'site','logo',$this->info['logo']);

            if( isset($data['logo_mobile']) ) $data['logo_mobile'] = $this->base_img($data['logo_mobile'],'site','logo_mobile',$this->info['logo_mobile']);

            $data['shop_contact'] = serialize($data['shop_contact']);
            $data['noticeset'] = serialize($data['noticeset']);

            
            if($data['id']){
                Db::table('site')->update($data,$data['id']);
            }else{
                Db::table('site')->insert($data);
            }
            $this->success('修改成功!');
        }

        if( $this->info['shop_contact'] ) $this->info['shop_contact'] = unserialize( $this->info['shop_contact'] );
        if( $this->info['noticeset'] ) $this->info['noticeset'] = unserialize( $this->info['noticeset'] );

        return $this->fetch('',[
            'meta_title'    =>  '网站设置',
            'info'  =>  $this->info,
        ]);
    }
}
