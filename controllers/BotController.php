<?php

namespace wm\bots\controllers;

use Yii;
use yii\web\Controller;
use yii\base\Exception;

use wm\bots\models\Bot;
use wm\bots\models\Chat;
use wm\bots\models\Nodes;
use wm\bots\models\Telegram;
use wm\bots\models\AnswersMult;

use wm\service\models\Client as ServiceClient;

use yii\log\FileTarget;




abstract class BotController extends Controller
{
    const MAX_MESSAGE_LEN = 0;
    const MAX_ANSWER_LEN = 0;

    const MESSENGER_NAME = '';


    public $layout = false;

    public $enableCsrfValidation = false;

    protected $linkDomain = 'riskover.ru';

    protected $controller_name = null;

    protected $showErrors = false;


    protected $replyMessageId;

    protected $logCategory;

    /**
     * @var Bot
     */
    protected $bot = null;

    /**
     * @var Chat
     */
    protected $chat = null;

    // abstract functions
    abstract public function actionAlgorithm(Bot $bot);
    abstract protected function sendMessage($text, $buttons = [], $controlButtons = false, $header = '');
    abstract protected function sendImage($imageURL, $imageFile, $captionHeader = '', $captionText = '',  $buttons = [], $controlButtons = false);
    abstract protected function sendVideoURL($videoURL, $header = '', $text = '',  $buttons = [], $controlButtons = false);
    abstract protected function sendURL($url, $header = '', $text = '',  $buttons = [], $controlButtons = false);
    abstract protected function sendFile($path, $header = '', $text = '',  $buttons = [], $controlButtons = false);
    abstract protected function editMessage($text, $buttons = [], $header = '');
    abstract protected function sendDebugMessage($text);


    abstract protected function startSession(
        $chat_id,
        $algorithm_id,
        $context_id
    );

    /**
     * @param $botId
     * @param $chatId
     * @return Chat
     */
    abstract protected function findChat($botId, $chatId);


    protected $showErrorsText = ['/e'];
    protected $restartText = ['/start', '/restart', '/r'];
    protected $stepBackText = ['/back', '/b'];
    protected $changeAnswerText = ['/qa'];


    protected $controlButtons = [
        ['id' => '/b', 'text' => 'Step back'],
        ['id' => '/r', 'text' => 'Restart'],
    ];

    /**
     * @var Telegram\Api
     */
    protected $telegram = null;


    public function init()
    {
        parent::init();

        /** @var Bot $bot */
        $bot = Yii::$app->request->get('bot');
        $this->setLanguage($bot);

        Yii::$app->errorHandler->errorAction =
            "bot/" . $this->controller_name . '/error';


        $this->initTextButtons($bot);
    }


    protected function setLanguage(Bot $bot) {
        switch ($bot->lang) {
            case 'ru':
                Yii::$app->language = 'ru-RU';
            break;
            case 'en':
                Yii::$app->language = 'en-US';
            break;
        }
    }


    protected function initTextButtons(Bot $bot) {

        if (!empty($bot->stepBackButtonText))
            $this->controlButtons[0]['text'] = $bot->stepBackButtonText;
        else
            $this->controlButtons[0]['text'] = Yii::t('bots', $this->controlButtons[0]['text']);

        if (!empty($bot->restartButtonText))
            $this->controlButtons[1]['text'] = $bot->restartButtonText;
        else
            $this->controlButtons[1]['text'] = Yii::t('bots', $this->controlButtons[1]['text']);


        $this->stepBackText[] =  mb_strtolower($this->controlButtons[0]['text']);
        $this->restartText[] = mb_strtolower($this->controlButtons[1]['text']);

    }

