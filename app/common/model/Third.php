<?php

namespace app\common\model;

use app\api\service\ApiAuthService;
use app\common\model\base\ConstTraits;
use think\facade\Db;
use think\facade\Log;
use think\Model;

/**
 * 第三方登录模型
 */
class Third extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [

    ];

    use ConstTraits;

    const PLATFORM = [
        'miniapp' => '微信小程序',
        'mpapp' => '微信公众号',
        'alipay-mini' => '支付宝小程序',
    ];

    public function user()
    {
        return $this->belongsTo('\app\common\model\User', 'user_id', 'id');
    }

    public static function connect($platform, $params = []):self
    {
        $time = time();
        $nickname = $params['nickname']??'用户昵称';
        $mobile = $params['mobile']??'';
        $avatar = $params['avatar']??request()->domain().'/assets/img/avatar.jpg';
        $values = [
            'platform'      => $platform,
            'openid'        => $params['openid'],
            'unionid'       => isset($params['unionid'])?$params['unionid']:null,
            'openname'      => $nickname,
            'avatar'        => $avatar,
            'access_token'  => isset($params['access_token'])?$params['access_token']:null,
            'refresh_token' => isset($params['refresh_token'])?$params['refresh_token']:null,
            'expires_in'    => isset($params['expires_in'])?$params['expires_in']:null,
            'logintime'     => $time,
            'expiretime'    => isset($params['expires_in'])?$time + $params['expires_in']:null,
        ];
        Db::startTrans();
        try {
            //是否有自己的
            $third = self::where(['platform' => $platform, 'openid' => $params['openid']])->with(['user'])->find();
            if($third){
                $third->save($values);
                if($platform!='miniapp'){
                    $third->user->save([
                        'nickname'=>$nickname,
                        'avatar'=>$avatar,
                        'mobile'=>$mobile,
                    ]);
                }
            }else{
                $user_id=0;
                if (isset($params['unionid']) && $params['unionid']) {
                    $nextthird = self::where(['unionid' => $params['unionid']])->with(['user'])->find();
                    if ($nextthird) {
                        $user_id=$nextthird->user_id;
                    }
                }
                if($user_id){
                    if($platform!='miniapp'){
                        User::where('id',$user_id)->update([
                            'nickname'=>$nickname,
                            'avatar'=>$avatar,
                            'mobile'=>$mobile,
                        ]);
                    }
                }else{
                    $user=User::createNewUser('',$nickname,$avatar,'',$mobile);
                    $user_id=$user->id;
                }
                $values['user_id'] = $user_id;
                $third=self::create($values);
            }
            Db::commit();
            return $third;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            throw $e;
        }
    }
}
