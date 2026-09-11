<?php

namespace App\Http\Controllers\Property;

use App\Actions\Property\SearchProperties;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\SearchPropertiesRequest;
use App\Http\Resources\Property\PropertyResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PropertyController extends Controller
{
    public function __invoke(SearchPropertiesRequest $request,
                             SearchProperties        $searchProperties): AnonymousResourceCollection
    {
        $properties = $searchProperties->handle([
            'check_in' => $request->validated('check_in'),
            'check_out' => $request->validated('check_out'),
            'guests' => $request->guests(),
            'city' => $request->validated('city'),
        ], $request->perPage());

        return PropertyResource::collection($properties);
    }
}