    /**
     * @param Bot $bot
     * @param $chatId
     * @param $conclusionId
     * @return string
     * @throws \Exception
     */
    public function actionConclusion(Bot $bot, $chatId, $conclusionId)
    {
        $chat = $this->findChat($bot->id, $chatId);

        if (!$conclusionId > 0) {
            throw new \Exception("Conclusion id not set");
        }

        $session = $chat->session;

        $answers = $session->getAnswers();
        $infoData = $session->getInfoData();

        $expert = new ServiceClient();
        $response = $expert->conclusionText(
            $session->algorithm_id,
            $session->context_id,
            $session->link_type,
            $answers,
            $infoData,
            $conclusionId,
            $session->algorithm_uid
        );

        $conclusionHeader = $response['conclusion_header'];
        $conclusionText = $response['conclusion'];

        $this->layout = 'advice';

        return $this->render('conclusion', [
            'pageName' => $conclusionHeader,
            'pageDescription' => '',
            'conclusionText' => $conclusionText
        ]);
    }

    /**
     * @param Bot $bot
     * @param $chatId
     * @return string
     * @throws \Exception
     */
    public function actionAutoConclusion(Bot $bot, $chatId)
    {
        $resultText = [
            0 => Yii::t('bots', 'No legal risks identified'),
            1 => Yii::t('bots', 'Transaction cannot be completed'),
            2 => Yii::t('bots', 'High risk'),
            3 => Yii::t('bots', 'Average risk'),
            4 => Yii::t('bots', 'Insignificant risk'),
        ];

        $chat = $this->findChat($bot->id, $chatId);

        $session = $chat->session;

        $answers = $session->getAnswers();
        $infoData = $session->getInfoData();

        $expert = new ServiceClient();

        $response = $expert->conclusion(
            $session->algorithm_id,
            $session->context_id,
            $session->link_type,
            $answers,
            $infoData,
            $session->algorithm_uid
        );

        $riskLevel = (int) $response['riskLevel'];

        $risks = $response['risks'];
        $documents = $response['documents'];
        $wrongDocuments = $response['wrongDocuments'];
        $warnings = $response['warnings'];
        $extraInfo = $response['extraInfo'];

        foreach ($wrongDocuments as &$document) {
            $document['text'] = str_replace(
                array('<p>', '</p>'),
                '',
                $document['text']
            );

            foreach ($document['properties'] as &$property) {
                $property['text'] = str_replace(
                    array('<p>', '</p>'),
                    '',
                    $property['text']
                );
            }
        }

        foreach ($extraInfo as &$info) {
            $info['text'] = strip_tags($info['text'], '<a><b>');
        }

        foreach ($risks as &$risk) {
            $risk['levelText'] = $resultText[(int) $risk['level']];
        }

        $algorithmName = '';

        foreach ($bot->algorithms as $algorithm) {
            if ($algorithm->algorithm_id == $chat->session->algorithm_id) {
                $algorithmName = $algorithm->name;
            }
        }

        $this->layout = 'advice';

        return $this->render('auto-conclusion', [
            'pageName' => '',
            'pageDescription' => $algorithmName,

            'risksCnt' => count($risks),
            'documentsCnt' => count($documents),
            'wrongDocumentsCnt' => count($wrongDocuments),

            'risks' => $risks,
            'documents' => $documents,
            'wrongDocuments' => $wrongDocuments,
            'extraInfo' => $extraInfo,
            'warnings' => $warnings,

            'riskLevel' => $riskLevel,
            'riskLevelText' => $resultText[$riskLevel]
        ]);
    }

    /**
     * @return string
     */
    public function actionInvalid()
    {
        return 'Invalid bot';
    }

    /**
     * @param Bot $bot
     * @return string
     */
    public function actionDebug(Bot $bot)
    {
        return "ok";
    }

    /**
     * @return string
     */
    public function actionError()
    {
        $exception = Yii::$app->getErrorHandler()->exception;
        $message = is_null($exception)
            ? 'Unknown error'
            : $exception->getMessage();

        return 'ERROR: ' . $message;
    }

