<?php
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use yii\helpers\Html;

/* @var $this yii\web\View */
$this->title = $bot->name . ' (список алгоритмов)';
$this->params['breadcrumbs'][] = $this->title;
?>
<h1><?= $this->title ?></h1>
<br>
<table class="table table-bordered table-hover">
    <thead>
    <tr>
        <th>Название</th>
        <th>ID алгоритма</th>
        <th>ID Контекста</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($bot->algorithms as $algorithm): ?>
        <tr>
            <td><?= $algorithm->name ?></td>
            <td><?= $algorithm->algorithm_id ?></td>
            <td><?= $algorithm->context_id ?></td>
            <td>
                <form action="<?= Url::to(['/bots/admin/telegram/algorithm-delete', 'id' => $bot->id]) ?>" method="post" class="form-inline">
                    <button class="btn btn-xs btn-danger" type="submit" onclick="return window.confirm('Удалить алгоритм?');"><i class="glyphicon glyphicon-remove" ></i></button>
                    <input type="hidden" name="algorithm_id" value="<?= $algorithm->algorithm_id ?>" />
                    <input type="hidden" name="context_id" value="<?= $algorithm->context_id ?>" />
                    <input type="hidden" name="_csrf" value="<?= Yii::$app->request->getCsrfToken() ?>" />
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<br>

<?php $form = ActiveForm::begin([
    'id' => 'algorithm-form',
    'options' => ['class' => 'form-inline']
]); ?>
<?= $form->field($model, 'name') ?>&nbsp;&nbsp;
<?= $form->field($model, 'algorithm_id') ?>&nbsp;&nbsp;
<?= $form->field($model, 'context_id') ?>&nbsp;&nbsp;

<div class="form-group" style="vertical-align: top;"><?= Html::submitButton(
    'Добавить',
    ['class' => 'btn btn-primary']
) ?></div>
<br>
<?php if (count($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= $error[0] ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<br>
<a href="<?= Url::to(['/bots/admin/telegram/index']) ?>" class="btn btn-default">Вернуться к списку</a>




<?php ActiveForm::end(); ?>
