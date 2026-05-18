<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Nette\Schema\ValidationException;

class ArtWorkController extends Controller
{
    //

    public function index(Request $request)
    {
        $request->validate([
            'input' => 'required|array',
            'input.*.id' => 'required|integer',
            'input.*.approved' => 'required|boolean',
            'input.*.rejected' => 'required|boolean',
            'input.*.time' => 'required|integer',
        ]);
        $inputs = collect($request->input('input'))->sortBy('id');

        $validateInput = [];

        foreach ($inputs as $input) {
            if ($input['approved'] && !$input['rejected']) {
                $validateInput[] = $input;
            }
        }
        return $this->response($validateInput);
    }

    public function response($validate)
    {
        if ($validate != null) {
            return response()->json(
                [
                    'success' => true,
                    'data' => ['id' => collect($validate)->sortBy('time')->pluck('id')->last()],
                    'error' => null
                ]
            );
        } else {
            return response()->json(
                [
                    'success' => false,
                    'data' => null,
                    'error' => 'No approved artwork found'
                ]
            );
        }
    }

    public function pricing(Request $request)
    {
        $request->validate([
            'input' => 'required|array',
            'input.quantity' => 'required|integer',
            'input.tiers' => 'required|array',
            'input.tiers.*.min' => 'required|integer',
            'input.tiers.*.price' => 'required|numeric',



        ]);
        $minQt = $request->input('input.quantity');
        $sortTier = collect($request->input('input.tiers'))->sortBy('min');

        $valideTiers = [];
        foreach ($sortTier as $tier) {
            if ($minQt >= $tier['min']) {
                $valideTiers[] = $tier;
            }
        }

        if (empty($valideTiers)) {
            return response()->json(
                [
                    'success' => false,
                    'data' => null,
                    'error' => 'no valid tiers found'
                ]
            );
        } else {
            return response()->json(
                [
                    'success' => true,
                    'data' => ['price' => collect($valideTiers)->last()['price']],
                    'error' => null
                ]
            );
        }
    }