    /**
     * @param $answerText
     * @param null $buttonData
     * @throws Exception
     */
    protected function runAlgorithm($answerText, $buttonData = [])
    {

        $answerText = mb_strtolower($answerText);

        $bot = $this->bot;
        $chat = $this->chat;


        // Проверка, не пришла ли команда показа ошибки
        if (in_array($answerText, $this->showErrorsText)) {
            $this->showErrors = true;
        }

        // Проверка команды "Начать сначала"
        if (in_array($answerText, $this->restartText)) {
            $chat->reset();
        }

        // Проверка команды "Шаг назад"
        if (in_array($answerText, $this->stepBackText)) {
            if (empty($chat->session_id))
                $chat->reset();
            else
                $chat->setState(Chat::ST_PREV_NODE);
        }

        if (isset($buttonData['algorithmId'])) {
            $chat->setState(Chat::ST_ANSWER_ALGORITHM);
        }

        if (isset($buttonData['questionId'])) {
            // strncmp($buttonData, $this->changeAnswerText[0], 3)==0
            // $parts = explode(' ', $answerText);
            // if (count($parts)!=3)
            //    throw new Exception("Invalid command arguments: {$answerText}");

            $chat->setState(Chat::ST_ANSWER_QUESTION_BY_ID);
        }

        $nodes = new Nodes(
            $this->module->serviceUrl,
            $this->linkDomain,
            $bot,
            $chat,
            $this->controller_name,
            static::MAX_ANSWER_LEN
        );

        do {
            $noAnswer = false;

            switch ($chat->state) {
                case Chat::ST_HELLO_MESSAGE:
                    Yii::debug("State:\n" . Chat::ST_HELLO_MESSAGE, 'bots');

                    $this->sendMessage($bot->hello_message, [], true);
                    $chat->setState(Chat::ST_ALGORITHM_LIST);

                    $noAnswer = true;
                    break;

                case Chat::ST_ALGORITHM_LIST:
                    Yii::debug("State:\n" . Chat::ST_ALGORITHM_LIST, 'bots');

                    /* @todo Сделать защиту от зацикливания когда всего один алгоритм */
                    // Если алгоритм всего один, тогда не выводить список алгоритм, а перейти сразу к ответу
                    if (count($this->bot->algorithms) === 1 && isset($this->bot->algorithms[0]['bot_id'])) {

                        $buttonData = ['algorithmId' => $this->bot->algorithms[0]['id']];
                        $noAnswer = true;
                    } else {
                        $this->sendAlgorithmList();
                    }

                    $chat->setState(Chat::ST_ANSWER_ALGORITHM);
                    break;

                case Chat::ST_ANSWER_ALGORITHM:
                    Yii::debug("State:\n" . Chat::ST_ANSWER_ALGORITHM, 'bots');

                    if (isset($buttonData['algorithmId']) and $this->startAlgorithmSession($buttonData['algorithmId'])) {
                        $chat->setState(Chat::ST_SHOW_NODE);
                    } else {
                        $chat->setState(Chat::ST_ALGORITHM_LIST);
                    }

                    $noAnswer = true;
                    break;

                case Chat::ST_SHOW_NODE:
                    Yii::debug("State:\n" . Chat::ST_SHOW_NODE, 'bots');

                    $nodes->load($chat->session);

                    if ($nodes->getNodeType()==Nodes::ND_CONCLUSION)
                        $nodes->loadConclusion($chat->session);

                    $this->sendNode($nodes);

                    switch ($nodes->getNodeType()) {
                        case Nodes::ND_QUESTION:
                            $chat->setState(Chat::ST_ANSWER_QUESTION);
                            break;

                        case Nodes::ND_COMPLEX_QUESTION:
                            $chat->setState(
                                Chat::ST_ANSWER_COMPLEX_QUESTION
                            );
                            break;

                        case Nodes::ND_INFO_DATA:
                            $chat->setState(Chat::ST_ANSWER_INFO);
                            break;

                        case Nodes::ND_RISK:
                            $chat->setState(Chat::ST_NEXT_NODE);
                            $noAnswer = true;
                            break;

                        case Nodes::ND_MESSAGE:
                            $chat->setState(Chat::ST_NEXT_NODE);
                            $noAnswer = true;
                            break;

                        case Nodes::ND_WARNING:
                            $chat->setState(Chat::ST_NEXT_NODE);
                            $noAnswer = true;
                            break;

                        case Nodes::ND_CONCLUSION:
                            $chat->setState(Chat::ST_NEXT_NODE);
                            $noAnswer = true;
                            break;

                        default:
                            throw new Exception(
                                "Unsupported node type: {$nodes->getNodeType()}"
                            );
                            break;
                    }

                    break;

                case Chat::ST_NEXT_NODE:
                    Yii::debug("State:\n" . Chat::ST_NEXT_NODE, 'bots');

                    $nodes->load($chat->session);

                    if ($nodes->nextNode()) {
                        $chat->session->setNodeIdx($nodes->getNodeIdx());
                        $chat->setState(Chat::ST_SHOW_NODE);

                        $noAnswer = true;
                    } else {
                        if ($chat->session->algorithm_done) {
                            $chat->setState(Chat::ST_ALGORITHM_DONE);
                            $this->sendAlgorithmDone($nodes);

                        } else {
                            throw new Exception("Algorithm ends unexpectedly");
                        }
                    }

                    break;

                case Chat::ST_ALGORITHM_DONE:
                    Yii::debug("State:\n". Chat::ST_ALGORITHM_DONE, 'bots');

                    $nodes->load($chat->session);

                    $this->sendAlgorithmDoneAnswer($bot);

                    break;

                case Chat::ST_ANSWER_QUESTION:
                    Yii::debug("State:\n".Chat::ST_ANSWER_QUESTION,'bots');

                    $nodes->load($chat->session);

                    if (!$nodes->currentNodeIs(Nodes::ND_QUESTION))
                        throw new Exception("Current node is not question");

                    if (isset($buttonData['answerId']))
                        $answerId = (int)$buttonData['answerId'];
                    else {
                        $answerId = $nodes->getNodeAnswers()->getAnswerId($answerText);

                        if (!$this->replyMessageId and $this->chat->last_qst_message_id)
                            $this->replyMessageId = $this->chat->last_qst_message_id;

                    }


                    if ($this->answerQuestion($bot, $answerId, $nodes)) {
                        $chat->setState(Chat::ST_NEXT_NODE);
                        $nodes->load($chat->session, true);
                        $noAnswer = true;
                    } else {
                        $chat->setState(Chat::ST_ANSWER_QUESTION);
                    }

                    break;

                case Chat::ST_ANSWER_COMPLEX_QUESTION:
                    Yii::debug("State:\n".Chat::ST_ANSWER_COMPLEX_QUESTION, 'bots');

                    $nodes->load($chat->session);

                    if (!$nodes->currentNodeIs(Nodes::ND_QUESTION))
                        throw new Exception("Current node is not complex question");


                    /**  @var AnswersMult $nodeAnswers */
                    $nodeAnswers = $nodes->getNodeAnswers();

                    if (isset($buttonData['answerId']))
                        $answerId = (int)$buttonData['answerId'];
                    else {
                        $answerId = $nodeAnswers->getAnswerId($answerText);

                        if (!$this->replyMessageId and $this->chat->last_qst_message_id)
                            $this->replyMessageId = $this->chat->last_qst_message_id;
                    }

                    if ($nodeAnswers->isComplete($answerId)) {
                        $chat->setState(Chat::ST_NEXT_NODE);
                        $noAnswer = true;

                    } else {
                        $this->answerComplexQuestion($bot, $answerId, $nodes);
                        $chat->setState(Chat::ST_ANSWER_COMPLEX_QUESTION);
                        $nodes->load($chat->session, true);
                    }

                    break;

                case Chat::ST_ANSWER_INFO:
                    Yii::debug("State: " . Chat::ST_ANSWER_INFO,'bots');

                    $nodes->load($chat->session);

                    if (!$nodes->currentNodeIs(Nodes::ND_INFO_DATA))
                        throw new Exception("Current node is not complex question");


                    if ($this->answerInfo($nodes, $answerText)) {
                        $chat->setState(Chat::ST_NEXT_NODE);
                        $nodes->load($chat->session, true);
                        $noAnswer = true;
                    } else {
                        $chat->setState(Chat::ST_ANSWER_INFO);
                    }

                    break;

                case Chat::ST_PREV_NODE:
                    Yii::debug("State:\n".Chat::ST_PREV_NODE, 'bots');

                    $nodes->load($chat->session);

                    if (!$nodes->prevNode()) {
                        $chat->setState(Chat::ST_SHOW_NODE);
                    } else {
                        $chat->session->setNodeIdx($nodes->getNodeIdx());

                        switch ($nodes->getNodeType()) {
                            case Nodes::ND_COMPLEX_QUESTION:
                            case Nodes::ND_QUESTION:
                            case Nodes::ND_INFO_DATA:
                                $chat->setState(Chat::ST_SHOW_NODE);

                                break;

                            case Nodes::ND_CONCLUSION:
                            case Nodes::ND_MESSAGE:
                            case Nodes::ND_WARNING:
                            case Nodes::ND_RISK:
                                $chat->setState(Chat::ST_PREV_NODE);

                                break;

                            default:
                                throw new Exception(
                                    "Unsupported node type: {$nodes->getNodeType()}"
                                );
                                break;
                        }
                    }

                    $noAnswer = true;
                    break;

                case Chat::ST_ANSWER_QUESTION_BY_ID:
                    Yii::debug("State:\n".Chat::ST_ANSWER_QUESTION_BY_ID, 'bots');

                    $nodes->load($chat->session);

                    $questionId = (int)$buttonData['questionId'];

                    if (!$nodes->isCurrent($questionId)) {
                        if (!$nodes->goToQuestion($questionId))
                            throw new Exception("Question not found id: {$questionId}");

                        $chat->session->setNodeIdx($nodes->getNodeIdx());
                        $this->sendNode($nodes);

                        if ($this->chat->last_qst_message_id)
                            $this->replyMessageId = $this->chat->last_qst_message_id;
                    }

                    switch ($nodes->getNodeType()) {
                        case Nodes::ND_COMPLEX_QUESTION:
                            $chat->setState(Chat::ST_ANSWER_COMPLEX_QUESTION);
                        break;
                        case Nodes::ND_QUESTION:
                            $chat->setState(Chat::ST_ANSWER_QUESTION);
                        break;
                        default:
                            throw new Exception('Answered unknown node type');
                    }


                    $noAnswer = true;

                    break;
            }
        } while ($noAnswer);
    }


