<?php

namespace box2box\hermes_partner_sdk;

use Exception;
use Guzzle\Http\Client;

class HermesPartnerSdk
{
    /**
     * base api url
     */
    const BASE_URL = "https://api.hermes-dpd.ru/ps/restservice.svc/rest";
    /**
     * base api url
     */
    const TEST_URL = "https://test-api.hermes-dpd.ru/ps/restservice.svc/rest";

    /** Принята в пункте выдачи */
    const STATUS_ARRIVED_AT_PARCEL_SHOP = 'ARRIVED_AT_PARCEL_SHOP';
    /** Выдана */
    const STATUS_RECEIVED = 'RECEIVED';
    /** Отправлена на терминал (возврат) */
    const STATUS_UNDELIVERED = 'UNDELIVERED';
    /** Инвентаризирована */
    const STATUS_INVENTORIED = 'INVENTORIED';
    /** Потеряна */
    const STATUS_MISSING = 'MISSING';

    const CODE_INTERNAL_SERVICE_FAULT      = 'InternalServiceFault';
    const CODE_DESERIALIZATION_FAILED      = 'DeserializationFailed';
    const CODE_AUTHENTICATION_ERROR        = 'AuthenticationError';
    const CODE_COMMON_FAIL                 = 'CommonFail';
    const CODE_SUCCESS                     = 'Success';
    const CODE_NO_SUFFICIENT_RIGHTS        = 'NoSufficientRights';
    const CODE_PARCEL_BARCODE_IS_NOT_FOUND = 'ParcelBarcodeIsNotFound';
    const CODE_STRING_LENGTH               = 'StringLength';
    const CODE_REQUIRED                    = 'Required';
    const CODE_DESERIALIZATION             = 'Deserialization';
    const CODE_UNKNOWN_STATUS              = 'UnknownStatus';
    const CODE_MANAGEMENT_STATUS           = 'ManagementStatus';
    const CODE_APPLY_STATUS                = 'ApplyStatus';

    /**
     * @var - url API
     */
    private $url;

    private $login;

    private $password;

    private $decodeJson;

    /**
     * @param string $url         урл, на который будем стучать
     * @param string $login
     * @param string $password
     * @param bool   $decode_json конвертировать ли json-строку ответа
     *
     * @throws Exception
     */
    public function __construct($url = self::BASE_URL, $login, $password, $decode_json = true)
    {
        if (
            is_string($url) && !empty($url)
            && is_string($login) && !empty($login)
            && is_string($password) && !empty($password)
        ) {
            $this->login    = trim($login);
            $this->password = trim($password);
            $this->url      = trim($url, " \t\n\r\0\x0B/");
        } else {
            throw new Exception('Url, login and password might be a non empty string!');
        }

        $this->decodeJson = (bool)$decode_json;
    }

    /**
     * Данный метод предназначен для изменения статусов посылки. Передаются посылки с массивом проставляемых статусов.
     * Если установка статуса посылки неудачна, то остальные статусы по текущей посылке не будут проставлены.
     *
     * @param array $parcelStatusData Массив записей с информацией о статусах в посылках
     *
     * @return object
     * @throws Exception
     */
    public function sendParcelStatuses(array $parcelStatusData)
    {
        $url  = $url = $this->url . '/SendParcelStatuses';
        $body = json_encode([
            'ParcelStatusData' => $parcelStatusData
        ]);

        return $this->call('POST', $url, $this->decodeJson, $body);
    }

    /**
     * Данный метод предназначен для получения данных по посылкам,
     * которые планируется передать партнеру по доставке и выдаче посылок.
     *
     * @param string $dateFrom          Дата и время, от которой необходимо получить данные. Обязательный
     * @param array  $partnerPointCodes Массив кодов партнёрских пунктов выдачи.
     *                                  Рекомендуется использовать для фильтрации результатов. Не обязательный
     * @param string $dateTo            Дата и время окончания периода, за который необходимо вернуть данные.
     *                                  Обычно должно быть пустым. Необходимо заполнять, только если
     *                                  нужны данные за старые периоды из-за сбоя на стороне партнёра. Не обязательный
     *
     * @return object
     * @throws Exception
     */
    public function getParcels($dateFrom, $partnerPointCodes = [], $dateTo = null)
    {
        $url  = $url = $this->url . '/GetParcels';
        $body = json_encode([
            'dateFrom'          => $dateFrom,
            'dateTo'            => $dateTo,
            'partnerPointCodes' => $partnerPointCodes,
        ]);

        return $this->call('POST', $url, $this->decodeJson, $body);
    }


    /** Общий метод для отправки запроса
     *
     * @param       $method      - метод (GET, POST, ....)
     * @param       $url         - на какой url отправлять запрос
     * @param       $decode_json - отдавать json-строку или конвертировать в объект
     * @param mixed $body        - тело запроса (для метода POST)
     * @param array $query       - параметры GET запроса
     *
     * @return object
     * @throws Exception
     */
    private function call($method, $url, $decode_json = true, $body = null, array $query = [])
    {
        try {
            $client  = new Client();
            $request = $client->createRequest(
                $method,
                $url,
                isset($body) ? ['Content-Type' => 'application/json'] : null,
                $body,
                [
                    'query'  => $query,
                    'auth'   => [$this->login, $this->password, 'basic'],
                    'verify' => false,
                ]
            );

            $response = $request->send();

            if ($response->isSuccessful()) {
                if (!$response->getBody()->getContentLength()) {
                    return (object)['result' => null, 'url' => $request->getUrl()];
                }

                $jsonStr = $response->getBody(true);

                //приходит строка с bom, поэтому json_decode не работает. Убираем bom
                $bom            = pack('H*', 'EFBBBF');
                $jsonWithoutBom = preg_replace("/^$bom/", '', $jsonStr);

                $result = (object)[
                    'result' => $decode_json ? json_decode($jsonWithoutBom) : $response->getBody(true),
                    'url'    => $request->getUrl()
                ];
                return $result;
            }

            throw new Exception($response->getMessage());
        } catch (Exception $e) {
            throw new Exception(iconv("WINDOWS-1251", "UTF-8", $e->getMessage()), $e->getCode(), $e);
        }
    }

