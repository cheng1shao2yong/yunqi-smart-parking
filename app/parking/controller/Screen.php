<?php
/**
 * ----------------------------------------------------------------------------
 * 行到水穷处，坐看云起时
 * 开发软件，找贵阳云起信息科技，官网地址:https://www.56q7.com/
 * ----------------------------------------------------------------------------
 * Author: 老成
 * email：85556713@qq.com
 */
declare (strict_types = 1);

namespace app\parking\controller;

use app\common\controller\ParkingBase;
use app\common\service\barrier\Utils;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingScreen;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("screen")]
class Screen extends ParkingBase
{
    #[Route('GET','index')]
    public function index()
    {
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id,'pid'=>0])->column('title','id');
        $screen_barrier=ParkingScreen::where(['parking_id'=>$this->parking->id,'admin_id'=>$this->auth->id])->column('barrier_id');
        $screen=ParkingBarrier::with(['fuji'])->whereIn('id',$screen_barrier)->select();
        foreach ($screen as &$item){
            $item['url']=ParkingScreen::getVideoUrl($item,'wide');
            foreach ($item->fuji as &$fuji){
                $fuji['url']=ParkingScreen::getVideoUrl($fuji,'wide');
            }
        }
        $this->assign('barrier',$barrier);
        $this->assign('screen',$screen);
        $this->assign('mqtt',site_config('mqtt'));
        $this->assign('clientId','mqtt-receive-pc-'.$this->parking->id.'-'.$this->auth->id);
        return $this->fetch();
    }

    #[Route('POST','add')]
    public function add()
    {
        $barrier_id=$this->request->post('barrier_id');
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id])->whereIn('id',$barrier_id)->column('id');
        if(empty($barrier)){
            $this->error('通道不存在');
        }
        ParkingScreen::where(['parking_id'=>$this->parking->id,'admin_id'=>$this->auth->id])->delete();
        $insert=[];
        foreach ($barrier as $bid){
            $insert[]=[
                'barrier_id'=>$bid,
                'admin_id'=>$this->auth->id,
                'parking_id'=>$this->parking->id,
            ];
        }
        if(ParkingScreen::insertAll($insert)){
            $this->success('添加成功');
        }else{
            $this->error('添加失败');
        }
    }

    #[Route('POST','open')]
    public function open()
    {
        $barrier_id=$this->request->post('barrier_id');
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id,'id'=>$barrier_id])->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        try{
            ParkingScreen::open($this->parking,$barrier,0,'管理员-'.$this->auth->nickname,'手动开闸');
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        ParkingScreen::sendRedMessage($barrier,'管理员-'.$this->auth->nickname.'手动开闸');
        $this->success('开闸成功');
    }

    #[Route('POST','close')]
    public function close()
    {
        $barrier_id=$this->request->post('barrier_id');
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id,'id'=>$barrier_id])->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        Utils::send($barrier,'关闸',[],function ($result) use ($barrier){
            if($result){
                ParkingScreen::sendRedMessage($barrier,'管理员-'.$this->auth->nickname.'手动关闸');
                $this->success('关闸成功');
            }else{
                $this->error('关闸失败');
            }
        });
    }

    #[Route('POST','photo')]
    public function photo()
    {
        $barrier_id=$this->request->post('barrier_id');
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id,'id'=>$barrier_id])->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        try{
            $photo=Utils::makePhoto($barrier);
            if($photo){
                sleep(1);
                ParkingScreen::sendRedMessage($barrier,'<br><img style="width:95%;" src="'.$photo.'"/>');
            }
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('拍照成功');
    }

    #[Route('POST','trigger')]
    public function trigger()
    {
        $barrier_id=$this->request->post('barrier_id');
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id,'id'=>$barrier_id])->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        try{
            Utils::send($barrier,'主动识别');
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('识别成功');
    }
}
