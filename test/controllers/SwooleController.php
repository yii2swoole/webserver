<?php

namespace yii2swoole\test\controllers;

use yii\db\Exception;
use yii\db\Query;
use yii\log\Logger;
use yii\web\Controller;

/**
 * Class SwooleController
 * @package yii2swoole\test\controllers
 */
class SwooleController  extends Controller
{

    public function actionIndex()
    {
        //print_ln(\Yii::$app->swooleServer);
//        \Yii::$app->session->set('test','test');
//        \Yii::$app->session->setFlash('success','test');
//        \Yii::error('我的快乐');
//        $db = \Yii::$app->db;
//        $transaction = $db->beginTransaction();
//        try {
//            $db->createCommand('UPDATE `admin_user` set `auth_key`=:authKey WHERE `id`=1',[
//                ':authKey' => time()
//            ])->execute();
//            $transaction->commit();
//        }catch (\Exception $e){
//            $transaction->rollBack();
//            print_ln($e->getMessage());
//        }
        return $this->render('index',['name'=>'张三','age'=>18]);
    }

    public function actionIndex2()
    {
        print_ln(\Yii::$app->session->get('test'));
        return $this->asJson(['name'=>'张三','age'=>18]);
    }

}