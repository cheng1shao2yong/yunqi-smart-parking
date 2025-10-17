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
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingQrcode;
use app\common\model\PlateTemporary;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("xqrcode")]
class Qrcode extends ParkingBase
{

    protected $noNeedRight=['show'];

    public function _initialize()
    {
        parent::_initialize();
    }

    #[Route('GET','index')]
    public function index()
    {
        $row=ParkingQrcode::QRCODE;
        $background=ParkingQrcode::where('parking_id',$this->parking->id)->column('id','name');
        foreach ($row as $k=>$v){
            if($this->parking->pid && $v['name']=='entry'){
                unset($k);
                continue;
            }
            $name=$v['name'];
            $row[$k]['id']=isset($background[$name])?$background[$name]:'';
            $row[$k]['background']=isset($background[$name]);
        }
        $barrier_entry=ParkingBarrier::where(['parking_id'=>$this->parking->id,'barrier_type'=>'entry'])->column('title','virtual_serialno');
        $barrier_exit=ParkingBarrier::where(['parking_id'=>$this->parking->id,'barrier_type'=>'exit'])->column('title','virtual_serialno');
        $this->assign('row',$row);
        $this->assign('barrier_entry',$barrier_entry);
        $this->assign('barrier_exit',$barrier_exit);
        $this->assign('parking_id',$this->parking->id);
        return $this->fetch();
    }

    #[Route('GET','show')]
    public function show()
    {
        $name=$this->request->get('name');
        $serialno=$this->request->get('serialno','');
        $background=$this->request->get('background','');
        $parking_id=$this->request->get('parking_id','');
        $parkingqrcode=ParkingQrcode::with(['parking'])->where(['parking_id'=>$parking_id,'name'=>$name])->find();
        if(!$parkingqrcode){
            $parkingqrcode=new ParkingQrcode();
            $parkingqrcode->name=$name;
            $parkingqrcode->background='';
            $parkingqrcode->parking_id=$parking_id;
        }
        if($background){
            $img=ParkingQrcode::createImage($parkingqrcode,$serialno);
        }else{
            $img=ParkingQrcode::getQrcode($parkingqrcode,$serialno);
        }
        Header("Content-type: image/png");
        echo $img;
        exit;
    }

    #[Route('GET','download')]
    public function download()
    {
        $name=$this->request->get('name');
        $size=$this->request->get('size/d');
        $serialno=$this->request->get('serialno','');
        $parkingqrcode=ParkingQrcode::with(['parking'])->where(['parking_id'=>$this->parking->id,'name'=>$name])->find();
        if(!$parkingqrcode){
            $parkingqrcode=new ParkingQrcode();
            $parkingqrcode->name=$name;
            $parkingqrcode->background='';
            $parkingqrcode->parking_id=$this->parking->id;
        }
        //图片文件内容
        $imgContent=ParkingQrcode::createImage($parkingqrcode,$serialno,$size);
        //下载图片
        $filename=date('YmdHis').'.png';
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Type: Image/png");
        echo $imgContent;
        exit;
    }

    #[Route('POST,GET','edit')]
    public function edit()
    {
        if(false==$this->request->isPost()){
            $name=$this->request->get('name');
            $qrcode=ParkingQrcode::where(['parking_id'=>$this->parking->id,'name'=>$name])->find();
            if(!$qrcode){
                $qrcode=new ParkingQrcode();
                $qrcode->name=$name;
                $qrcode->size=150;
                $qrcode->left=0;
                $qrcode->top=0;
                $qrcode->background='';
                $arr=[
                    'title'=>'',
                    'color'=>'#000000',
                    'size'=>30,
                    'left'=>0,
                    'top'=>0
                ];
                $qrcode->text=json_encode($arr);
            }
            $url=$this->request->domain().'/'.'xqrcode/show?parking_id='.$this->parking->id.'&name='.$name;
            $this->assign('url',$url);
            $this->assign('qrcode',$qrcode);
            return $this->fetch();
        }
        $data=$this->request->post();
        $qrcode=ParkingQrcode::where(['parking_id'=>$this->parking->id,'name'=>$data['name']])->find();
        if(!$qrcode){
            $qrcode=new ParkingQrcode();
            $data['parking_id']=$this->parking->id;
        }
        $data['text']=json_encode($data['text'],JSON_UNESCAPED_UNICODE);
        $qrcode->save($data);
        $this->success();
    }

    #[Route('POST','del')]
    public function del()
    {
        $ids=$this->request->post('ids');
        ParkingQrcode::where(['parking_id'=>$this->parking->id,'id'=>$ids])->delete();
        $this->success();
    }
}
