<?php

return [
  'enable_review_type' => false,
  'enable_rating' => true,
  'enable_likes' => true,

  // Is default review moderated
  'is_moderated_default' => false,

  'can_moderate' => fn () => backpack_auth()->check(),

  // CATALOG
  'per_page' => 12,

  // OWNER
  'owner_model' => null,

  //GUARD
  'auth_guard' => 'profile',

  // Seed batabase
  'reviewable_model' => null,

  'rating_type' => 'detailed', // 'detailed' - allow multiple rating params, 'simple' - allow only single digit  

  'detailed_rating_params' => [
    'param_1' => 'label_1',
    'param_2' => 'label_2',
    'param_3' => 'label_2',
  ],

  'rating_length' => 5,

  // Override
  'review_model' => 'Backpack\Reviews\app\Models\Review',
  'review_controller_api' => 'Backpack\Reviews\app\Http\Controllers\Api\ReviewController',

  // Resources
  'resource' => [
    'small' => 'Backpack\Reviews\app\Http\Resources\ReviewSmallResource',
    'medium' => 'Backpack\Reviews\app\Http\Resources\ReviewMediumResource',
    'large' => 'Backpack\Reviews\app\Http\Resources\ReviewLargeResource'
  ],
  
  // Reviewable
  'reviewable_types_list' => [
    'Backpack\Store\app\Models\Product' => 'Товар',
    'Backpack\Articles\app\Models\Article' => 'Статья'
  ],

  // Validation fields
  'fields' => [
    'text' => [
      'rules' => 'required|string|min:2|max:1000'
    ],
    'parent_id' => [
      'rules' => 'nullable|integer'
    ],
    'reviewable_id' => [
      'rules' => 'nullable|integer'
    ],
    'reviewable_type' => [
      'rules' => 'nullable|string|min:2|max:255'
    ],
    'rating' => [
      'rules' => 'nullable|integer'
    ],
    'owner' => [
      // 'rules' => 'array:city,address,zip,method,warehouse',
      'store_in' => 'extras',
      'id' => [
        'rules' => 'required_if:provider,id|integer'
      ],
      'name' => [
        'rules' => 'required_if:provider,data|string|min:2|max:100'
      ],
      'photo' => [
        'rules' => 'nullable|string'
      ],
      'email' => [
        'rules' => 'nullable|email'
      ],
    ],
    'provider' => [
      'rules' => 'required|string|in:id,data,auth'
    ],
    'extras' => [
      'rules' => 'nullable|array'
    ]
  ]
];