    protected function sendAlgorithmList()
    {
        $buttons = [];

        foreach ($this->bot->algorithms as $k => $algorithm) {
            $buttons[] = [
                'id' => $k,
                'text' => $algorithm->name,
                'algorithmId' => $algorithm->id,
                'selected' => false,
            ];
        }

        $this->sendMessage(Yii::t('bots', 'Select consultation'), $buttons);
    }

    /**
     * @param $algorithmId
     * @return bool
     */
    protected function startAlgorithmSession($algorithmId)
    {
        //$botAlgId = null;
        $botAlgorithm = null;

        foreach ($this->bot->algorithms as $algorithm) {
            Yii::debug("algorithm=" . $algorithm->name, __METHOD__);
            if ($algorithm->id == $algorithmId) {
                //$botAlgId = $algorithm->id;
                $botAlgorithm = $algorithm;
                break;
            }
        }

        if (is_null($botAlgorithm)) {
            return false;
        }

        Yii::debug("Start algorithm:\nalgorithm: {$botAlgorithm->algorithm_id} context: {$botAlgorithm->context_id}", 'bots');

        $session = $this->startSession(
            $this->chat->chat_id,
            $botAlgorithm->algorithm_id,
            $botAlgorithm->context_id
        );

        if (is_null($session)) {
            return false;
        }

        $this->chat->session_id = $session->id;
        $this->chat->save();

        // Если всего одна экспертиза, тогда не выводить сообщения с указанием выбранной экспертизы
        if (count($this->bot->algorithms) !== 1) {
            $text = Yii::t('bots','Consultation selected').":\n" . $botAlgorithm->name;

            $this->sendMessage($text, [], true);
        }

        return true;
    }

