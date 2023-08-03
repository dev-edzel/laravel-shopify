<?php

namespace App\Http\Controllers;

use App\Traits\FunctionTrait;
use App\Traits\RequestTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InstallationController extends Controller
{   
    use FunctionTrait, RequestTrait;

    // public $app_scopes = 'write_orders, write_fullfillments,read_all_orders,write_customers,read_locations,write_products';
    public function startInstallation(Request $request)
    {
        try {
            $validRequest = $this->validateRequestFromShopify($request->all());
            if($validRequest) {
                $shop = $request->has('shop');
                if($shop){
                    $storeDetails = $this->getStoreByDomain($request->shop); 
                    if($storeDetails !== null && $storeDetails !== false) {
                        $validAccessToken = $this->checkIfAccessTokenIsValid($storeDetails);
                        if($validAccessToken) {
                            print_r('Valid');exit;
                        } else {
                            print_r(('Invalid'));exit;
                        }
                    } else {    
                        $endpoint = 'https://'.$request->shop.
                        '/admin/oauth/authorize?client_id='.config('custom.shopify_api_key').'&scope='.config('custom.api_scopes').
                        '&redirect_uri='.route('app_install_redirect');
                    }
                } else throw new Exception('Shop parameter not present in the request');
            } else throw new Exception('Request is not valid');
        }catch(Exception $e) {
            Log::info($e->getMessage().' '.$e->getLine());
            dd($e->getMessage(). ' '.$e->getLine());
        }
    }

    private function validateRequestFromShopify($request) {
        try{
            $arr= [];
            $hmac = $request['hmac'];
            unset($request['hmac']);
            foreach($request as $key=>$value){  
              $key=str_replace("%","%25",$key);
              $key=str_replace("&","%26",$key);
              $key=str_replace("=","%3D",$key);
              $value=str_replace("%","%25",$value);
              $value=str_replace("&","%26",$value);
              $arr[] = $key."=".$value;
            }       
            $str = implode('&',$arr);
            $ver_hmac =  hash_hmac('sha256',$str,config('custom.shopify_api_secret'),false);
            return $ver_hmac==$hmac;
        }catch(Exception $e) {
            Log::info('Problem with verify hmac from request');
            Log::info($e->getMessage(). ' '.$e->getLine());
            return false;
        }
    }

    private function checkIfAccessTokenIsValid($storeDetails) {
        try {
            if($storeDetails !== null && isset($storeDetails->access_token) && strlen($storeDetails->access_token) > 0) {
                $token = $storeDetails->access_token;
                $endpoint = getShopifyURLForStore('shop.json', $storeDetails);
                $headers = getShopifyHeadersForStore($storeDetails);
                $response = $this->makeAnAPICallToShopify('GET', $endpoint, null, $headers, null);
                Log::info('Response for checking the validity of token');
                Log::info($response);
                return $response['statusCode'] === 200;
            }
            return false;
        } catch(Exception $e) {
            return false;
        }
    }
}
