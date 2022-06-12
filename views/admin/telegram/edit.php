<?php
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use yii\helpers\Html;

/* @var $this yii\web\View */
$this->title = 'Редактирование';
$this->params['breadcrumbs'][] = $this->title;
?>
<h1><?= $this->title ?></h1>

<?php $form = ActiveForm::begin([
    'id' => 'bot-form'
]); ?>
    <?= $form->field($model, 'name') ?>
    <?= $form->field($model, 'api_key') ?>
    <?= $form->field($model, 'route') ?>
    <?= $form->field($model, 'hello_message')->textarea(['rows' => 6]) ?>
    <?= $form->field($model, 'invalid_answer_message')->textarea(['rows' => 6]) ?>
    <?= $form->field($model, 'lang')->dropdownList(['ru' => 'Русский', 'en' => 'English'], []) ?>

    <?= $form->field($model, 'settings[restart_button_text]')->textInput()->label('Текст кнопки "Начать сначала"') ?>
    <?= $form->field($model, 'settings[step_back_button_text]')->textInput()->label('Текст кнопки "Шаг назад"') ?>

    <?php // $form->field($model, 'settings[hide_answer_buttons]')->checkbox(['label' => 'Скрывать кнопки после ответа']); ?>

    <div class="row">
        <div class="col-md-8">
            <a href="<?= Url::to(['/bots/admin/telegram/index']) ?>" class="btn btn-default">Вернуться к списку</a>
            <?= Html::submitButton('Сохранить', [
                'class' => 'btn btn-primary'
            ]) ?>
        </div>
        <div class="col-md-4">
            <?php if ($model->api_key): ?>
            <a href="https://api.telegram.org/bot<?= $model->api_key ?>/setWebhook?url=<?= $model->webhookUrl ?>" class="btn btn-warning pull-right" target="_blank">Зарегистрировать URL</a>
            <?php endif; ?>
        </div>
    </div>




<?php ActiveForm::end(); ?>
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
<h3>Регистрация бота в Telegram</h3>
<div class="well">
    <ol>
        <li>Открыть чат с @BotFather</li>
        <li>Ввести команду /newbot</li>
        <li>Ввести название бота (человеческое название, что-то вроде "Экспертные медицинские системы")</li>
        <li>Ввести username бота, по которому его будут находить через поиск. Обязательно на конце вашего юзернейма должно быть слово «bot» или «_bot». (системное название, что-то вроде MedExpertBot)</li>
        <li>После регистрации будет получен HTTP API token</li>
        <li>Заполнить форму выше:
            <ol>
                <li>Имя - можно использовать введенное на шаге 3 (но это не принципиально)</li>
                <li>Api token - использовать полученный при регистрации </li>
                <li>Url - выбрать адрес для интеграции с Telegram (без пробелов, в нижнем регистре - med_expert_bot)</li>
            </ol>
        </li>
        <li>Сохранить</li>
        <li>Нажать кнопку "Зарегистрировать URL"</li>
    </ol>
</div>

