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
  // 'reviewable_types_list' => [
  //   'Backpack\Store\app\Models\Product' => 'Товар',
  //   'Backpack\Articles\app\Models\Article' => 'Статья'
  // ],

  // Reviewable
  'reviewable_types_list' => [
    'product' => [
      'model' => 'App\Models\Product',
      'name' => 'Товар',
      'name_plur' => 'Товары',
      'fetch_helper_key' => 'product_base',
    ],
    'article' => [
      'model' => 'Backpack\Articles\app\Models\Article',
      'name' => 'Статья',
      'name_plur' => 'Статьи',
    ]
  ],

  'global_country_code' => 'zz',
  
  'morph_aliases' => [
    'App\Models\Product' => [
      'model' => 'Backpack\Store\app\Models\Catalog',
      'key' => 'group_id',
      'country_field' => 'country_code'
    ],
    'Backpack\Store\app\Models\Product' => [
      'model' => 'Backpack\Store\app\Models\Catalog',
      'key' => 'group_id',
      'country_field' => 'country_code'
    ]
  ],

  // Reviewable Cards Configuration
  'reviewable_cards_config' => [
    'App\Models\Product' => [
      'view' => 'store::reviews.reviewable_card',
      'edit_route' => 'product.edit',
    ],
    'Backpack\Store\app\Models\Product' => [
      'view' => 'store::reviews.reviewable_card',
      'edit_route' => 'product.edit',
    ],
    'Backpack\Articles\app\Models\Article' => [
      'view' => 'articles::reviews.reviewable_card',
      'edit_route' => 'article.edit',
    ],
  ],

  // Validation fields
  'fields' => [
    'text' => [
      'rules' => 'nullable|string|min:2|max:1000|required_unless:is_video,1,true,on'
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
    'is_video' => [
      'rules' => 'nullable|boolean'
    ],
    'video_url' => [
      'rules' => 'nullable|url|max:2048|required_if:is_video,1,true,on'
    ],
    'video_title' => [
      'rules' => 'nullable'
    ],
    'video_poster' => [
      'rules' => 'nullable'
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
