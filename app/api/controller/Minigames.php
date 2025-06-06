<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\MinigamesLog;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use app\common\model\Minigames as MinigamesModel;
use app\common\model\MinigamesScore;
use think\facade\Db;

#[Group("minigames")]
class Minigames extends Api
{
    #[Get('list')]
    public function list()
    {
        $token='';
        if($this->auth->nickname!='微信用户'){
            $token=$this->auth->getToken();
        }
        $list=MinigamesModel::select();
        foreach ($list as $k=>$v){
            $score=MinigamesScore::where(['game_id'=>$v->id])->limit(26)->order('score desc')->select();
            foreach ($score as $k1=>$v1){
                $score[$k1]['score']=$score[$k1]['score'].$v->unit;
            }
            $list[$k]['score']=$score;
        }
        $this->success('',compact('list','token'));
    }

    #[Post('play')]
    public function play()
    {
        $data=$this->request->post();
        $gameId=$data['gameId'];
        if($data['gameEnd']){
            $log=MinigamesLog::where(['game_id'=>$gameId,'uniqid'=>$data['uniqId']])->find();
            if(!$log || $log->endtime){
                $this->error();
            }
            if(time()-$log->starttime<10){
                $this->error();
            }
            try{
                Db::startTrans();
                $log->endtime=intval($data['gameEnd']);
                $log->score=intval($data['Score']);
                $log->save();
                $count=MinigamesScore::where(['game_id'=>$gameId])->count();
                $ifinsert=false;
                if($count>=26){
                    $games=MinigamesScore::where(['game_id'=>$gameId])->order('score desc')->find();
                    if($games->score<$data['Score']){
                        $games->delete();
                        $ifinsert=true;
                    }
                }else{
                    $ifinsert=true;
                }
                if($ifinsert){
                    (new MinigamesScore())->insert([
                        'game_id'=>$gameId,
                        'score'=>intval($data['Score']),
                        'log_id'=>$log->id,
                        'createtime'=>date('Y-m-d H:i:s',time()),
                        'user_id'=>$this->auth->id,
                        'nickname'=>$this->auth->nickname,
                    ]);
                }
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
            }
            $this->success();
        }else{
            $uniqId=uniqid();
            $insert=[
                'game_id'=>$gameId,
                'user_id'=>$this->auth->id,
                'uniqid'=>$uniqId,
                'starttime'=>intval($data['gameStart']),
            ];
            (new MinigamesLog())->save($insert);
            $this->success('',$uniqId);
        }
    }

    #[Get('checklogin')]
    public function checklogin()
    {
        $this->success();
    }
}
