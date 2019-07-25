<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/4/22
 * Time: 18:14
 */

namespace app\api\validate;

use think\Db;
use think\Validate;

class User extends Validate
{
    protected $rule = [
        'name' => 'require|chs|length:2,8',
        'gender' => 'require|number|in:1,2',
        'birth_year' => 'require|dateFormat:Y',
        'birth_month' => 'require|dateFormat:m',
        'birth_day' => 'require|dateFormat:d',
        'school' => 'require|chs|length:4,30',
        'school_type' => 'require',
        'graduate_year' => 'require|dateFormat:Y',
        'graduate_month' => 'require|dateFormat:m',
        'graduate_day' => 'require|dateFormat:d',
        'idcard_front' => 'require',
        'idcard_back' => 'require',

        'nation' => 'require|chs|length:1,6',
        'age' => 'require|number',
        'job_type' => 'require|number',
        'work_age' => 'require',
        'daogang_time' => 'require',
        'salary' => 'require|number',
        'experience' => 'require',
        'education' => 'require',
        'person_desc' => 'require',

        'contacts' => 'require|chs|length:2,8',
        'mobile' => 'require|checkMobile',
        'telephone' => 'checkTel',
        'district' => 'require|checkDistinct',
        'type' => 'require',
        'company_name' => 'require|chs|length:4,40',
        'desc' => 'require',
        'c_img' => 'require',

        'open_year' => 'require|dateFormat:Y',
        'open_month' => 'require|dateFormat:m',
        'open_day' => 'require|dateFormat:d',
        'contacts_scale' => 'require|number',
        'achievement' => 'require',
        'introduction' => 'require',
    ];

    protected $message = [
        'name.require' => '请填写姓名',
        'name.chs' => '姓名必须是中文',
        'name.length' => '姓名长度2-8',
        'gender.require' => '请选择性别',
        'gender.number' => '请选择正确的性别',
        'gender.in' => '请选择正确的性别',
        'birth_year.require' => '请选择出生日期',
        'birth_year.dateFormat' => '请选择正确的出生日期',
        'birth_month.require' => '请选择出生日期',
        'birth_month.dateFormat' => '请选择正确的出生日期',
        'birth_day.require' => '请选择毕业时间',
        'birth_day.dateFormat' => '请选择正确的出生日期',
        'school.require' => '请填写毕业学校',
        'school.chs' => '毕业学校必须是中文',
        'school.length' => '毕业学校长度4-30',
        'school_type.require' => '请选择学校类型',
        'graduate_year.require' => '请选择毕业时间',
        'graduate_year.dateFormat' => '请选择正确的毕业时间',
        'graduate_month.require' => '请选择毕业时间',
        'graduate_month.dateFormat' => '请选择正确的毕业时间',
        'graduate_day.require' => '请选择毕业时间',
        'graduate_day.dateFormat' => '请选择正确的毕业时间',
        'idcard_front.require' => '请上传身份证正面',
        'idcard_back.require' => '请上传身份证反面',

        'nation.require' => '请填写民族',
        'nation.chs' => '民族必须是中文',
        'nation.length' => '民族长度1-6',
        'job_type.require' => '请选择求职类型',
        'age.require' => '请填写年龄',
        'age.number' => '年龄必须是数字',
        'work_age.require' => '请填写工龄',
        'daogang_time.require' => '请选择到岗时间',
        'salary.require' => '请填写薪资要求',
        'salary.number' => '薪资要求必须数字',
        'experience.require' => '请填写工作经历',
        'education.require' => '请填写教育经历',
        'person_desc.require' => '请填写个人说明',


        'contacts.require' => '请填写联系人',
        'contacts.chs' => '联系人必须是中文',
        'contacts.length' => '联系人字符2-8',
        'mobile.require' => '请填写手机号',
        'mobile.checkMobile' => '请填写正确的手机号',
        'telephone.checkTel' => '请填写正确的固定电话',
        'district.require' => '请选择公司地区',
        'district.checkDistinct' => '请选择正确的公司地区',
        'company_name.require' => '请填写公司名称',
        'company_name.chs' => '公司名称必须是中文',
        'company_name.length' => '公司名称长度4-40',
        'type.require' => '请选择公司类型',
        'type.number' => '请选择正确的公司类型',
        'desc.require' => '请填写公司介绍',
        'c_img.require' => '请上传营业执照',

        'open_year.require' => '请选择成立时间',
        'open_year.dateFormat' => '请选择正确的成立时间',
        'open_month.require' => '请选择成立时间',
        'open_month.dateFormat' => '请选择正确的成立时间',
        'open_day.require' => '请选择成立时间',
        'open_day.dateFormat' => '请选择正确的成立时间',
        'contacts_scale.require' => '请填写公司规模',
        'contacts_scale.number' => '公司规模必须为数字',
        'achievement.require' => '请填写成就',
        'introduction.require' => '请填写名人介绍',

    ];

    protected $scene = [
        'person' => ['name', 'gender', 'birth_year', 'birth_month', 'birth_day', 'graduate_year', 'graduate_month', 'graduate_day', 'school', 'school_type', 'idcard_front', 'idcard_back', 'job_type'],
        'company' => ['contacts', 'mobile', 'telephone', 'district', 'company_name', 'type', 'desc', 'c_img'],
        'person_edit' => ['name', 'gender', 'age', 'nation', 'job_type', 'work_age', 'daogang_time', 'salary', 'experience', 'education', 'person_desc'],
        'company_edit' => ['open_year', 'open_month', 'open_day', 'company_name', 'type', 'desc', 'contacts_scale', 'achievement', 'introduction'],
    ];

    protected function checkMobile($value)
    {
        return checkMobile($value) ? true : false;
    }

    protected function checkTel($value)
    {
        return $value ? isTel($value) : true;
    }

    protected function checkDistinct($value)
    {
        return Db::name('region')->where(['code' => $value, 'area_type' => 3])->value('area_id') > 0 ? true : false;
    }


}