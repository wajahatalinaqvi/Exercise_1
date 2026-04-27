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

    public function response($validate){
           if ($validate != null) {
                return response()->json(
                    [
                        'success' => true,
                        'data' => ['id' => collect($validate)->sortBy('time')->pluck('id')->last()],
                        'error' => null
                    ]
                );
            }
             else  {
                return response()->json(
                    [
                        'success' => false,
                        'data' => null,
                        'error' => 'No approved artwork found'
                    ]
                );
          
        }
    }

}



