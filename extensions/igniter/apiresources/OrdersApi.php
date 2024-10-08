<?php

namespace Igniter\ApiResources;

use Admin\Models\Customers_model;
use Admin\Models\Menu_option_values_model;
use Admin\Models\Menu_options_model;
use Admin\Models\Menus_model;
use Admin\Models\Mobile_cart_model;
use Admin\Models\Order_menu_options_model;
use Admin\Models\Order_menus_model;
use Admin\Models\Order_totals_model;
use Admin\Models\Orders_model;
use Illuminate\Http\Request;
use Igniter\Api\Classes\ApiController;
use Illuminate\Support\Facades\DB;
use igniter\local\traits\SearchesNearby;

use Exception;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Facades\Location;

/**
 * Orders_Api API Controller
 */
class OrdersApi extends ApiController
{

    use SearchesNearby;

    public $implement = [
        'Igniter.Api.Actions.RestController'
    ];

    public $restConfig = [
        'actions' => [
            'index' => [
                'pageSize' => 20,
            ],
            'store' => [],
            'show' => [],
            'update' => [],
            'destroy' => [],
            'add_to_cart' => [],
            'get_cart_data' => [],
            'update_cart' => [],
            'delete_cart' => [],
        ],
        'request' => '{{request}}',
        'repository' => 'Acme\Extension\Repositories\OrdersApi',
        'transformer' => 'Acme\Extension\ApiResources\Transformers\OrdersApiTransformer',
    ];

    protected $requiredAbilities = ['orders_api:*'];



