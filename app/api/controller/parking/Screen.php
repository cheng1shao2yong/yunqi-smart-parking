<?php
declare (strict_types = 1);

namespace app\api\controller\parking;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingRecords;
use app\common\service\barrier\Utils;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingScreen;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Cache;
use think\facade\Db;

#[Group("parking/screen")]
class Screen extends Base
{

    #[Get('barrier')]
    public function barrier()
    {
        $barrier_id=$this->request->get('barrier_id');
        if(!$barrier_id){
            $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'pid'=>0,'status'=>'normal'])->select();
            $this->success('',$barrier);
        }else{
            $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'id'=>$barrier_id])->find();
            $barrier['url']=ParkingScreen::getVideoUrl($barrier,'wide');
            $mqtt=site_config('mqtt');
            $clientId='mqtt-receive-h5-'.$this->parking_id.'-'.$this->auth->id;
            $this->success('',compact('barrier','mqtt','clientId'));
        }
    }

    #[Get('online')]
    public function online()
    {
        $ids=$this->request->get('ids');
        $ids=explode(',',$ids);
        $barrierList=ParkingBarrier::whereIn('id',$ids)->select();
        $now=time();
        $r=[];
        foreach ($barrierList as $barrier){
            $updatetime=Cache::get('barrier-online-'.$barrier->serialno);
            if($updatetime && $updatetime>=$now-60){
                $r[$barrier->serialno]['online']=1;
            }else{
                $r[$barrier->serialno]['online']=0;
            }
            $r[$barrier->serialno]['cloud_online']=0;
            $cloud=ParkingScreen::getVideoInfo($barrier);
            if($cloud){
                $r[$barrier->serialno]['cloud']=$cloud['state']?0:1;
            }
            usleep(100000);
        }
        $this->success('',$r);
    }

    #[Post('open')]
    public function open()
    {
        $barrier_id=$this->request->post('barrier_id');
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'id'=>$barrier_id])->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        try{
            Db::startTrans();
            $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
            ParkingScreen::open($parking,$barrier,0,'管理员-'.$this->parkingAdmin['nickname'],'手动开闸');
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        ParkingScreen::sendBlackMessage($barrier,'管理员-'.$this->parkingAdmin['nickname'].'手动开闸');
        $this->success('开闸成功');
    }

    #[Post('close')]
    public function close()
    {
        $barrier_id=$this->request->post('barrier_id');
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'id'=>$barrier_id])->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        Utils::close($barrier,ParkingRecords::RECORDSTYPE('人工确认'),function ($result) use ($barrier){
            if($result){
                ParkingScreen::sendBlackMessage($barrier,'管理员-'.$this->parkingAdmin['nickname'].'手动关闸');
                $this->success('关闸成功');
            }else{
                $this->error('关闸失败');
            }
        });
    }

    #[Post('photo')]
    public function photo()
    {
        $barrier_id=$this->request->post('barrier_id');
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'id'=>$barrier_id])->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        try{
            $photo=Utils::makePhoto($barrier);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('',$photo);
    }
}
