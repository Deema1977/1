<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 06.01.2018
 * Time: 16:30
 */

namespace wm\bots\models;

use yii;
use yii\base\Exception;
use yii\helpers\Url;

use wm\service\models\Client as ServiceClient;
use wm\service\models\File\Cache;
use wm\service\models\File\CacheMessage;


class Nodes
{
    const ND_QUESTION = 'question';
    const ND_COMPLEX_QUESTION = 'complex_question';
    const ND_INFO_DATA = 'infoData';
    const ND_INFO_FILE = 'infoFile';
    const ND_RISK = 'risk';
    const ND_WARNING = 'warning';
    const ND_CONCLUSION = 'conclusion';
    const ND_ERROR = 'error';
    const ND_MESSAGE = 'message';

    protected $nodes;

    protected $currentIdx;

    protected $nodeText;
    protected $nodeType;
    protected $nodeObjectId;
    protected $nodeHidden = false;
    protected $nodeHeader;
    protected $nodeUrl;
    protected $nodeImageUrl;
    protected $nodeImageFile;
    protected $nodeVideoUrl;
    protected $nodeFile;

    protected $resultText;

    /**
     * @var Format
     */
    protected $nodeFormat;

    /**
     * @var Answers;
     */
    protected $nodeAnswers;

    protected $serviceUrl;
    protected $linkDomain;

    protected $loaded = false;

    /**
     * @var Bots\Bot
     */
    protected $bot;

    /**
     * @var Bots\Chat
     */
    protected $chat;

    protected $controller_name;

    protected $autoConclusion = false;

    protected $ansMaxLen;



    public function __construct(
        $serviceUrl,
        $linkDomain,
        Bot $bot,
        Chat $chat,
        $controller_name,
        $ansMaxLen
    ) {
        $this->serviceUrl = $serviceUrl;
        $this->linkDomain = $linkDomain;
        $this->bot = $bot;
        $this->chat = $chat;
        $this->controller_name = $controller_name;
        $this->ansMaxLen = $ansMaxLen;

        // Yii::trace("Auto conclusion: ".var_export($this->autoConclusion, true) ,__METHOD__);
    }

    /**
     * @param Session $session
     * @param bool $force
     * @throws Exception
     */

    public function load(Session $session, $force = false)
    {
        if ($this->loaded and !$force) {
            $this->setCurrent($session->node_idx);
            return;
        }

        $answers = $session->getAnswers();
        $infoData = $session->getInfoData();

        $expert = new ServiceClient();

        //$expert = new  \wp\service\models\Connector($this->serviceUrl, false);

        $expert->setRenderMode($expert::RENDER_MODE_ARRAY);
        $response = $expert->nodeList(
            $session->algorithm_id,
            $session->context_id,
            $session->link_type,
            $answers,
            $infoData,
            $session->algorithm_uid,
            $session->getDoneActions()
        );

        $session->setAlgorithmUID($response['UID']);

        if ($response['isDone'])
            $session->setDone();

        $session->setDoneActions($response['doneActions']);


        $this->initResultText(
            $session->algorithm_id,
            (int) $response['riskLevel']
        );

        $this->nodes = $response['nodes'];
        $this->loaded = true;

        foreach ($this->bot->algorithms as $algorithm) {
            if ($algorithm->algorithm_id == $session->algorithm_id) {
                $this->autoConclusion = $algorithm->auto_conclusion;
            }
        }

        if (!isset($this->nodes[$session->node_idx])) {
            $session->setNodeIdx(count($this->nodes) - 1);
        }

        $this->setCurrent($session->node_idx);
    }

