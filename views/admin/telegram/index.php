<?php
use yii\helpers\Url;

/* @var $this yii\web\View */
$this->title = 'Список';
$this->params['breadcrumbs'][] = $this->title;
?>

<div align="right">
    <a href="<?= Url::to(['/bots/admin/telegram/add']) ?>" class="btn btn-default"><i class="glyphicon glyphicon-plus"></i>&nbsp;&nbsp;Добавить</a>
</div>
<br>
<div>
        <?php foreach ($bots as $bot): ?>
            <div class="panel panel-default">
                <div class="panel-heading">
                    <?= $bot->name ?>
                    <span class="label label-default pull-right" style="font-size: 14px;">ID: <?= $bot->id ?></span>
                </div>
                <div>
                    <div class="panel-body row">
                        <div class="col-md-10">
                            <p>API Key: <?= $bot->api_key ?></p>
                            <p>Webhook URL:  <a href="<?= $bot->webhookUrl ?>" target="_blank"><?= $bot->webhookUrl ?></a></p>
                            <strong>Алгоритмы</strong>
                            <ul>
                                <?php foreach (
                                    $bot->algorithms
                                    as $algorithm
                                ): ?>
                                    <li><span class="label label-info">ID: <?= $algorithm->algorithm_id ?> CX: <?= $algorithm->context_id ?></span>&nbsp;&nbsp;<?= $algorithm->name ?></li>
                                <?php endforeach; ?>
                                <?php if (!count($bot->algorithms)): ?>
                                    <li style="color: #ff3c00">не заданы</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="col-md-2" align="right">
                            <a href="<?= Url::to(['/bots/admin/telegram/edit', 'id' => $bot->id]) ?>" class="btn btn-default btn-sm" style="width: 80%; text-align: left;"><i class="glyphicon glyphicon-edit"></i>&nbsp;&nbsp;Редактировать</a><br>
                            <a href="<?= Url::to(['/bots/admin/telegram/algorithm-list', 'id' => $bot->id]) ?>" class="btn btn-default btn-sm" style="margin-top: 5px; width: 80%; text-align: left;"><i class="glyphicon glyphicon-list"></i>&nbsp;&nbsp;Алгоритмы</a><br>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

</div>