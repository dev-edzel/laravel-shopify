<?php

namespace App\Traits;

use Exception;

trait RequestTrait {
    public function makeAnAPICallToShopify($url, $params = null, $headers)
    {
        try {
            $response = new Client();
        }catch(Exception $e) {
            return null;
        }
    }   
}