    public function loadConclusion(Session $session)
    {
        /**
         * @todo Загрузка заключений
         */
        /*
        if ($this->loaded and !$force) {
            $this->setCurrent($session->node_idx);
            return;
        }

        $answers = $session->getAnswers();
        $infoData = $session->getInfoData();

        $expert = new ServiceClient();

        //$expert = new  \wp\service\models\Connector($this->serviceUrl, false);

        $expert->setRenderMode($expert::RENDER_MODE_ARRAY);
        $response = $expert->nodeList(
            $session->algorithm_id,
            $session->context_id,
            $session->link_type,
            $answers,
            $infoData,
            $session->algorithm_uid,
            $session->getDoneActions()
        );

        $session->setAlgorithmUID($response['UID']);

        if ($response['isDone'])
            $session->setDone();

        $session->setDoneActions($response['doneActions']);


        $this->initResultText(
            $session->algorithm_id,
            (int) $response['riskLevel']
        );

        $this->nodes = $response['nodes'];
        $this->loaded = true;

        foreach ($this->bot->algorithms as $algorithm) {
            if ($algorithm->algorithm_id == $session->algorithm_id) {
                $this->autoConclusion = $algorithm->auto_conclusion;
            }
        }

        if (!isset($this->nodes[$session->node_idx])) {
            $session->setNodeIdx(count($this->nodes) - 1);
        }

        $this->setCurrent($session->node_idx);
        */
    }


    /**
     * @param $idx
     * @throws \Exception
     */

    public function setCurrent($idx)
    {
        if (!isset($this->nodes[$idx])) {
            throw new Exception("Node id: {$idx} not found");
        }

        $this->currentIdx = $idx;

        $this->resetNode();
        $node = $this->nodes[$idx];

        switch ($node['type']) {
            case self::ND_INFO_FILE:
                throw new Exception("Node info file unsupported");
                break;

            case self::ND_INFO_DATA:
                $this->initInfoDataNode($node);
                break;

            case self::ND_QUESTION:
                $this->initQuestionNode($node);
                break;

            case self::ND_RISK:
                $this->initRiskNode($node);
                break;

            case self::ND_WARNING:
                $this->initWarningNode($node);
                break;

            case self::ND_CONCLUSION:
                $this->initConclusionNode($node);
                break;

            case self::ND_MESSAGE:
                $this->initMessageNode($node);
                break;

            case self::ND_ERROR:
                throw new Exception("Node error");
                break;

            default:
                throw new Exception("Unsupported block type");
                break;
        }
    }

    public function nextNode()
    {
        $idx = $this->currentIdx + 1;

        if (isset($this->nodes[$idx])) {
            $this->setCurrent($idx);
            return true;
        } else {
            return false;
        }
    }

    public function prevNode()
    {
        $idx = $this->currentIdx - 1;

        if (isset($this->nodes[$idx])) {
            $this->setCurrent($idx);
            return true;
        } else {
            return false;
        }
    }

    public function currentNodeIs($type) {
        if (!isset($this->nodes[$this->currentIdx]))
            return false;

        $currentNode = $this->nodes[$this->currentIdx];

        if ($currentNode['type']==$type)
            return true;
        else
            return false;
    }


    public function isCurrent($questionId) {
        if (!isset($this->nodes[$this->currentIdx]))
            return false;

        $currentNode = $this->nodes[$this->currentIdx];

        if (($currentNode['type']==self::ND_QUESTION) and ($currentNode['question_id']==$questionId))
            return true;
        else
            return false;
    }


    public function goToQuestion($questionId) {

        foreach ($this->nodes as $idx=>$node) {
            if (($node['type']==self::ND_QUESTION) and ($node['question_id']==$questionId)) {
                $this->setCurrent($idx);
                return true;
            }
        }

        return false;
    }


    public function getNodeIdx()
    {
        return $this->currentIdx;
    }

    public function getNodeText()
    {
        return $this->nodeText;
    }

    public function getNodeHeader()
    {
        return $this->nodeHeader;
    }

    public function getNodeType()
    {
        return $this->nodeType;
    }

    public function getNodeObjectId()
    {
        return $this->nodeObjectId;
    }

    public function nodeHasImage() {
        return ($this->nodeImageUrl or $this->nodeImageFile)?true:false;
    }

    public function getNodeImageUrl() {
        return $this->nodeImageUrl;
    }

    public function getNodeImageFile() {
        return $this->nodeImageFile;
    }

    public function nodeHasVideo() {
        return ($this->nodeVideoUrl)?true:false;
    }

    public function getNodeVideoUrl() {
        return $this->nodeVideoUrl;
    }

    public function nodeHasUrl() {
        return ($this->nodeUrl)?true:false;
    }

