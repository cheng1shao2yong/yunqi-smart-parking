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
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrierTjtc;
use app\common\service\barrier\Utils;
use app\parking\traits\Actions;
use app\common\library\Tree;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingScreen;
use app\common\model\parking\ParkingSetting;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\facade\Cache;
use think\facade\Db;

#[Group("barrier")]
class Barrier extends ParkingBase
{
    use Actions{
        add as _add;
        edit as _edit;
        multi as _multi;
        del as _del;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingBarrier();
        $this->assign('plate_type',ParkingMode::PLATETYPE);
        $this->assign('camera',ParkingBarrier::CAMERA);
        $this->assign('rules_type',['provisional'=>'临时车','unprovisional'=>'非临时车']);
        $this->assign('rules',ParkingRules::where(['parking_id'=>$this->parking->id])->where('rules_type','<>','provisional')->column('title','id'));
    }

    #[Route('GET','get-control')]
    public function getControl()
    {
        $barrier_id=$this->request->get('barrier_id');
        $barrier=ParkingBarrier::where(['id'=>$barrier_id,'parking_id'=>$this->parking->id])->find();
        if(!$barrier){
            $this->error('数据不存在');
        }
        try{
            $url=ParkingScreen::getBarrierControl($barrier);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('',$url);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        $where[]=['pid','=',0];
        if($this->request->post('selectpage')){
            return $this->selectpage($where);
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->order('id asc')
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $rows1=collect($list->items())->toArray();
        $total=$list->total();
        $ids=[];
        foreach ($rows1 as $v){
            $ids[]=$v['id'];
        }
        $rows2=ParkingBarrier::whereIn('pid',$ids)->select()->toArray();
        $tree = Tree::instance();
        $tree->init(array_merge($rows1,$rows2), 'pid');
        $list = $tree->getTreeList($tree->getTreeArray(0));
        $result = ['total' => $total, 'rows' => $list];
        return json($result);
    }

    #[Route('GET,POST','tjtc')]
    public function tjtc()
    {
        if($this->request->isPost()){
            $data=$this->request->post('value',[]);
            $times=$this->request->post('times',[]);
            foreach ($times as $time){
                if(!$time || $time<0){
                    $this->error('请输入正确的时间');
                }
            }
            Db::startTrans();
            try{
                $serialno=[];
                $insert=[];
                foreach ($data as $k=>$v){
                    $serialno=array_merge($serialno,$v);
                    $insert[$k]['parking_id']=$this->parking->id;
                    $insert[$k]['times']=$times[$k];
                    $insert[$k]['serialno']=implode(',',$v);
                }
                ParkingBarrierTjtc::where(['parking_id'=>$this->parking->id])->delete();
                if(count($data)>0){
                    (new ParkingBarrierTjtc())->insertAll($insert);
                }
                ParkingBarrier::whereIn('serialno',$serialno)->where('parking_id',$this->parking->id)->update(['tjtc'=>1]);
                Cache::delete('barrier_tjtc_'.$this->parking->id);
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success();
        }
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id,'pid'=>0])->field('serialno as `key`,title as `label`,barrier_type,tjtc')->select();
        $value=[];
        $times=[];
        $tjtc=ParkingBarrierTjtc::where('parking_id',$this->parking->id)->select();
        foreach ($tjtc as $k=>$v){
            $value[$k]=explode(',',$v->serialno);
            $times[$k]=$v->times;
        }
        $this->assign('value',empty($value)?null:$value);
        $this->assign('times',empty($times)?null:$times);
        $this->assign('barrier',$barrier);
        return $this->fetch();
    }

    #[Route('POST,GET','multi')]
    public function multi()
    {
        $postdata=$this->request->post();
        $barrier=ParkingBarrier::where(['id'=>$postdata['ids'][0],'parking_id'=>$this->parking->id])->find();
        $status=$postdata['value'];
        if(!$barrier->serialno){
            $this->error('请先设置设备序列号');
        }
        if($status=='normal'){
            $row=ParkingBarrier::where(['serialno'=>$barrier->serialno,'status'=>'normal'])->find();
            if($row && $row->barrier_type==$barrier->barrier_type){
                $this->error('同种类型的序列号已存在');
            }
            if($row){
                $parking=Parking::find($row->parking_id);
                if($row->parking_id!=$this->parking->pid && $parking->pid!=$this->parking->id){
                    $this->error('仅允许场内场添加相同序列号的通道');
                }
                $barrier->trigger_type=$this->parking->pid?'inside':'outside';
                $row->trigger_type=$this->parking->pid?'outside':'inside';
                $barrier->save();
                $row->save();
            }
            $mqttadd=Cache::get('mqtt_barrier_add');
            if(!$mqttadd){
                $mqttadd=[$barrier->serialno];
            }else{
                $mqttadd[]=$barrier->serialno;
            }
            Cache::set('mqtt_barrier_add',$mqttadd);
            $mqttalive=Cache::get('mqtt_barrier_alive');
            if(!$mqttalive){
                $mqttalive=[$barrier->serialno];
            }else{
                $mqttalive[]=$barrier->serialno;
            }
            Cache::set('mqtt_barrier_alive',$mqttalive);
            try {
                ParkingScreen::bindBarrier($barrier);
            }catch (\Exception $e){

            }
        }
        $barrier->status=$status;
        $barrier->save();
        $this->success('操作成功');
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        $ids=$this->request->get('ids');
        $row=ParkingBarrier::where(['id'=>$ids,'parking_id'=>$this->parking->id])->find();
        if($this->request->isPost()){
            $monthly_screen=$this->request->post('row.monthly_screen');
            if($monthly_screen!='last'){
                $this->postParams['monthly_screen_day']=null;
            }
            $monthly_voice=$this->request->post('row.monthly_voice');
            if($monthly_voice!='last'){
                $this->postParams['monthly_voice_day']=null;
            }
            $this->postParams['serialno']=trim($this->request->post('row.serialno'));
            $this->postParams['status']='hidden';
            $pid=$this->request->post('row.pid');
            if($pid){
                $parent=ParkingBarrier::find($pid);
                $this->postParams['barrier_type']=$parent->barrier_type;
                $this->postParams['title']=$parent->title.'-辅机';
            }
        }
        if(!$this->request->isPost() && $row->pid){
            $parent=ParkingBarrier::where(['parking_id'=>$this->parking->id,'pid'=>0])->column('title','id');
            $this->assign('list',$parent);
            $this->assign('fuji',1);
        }
        return $this->_edit($row);
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $this->postParams['parking_id']=$this->parking->id;
            $this->postParams['serialno']=trim($this->request->post('row.serialno'));
            $monthly_screen=$this->request->post('row.monthly_screen');
            if($monthly_screen!='last'){
                $this->postParams['monthly_screen_day']=null;
            }
            $monthly_voice=$this->request->post('row.monthly_voice');
            if($monthly_voice!='last'){
                $this->postParams['monthly_voice_day']=null;
            }
            $this->postParams['status']='hidden';
            $pid=$this->request->post('row.pid');
            if($pid){
                $parent=ParkingBarrier::find($pid);
                $this->postParams['barrier_type']=$parent->barrier_type;
                $this->postParams['title']=$parent->title.'-辅机';
            }
        }
        $fuji=$this->request->get('fuji');
        if($fuji){
            $parent=ParkingBarrier::where(['parking_id'=>$this->parking->id,'pid'=>0])->column('title','id');
            $this->assign('list',$parent);
            $this->assign('fuji',$fuji);
        }
        return $this->_add();
    }

