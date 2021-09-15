<?php
use yii\widgets\Breadcrumbs;
/** @var $this \yii\web\View */
$this->registerJsFile('test.js');
?>
<?= Breadcrumbs::widget([
    'itemTemplate' => "<li><i>{link}</i></li>\n", // template for all links
    'links' => [
        [
            'label' => 'Post Category',
            'url' => ['post-category/view', 'id' => 10],
            'template' => "<li><b>{link}</b></li>\n", // template for this link only
        ],
        ['label' => 'Sample Post', 'url' => ['post/edit', 'id' => 1]],
        'Edit',
    ],
]);?>
<h1><?=\yii\helpers\Html::encode($name);?></h1>
<h1><?=\yii\helpers\Html::encode($age);?></h1>
<h1><?=\yii\helpers\Html::encode(Yii::$app->session->getFlash('success'));?></h1>
