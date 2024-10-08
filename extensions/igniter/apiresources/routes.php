<?php

use Igniter\ApiResources\OrdersApi;

$api = app('api.router');

$api->version('v1', function ($api) {
    // Define the routes to issue tokens.
    // $api->post('/token', [\Igniter\Api\Controllers\Tokens::class, 'create']);

    $api->post("create_order", [OrdersApi::class, "store"]);
    $api->post("add_to_cart", [OrdersApi::class, "add_to_cart"]);
    $api->post("get_cart_data", [OrdersApi::class, "get_cart_data"]);
    $api->post("update_cart", [OrdersApi::class, "update_cart"]);
    $api->post("delete_cart", [OrdersApi::class, "delete_cart"]);
    // $api->get("order_api", [OrdersApi::class, "index"]);
});
