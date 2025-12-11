<?php
declare(strict_types=1);

use think\facade\Env;

return [
    'mch_id'=>Env::get('WECHAT_MERCH'),
    'mch_secret_key'=>Env::get('WECHAT_KEY'),
    'apiclient_key_path'=>root_path().'cert/apiclient_key.pem',
    'apiclient_cert_path'=>root_path().'cert/apiclient_cert.pem',
    'wechat_public_cert_path'=>root_path().'cert/pub_key.pem'
];