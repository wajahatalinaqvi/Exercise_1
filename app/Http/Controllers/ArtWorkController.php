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
                'valid' => true,
                'invalid_items' => []
            ]);
        }
        return response()->json([
            'success' => true,
            'valid' => empty($inValidItem),
            'invalid_items' => $inValidItem
        ]);
    }
}