    public function validateCart(Request $request)
    {
        $request->validate([
            'input' => 'required|array',
            'input.*.id' => 'required|integer',
            'input.*.required' => 'required|boolean',
            'input.*.done' => 'required|boolean',
        ]);

        $inputs = collect($request->input('input'))->sortBy('id');
        $inValidItem = $inputs->filter(function ($item) {
            return $item['required'] && !$item['done'];
        })->pluck('id')->toArray();
        if (empty($inValidItem)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'invalid_items' => [],
                ],

                'error' => 'No invalid items found'
            ]);
        }
        return response()->json([
            'success' => true,
            'data' => [

                'valid' => empty($inValidItem),
                'invalid_items' => $inValidItem,
            ],
            'error' => null
        ]);
    }


    public function multiVendorAllocation(Request $request)
    {
        $request->validate([
            'input' => 'required|array',
            'input.order_qty' => 'required|integer',
            'input.vendors' => 'required|array',
            'input.vendors.*.id' => 'required|integer',
            'input.vendors.*.stock' => 'required|integer',
        ]);


        $orderQty = $request->input('input.order_qty');
        $vendors = collect($request->input('input.vendors'))->sortBy('stock');
        $remainingQty = $orderQty;
        $allocations = $vendors->map(function ($vendor) use (&$remainingQty) {
            if ($remainingQty <= 0) {
                return [
                    'id' => $vendor['id'],
                    'allocated' => 0
                ];
            }
            $allocated = min($vendor['stock'], $remainingQty);
            $remainingQty -= $allocated;
            $vendor_id = $vendor['id'];

            return [
                'id' => $vendor_id,
                'allocated' => $allocated
            ];
        });
        $sortedAllocations = $allocations->sortBy('id')->values()->all();
        if ($remainingQty > 0) {
            return response()->json([
                'success' => true,
                'data' => null,
                'error' => 'Insufficient stock to fulfill the order'
            ]);
        }
        return response()->json([
            'success' => true,
            'data' => $sortedAllocations,
            'error' => null
        ]);
    }

    public function discountConflictResolver(Request $request)
    {
        $validated =    $request->validate([
            "input" => 'required|array',
            "input.price" => 'required|numeric|min:0',
            'input.discounts' => 'required|array',
            'input.discounts.*.type' => 'required|string|in:percentage,flat',
            'input.discounts.*.value' => 'required|numeric|min:0',
        ]);

        $price = $validated['input']['price'];
        $discounts = collect($validated['input']['discounts']);
        $prices = $discounts->map(function ($discount) use ($price) {
            if ($discount['type'] === 'percentage') {
                return $price - ($price * ($discount['value'] / 100));
            }
            if ($discount['type'] === 'flat') {
                return max(0, $price - $discount['value']);
            } else {
                return max(0, $price - $discount['value']);
            }
        });
        $finalPrices = $prices->map(function ($discountedPrice) {
            return max(0, $discountedPrice);
        });

        $bestPrice = $finalPrices->min();
        return response()->json([
            'success' => true,
            'data' => ['final_price' => $bestPrice],
            'error' => null
        ]);
    }

    public function flowValidator(Request $request)
    {
        $validate =   $request->validate([
            'input' => 'required|array',
            'input.steps' => 'required|array',
            'input.steps.*.id' => 'required',
            'input.steps.*.depends_on' => 'nullable|string',
        ]);

        $ids = collect($validate['input']['steps'])->pluck('id')->toArray();
        $steps = collect($validate['input']['steps']);
        // check duplicate id
        if (count($ids) !== count(array_unique($ids))) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Duplicate step ids found'
            ]);
        }

        // first dependency should be null or empty and second step should be dependent to the first step if it gives null then give errror
        foreach ($steps as $step) {
            if (empty($step['depends_on'])) {
                continue;
            }
            $dependencies = explode(',', $step['depends_on']);
            $firstDependency = $dependencies[0] ?? null;
            if ($firstDependency && !empty($firstDependency) && !in_array($firstDependency, $ids)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => "Invalid first dependency: Step {$step['id']} has an invalid first dependency {$firstDependency}"
                ]);
            }
            if (in_array($step['id'], $dependencies)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => "Invalid dependency: Step {$step['id']} cannot depend on itself"
                ]);
            }
            foreach ($dependencies as $dependency) {

                if (!in_array($dependency, $ids)) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'error' => "Invalid dependency: Step {$step['id']} depends on non-existent step {$dependency}"
                    ]);
                }
                if (array_search($dependency, $ids) > array_search($step['id'], $ids)) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'error' => "Invalid dependency: Step {$step['id']} cannot depend on future step {$dependency}"
                    ]);
                }
            }
        }
        return response()->json([
            'success' => true,
            'data' => ['valid' => true],
            'error' => null
        ]);
    }
    public function inventoryReservation(Request $request)
    {
        $validate = $request->validate([
            'input' => 'required|array',
            'input.stock' => 'required|integer|min:0',
            'input.requests' => 'required|array',
        ]);

        $stock = $validate['input']['stock'];
        $requests = $validate['input']['requests'];
        $remainingStock = $stock;
        $results = [];
        foreach ($requests as $req) {
            if (!is_int($req) || $req < 0) {
                $results[] = false;
                continue;
            }
            if ($remainingStock >= $req) {
                $results[] = true;
                $remainingStock -= $req;
            } else {
                $results[] = false;
            }
        }
        return response()->json([
            'success' => true,
            'data' => $results,
            'error' => null
        ]);
    }
    public function shipmentTracker(Request $request)
    {
        try {
            $validated = $request->validate([
                'input.ordered' => 'required|integer|min:1',
                'input.shipped' => 'required|array|min:1',
                'input.shipped.*' => 'integer|min:0'
            ], [
                'input.ordered.required' => 'Ordered quantity is required.',
                'input.ordered.integer' => 'Ordered quantity must be an integer.',
                'input.ordered.min' => 'Ordered quantity must be at least 1.',
                'input.shipped.required' => 'Shipped array is required.',
                'input.shipped.array' => 'Shipped must be an array.',
                'input.shipped.min' => 'At least one shipped entry is required.',
                'input.shipped.*.integer' => 'Each shipped quantity must be an integer.',
                'input.shipped.*.min' => 'Shipped quantities cannot be negative.'
            ]);

            $ordered = $validated['input']['ordered'];
            $shipped = $validated['input']['shipped'];

            $shippedArray = array_sum($shipped);
            $totalShipped = $ordered - $shippedArray;
            $remaining = max(0, $totalShipped);

            return response()->json([
                'success' => true,
                'data'    => ['remaining' => $remaining],
                'error'   => null
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'Validation failed',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'An unexpected error occurred.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function webhookDeduplicator(Request $request)
    {

        try {
            $validate = Validator::make($request->all(), [
                'input' => 'required|array',
                'input.*.id' => 'required|string',
                'input.*.time' => 'required|numeric|min:0',
            ], [
                'input.required' => 'Input array is required.',
                'input.array' => 'Input must be an array.',
                'input.*.id.required' => 'Each item must have an id.',
                'input.*.id.string' => 'Each id must be a string.',
                'input.*.time.required' => 'Each item must have a timestamp.',
                'input.*.time.numeric' => 'Each timestamp must be a numeric value.',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'success' => false,
                    'data'    => null,
                    'error'   => $validate->errors()->first(),
                ], 422);
            }
            $inputs = collect($request->input('input'))->sortBy('time');
            $uniqueIds = $inputs->pluck('id')->unique()->values()->all();
            return response()->json([
                'success' => true,
                'data'    => $uniqueIds,
                'error'   => null,
            ], 200);
        }
    
        catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'An unexpected error occurred.',
                'message' => "Something went wrong while processing the request."
            ], 500);
        }
    }

    public function quoteExpiryEngine(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'input' => 'required|array',
                'input.created_at' => 'required|date_format:Y-m-d',
                'input.valid_days' => 'required|integer|min:0',
                'input.current_date' => 'required|date_format:Y-m-d',
            ], [
                'input.required' => 'Input array is required.',
                'input.array' => 'Input must be an array.',
                'input.created_at.required' => 'Created date is required.',
                'input.created_at.date' => 'Created date must be a valid date.',
                'input.created_at.date_format' => 'Created date must be in Y-m-d format.',
                'input.valid_days.required' => 'Valid days is required.',
                'input.valid_days.integer' => 'Valid days must be an integer.',
                'input.valid_days.min' => 'Valid days cannot be negative.',
                'input.current_date.required' => 'Current date is required.',
                'input.current_date.date' => 'Current date must be a valid date.',
                'input.current_date.date_format' => 'Current date must be in Y-m-d format.',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'success' => false,
                    'data'    => null,
                    'error'   => $validate->errors()->first(),
                ], 422);
            }
            $validated = $validate->validated();
            $createdAt = Carbon::parse($validated['input']['created_at']);
            $validDays = $validated['input']['valid_days'];
            $currentDate = Carbon::parse($validated['input']['current_date']);
            $expiryDate = $createdAt->copy()->addDays($validDays);
            $isValid = $currentDate->lessThanOrEqualTo($expiryDate);

           
            return response()->json([
                'success' => true,
                'data'    => ["valid" => $isValid],
                'error'   => null,
            ], 200);

        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'An unexpected error occurred.',
                'message' => "Something went wrong while processing the request."
            ], 500);
        }
    }


   
    public function productVisibilityEngine(Request $request){

     try {
   $validate = Validator::make($request->all(),[
        'input'=>'required|array',
        'input.customer.tags'=>'required|array',
        'input.customer.tags.*'=>'required|string',
        'input.products'=>'required|array',
        'input.products.*.id'=>'required|integer',
        'input.products.*.allow'=>'array|nullable',
        'input.products.*.allow.*'=>'required|string',
        'input.products.*.block'=>'array|nullable',
        'input.products.*.block.*'=>'required|string',
    ],[
        'input.required'=>'Input array is required.',
        'input.array'=>'Input must be an array.',
        'input.customer.tags.required'=>'Customer tags are required.',
        'input.customer.tags.array'=>'Customer tags must be an array.',
        'input.customer.tags.*.required'=>'Each customer tag is required.',
        'input.customer.tags.*.string'=>'Each customer tag must be a string.',
        'input.products.required'=>'Products array is required.',
        'input.products.array'=>'Products must be an array.',
        'input.products.*.id.required'=>'Each product must have an id.',
        'input.products.*.id.integer'=>'Each product id must be an integer.',
        'input.products.*.allow.required'=>'Each product must have an allow list.',
        'input.products.*.allow.array'=>'Each product allow list must be an array.',
        'input.products.*.allow.*.required'=>'Each allow tag is required.',
        'input.products.*.allow.*.string'=>'Each allow tag must be a string.',
        'input.products.*.block.required'=>'Each product must have a block list.',
        'input.products.*.block.array'=>'Each product block list must be an array.',
        'input.products.*.block.*.required'=>'Each block tag is required.',
        'input.products.*.block.*.string'=>'Each block tag must be a string.',
    ]);
    if($validate->fails()){
        return response()->json([
            'success'=>false,
            'data'=>null,
            'error'=> $validate->errors()->first(),
        ],422);
    }
    $validated = $validate->validated();
    $customerTags = collect($validated['input']['customer']['tags']);
    $visibleProducts = collect($validated['input']['products'])->filter(function($product)use ($customerTags){
        $allow = collect($product['allow'] ?? []);
        $block = collect($product['block'] ?? []);
     if($block->intersect($customerTags)->isNotEmpty()){
        return false;
     }
     if($allow->isEmpty()){
        return true;
     }

    //  if customer tags is not intersect with allow then that prodct is not visible.
        return $allow->intersect($customerTags)->isNotEmpty();
    })->pluck('id')->values()->all();
        
    return response()->json([
        'success'=>true,
        'data'=>$visibleProducts,
        'error'=>null,
    ],200);
    
     } catch (\Exception $th) {
        //throw $th;

        return response()->json([
            'success' => false,
            'data'    => null,
            'error'   => 'An unexpected error occurred.',
            'message' => "Something went wrong while processing the request."
        ], 500);
     }
   }


   public function  bundlePricingEngine(Request $request){

    try {
        
            $validate = Validator::make($request->all(),[
            "input"=>'required|array',
            'input.items'=>'required|array',
            'input.items.*.id'=>'required|integer',
            'input.items.*.price'=>'required|numeric|min:0',
            'input.bundle_price'=>'required|numeric|min:0',
            'input.apply_bundle'=>'required|boolean',
            

            ],
        [
                'input.required'=>'Input array is required.',
                'input.array'=>'Input must be an array.',
                'input.items.required'=>'Items array is required.',
                'input.items.array'=>'Items must be an array.',
                'input.items.*.id.required'=>'Each item must have an id.',
                'input.items.*.id.integer'=>'Each item id must be an integer.',
                'input.items.*.price.required'=>'Each item must have a price.',
                'input.items.*.price.float'=>'Each item price must be a float.',
                'input.items.*.price.min'=>'Each item price must be greater than or equal to 0.',
                'input.bundle_price.required'=>'Bundle price is required.',
                'input.bundle_price.float'=>'Bundle price must be a float.',
                'input.bundle_price.min'=>'Bundle price must be greater than or equal to 0.',
                'input.apply_bundle.required'=>'Apply bundle is required.',
                'input.apply_bundle.boolean'=>'Apply bundle must be a boolean.',
        ]);
                    if($validate->fails()){
                        return response()->json([
                            'success'=>false,
                            'data'=>null,
                            'error'=>$validate->errors()->first(),
                        ],422);
                    }
                    $validated = $validate->validated();    
                    $individualTotal = collect($validated['input']['items'])->sum('price');

                    $final_price =$individualTotal;

                    if($validated['input']['apply_bundle'] && $validated['input']['bundle_price'] < $individualTotal){
                        $final_price = $validated['input']['bundle_price'];
                    }
                    return response()->json([
                        'success'=>true,
                        'data'=>['final_price'=>$final_price],
                        'error'=>null,
                    ],200);

                    

                        } catch (\Exception $th) {
                        return response()->json([
                            'success'=>false,   
                            'data'=>null,
                            'error'=>$th->getMessage(),
                            'message'=>'An unexpected error occurred.',
                        ],500);

                        }

                    }



     public function mergeCarts(Request $request){
        try {
           $validate= Validator::make($request->all(),[
        'input'=> 'required|array',
        'input.guest'=>'array|nullable',
        'input.guest.*.id'=>'required|integer',
        'input.guest.*.qty'=>'required|integer|min:0',
        'input.user'=>'array|nullable',
        'input.user.*.id'=>'required|integer',
        'input.user.*.qty'=>'required|integer|min:0',
     ],[
        'input.required'=>'Input array is required.',
        'input.array'=>'Input must be an array.',
        'input.guest.required'=>'Guest cart is required.',
        'input.guest.array'=>'Guest cart must be an array.',    
        'input.guest.*.id.required'=>'Each guest cart item must have an id.',
        'input.guest.*.id.integer'=>'Each guest cart item id must be an integer.',
        'input.guest.*.qty.required'=>'Each guest cart item must have a quantity.',
        'input.guest.*.qty.integer'=>'Each guest cart item quantity must be an integer.',
        'input.guest.*.qty.min'=>'Each guest cart item quantity must be at least 0.',
        'input.user.required'=>'User cart is required.',
        'input.user.array'=>'User cart must be an array.',
        'input.user.*.id.required'=>'Each user cart item must have an id.',
        'input.user.*.id.integer'=>'Each user cart item id must be an integer.',
        'input.user.*.qty.required'=>'Each user cart item must have a quantity.',
        'input.user.*.qty.integer'=>'Each user cart item quantity must be an integer.',
        'input.user.*.qty.min'=>'Each user cart item quantity must be at least 0.',
        ]);
     if($validate->fails()){
        return response()->json([
            'success'=>false,
            'data'=>null,
            'error'=>$validate->errors()->first(),
        ],422);
     } else {

        $validated = $validate->validated();
        $guestCart = collect($validated['input']['guest']);
        $userCart = collect($validated['input']['user']);
        $mergeCart = $guestCart->merge($userCart)->groupBy('id')->map(function($items,$id){
            return [
                'id'=>$id,
                'qty'=>$items->sum('qty'),
            ];
        })->values()->all();
        
        return response()->json([
            'success'=>true,
            'error'=>null,
            'data'=>$mergeCart,
        ],200);
     }
        } catch (\Exception $th) {
            return response()->json([
                'success'=>false,   
                'data'=>null,
                'error'=>$th->getMessage(),
                'message'=>'An unexpected error occurred.',
            ],500);
        }

   
     }

     public function findtwoNumberIndices(Request $request){

     $validate = Validator::make($request->all(),[
        'input'=>'required|array',
        'input.nums'=>'required|array|min:2',
        'input.nums.*'=>'required|numeric',
        'input.target'=>'required|numeric',
     ],[
        'input.required'=>'Input array is required.',
        'input.array'=>'Input must be an array.',
        'input.nums.required'=>'Nums array is required.',
        'input.nums.array'=>'Nums must be an array.',
        'input.nums.min'=>'Nums array must have at least 2 elements.',
        'input.nums.*.required'=>'Each num is required.',
        'input.nums.*.numeric'=>'Each num must be numeric.',
        'input.target.required'=>'Target is required.',
        'input.target.numeric'=>'Target must be numeric.',

     ]);

     $validated = $validate->validated();
     $nums = $validated['input']['nums'];
     $target = $validated['input']['target'];
     $numToIndex = [];
     foreach($nums as $index=>$num){
        $complement = $target - $num;
        if(isset($numToIndex[$complement])){
            return response()->json([
                'success'=>true,
                'data'=>[$numToIndex[$complement],$index],
                'error'=>null,
            ],200);

            
            }
            $numToIndex[$num]=$index;
            }
            return response()->json([
               'success'=>false,
               'data'=>null,
               'error'=>'No two numbers sum up to the target.',
            ],200);
}


