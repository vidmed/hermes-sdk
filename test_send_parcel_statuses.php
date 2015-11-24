<?php
require('vendor/autoload.php');
use box2box\hermes_partner_sdk\HermesPartnerSdk;

try {

    $request = new HermesPartnerSdk(HermesPartnerSdk::TEST_URL, 'testlogin', 'testpassword', true);

    $parcelStatusData = [
        [
            'ParcelBarcode' => '21750100012392',
            'Statuses'      => [
                'ExtraParams'      => [
                    'Name1' => 'Value1',
                    'Name2' => 'Value2',
                ],
                'StatusSystemName' => 'MISSING',
                'StatusTimestamp'  => date('c'),
                'PartnerPointCode'  => 'soPS2',
            ]
        ],
    ];

    $result = $request->sendParcelStatuses($parcelStatusData);
    print_r($result);
    exit;

} catch (Exception $e) {
    print_r($e->getMessage());
    exit;
}