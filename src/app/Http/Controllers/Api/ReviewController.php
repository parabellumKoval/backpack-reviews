<?php

namespace Backpack\Reviews\app\Http\Controllers\Api;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

// EXCEPTIONS
use Rd\app\Exceptions\DetailedException;
use \Illuminate\Database\Eloquent\ModelNotFoundException;

use \Rd\app\Traits\RdTrait;

class ReviewController extends \App\Http\Controllers\Controller
{
  use RdTrait;

  protected $review_model = '';

  public $rd_fields = null;

  public function __construct() {
    $this->review_model = config('backpack.reviews.review_model', 'Backpack\Reviews\app\Models\Review');

    // Rd 
    $this->rd_fields = config('backpack.reviews.fields');
  }
  
  /**
   * index
   *
   * @param  mixed $request
   * @return void
   */
  public function index(Request $request, bool $json_response = false) {
    
    $reviewable_id = $request->input('reviewable_id');

    if($request->input('reviewable_slug') && $request->input('reviewable_type')) {
      $reviewable = $request->input('reviewable_type')::where('slug', $request->input('reviewable_slug'))->first();
      $reviewable_id = $reviewable? $reviewable->id: null;
    }

    $reviews = $this->review_model::query()
              ->select('ak_reviews.*')
              ->distinct('ak_reviews.id')
              ->root()
              ->when($reviewable_id, function($query) use($reviewable_id){
                $query->where('ak_reviews.reviewable_id', $reviewable_id);
              })
              ->when($request->input('reviewable_type'), function($query) use($request){
                $query->where('ak_reviews.reviewable_type', $request->input('reviewable_type'));
              })
              ->when($request->input('is_moderated'), function($query) use($request){
                $query->where('ak_reviews.is_moderated', $request->input('is_moderated'));
              })
              ->orderBy('created_at', 'desc');

    $per_page = $request->input('per_page')? $request->input('per_page'): config('backpack.reviews.per_page', 12);
    $reviews = $reviews->paginate($per_page);

    return config('backpack.reviews.resource.medium', 'Backpack\Reviews\app\Http\Resources\ReviewMediumResource')::collection($reviews);
  }

 /** 
 * Create new review
 *
 * @param Request $request
 * @return Review
 **/

  public function create(Request $request) {
    try {
      // Validate data using RdTrait validation method
      $data = $this->validateData($request);
    } catch(DetailedException $e) {
      return response()->json([
        'message' => $e->getMessage(),
        'options' => $e->getOptions()
      ], $e->getCode());
    }

    // Create new model
    $review = new $this->review_model();

    $review = $this->prepareModel($review);

    // Fill model with data using RdTrait
    $review = $this->setRequestFields($review, $data);

    // SET EXTRAS FROM REQUEST
    try {
      [$owner_id, $owner_model] = $this->getUserData($data);
      $review->owner_id = $owner_id;

      if($owner_model) {
        $review->extras = $this->addToExtras($review->extras, 'owner', $owner_model->toArray());
      }
    }catch(\Exception $e) {
      return response()->json($e->getMessage(), $e->getCode());
    }

    // CREATE REVIEW
    try {
      // Save order
      $review->save();
    }catch(\Expression $e) {
      return response()->json($e->getMessage(), $e->getCode());
    }

    return response()->json($review);
  }
     
  /**
   * prepareModel
   *
   * @param  mixed $model
   * @return void
   */
  protected function prepareModel($model) {
    if(config('backpack.reviews.is_moderated_default', false)) {
      $model->is_moderated = true;
    }else {
      $model->is_moderated = false;
    }

    return $model;
  }
  
  /**
   * addToExtras
   *
   * @param  mixed $extras
   * @param  mixed $key
   * @param  mixed $data
   * @return void
   */
  protected function addToExtras(array $extras, string $key, array $data) {
    // add new data
    $extras[$key] = $data;

    return $extras;
  } 

  /**
   * getUserData
   *
   * @param  mixed $data
   * @return void
   */
  protected function getUserData(array $data = null) {
    
    // INIT OWNER MODEL
    $owner = [
      'id' => null,
      'model' => null
    ];

    // Set owner by id
    if($data['provider'] === 'id')
    {
      try{
        $class = config('backpack.reviews.owner_model', 'Backpack\Profile\app\Models\Profile');
        $owner_model = $class::findOrFail((int)$data['owner']['id']);
      }catch(ModelNotFoundException $e) {
        throw new \Exception($e->getMessage(), $e->getCode());
      }

      $owner = [
        'id' => $owner_model->id,
        'model' => $owner_model
      ];

    }

    // Set owner from auth session
    else if($data['provider'] === 'auth') 
    {
      if(!Auth::guard(config('backpack.reviews.auth_guard', 'profile'))->check()){
        throw new \Exception('User not authenticated', 404);
      }

      $owner_model = Auth::guard(config('backpack.reviews.auth_guard', 'profile'))->user();

      $owner = [
        'id' => $owner_model->id,
        'model' => $owner_model
      ];
    }

    // Set owner by exterior data
    else if($data['provider'] === 'data')
    {
      $owner = [
        'id' => null,
        'model' => null
      ];
    }

    return [$owner['id'], $owner['model']];
  }

  /**
   * likeOrDislike
   *
   * @param  mixed $request
   * @param  mixed $id
   * @return void
   */
  public function likeOrDislike(Request $request, $id) {
    $data = $request->only(['direction', 'type']);

    $validator = Validator::make($data, [
      'direction' => [ 
        'nullable',
        Rule::in(['minus', 'plus'])
      ],
      'type' => [
        'required',
        Rule::in(['likes', 'dislikes'])
      ]
    ]);

    $dir = isset($data['direction'])? $data['direction']: 'plus';

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    // if(!Auth::guard(config('backpack.reviews.auth_guard', 'profile'))->check()){
    //   return response()->json('User not authenticated', 401);
    // }

    try {
      $review_model = $this->review_model::findOrFail($id);
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }
    
    if($dir === 'plus')
      $review_model->{$data['type']} = $review_model->{$data['type']} + 1;
    else
      $review_model->{$data['type']} = $review_model->{$data['type']} > 0? $review_model->{$data['type']} - 1: 0;


    $review_model->save();

    return response()->json([
        $data['type'] => $review_model->{$data['type']}
    ]);
  }

}