    public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            'customer_id' => 'required|integer',
            'location_id' => 'required|integer',
            'address_id' => 'required|string',
            'total_items' => 'required|integer',
            'comment' => 'nullable|string',
            'payment' => 'required|string',
            'order_type' => 'required|string',
            'delivery_charge' => 'required|numeric',
            'status_id' => 'required|integer',
            'ip_address' => 'required|string',
            'assignee_id' => 'nullable|integer',
            'assignee_group_id' => 'nullable|integer',
            'invoice_prefix' => 'required|string',
            'hash' => 'required|string',
            'processed' => 'required|boolean',
            'order_time_is_asap' => 'required|boolean',
            'delivery_comment' => 'nullable|string',
            'order_menus' => 'required|array',
            'order_menus.*.menu_id' => 'required|integer',
            'order_menus.*.quantity' => 'required|integer',
            'order_menus.*.options' => 'nullable|array',
            'order_menus.*.options.*.option_id' => 'required|integer',
            'order_menus.*.options.*.item' => 'required|array',
            'order_menus.*.options.*.item.*.id' => 'required|integer'
        ]);

        DB::beginTransaction();

        try {
            $customer = Customers_model::findOrFail($request->customer_id);

            $subtotal = 0;

            foreach ($request->order_menus as $item) {
                $menu = Menus_model::findOrFail($item['menu_id']);
                $menu_total = $menu->menu_price * $item['quantity'];

                foreach ($item['options'] as $option) {
                    foreach ($option['item'] as $option_item) {
                        $option_value = Menu_option_values_model::findOrFail($option_item['id']);
                        $menu_total += $option_value->price * $item['quantity'];
                    }
                }

                $subtotal += $menu_total;
            }

            $order_total = $subtotal + $request->delivery_charge;

            if ($order_total < 25) {
                return response()->json(['error' => "Minimum order value is 25"], 422);
            }

            $order = new Orders_model;
            $order->customer_id = $request->customer_id;
            $order->first_name = $customer->first_name;
            $order->last_name = $customer->last_name;
            $order->email = $customer->email;
            $order->telephone = $customer->telephone;
            $order->location_id = $request->location_id;
            $order->address_id = $request->address_id;
            $order->total_items = $request->total_items;
            $order->comment = $request->comment;
            $order->payment = $request->payment;
            $order->order_type = $request->order_type;
            $order->order_total = $order_total;
            $order->status_id = $request->status_id;
            $order->ip_address = $request->ip_address;
            $order->user_agent = $request->userAgent();
            $order->assignee_id = $request->assignee_id;
            $order->assignee_group_id = $request->assignee_group_id;
            $order->invoice_prefix = $request->invoice_prefix;
            $order->hash = $request->hash;
            $order->processed = $request->processed;
            $order->order_time_is_asap = $request->order_time_is_asap;
            $order->delivery_comment = $request->delivery_comment;
            $order->save();

            foreach ($request->order_menus as $item) {
                $menu = Menus_model::findOrFail($item['menu_id']);
                $menu_total = $menu->menu_price * $item['quantity'];

                $order_menus = new Order_menus_model;
                $order_menus->order_id = $order->order_id;
                $order_menus->menu_id = $item['menu_id'];
                $order_menus->name = $menu->menu_name;
                $order_menus->quantity = $item['quantity'];
                $order_menus->price = $menu->menu_price;
                $order_menus->subtotal = $menu_total;
                $order_menus->comment = $item['comment'] ?? '';
                $order_menus->save();

                foreach ($item['options'] as $option) {
                    foreach ($option['item'] as $option_item) {
                        $option_value = Menu_option_values_model::findOrFail($option_item['id']);

                        $order_menu_options = new Order_menu_options_model;
                        $order_menu_options->order_id = $order->order_id;
                        $order_menu_options->menu_id = $item['menu_id'];
                        $order_menu_options->order_menu_id = $order_menus->id;
                        $order_menu_options->menu_option_value_id = $option_item['id'];
                        $order_menu_options->order_option_name = $option_value->value;
                        $order_menu_options->order_option_price = $option_value->price;
                        $order_menu_options->quantity = $item['quantity'];
                        $order_menu_options->save();
                    }
                }
            }

            $order_totals_data = [
                [
                    'order_id' => $order->order_id,
                    'code' => 'delivery',
                    'title' => 'Delivery',
                    'value' => $request->delivery_charge,
                ],
                [
                    'order_id' => $order->order_id,
                    'code' => 'subtotal',
                    'title' => 'Sub Total',
                    'value' => $subtotal,
                ],
                [
                    'order_id' => $order->order_id,
                    'code' => 'total',
                    'title' => 'Order Total',
                    'value' => $order_total,
                ]
            ];

            foreach ($order_totals_data as $order_total_data) {
                $order_totals = new Order_totals_model;
                $order_totals->order_id = $order_total_data['order_id'];
                $order_totals->code = $order_total_data['code'];
                $order_totals->title = $order_total_data['title'];
                $order_totals->value = $order_total_data['value'];
                $order_totals->save();
            }


            DB::commit();

            return response()->json($order);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function add_to_cart(Request $request)
    {
        // dd("oihdfygbf");
        $searchQuery = $request->address;

        // dd($searchQuery);
        // if (!$searchQuery = $this->getRequestSearchQuery())
        //     throw new ApplicationException(lang('igniter.local::default.alert_no_search_query'));

        $userLocation = is_array($searchQuery)
            ? $this->geocodeSearchPoint($searchQuery)
            : $this->geocodeSearchQuery($searchQuery);
            // dd($userLocation);

        $nearByLocation = Location::searchByCoordinates(
            $userLocation->getCoordinates()
        )->first(function ($location) use ($userLocation) {
            if ($area = $location->searchDeliveryArea($userLocation->getCoordinates())) {
                Location::updateNearbyArea($area);

                return $area;
            }
        });
//         dd($nearByLocation);
        if (!$nearByLocation) {
//            throw new ApplicationException(lang('igniter.local::default.alert_no_found_restaurant'));
        }
        // $nearByLocationArray = $nearByLocation->toArray();
        $nearByLocationArray = $nearByLocation;

        // dd($nearByLocationArray);



        $sub_total = 0;

        foreach ($request->menus as $menu) {
            // Fetch menu details from Menus_model
            $menu_details = Menus_model::find($menu['menu_id']);
            $menu_price = $menu_details->menu_price;
            $menu_name = $menu_details->menu_name;

            // Calculate the base subtotal for the current menu item
            // dd($menu_price );
            // dd($menu['quantity'] );
            $menu_subtotal = $menu['quantity'] * $menu_price;
            // Check if the menu item has options
            if (isset($menu['options'])) {
                foreach ($menu['options'] as $option) {
                    foreach ($option['item'] as $item) {
                        // Fetch option item details from Menu_option_values_model
                        $item_details = Menu_option_values_model::find($item['id']);
                        $item_price = $item_details->price;
                        $item_name = $item_details->value;

                        // Add the price of each option item
                        $menu_subtotal += $menu['quantity'] * $item_price;
                    }
                }
            }

            // Add to the overall sub_total
            $sub_total += $menu_subtotal;
        }



        // Calculate the order total
        $order_total = $sub_total + $request->delivery;

        // Insert the cart data into the database
        $mobile_cart = new Mobile_cart_model;
        $mobile_cart->customer_id = $request->customer_id;
        $mobile_cart->menus = json_encode($request->menus);
        $mobile_cart->area_id = $nearByLocationArray;
        $mobile_cart->sub_total = $sub_total;
        $mobile_cart->order_total = $order_total;
        $mobile_cart->comment = $request->comment;
        $mobile_cart->save();

        $response = [
            'success' => true,
            'message' => "item successfully add to cart",
            'cart_id' => $mobile_cart
        ];


        return response()->json($response, 201);
    }

    public function get_cart_data(Request $request)
    {
        // Validate the request
        $request->validate([
            'customer_id' => 'required'
        ]);

        // Find the customer
        $customer = Customers_model::where('customer_id', $request->customer_id)->first();

        // Check if customer exists
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => "Customer not found",
                'data' => []
            ], 404);
        }

        // Get cart data for the customer
        $cart = Mobile_cart_model::where('customer_id', $request->customer_id)->get();

        // Iterate over the cart items
        foreach ($cart as $key => $value) {
            $value->menus = json_decode($value->menus);
            foreach ($value->menus as $menu) {
                // Fetch the menu name
                $menu_details = Menus_model::where('menu_id', $menu->menu_id)->first();
                if ($menu_details) {
                    $menu->menu_name = $menu_details->menu_name;
                }

                // Fetch the option item details
                if (isset($menu->options)) {
                    foreach ($menu->options as $option) {
                        $option_details = Menu_options_model::where('option_id', $option->option_id)->first();
                        $option->option_name = $option_details->option_name;
                        foreach ($option->item as $item) {
                            $item_details = Menu_option_values_model::where('option_value_id', $item->id)->first();
                            if ($item_details) {
                                $item->item_name = $item_details->value;
                                $item->item_price = $item_details->price;
                            }
                        }
                    }
                }
            }
        }

        // Prepare the response
        $response = [
            'success' => true,
            'message' => "Cart details retrieved successfully",
            'customer' => $customer,
            'cart_data' => $cart,
        ];

        // Return the response
        return response()->json($response);
    }

    public function update_cart(Request $request)
    {
        $request->validate([
            'cart_id' => 'required',
            'customer_id' => 'required',
            'menus' => 'required|array',
//            'delivery' => 'required|numeric',
            'comment' => 'nullable'
        ]);

        // Fetch the existing cart
        $cart = Mobile_cart_model::where('id', $request->cart_id)->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => "Cart not found"
            ], 404);
        }

        // Calculate new sub_total and order_total
        $sub_total = 0;
        $detailed_menus = [];

        foreach ($request->menus as $menu) {
            // Fetch menu details from Menus_model
            $menu_details = Menus_model::find($menu['menu_id']);
            if (!$menu_details) {
                continue;
            }
            $menu_price = $menu_details->menu_price;
            $menu_name = $menu_details->menu_name;

            // Calculate the base subtotal for the current menu item
            $menu_subtotal = $menu['quantity'] * $menu_price;

            // Initialize array to hold detailed options
            $detailed_options = [];

            // Check if the menu item has options
            if (isset($menu['options'])) {
                foreach ($menu['options'] as $option) {
                    $option_details = [
                        'option_id' => $option['option_id'],
                        'name' => 'Option Name' // Update if you fetch the actual name
                    ];

                    $detailed_items = [];
                    foreach ($option['item'] as $item) {
                        // Fetch option item details from Menu_option_values_model
                        $item_details = Menu_option_values_model::find($item['id']);
                        if (!$item_details) {
                            continue;
                        }
                        $item_price = $item_details->price;
                        $item_name = $item_details->value;

                        // Add the price of each option item
                        $menu_subtotal += $menu['quantity'] * $item_price;

                        // Add detailed item to the array
                        $detailed_items[] = [
                            'id' => $item['id'],
                            'name' => $item_name,
                            'price' => $item_price
                        ];
                    }
                    // Add detailed items to the option details
                    $option_details['item'] = $detailed_items;

                    // Add detailed option to the array
                    $detailed_options[] = $option_details;
                }
            }

            // Add to the overall sub_total
            $sub_total += $menu_subtotal;

            // Store the complete menu details in the detailed_menus array
            $detailed_menus[] = [
                'menu_id' => $menu['menu_id'],
                'name' => $menu_name,
                'quantity' => $menu['quantity'],
                'price' => $menu_price,
                'options' => $detailed_options
            ];
        }

        // Calculate the order total
        $order_total = $sub_total + $request->delivery;

        // Update the cart data in the database
        Mobile_cart_model::where('id', $request->cart_id)->update([
            'customer_id' => $request->customer_id,
            'menus' => json_encode($detailed_menus),
//            'delivery' => $request->delivery,
            'sub_total' => $sub_total,
            'order_total' => $order_total,
            'comment' => $request->comment
        ]);

        $response = [
            'success' => true,
            'message' => "Cart updated successfully",
            'cart_id' => $request->cart_id
        ];

        return response()->json($response, 200);
    }
    public function delete_cart()
    {
        $request = request();
        $cart_id = $request->cart_id;
        Mobile_cart_model::where('id', $cart_id)->delete();
        $response = [
            'success' => true,
            'message' => "Cart deleted successfully"
        ];
        return response()->json($response, 200);
    }

    public function abcd()
    {
        $data = Order_menu_options_model::all();
        dd($data);
    }
}
