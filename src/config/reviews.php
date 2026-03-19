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

  'photo_review' => [
    'max_files' => 5,
    'max_file_size_kb' => 4096,
    'max_input_file_size_kb' => 12288,
    'max_resolution' => [
      'width' => 1920,
      'height' => 1920,
    ],
    'jpeg_quality' => 84,
    'min_jpeg_quality' => 60,
  ],

  'generated_product_photos' => [
    'product_model' => \App\Models\Product::class,
    'moderation_batch' => 20,
    'image_driver' => env('REVIEWS_PRODUCT_PHOTO_IMAGE_DRIVER', 'gemini'),
    'image_model' => env('REVIEWS_PRODUCT_PHOTO_IMAGE_MODEL', 'gemini-2.5-flash-image'),
    'prompt_driver' => env('REVIEWS_PRODUCT_PHOTO_PROMPT_DRIVER', 'openai'),
    'prompt_model' => env('REVIEWS_PRODUCT_PHOTO_PROMPT_MODEL', 'gpt-4o-mini'),
    'image_driver_options' => [
      'gemini' => 'Gemini',
      'openai' => 'OpenAI',
    ],
    'image_model_options' => [
      'gemini-2.5-flash-image' => 'Gemini 2.5 Flash Image',
      'gemini-3.1-flash-image-preview' => 'Gemini 3.1 Flash Image Preview',
      'gemini-3-pro-image-preview' => 'Gemini 3 Pro Image Preview',
      'gpt-image-1' => 'GPT Image 1',
    ],
    'prompt_driver_options' => [
      'openai' => 'OpenAI',
      'gemini' => 'Gemini',
      'grok' => 'Grok',
    ],
    'prompt_model_options' => [
      'gpt-4o-mini' => 'GPT-4o mini',
      'gpt-4o' => 'GPT-4o',
      'gpt-5.1' => 'GPT-5.1',
      'gemini-2.5-flash' => 'Gemini 2.5 Flash',
      'gemini-2.5-pro' => 'Gemini 2.5 Pro',
      'gemini-3-flash-preview' => 'Gemini 3 Flash Preview',
      'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro Preview',
      'grok-2' => 'Grok 2',
    ],
    'prompt' => [
      'templates' => [
        'reference_instruction' => 'Use the uploaded packaging reference exactly.',
        'main_line' => 'Casual :orientation smartphone photo of :product_name :distance.',
        'scene_line' => 'Scene: :scene.',
        'camera_line' => 'Capture device and rendering: :camera.',
        'lighting_line' => 'Lighting: :lighting.',
        'package_state_line' => 'Packaging state: :package_state.',
        'defects_line' => 'Quality defects combined: :defects.',
        'closing_lines' => [
          ['line' => 'No professional sharpness.'],
          ['line' => 'No retouching.'],
          ['line' => 'No studio setup.'],
          ['line' => 'Ordinary or moderately worn interiors are common; avoid luxury spaces and avoid extreme filth.'],
          ['line' => 'If dust or dirt appears, keep it subtle, sparse, and mostly near the periphery of the frame.'],
        ],
      ],
      'variants' => [
        'orientation' => [
          ['text' => 'square', 'weight' => 4],
          ['text' => 'portrait', 'weight' => 3],
          ['text' => 'landscape', 'weight' => 3],
        ],
        'distance' => [
          ['text' => 'shot slightly too close', 'weight' => 3],
          ['text' => 'shot from medium distance', 'weight' => 4],
          ['text' => 'shot too close with clipped edges', 'weight' => 3],
        ],
        'camera' => [
          ['text' => 'older Xiaomi 2018 phone photo with weak dynamic range and mushy fine detail', 'weight' => 4],
          ['text' => 'budget Android phone shot with compression artifacts and noisy shadows', 'weight' => 3],
          ['text' => 'cheap smartphone camera with slightly greasy lens haze and softened corners', 'weight' => 2],
          ['text' => 'midrange older phone photo with oversharpening halos and unstable auto white balance', 'weight' => 2],
        ],
        'lighting' => [
          ['text' => 'dim room lit by one weak warm ceiling bulb', 'weight' => 4],
          ['text' => 'mixed indoor light with a dull yellow lamp and dark corners', 'weight' => 3],
          ['text' => 'low evening room light with weak window spill and underexposed background', 'weight' => 3],
          ['text' => 'uneven indoor light with shadows, weak exposure and a tired color cast', 'weight' => 2],
        ],
        'scene' => [
          ['text' => 'kitchen table in an ordinary apartment with simple everyday surroundings', 'weight' => 4],
          ['text' => 'small bedroom shelf in a modest but lived-in home interior', 'weight' => 3],
          ['text' => 'hallway cabinet in a mid-range apartment with a few everyday items nearby', 'weight' => 3],
          ['text' => 'living room side table in a normal home with slightly worn furniture', 'weight' => 3],
          ['text' => 'work desk in a regular apartment with cables, receipts, and casual household clutter', 'weight' => 2],
          ['text' => 'bathroom shelf in a standard apartment with common toiletries and mostly clean surfaces', 'weight' => 2],
          ['text' => 'windowsill in a modest apartment with faint dust only near the frame edges', 'weight' => 1],
        ],
        'defects' => [
          ['text' => 'off-center framing, soft focus, light hand-motion blur', 'weight' => 3],
          ['text' => 'slight tilt angle, mild blur, reduced sharpness and weak texture detail', 'weight' => 3],
          ['text' => 'small glare reflection, partial overexposure, low micro-contrast and slight distortion', 'weight' => 3],
          ['text' => 'visible sensor noise, imperfect focus lock, muddy shadows and compression artifacts', 'weight' => 3],
          ['text' => 'blown lamp highlights, dull shadows and mild lens haze', 'weight' => 2],
        ],
        'package_state' => [
          ['text' => 'package closed and fully visible', 'weight' => 4],
          ['text' => 'package in one hand, slightly rotated', 'weight' => 3],
          ['text' => 'package on table with a few tiny dust particles near the edge of the frame', 'weight' => 1],
          ['text' => 'package partly opened with realistic folds', 'weight' => 1],
        ],
      ],
      'prevent_immediate_repeat' => true,
      'repeat_penalty_factor' => 0.35,
    ],
    'excluded_product_ids' => array_values(array_filter(array_map('intval', explode(',', (string) env('REVIEWS_PRODUCT_PHOTO_EXCLUDED_IDS', ''))))),
    'watermark_crop_right_percent' => (float) env('REVIEWS_PRODUCT_PHOTO_CROP_RIGHT', 3),
    'watermark_crop_bottom_percent' => (float) env('REVIEWS_PRODUCT_PHOTO_CROP_BOTTOM', 3),
  ],

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
      'rules' => 'nullable|string|min:2|max:1000|required_without_all:video_url,photo_gallery'
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
    'review_type' => [
      'rules' => 'nullable|string|in:text,video,photo'
    ],
    'is_video' => [
      'rules' => 'nullable|boolean'
    ],
    'video_url' => [
      'rules' => 'nullable|url|max:2048|required_if:is_video,1,true,on|required_if:review_type,video'
    ],
    'video_title' => [
      'rules' => 'nullable'
    ],
    'video_poster' => [
      'rules' => 'nullable'
    ],
    'photo_gallery' => [
      'rules' => 'nullable|array|max:5|required_if:review_type,photo'
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
  ],

  'google' => [
    'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_url' => 'https://oauth2.googleapis.com/token',
    'accounts_api_base' => 'https://mybusinessaccountmanagement.googleapis.com/v1',
    'locations_api_base' => 'https://mybusinessbusinessinformation.googleapis.com/v1',
    'reviews_api_base' => 'https://mybusiness.googleapis.com/v4',
    'scopes' => [
      'https://www.googleapis.com/auth/business.manage',
    ],
    'access_type' => 'offline',
    'prompt' => 'consent',
    'token_leeway' => 60,
    'accounts_page_size' => 20,
    'locations_page_size' => 100,
    'reviews_page_size' => 50,
    'reviews_order_by' => 'updateTime desc',
    'location_read_mask' => 'name,title,storeCode,languageCode,storefrontAddress',
    'per_page' => 12,
  ],
];
