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
    <?= $form->field($model, 'confirmation_code') ?>
    <?= $form->field($model, 'route') ?>
    <?= $form->field($model, 'hello_message')->textarea(['rows' => 6]) ?>
    <?= $form->field($model, 'invalid_answer_message')->textarea(['rows' => 6]) ?>
    <?= $form->field($model, 'lang')->dropdownList(['ru' => 'Русский', 'en' => 'English'], []) ?>

    <?= $form->field($model, 'settings[restart_button_text]')->textInput()->label('Текст кнопки "Начать сначала"') ?>
    <?= $form->field($model, 'settings[step_back_button_text]')->textInput()->label('Текст кнопки "Шаг назад"') ?>



<div class="row">
        <div class="col-md-8">
            <a href="<?= Url::to(['/bots/admin/vk/index']) ?>" class="btn btn-default">Вернуться к списку</a>
            <?= Html::submitButton('Сохранить', [
                'class' => 'btn btn-primary'
            ]) ?>
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
<h3>Регистрация бота в Vkcom</h3>
<div class="well">
    <ol>
        <li>Зайти на страницу "Управление" в группе vk.com</li>
        <li>Выбрать пункт "Работа с API"</li>
        <li>Во вкладке "Ключи доступа" нажать на кнопку "Создать ключ"</li>
        <li>Поставить разрешение "доступ к сообщениям сообщества"</li>
        <li>Скопировать ключ доступа в поле API token</li>
        <li>Зайти на вкладку Callback API</li>
        <li>В настройках сервера убедится, что в поле "Версия API" установлено 5.50</li>
        <li>Скопировать значение "Строка, которую должен вернуть сервер" в поле "Code for confirmation"</li>
        <li>Заполнить форму выше:
            <ol>
                <li>Имя - можно использовать введенное на шаге 3 (но это не принципиально)</li>
                <li>Api token - использовать полученный при регистрации ключ доступа</li>
                <li>Code for confirmation - Строка, которую должен вернуть сервер</li>
                <li>Url - выбрать адрес для интеграции с vk.com (без пробелов, в нижнем регистре - med_expert_bot)</li>
            </ol>
        </li>
        <li>Сохранить</li>
        <li>Скопировать Webhook URL созданного бота</li>
        <li>Ввести Webhook URL в поле "Адрес" на странице настройки сервера в группе</li>
        <li>Нажать кнопку "Подтвердить"</li>
        <li>На вкладке "Типы событий" поставить галочки в разделе "Сообщения" (чтобы разрешить боту писать-читать чат) </li>
        <li>В настройках сообщества включить сообщения</li>
        <li>Сообщения->Настройки для бота->Возможности ботов поставить "Возможности ботов: Включены"</li>
        <li>Там же поставить "Добавить кнопку «Начать»"</li>
    </ol>
    После этих действий бот подключается к сообщениям сообщества. Написать ему можно нажав "Написать сообщения" под логотипом сообщества.
</div>