    /**
     * @return array Список доступных статусов посылок для проставления
     */
    public function getStatuses()
    {
        return [
            self::STATUS_ARRIVED_AT_PARCEL_SHOP => 'Принята в пункте выдачи',
            self::STATUS_RECEIVED               => 'Выдана',
            self::STATUS_UNDELIVERED            => 'Отправлена на терминал (возврат)',
            self::STATUS_INVENTORIED            => 'Инвентаризирована',
            self::STATUS_MISSING                => 'Потеряна',
        ];
    }

    /**
     * @return array Коды ошибок
     */
    public function getCodes()
    {
        return [
            self::CODE_INTERNAL_SERVICE_FAULT => [
                'code'        => null,
                'systemName'  => 'InternalServiceFault',
                'description' => 'Внутренняя ошибка сервера',
                'reason'      => 'Возникает при неизвестных или неверных действиях процессов SOAP - сервиса',
            ],
            self::CODE_DESERIALIZATION_FAILED => [
                'code'        => null,
                'systemName'  => 'DeserializationFailed',
                'description' => 'Внутренняя ошибка десериализации на сервере',
                'reason'      => 'Возникает при попытке передать запрос неверного формата, сервер не может десериализовать объект',
            ],
            self::CODE_AUTHENTICATION_ERROR => [
                'code'        => null,
                'systemName'  => 'AuthenticationError',
                'description' => 'Ошибка авторизации пользователя',
                'reason'      => 'Возникает, когда лоиг или пароль не верны, нет доступа к сервису, или возникают другие ошибки, запрещающие работу с сервисом',
            ],
            self::CODE_COMMON_FAIL => [
                'code'        => -1,
                'systemName'  => 'CommonFail',
                'description' => 'Ошибочный результат',
                'reason'      => 'Возникает при неизвестных ошибках, ошибках общего характера или внутренних ошибок сервера',
            ],
            self::CODE_SUCCESS => [
                'code'        => 0,
                'systemName'  => 'Success',
                'description' => 'Успешный результат',
                'reason'      => 'Данный код возвращается в параметре ErrorCode в случае успешного результата обработки запроса. Код не является ошибкой',
            ],
            self::CODE_NO_SUFFICIENT_RIGHTS => [
                'code'        => 11,
                'systemName'  => 'NoSufficientRights',
                'description' => 'У текущего пользователя недостаточно прав',
                'reason'      => 'Возникает при отправке посылок на доставку, в этом случае нужно связаться с техподдержкой Hermes-DPD',
            ],
            self::CODE_PARCEL_BARCODE_IS_NOT_FOUND => [
                'code'        => 14,
                'systemName'  => 'ParcelBarcodeIsNotFound',
                'description' => 'Штрих-код посылки [{ParcelBarcode}] не найден',
                'reason'      => 'Штрих-код посылки не найден в системе',
            ],
            self::CODE_STRING_LENGTH => [
                'code'        => 20,
                'systemName'  => 'StringLength',
                'description' => 'Поле {[StringField]} должно быть строкой с длиной от {[MinLength]} до {[MaxLength]} символов',
                'reason'      => 'Возникает, если строковое значения поле не соответствует указанной длине',
            ],
            self::CODE_REQUIRED => [
                'code'        => 21,
                'systemName'  => 'Required',
                'description' => 'Поле [{RequiredField}] должно быть обязательно для заполнения',
                'reason'      => 'Возникает, если не было заполнено обязательное для заполнения поле',
            ],
            self::CODE_DESERIALIZATION => [
                'code'        => 28,
                'systemName'  => 'Deserialization',
                'description' => 'Ошибка десериализации объекта',
                'reason'      => 'Проверьте ваш запрос на наличие ошибок, прочитайте рекомендации к запросам',
            ],
            self::CODE_UNKNOWN_STATUS => [
                'code'        => 30,
                'systemName'  => 'UnknownStatus',
                'description' => 'Неизвестный статус',
                'reason'      => 'Возникает при попытке указать несуществующий статус',
            ],
            self::CODE_MANAGEMENT_STATUS => [
                'code'        => 31,
                'systemName'  => 'ManagementStatus',
                'description' => 'Ошибка обработки статуса в системе',
                'reason'      => 'Означает, что при обработке статуса возникла ошибка',
            ],
            self::CODE_APPLY_STATUS => [
                'code'        => 32,
                'systemName'  => 'ApplyStatus',
                'description' => 'Ошибка применения системного наименования статуса',
                'reason'      => 'При передаче статуса указан несопоставимое системное наименование статуса. Используйте таблицу Список доступных статусов посылок для проставления для решения ошибки',
            ],
        ];
    }
}
