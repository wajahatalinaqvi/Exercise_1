<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

    if(empty($valideTiers)){
        return response()->json(
            [
                'success' => false,
                'data' => null,
                'error' => 'no valid tiers found'
            ]
        );
    }
    else{
        return response()->json(
            [
                'success' => true,
                'data' => ['price' => collect($valideTiers)->last()['price']],
                'error' => null
            ]
        );
    }
    }
    
    public function validateCart(Request $request){
        $request->validate([
            'input' => 'required|array',
            'input.*.id' => 'required|integer',
            'input.*.required' => 'required|boolean',
            'input.*.done' => 'required|boolean',
        ]);

        $inputs = collect($request->input('input'))->sortBy('id');
        $inValidItem= $inputs->filter(function($item){
            return $item['required'] && !$item['done'];
        })->pluck('id')->toArray();
        if(empty($inValidItem)) {
            return response()->json([
                'success' => true,
                'data'=>[
                    'valid' => true,
                'invalid_items' => [],      
                ],
               
                'error'=>'No invalid items found'
            ]);
        }
        return response()->json([
            'success' => true,
            'data'=>[

            'valid' => empty($inValidItem),
            'invalid_items' => $inValidItem,
            ],
            'error'=>null
        ]);
    }


    public function multiVendorAllocation(Request $request){
        $request-> validate([
            'input' => 'required|array',
            'input.order_qty' => 'required|integer',
            'input.vendors' => 'required|array',
            'input.vendors.*.id' => 'required|integer',
            'input.vendors.*.stock' => 'required|integer',
        ]);


        $orderQty = $request->input('input.order_qty');
        $vendors =collect($request->input('input.vendors'))->sortBy('stock');
        $remainingQty = $orderQty;
        $allocations =$vendors->map(function($vendor) use(&$remainingQty){
            if($remainingQty <= 0){
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
        if($remainingQty > 0){
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

    public function discountConflictResolver(Request $request){
    $validated =    $request->validate([
            "input"=>'required|array',
            "input.price"=>'required|numeric|min:0',
            'input.discounts'=>'required|array',
            'input.discounts.*.type'=>'required|string|in:percentage,flat',
            'input.discounts.*.value'=>'required|numeric|min:0',
        ]);
       
        $price = $validated['input']['price'];
        $discounts = collect($validated['input']['discounts']);
        $prices = $discounts->map(function($discount) use ($price){
            if($discount['type'] === 'percentage'){
                return $price - ($price * ($discount['value'] / 100));
            }
            if ($discount['type'] === 'flat') {
                return max(0, $price - $discount['value']);
            }
            else {
                return max(0, $price - $discount['value']);
            }
        });
       $finalPrices= $prices->map(function($discountedPrice) {
            return max(0, $discountedPrice);
        });
       
      $bestPrice=$finalPrices->min();
        return response()->json([
            'success' => true,
            'data' => ['final_price' => $bestPrice],
            'error' => null
        ]);


    }
}