    /**
     * @param Nodes $nodes
     */
    protected function sendAlgorithmDone(Nodes $nodes)
    {
        $resultText = $nodes->getResultText();
        $text =  Yii::t('bots', 'Consultation done').".";

        if ($resultText) {
            $text .= "\n\n".Yii::t('bots', 'Result').":\n" . $resultText;
        }

        if ($nodes->autoConclusion()) {
            $text .= "\n\n" . $nodes->getAutoConclusionText();
        }

        $this->sendMessage($text, [$this->controlButtons[1]]);
    }


    /**
     * @param Bot $bot
     * @param Nodes $nodes
     */
    protected function sendAlgorithmDoneAnswer(Bot $bot)
    {
        $text =  empty($bot->invalid_answer_message)?Yii::t('bots', 'Consultation done'):$bot->invalid_answer_message;

        $this->sendMessage($text, [$this->controlButtons[1]]);
    }

    /**
     * @param Bot $bot
     * @param $answerId
     * @param Nodes $nodes
     * @param bool $edit
     * @return bool
     *
     * @throws Exception
     */

    protected function answerQuestion(Bot $bot, $answerId, Nodes $nodes)
    {
        if ($nodes->getNodeType() != Nodes::ND_QUESTION) {
            throw new Exception(
                "Invalid node idx: {$this->chat->session->node_idx} current node is not question"
            );
        }


        if (is_null($answerId)) {
            $text = empty($bot->invalid_answer_message)?Yii::t('bots', 'Please enter a valid answer'):$bot->invalid_answer_message;

            $this->sendMessage($text, [], true);

            return false;
        }
        
        $this->chat->session->setAnswer($nodes->getNodeObjectId(), $answerId);

        $this->editQuestion($answerId, $nodes);

        return true;
    }


