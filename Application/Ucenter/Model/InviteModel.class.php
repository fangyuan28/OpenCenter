<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-3-24
 * Time: 下午5:04
 * @author 郑钟良<zzl@ourstu.com>
 */

namespace Ucenter\Model;


use Think\Model;

class InviteModel extends Model
{
    protected $_auto = array(
        array('already_num', '0', self::MODEL_INSERT),
        array('create_time', NOW_TIME, self::MODEL_INSERT),
        array('status', '1', self::MODEL_BOTH),
    );

    /**
     * 管理员后台生成邀请码
     * @param array $data
     * @param int $num
     * @return bool|string
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function createCodeAdmin($data=array(),$num=1){
        $map['status']=1;
        $map['id']=$data['invite_type'];
        $invite_type=D('Ucenter/InviteType')->getSimpleList($map,'length,time');
        $data['end_time']=unitTime_to_time($invite_type[0]['time'],'+');
        $data['uid']=-is_login();//管理员后台生成，以负数uid标记

        $dataList=array();
        do{
            $dataList[]=$this->createOneCode($data,$invite_type[0]['length']);
        }while(count($dataList)<$num);
        $res=$this->addAll($dataList);
        if($res){
            $result['status']=1;
            $result['url']=U('Admin/Invite/invite',array('status'=>1,'buyer'=>-1));
        }else{
            $result['status']=0;
            $result['info']="生成邀请码时失败！".$this->getError();
        }
        return $result;
    }

    /**
     * 用户前台生成邀请码
     * @param array $data
     * @param int $num
     * @return mixed
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function createCodeUser($data=array(),$num=1)
    {
        $map['status']=1;
        $map['id']=$data['invite_type'];
        $invite_type=D('Ucenter/InviteType')->getSimpleList($map,'length,time');
        $data['end_time']=unitTime_to_time($invite_type[0]['time'],'+');
        $data['uid']=is_login();//用户前台生成，以正数uid标记

        $dataList=array();
        do{
            $dataList[]=$this->createOneCode($data,$invite_type[0]['length']);
        }while(count($dataList)<$num);
        $res=$this->addAll($dataList);
        if($res){
            $result['status']=1;
            $result['url']=U('Ucenter/Invite/invite');
        }else{
            $result['status']=0;
            $result['info']="生成邀请码时失败！".$this->getError();
        }
        return $result;
    }

    public function getList($map=array(),$page=1,&$totalCount,$r=20,$order='id desc')
    {
        $totalCount=$this->where($map)->count();
        if($totalCount){
            $dataList=$this->where($map)->page($page,$r)->order($order)->select();
            return $this->_initSelectData($dataList);
        }
        return null;
    }

    public function backCode($id=0)
    {
        $result=$this->where(array('id'=>$id))->setField('status',2);
        if($result){
            $invite=$this->where(array('id'=>$id))->find();
            $num=$invite['can_num']-$invite['already_num'];
            if($num>0){
                $map['invite_type']=$invite['invite_type'];
                $map['uid']=$invite['uid'];
                D('InviteUserInfo')->where($map)->setDec('already_num',$num);
                D('InviteUserInfo')->where($map)->setInc('num',$num);
            }
        }
        return $result;
    }

    public function getByCode($code='')
    {
        $map['code']=$code;
        $map['status']=1;
        $data=$this->where($map)->find();
        if($data){
            $data['user']=query_user(array('uid','nickname'),abs($data['uid']));
            return $data;
        }
        return null;
    }

    private function _initSelectData($dataList=array())
    {
        $invite_type_id=array_column($dataList,'invite_type');
        $map['id']=array('in',$invite_type_id);
        $invite_types=D('Ucenter/InviteType')->getSimpleList($map);
        $invite_types=array_combine(array_column($invite_types,'id'),$invite_types);
        foreach($dataList as &$val){
            $val['invite']=$invite_types[$val['invite_type']]['title'];
            $val['code_url']=U('Ucenter/Member/register',array('code'=>$val['code']),true,true);
            if($val['uid']>0){
                $val['buyer']=query_user('nickname',$val['uid']);
            }else{
                $val['buyer']=query_user('nickname',-$val['uid']).'后台生成';
            }
        }
        unset($val);
        return $dataList;
    }


    private function createOneCode($data=array(),$length)
    {
        $length=$length?$length:11;
        do{
            //生成随机数
            $map['code']=create_rand($length);
        }while($this->where($map)->count());
        $data['code']=$map['code'];
        $data=$this->create($data);
        return $data;
    }
} 