    #[Route('GET,POST','screen')]
    public function screen()
    {
        if($this->request->isPost()){
            $postdata=$this->request->post();
            if($postdata['type']=='screen'){
                $text=array_filter(explode("\n", str_replace("\r\n", "\n", $postdata['text'])));
                if(count($text)!=4){
                    $this->error('广告内容必须有4行');
                }
                $barrier=ParkingBarrier::where(['id'=>$postdata['id'],'parking_id'=>$this->parking->id])->find();
                $show_last_space=false;
                foreach ($text as $k=>$t){
                    if(strpos($t,'{剩余车位}')!==false){
                        $barrier->show_last_space=json_encode([
                            'line'=>$k,
                            'text'=>$t
                        ],JSON_UNESCAPED_UNICODE);
                        $barrier->save();
                        $show_last_space=true;
                    }
                    $parking_space_entry=ParkingRecords::where(['parking_id'=>$this->parking->id])->whereIn('status',[0,1])->count();
                    $parking_space_total=ParkingSetting::where('parking_id',$this->parking->id)->value('parking_space_total');
                    $last=$parking_space_total-$parking_space_entry;
                    $last=$last>0?$last:0;
                    $t=str_replace('{剩余车位}',(string)$last,$t);
                    Utils::send($barrier,'设置广告',[
                        'line'=>$k,
                        'text'=>$t
                    ]);
                }
                if(!$show_last_space){
                    $barrier->show_last_space=null;
                    $barrier->save();
                }
            }
            if($postdata['type']=='voice'){
                $voice=$postdata['voice'];
                $barrier=ParkingBarrier::where(['id'=>$postdata['id'],'parking_id'=>$this->parking->id])->find();
                Utils::send($barrier,'设置音量',[
                    'voice'=>$voice,
                    'step'=>1
                ]);
                Utils::send($barrier,'设置音量',[
                    'step'=>2
                ]);
            }
            if($postdata['type']=='time'){
                $barrier=ParkingBarrier::where(['id'=>$postdata['id'],'parking_id'=>$this->parking->id])->find();
                $time=time();
                Utils::send($barrier,'设置时间',[
                    'time'=>[
                        'year'=>date('Y',$time),
                        'month'=>date('m',$time),
                        'day'=>date('d',$time),
                        'hour'=>date('H',$time),
                        'min'=>date('i',$time),
                        'sec'=>date('s',$time)
                    ],
                ]);
            }
            $this->success('设置成功');
        }
        $this->assign('id',$this->request->get('barrier_id'));
        $this->assign('barrier_type',$this->request->get('barrier_type'));
        $this->assign('time',time());
        return $this->fetch();
    }

    #[Route('GET','online')]
    public function online()
    {
        $ids=$this->request->get('ids');
        $ids=explode(',',$ids);
        $barrierList=ParkingBarrier::whereIn('id',$ids)->where(['status'=>'normal','parking_id'=>$this->parking->id])->select();
        $r=[];
        foreach ($barrierList as $barrier){
            $r[$barrier->serialno]['online']=$barrier->isOnline()?1:0;
            $r[$barrier->serialno]['cloud_online']=0;
            $cloud=ParkingScreen::getVideoInfo($barrier);
            if($cloud){
                $r[$barrier->serialno]['cloud_online']=$cloud['state']?0:1;
            }
            usleep(100000);
        }
        $this->success('',$r);
    }
    #[Route('GET,POST','del')]
    public function del()
    {
        $ids = $this->request->post("ids");
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
        $this->callback=function () use ($barrier){
            ParkingBarrier::where('serialno',$barrier->serialno)->update(['trigger_type'=>'outfield']);
        };
        return $this->_del();
    }
}