    /**
     * @param Nodes $nodes
     * @param $text
     * @return bool
     * @throws Exception
     */
    protected function answerComplexQuestion(Bot $bot, $answerId, Nodes $nodes)
    {
        if ($nodes->getNodeType() != Nodes::ND_COMPLEX_QUESTION) {
            throw new Exception(
                "Invalid node idx: {$this->chat->session->node_idx} current node is not complex question"
            );
        }

        if (is_null($answerId)) {
            $text = empty($bot->invalid_answer_message)?Yii::t('bots', 'Please enter a valid answer'):$bot->invalid_answer_message;

            $this->sendMessage($text, [], true);

            return false;
        }


        $answers = $this->chat->session->getAnswer($nodes->getNodeObjectId());
        $answers = is_array($answers)?$answers:[];

        $k = array_search($answerId, $answers);

        if ($k===false)
            $answers[] = $answerId;
        else
            unset($answers[$k]);

        array_unique($answers);

        $this->chat->session->setAnswer($nodes->getNodeObjectId(), $answers);


        $this->editQuestion($answers, $nodes);
        return true;
    }

    /**
     * @param Nodes $nodes
     * @param $text
     * @return bool
     * @throws Exception
     */
    protected function answerInfo(Nodes $nodes, $text)
    {
        if ($nodes->getNodeType() != Nodes::ND_INFO_DATA) {
            throw new Exception(
                "Invalid node idx: {$this->chat->session->node_idx} current node is not info"
            );
        }

        $infoData = $nodes->getNodeFormat()->getInfoData($text);

        if (is_null($infoData)) {
            $formatText = $nodes->getNodeFormat()->getFormatText();

            $text =  Yii::t('bots', 'Please enter a valid answer').
                ($formatText ? ":\n" . $formatText : '');

            $this->sendMessage($text, [], true);

            return false;
        }

        $this->chat->session->setInfoData($nodes->getNodeObjectId(), $infoData);

        return true;
    }