    public function getNodeUrl() {
        return $this->nodeUrl;
    }

    public function nodeHasFile() {
        return ($this->nodeFile)?true:false;
    }

    public function getNodeFile() {
        return $this->nodeFile;
    }


    /**
     * @return Format
     */
    public function getNodeFormat()
    {
        return $this->nodeFormat;
    }

    /**
     * @return Answers
     */
    public function getNodeAnswers()
    {
        return $this->nodeAnswers;
    }

    public function getResultText()
    {
        return $this->resultText;
    }

    public function autoConclusion()
    {
        return $this->autoConclusion;
    }

    protected function initQuestionNode($node)
    {
        // Yii::trace("Node: ".var_export($node, true) ,__METHOD__);

        if ($node['data']['type'] == 'complex') {
            $this->nodeType = self::ND_COMPLEX_QUESTION;
            $this->nodeAnswers = new AnswersMult($node['question_id'], $this->ansMaxLen);
        } else {
            $this->nodeType = self::ND_QUESTION;
            $this->nodeAnswers = new Answers($node['question_id'], $this->ansMaxLen);
        }

        $this->nodeObjectId = $node['question_id'];
        $this->nodeText = $node['data']['text'];

        foreach ($node['data']['answers'] as $id => $answer) {
            $text = html_entity_decode(strip_tags($answer), null, 'UTF-8');
            $this->nodeAnswers->addAnswer($id, $text);
        }

        $this->nodeHidden = $node['hidden'] ? true : false;
        $this->nodeFormat = null;
    }

    protected function initInfoDataNode($node)
    {
        // Yii::trace("Node: ".var_export($node, true) ,__METHOD__);

        $this->nodeType = self::ND_INFO_DATA;

        $this->nodeObjectId = $node['info_id'];
        $this->nodeText = $node['data']['text'];

        $this->nodeAnswers = null;

        $this->nodeHidden = $node['hidden'] ? true : false;
        $this->nodeFormat = new Format($node['data']['type']);
    }

    protected function initRiskNode($node)
    {
        $this->nodeType = self::ND_RISK;

        $this->nodeObjectId = null;
        $this->nodeText =
            Yii::t('bots', 'Risk found').": \n{$node['risk_data']['text']}\n" .
            Yii::t('bots', 'Reason').": \n{$node['data']['text']}";
        $this->nodeAnswers = null;
        $this->nodeHidden = false;
        $this->nodeFormat = null;
    }

    protected function initWarningNode($node)
    {
        $this->nodeType = self::ND_WARNING;

        $this->nodeObjectId = null;
        $this->nodeText = $node['data']['text'];

        $this->nodeAnswers = null;
        $this->nodeHidden = false;
        $this->nodeFormat = null;
    }


    /**
     * @param $node
     * @throws \Exception
     */

    protected function initMessageNode($node)
    {
        $this->nodeType = self::ND_MESSAGE;

        $this->nodeObjectId = $node['message_id'];

        $this->nodeAnswers = null;
        $this->nodeHidden = $node['hidden']?true:false;;
        $this->nodeFormat = null;


        switch ($node['message_type']) {
            case 'text':
                $this->nodeHeader = $node['header'];
                $this->nodeText = $node['data']['text'];

            break;
            case 'image':
                $this->nodeHeader = $node['header'];
                $this->nodeText = $node['data']['text'];

                switch ($node['subtype']) {
                    case 'url':
                        $this->nodeImageUrl =  $node['url'];
                        break;
                    case 'file':
                        $uri = CacheMessage::fileUrl($node['message_id'], $node['file_web_id']);
                        $fileCache = Cache::create($uri, Yii::getAlias('@app/web'));

                        $this->nodeImageFile = $fileCache->getFilePath();
                        break;
                    default:
                        throw new \Exception("Invalid image subtype: {$node['subtype']}");
                }

            break;
            case 'video':
                $this->nodeHeader = $node['header'];
                $this->nodeText = $node['data']['text'];

                switch ($node['subtype']) {
                    case 'youtube':
                        $this->nodeVideoUrl =  $node['url'];
                        break;
                    default:
                        throw new \Exception("Invalid video subtype: {$node['subtype']}");
                }

                break;

            case 'iframe':
                $this->nodeHeader = $node['header'];
                $this->nodeText = $node['data']['text'];
                $this->nodeUrl =  $node['src'];

                break;

            case 'file':
                $this->nodeHeader = $node['header'];
                $this->nodeText = $node['data']['text'];

                $uri = CacheMessage::fileUrl($node['message_id'], $node['file_web_id']);
                $fileCache = Cache::create($uri, Yii::getAlias('@app/web'));

                $this->nodeFile = $fileCache->getFilePath();
                break;

            case 'chart':
                $this->nodeText = 'Графики на текущий момент не поддерживаются';
                break;

            default:
                $this->nodeText = 'Данный тип сообщений не поддерживается';
            break;

        }

    }


