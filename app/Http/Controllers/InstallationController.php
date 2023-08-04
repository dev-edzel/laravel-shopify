<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Traits\FunctionTrait;
use App\Traits\RequestTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

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
                        Log::info('New Installation for shop' .$request->shop);
                        $endpoint = 'https://'.$request->shop.
                        '/admin/oauth/authorize?client_id='.config('custom.shopify_api_key').'&scope='.config('custom.api_scopes').
                        '&redirect_uri='.config('app.ngrok_url').'shopify/auth/redirect';
                        return Redirect::to($endpoint);
                    }
                } else throw new Exception('Shop parameter not present in the request');
            } else throw new Exception('Request is not valid');
        }catch(Exception $e) {
            Log::info($e->getMessage().' '.$e->getLine());
            dd($e->getMessage(). ' '.$e->getLine());
        }
    }
    public function handleRedirect(Request $request)
    {
        try {
            $validRequest = $this->validateRequestFromShopify($request->all());
            if($validRequest) {
                Log::info(json_encode($request->all()));
                // https://ngrok_url/shopify/auth/redirect?code={code}&timestamp={timestamp}
                if($request->has('shop') && $request->has('code')) {
                    $shop = $request->shop;
                    $code = $request->code;
                    $accessToken = $this->requestAccessTokenFromShopifyForThisStore($shop, $code);
                    if($accessToken !== false && $accessToken !== null){
                        $shopDetails = $this->getShopDetailsFromShopify($accessToken, $shop);
                        $saveDetails = $this->saveStoreDetailsToDatabase($shopDetails, $accessToken);
                        if($saveDetails) {
                            Redirect::to(config('app.ngrok.url').'shopify/auth/complete');
                        } else {
                            Log::info('Error Saving');
                            Log::info($saveDetails);
                        }
                    } else throw new Exception('Invalid Token'.$accessToken);
                } else throw new Exception('Code / Shop Invalid');
            } else throw new Exception('Invalid');
        } catch(Exception $e) {
            Log::info($e->getMessage().' '.$e->getLine());
            dd($e->getMessage().' '.$e->getLine());
        }
    }

    public function saveStoreDetailsToDatabase($shopDetails, $accessToken)
    {
        try {
            $payload = [
                'access_token' => $accessToken,
                'myshopify_domain' => $shopDetails['myshopify_domain'],
                'id' => $shopDetails['id'],
                'name' => $shopDetails['name'],
                'phone' => $shopDetails['phone'],
                'address1' => $shopDetails['address1'],
                'address2' => $shopDetails['address2'],
                'zip' => $shopDetails['zip']
            ];
            Store::updateOrCreate(['myshopify_domain' => $shopDetails['myshopify_domain']], $payload);
            return true;
        } catch(Exception $e) {
            Log::info($e->getMessage().' '.$e->getLine());
            return false;
        }
    }    

    public function completeInstallation(Request $request)
    {
        print_r('Complete');exit;
    }

    private function getShopDetailsFromShopify($accessToken, $shop){
        try {
            $endpoint = getShopifyURLForStore('shop.json', $shop);
            $headers = getShopifyHeadersForStore(['access_token' => $accessToken]);
            $response = $this->makeAnAPICallToShopify('GET', $endpoint, null, $headers);
            if($response['statusCode'] == 200) {
                $body = $response['body'];
                if(!is_array($body)) $body = json_decode($body, true);
                return $body['shop'] ?? null;
            } else {
                Log::info('Received');
                Log::info($response);
                return null;
            }
        } catch(Exception $e) {
            Log::info('Error');
            Log::info($e->getMessage().' '.$e->getLine());
            return null;
        }
    }

    private function requestAccessTokenFromShopifyForThisStore($shop, $code) {
        try {
            $endpoint = 'https://'.$shop.'/admin/oauth/access_token';
            $headers = ['Content-Type' => 'application/json'];
            $requestBody = [ 
                'client_id' => config('custom.shopify.api_key'),
                'client_secret' => config('custom.shopify.api_secret'),
                'code' => $code
            ];
            $response = $this->makeAnAPICallToShopify('POST', $endpoint, null, $headers, $requestBody);
            Log::info('Valid Token');
            Log::info(json_encode($response));
            if($response['statusCode'] == 200){
                $body = $response['body'];
                if(!is_array($body)) $body = json_decode($body, true);
                if(isset($body['access_token']) && $body['access_token'] !==null)
                return $body['access_token'];
            }
            return false;
        } catch(Exception $e) {
            return false;
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
