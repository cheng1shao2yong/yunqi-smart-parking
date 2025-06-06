<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Qrcode;
use app\common\model\QrcodeScan;
use app\common\model\Third;
use app\common\service\upload\PrivateUploadService;
use think\annotation\route\Post;
use think\annotation\route\Get;
use think\annotation\route\Group;
use app\common\service\upload\PublicUploadService;

#[Group("common")]
class Common extends Api{
    /**
     * 上传文件
     * @param File $file 文件流
     */
    #[Post('upload')]
    public function upload()
    {
        $file = $this->request->file('file');
        $private = $this->request->get('private');
        try{
            if($private){
                $savename=PrivateUploadService::newInstance([
                    'config'=>config('site.upload'),
                    'user_id'=>$this->auth->id,
                    'file'=>$file
                ])->save();
            }else{
                $savename=PublicUploadService::newInstance([
                    'config'=>config('site.upload'),
                    'user_id'=>$this->auth->id,
                    'file'=>$file
                ])->save();
            }
        }catch (\Exception $e){
            $this->error(__('上传文件出错'),[
                'file'=>$e->getFile(),
                'line'=>$e->getLine(),
                'msg'=>$e->getMessage()
            ]);
        }
        $this->success('',$savename);
    }

    #[Get('area')]
    public function area($pid)
    {
        if(!class_exists('\app\common\model\Area')){
           $this->error('请先安装插件-省份城市地区数据');
        }
        $area=\app\common\model\Area::where('pid',$pid)->field('id,name')->select();
        $this->success('',$area);
    }

    #[Post('plate-number')]
    public function plateNumber()
    {
        $plate_number=$this->request->post('plate_number');
        if(!is_car_license($plate_number)){
            $this->error('车牌号格式错误');
        }
        $this->success();
    }
}