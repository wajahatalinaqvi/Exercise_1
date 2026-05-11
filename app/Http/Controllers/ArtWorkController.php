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
            $createdAt = Carbon::parse($validate->input('input.created_at'));
            $validDays = $validate->input('input.valid_days');
            $currentDate = Carbon::parse($validate->input('input.current_date'));
            $expiryDate = $createdAt->copy()->addDays($validDays);
            $isValid = $currentDate->lessThanOrEqualTo($expiryDate);

           
            return response()->json([
                'success' => true,
                // 'data'    => ["valid" => $isValid],
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
}