    /**
     * @param Nodes $nodes
     * @param $answerId
     */
    protected function sendNode(Nodes $nodes)
    {
        $text = $nodes->getNodeText();
        $header = $nodes->getNodeHeader();
        $answers = $nodes->getNodeAnswers();
        $nodeType = $nodes->getNodeType();
        $buttons = [];


        if ($format = $nodes->getNodeFormat()) {
            $formatText = $format->getFormatText();

            if ($formatText)
                $text .= "\n\n".Yii::t('bots', 'Please write').":  " . $formatText;
        }

        if ($answers and $answers->hasLongText())
            $text .= "\n\n".Yii::t('bots', 'Full answers').":\n".$nodes->getNodeAnswers()->getAnswersText();


        if ($nodeType == Nodes::ND_COMPLEX_QUESTION) {
            $text .= "\n\n".Yii::t('bots', 'You can choose multiple answers');
        }

        $text = $this->formatMessageText($text);


        if ($answers) {
            $answerId = $this->chat->session->getAnswer($answers->questionId);
            $buttons = $answers->getButtons($answerId);
        }

        if ($nodes->nodeHasImage()) {
            $msgId = $this->sendImage($nodes->getNodeImageUrl(), $nodes->getNodeImageFile(), $header, $text, $buttons, true);

        } elseif ($nodes->nodeHasVideo()) {
            $msgId = $this->sendVideoURL($nodes->getNodeVideoUrl(), $header, $text, $buttons, true);

        } elseif ($nodes->nodeHasUrl()) {
            $msgId = $this->sendURL($nodes->getNodeUrl(), $header, $text, $buttons, true);

        } elseif ($nodes->nodeHasFile()) {
            $msgId = $this->sendFile($nodes->getNodeFile(), $header, $text, $buttons, true);

        } else
            $msgId =  $this->sendMessage($text, $buttons, true, $header);


        if ($nodeType == Nodes::ND_QUESTION or $nodeType == Nodes::ND_COMPLEX_QUESTION)
            $this->chat->last_qst_message_id = $msgId;
    }

    protected function formatMessageText($text) {

        $text = preg_replace('~<a[^>]+href=(["\'])([^\'"]+)\1[^>]*>~', '<a href="$2">', $text);

        $text = str_replace('href="/','href="http://' . $this->linkDomain . '/', $text);

        $text = html_entity_decode($text, null, 'UTF-8');

        return $text;
    }


    protected function splitMessage($message) {

        $messages = [];

        // Проверка: превышает ли сообщение заданную в параметрах длину
        $msgCount = (int)(strlen($message) / static::MAX_MESSAGE_LEN);

        if ($msgCount==0) {
            $messages[] = $message;
            return $messages;
        }

        $message = strip_tags($message);
        $words = explode(' ', $message); // сообщение разбивается на слова
        $part = '';

        foreach($words as $word) {
            $nextLength = mb_strlen($part)+mb_strlen($word)+ 1;

            if ($nextLength > static::MAX_MESSAGE_LEN) {
                $messages[] = $part;
                $part = '';
            }

            $part .= $word.' ';

            if (mb_strlen($part) > static::MAX_MESSAGE_LEN) {
                // страховка на случай если само word оказалось слишком длинным
                $part = mb_substr($part, 0, self::MAX_MESSAGE_LEN-4) . '...';
                $messages[] = $part;
                $part = '';
            }
        }

        $messages[] = $part;

        return $messages;
    }

    protected function editQuestion($answerId, Nodes $nodes) {
        if (!$this->replyMessageId)
            return;

        $answers = $nodes->getNodeAnswers();
        $text = $nodes->getNodeText();


        if ($answers and $answers->hasLongText())
            $text .= "\n\n".Yii::t('bots', 'Full answers').":\n".$nodes->getNodeAnswers()->getAnswersText();


        if ($nodes->getNodeType() == Nodes::ND_COMPLEX_QUESTION) {
            $text .= "\n\n".Yii::t('bots', 'You can choose multiple answers');
        }

        $text = $this->formatMessageText($text);

        $buttons = $answers->getButtons($answerId);


        $this->editMessage($text, $buttons);
    }
}
