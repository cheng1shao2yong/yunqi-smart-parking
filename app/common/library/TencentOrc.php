<?php
declare(strict_types=1);

namespace app\common\library;

use TencentCloud\Common\Credential;
use TencentCloud\Tiia\V20190529\Models\CarTagItem;
use TencentCloud\Tiia\V20190529\Models\RecognizeCarProRequest;
use TencentCloud\Tiia\V20190529\TiiaClient;

class TencentOrc
{
    private TiiaClient $client;
    private static TencentOrc $instance;
    private function __construct()
    {
        $this->instance=new self();
        $secret_id=site_config("addons.tencent_orc_secret_id");
        $secret_key=site_config("addons.tencent_orc_secret_key");
        $cred = new Credential($secret_id,$secret_key);
        $this->client = new TiiaClient($cred, "ap-chengdu");
    }

    public static function getInstance()
    {
        if(!self::$instance){
            self::$instance=new self();
        }
        return self::$instance;
    }


    /**
     * 车牌识别
     * @param string $photo
     * @return array
     */
    public function checkPlate(string $photo)
    {
        $req = new RecognizeCarProRequest();
        $req->setImageUrl($photo);
        try{
            $resp = $this->client->RecognizeCarPro($req);
        }catch (\Exception $e){
            return [false,'',''];
        }
        $tags=$resp->getCarTags();
        if(empty($tags)){
            return [false,'',''];
        }
        /* @var CarTagItem $item */
        $item=$tags[0];
        $plateContent=$item->getPlateContent();
        $plate_number=$plateContent->getPlate();
        $color=[
            '蓝色'=>'blue',
            '绿色'=>'green',
            '黄色'=>'yellow',
            '黑色'=>'black',
            '白色'=>'white'
        ];
        if($plate_number){
            $plate_color=$plateContent->getColor();
            $plate_type=isset($color[$plate_color])?$color[$plate_color]:'blue';
            return [true,$plate_number,$plate_type];
        }
        return [true,'',''];
    }
}