<?php

namespace yii2swoole\test\models;

use yii\base\Component;
use yii\web\IdentityInterface;

/**
 * Class User
 * @package yii2swoole\test
 */
class User  extends Component implements IdentityInterface
{
    public $id;
    public $name;

    public static function findIdentity($id)
    {
        if($id == 1){
            return self::testUser();
        }
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        if($token == 'test'){
            return self::testUser();
        }
        return null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        // TODO: Implement getAuthKey() method.
    }

    public function validateAuthKey($authKey)
    {
        // TODO: Implement validateAuthKey() method.
    }

    private static function testUser()
    {
        return new self([
            'id'=> 1,
            'name'=>'test',
        ]);
    }
}