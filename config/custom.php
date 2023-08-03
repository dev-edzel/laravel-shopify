<?php

return [
    'shopify_api_key' => env('SHOPIFY_API_KEY', '5a480884eff15e0ce7cd1db8e1d5294b'),
    'shopify_api_secret' => env('SHOPIFY_API_SECRET', 'e942acb5bb7afcc12de023136ffb6a12'),
    'shopify_api_version' => '2023-07',
    'api_scopes' => 'write_orders,write_fulfillments,write_customers,write_fulfillments,read_locations,write_products'
];