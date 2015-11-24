<?php
require('vendor/autoload.php');
use box2box\hermes_partner_sdk\HermesPartnerSdk;

try {

    $request = new HermesPartnerSdk(HermesPartnerSdk::TEST_URL, 'testlogin', 'testpassword', true);

    $result = $request->getParcels('2014-08-12');
    print_r($result);
    exit;

} catch (Exception $e) {
    print_r($e->getMessage());
    exit;
}