<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 06.01.2018
 * Time: 16:57
 */

namespace wm\bots\models;

use Yii;

class Format
{
    const UNKNOWN = 'unknown';

    const TEXT = 'text';
    const LONG_TEXT = 'long_text';
    const THRESHOLD01 = 'threshold01';
    const THRESHOLD2 = 'threshold2';
    const THRESHOLD10 = 'threshold10';
    const THRESHOLD25_50 = 'threshold25_50';
    const FIO = 'fio';
    const DATE = 'date';
    const NUMBER = 'number';
    const MONEY = 'money';
    const PERIOD = 'period';
    const SQUARE = 'square';
    const PAYMENTS = 'payments';

    protected $type;

    public function __construct($type)
    {
        switch ($type) {
            case self::TEXT:
                $this->type = self::TEXT;
                break;
            case self::LONG_TEXT:
                $this->type = self::LONG_TEXT;
                break;
            case self::THRESHOLD01:
                $this->type = self::THRESHOLD01;
                break;
            case self::THRESHOLD2:
                $this->type = self::THRESHOLD2;
                break;
            case self::THRESHOLD10:
                $this->type = self::THRESHOLD10;
                break;
            case self::THRESHOLD25_50:
                $this->type = self::THRESHOLD25_50;
                break;
            case self::FIO:
                $this->type = self::FIO;
                break;
            case self::DATE:
                $this->type = self::DATE;
                break;
            case self::NUMBER:
                $this->type = self::NUMBER;
                break;
            case self::MONEY:
                $this->type = self::MONEY;
                break;
            case self::SQUARE:
                $this->type = self::SQUARE;
                break;
            case self::PAYMENTS:
                $this->type = self::PAYMENTS;
                break;
            case self::PERIOD:
                $this->type = self::PERIOD;
                break;

            default:
                $this->type = self::UNKNOWN;
                break;
        }
    }

    public function getInfoData($text)
    {
        switch ($this->type) {
            case self::DATE:
                return $this->extractDate($text);
                break;

            case self::FIO:
                return $this->extractFIO($text);
                break;

            case self::NUMBER:
                return $this->extractNumber($text);
                break;

            case self::MONEY:
                return $this->extractMoney($text);
                break;

            case self::PERIOD:
                return $this->extractPeriod($text);
                break;

            case self::SQUARE:
                return $this->extractSquare($text);
                break;

            case self::THRESHOLD01:
            case self::THRESHOLD2:
            case self::THRESHOLD10:
            case self::THRESHOLD25_50:
                return $this->extractThreshold($text);
                break;

            case self::TEXT:
            case self::LONG_TEXT:
            case self::PAYMENTS:
            case self::UNKNOWN:
            default:
                return $text;
                break;
        }
    }

    public function getFormatText()
    {
        switch ($this->type) {
            case self::DATE:
                return Yii::t('bots', 'Day.Month.Year');
                break;

            case self::FIO:
                return Yii::t('bots', 'Full name');
                break;

            case self::MONEY:
                return Yii::t('bots', 'Amount currency');
                break;

            case self::PERIOD:
                return Yii::t('bots', 'Term period');
                break;

            case self::SQUARE:
                return Yii::t('bots', 'Area measurement');
                break;

            case self::THRESHOLD01:
            case self::THRESHOLD2:
            case self::THRESHOLD10:
            case self::THRESHOLD25_50:
                return Yii::t('bots', 'Asset_value transaction_amount');
                break;

            case self::NUMBER:
            case self::TEXT:
            case self::LONG_TEXT:
            case self::PAYMENTS:

            case self::UNKNOWN:
            default:
                return null;
                break;
        }
    }

    protected function extractDate($text)
    {
        $text = trim($text);
        $m = [];

        if (!preg_match('~(\d{1,2})\.(\d{1,2})\.(\d{2,4})~', $text, $m)) {
            return null;
        }

        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = (int) $m[3];

        if ($day < 1 or $day > 31) {
            return null;
        }

        if ($month < 1 or $month > 12) {
            return null;
        }

        if ($year < 100) {
            $year += 2000;
        }

        return "{$day}.{$month}.{$year}";
    }

    protected function extractFIO($text)
    {
        $text = trim($text);
        $m = [];

        if (!preg_match('~([^\s]+)\s+([^\s]+)\s+([^\s]+)~', $text, $m)) {
            return null;
        }

        $data = [
            'surname' => $m[1],
            'name' => $m[2],
            'patronymic' => $m[3]
        ];

        return $data;
    }

    protected function extractNumber($text)
    {
        $text = trim($text);
        $text = str_replace(',', '.', $text);

        if (!is_numeric($text)) {
            return null;
        }

        return $text;
    }

    protected function extractMoney($text)
    {
        $text = trim($text);
        $m = [];

        if (!preg_match('~([\d.,]+)\s+(.+)~', $text, $m)) {
            return null;
        }

        $amount = str_replace(',', '.', $m[1]);
        $currency = mb_strtolower($m[2], 'UTF-8');

        $currencies = [
            Yii::t('bots', 'rub') => 1,
            'usd' => 2,
            'eur' => 3
        ];

        if (!is_numeric($amount)) {
            return null;
        }

        if (!isset($currencies[$currency])) {
            return null;
        }

        $currency = $currencies[$currency];

        return [
            'amount' => $amount,
            'currency' => $currency
        ];
    }

    protected function extractPeriod($text)
    {
        $text = trim($text);
        $m = [];

        if (!preg_match('~([\d.,]+)\s+(.+)~', $text, $m)) {
            return null;
        }

        $amount = str_replace(',', '.', $m[1]);
        $period = mb_strtolower($m[2], 'UTF-8');

        $periods = [
            Yii::t('bots', 'days') => 1,
            Yii::t('bots', 'mounths') => 2,
            Yii::t('bots', 'years') => 3
        ];

        if (!is_numeric($amount)) {
            return null;
        }

        if (!isset($periods[$period])) {
            return null;
        }

        $period = $periods[$period];

        return [
            'amount' => $amount,
            'period' => $period
        ];
    }

    protected function extractSquare($text)
    {
        $text = trim($text);
        $m = [];

        if (!preg_match('~([\d.,]+)\s+(.+)~', $text, $m)) {
            return null;
        }

        $amount = str_replace(',', '.', $m[1]);
        $unit = mb_strtolower($m[2], 'UTF-8');

        $units = [
            Yii::t('bots', 'meters') => 1,
            Yii::t('bots', 'hectare') => 2
        ];

        if (!is_numeric($amount)) {
            return null;
        }

        if (!isset($units[$unit])) {
            return null;
        }

        $unit = $units[$unit];

        return [
            'amount' => $amount,
            'unit' => $unit
        ];
    }

    protected function extractThreshold($text)
    {
        $text = trim($text);
        $m = [];

        if (!preg_match('~([\d.,]+)\s+([\d.,]+)~', $text, $m)) {
            return null;
        }

        $assets = str_replace(',', '.', $m[1]);
        $realty = str_replace(',', '.', $m[2]);

        if (!is_numeric($assets)) {
            return null;
        }

        if (!is_numeric($realty)) {
            return null;
        }

        return [
            'assets_cost' => $assets,
            'realty_cost' => $realty
        ];
    }
}