    protected function resetNode() {

        $this->nodeType = null;

        $this->nodeObjectId = null;
        $this->nodeHeader = null;
        $this->nodeText = null;

        $this->nodeAnswers = null;
        $this->nodeHidden = false;
        $this->nodeFormat = null;

        $this->nodeUrl = null;

        $this->nodeImageUrl = null;
        $this->nodeImageFile = null;

        $this->nodeVideoUrl = null;

        $this->nodeFile = null;
    }


    protected function initConclusionNode($node)
    {
        // Yii::trace("Node: ".var_export($node, true) ,__METHOD__);

        $this->nodeType = self::ND_CONCLUSION;

        $this->nodeObjectId = null;

        $link = Url::to([
            "bot/" . $this->controller_name . "/conclusion",
            'bot_id' => $this->bot->id,
            'chat_id' => $this->chat->chat_id,
            'conclusion_id' => $node['data']['id'],
            'hash' => $this->bot->conclusionHash(
                $this->chat->chat_id,
                $node['data']['id']
            )
        ]);

        $this->nodeText =
            Yii::t('bots', 'Document prepared').":\n<a href=\"http://{$this->linkDomain}{$link}\">{$node['data']['header']}</a>";

        $this->nodeAnswers = null;
        $this->nodeHidden = false;
        $this->nodeFormat = null;
    }


    protected function initResultText($algorithmId, $riskLevel)
    {
        $text = [];

        if ($this->autoConclusion) {
            $text = [
                0 => Yii::t('bots', 'No legal risks identified'),
                1 => Yii::t('bots', 'Transaction cannot be completed'),
                2 => Yii::t('bots', 'High risk'),
                3 => Yii::t('bots', 'Average risk'),
                4 => Yii::t('bots', 'Insignificant risk'),
            ];
        } else {
            switch ($algorithmId) {
                case 284:
                    $text = [
                        0 => Yii::t('bots', 'No right to grant vacation'),
                        1 => Yii::t('bots', 'No vacation is provided'),
                        2 => Yii::t('bots', 'Significant risk'),
                        3 => Yii::t('bots', 'The risk of negative consequences'),
                        4 => Yii::t('bots', 'Minor risk'),
                    ];

                    break;
                case 282:
                    $text = [
                        0 => Yii::t('bots', 'No risk of dismissal'),
                        1 => Yii::t('bots', 'The threat of dismissal'),
                        2 => Yii::t('bots', 'High risk of dismissal'),
                        3 => Yii::t('bots', 'Average risk of dismissal'),
                        4 => Yii::t('bots', 'Minor risk of dismissal'),
                    ];

                    break;
            }
        }

        $this->resultText = isset($text[$riskLevel]) ? $text[$riskLevel] : null;
    }

    public function getAutoConclusionText()
    {
        $link = Url::to([
            "bot/" . $this->controller_name . "/auto-conclusion",
            'bot_id' => $this->bot->id,
            'chat_id' => $this->chat->chat_id,
            'conclusion_id' => 0,
            'hash' => $this->bot->conclusionHash($this->chat->chat_id, 0)
        ]);

        $text = Yii::t('bots', 'Document prepared').":\n<a href=\"http://{$this->linkDomain}{$link}\">".Yii::t('bots', 'Conclusion')."</a>";

        return $text;
    }
}
