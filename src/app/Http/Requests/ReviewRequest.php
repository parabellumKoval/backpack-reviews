<?php

namespace Backpack\Reviews\app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $type = $this->type;
        
        $this->redirect = url()->previous().'#review_' . $type;
      
        return [
          'text' => 'nullable|string|min:2|max:1000|required_unless:is_video,1,true,on',
          'is_video' => 'nullable|boolean',
          'video_url' => 'nullable|url|max:2048|required_if:is_video,1,true,on',
          'video_title' => 'nullable',
          'video_poster' => 'nullable',
          'lang' => 'nullable|string|min:2|max:5',
          'country' => 'nullable|string|size:2',
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'text_review_text' => 'review text'
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            //
        ];
    }
}