public function shippingEngineRule(){
    $validate =Validator::make(request()->all(),[
        'input'=> 'required|array',
        'input.order'=>'required|array',
'input.order.weight'=>'required|numeric|min:0',
'input.order.country'=>'required|string',
'input.rules'=>'required|array|min:1',
'input.rules.*.id'=>'required|integer',
'input.rules.*.max_weight'=>'nullable|numeri',
'input.rules.*.country'=>'nullable|string',
'input.rules.*.method'=>'required|string',
'input.rules.*.priority'=>'required|integer|min:0',
    ],[
        'input.required'=>'Input array is required.',
        'input.array'=>'Input must be an array.',
        'input.order.required'=>'Order object is required.',
        'input.order.array'=>'Order must be an array.',
        'input.order.weight.required'=>'Order weight is required.',
        'input.order.weight.numeric'=>'Order weight must be numeric.',
        'input.order.weight.min'=>'Order weight cannot be negative.',
        'input.order.country.required'=>'Order country is required.',
        'input.order.country.string'=>'Order country must be a string.',
        'input.rules.required'=>'Rules array is required.',
        'input.rules.array'=>'Rules must be an array.',
        'input.rules.min'=>'At least one rule is required.',
        'input.rules.*.id.required'=>'Each rule must have an id.',
        'input.rules.*.id.integer'=>'Each rule id must be an integer.',
        'input.rules.*.max_weight.numeric'=>'Rule max_weight must be numeric.',
        'input.rules.*.max_weight.min'=>'Rule max_weight cannot be negative.',
        'input.rules.*.country.string'=>'Rule country must be a string.',
        'input.rules.*.method.required'=>'Each rule must have a shipping method.',
        'input.rules.*.method.string'=>'Each rule method must be a string.',
        'input.rules.*.priority.required'=>'Each rule must have a priority.',
        'input.rules.*.priority.integer'=>'Each rule priority must be an integer.',
        'input.rules.*.priority.min'=>'Each rule priority cannot be negative.',

    ]);
    if($validate->fails()){
        return response()->json([
            'success'=>false,
            'data'=>null,       
            'error'=>$validate->errors()->first(),
        ],422);}

    $validated = $validate->validated();
    $orderWeight = $validated['input']['order']['weight'];
    $orderCountry = $validated['input']['order']['country'];
    $matchedRules = collect($validated['input']['rules'])->filter(function($rule) use ($orderWeight,$orderCountry){
        if(isset($rule['max_weight']) && $orderWeight > $rule['max_weight']){
            return false;
        }
        if(isset($rule['country']) && $orderCountry !== $rule['country']){
            return false;
        }
        return true;
    })->sortBy('priority')->first();

    if(empty($matchedRules)){
        return response()->json([
            'success'=>false,
            'data'=>null,
            'error'=>'No shipping method matches the order criteria.',
        ],200);
    }

    return response()->json([
        'success'=>true,
        'data'=>$matchedRules['method'],
        'error'=>null,
    ],200);

}
}