<?php
namespace yii2swoole\webserver\log;

use Yii;
use yii\helpers\ArrayHelper;

trait TraitTarget
{
    /**
     * getContextMessage
     * @return string
     */
    protected function getContextMessage()
    {
        if(empty($this->logVars)){
            return '';
        }

        $context = [];

        foreach ($this->logVars as $logVar){
            $logVar = strtolower(ltrim($logVar,'_'));
            $context[$logVar] = Yii::$app->request->swooleRequest->{$logVar}??[];
        }

        foreach ($this->maskVars as $var) {
            if (ArrayHelper::getValue($context, $var) !== null) {
                ArrayHelper::setValue($context, $var, '***');
            }
        }

        $result = [];

        foreach ($context as $key => $value) {
            $result[] = "\${$key} = " . var_export($value,1);
        }

        return PHP_EOL.implode("\n\n", $result);
    }
}