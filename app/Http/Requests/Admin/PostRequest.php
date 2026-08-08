<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Restaurant;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {   
        $platforms = ['facebook', 'instagram', 'x', 'google', 'tiktok', 'youtube', 'linkedin', 'whatsapp', 'threads', 'pinterest'];
        
        $rules = [
            'content'    => ['required', 'string', 'min:1', 'max:5000'],
            'platforms'  => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', 'string', 'in:' . implode(',', $platforms)],
            //'ai_image_url' => ['nullable', 'required_without:media'],
            'media'        => ['nullable', 'array'],
            'media.*'        => ['nullable', 'file'],
            
            'url' => ['nullable', 'url'],
            
            'category_id' => ['required', 'integer', 'exists:post_categories,id'],
            
            'schedule_mode' => ['nullable', 'boolean'],
            'schedule_at' => [
                'nullable',
                Rule::requiredIf(fn () => request('schedule_mode') == 1),
                Rule::when(
                    request('schedule_mode') == 1,
                    [
                        'date',
                        'after:' . Carbon::now()->addMinutes(10)->toDateTimeString(),
                    ]
                ),
            ],
            
            'expiry_mode' => ['nullable', 'boolean'],
            'expiry_at' => [
                'nullable',
                Rule::requiredIf(fn () => request('expiry_mode') == 1),
                Rule::when(
                    request('expiry_mode') == 1,
                    [
                        'date',
                        'after:now',
                        'after:schedule_at',
                    ]
                ),
            ],

            // WhatsApp has no public feed to post to - a WhatsApp "post"
            // here is a broadcast: an already-approved Message Template
            // (WhatsApp requires templates for messages outside an active
            // 24h customer conversation) sent to a list of numbers. See
            // WhatsAppPostService.
            'whatsapp_recipients' => [
                Rule::requiredIf(fn () => in_array('whatsapp', request('platforms', []))),
                'nullable',
                'string',
            ],
            'whatsapp_template_name' => [
                Rule::requiredIf(fn () => in_array('whatsapp', request('platforms', []))),
                'nullable',
                'string',
                'max:255',
            ],
            'whatsapp_template_language' => ['nullable', 'string', 'max:10'],
        ];

        // Add validation rules for each platform's pages
        foreach ($platforms as $platform) {
            $rules[$platform . '.pages'] = [
                'nullable',
                'array',
                // Only validate if the platform is selected in platforms array
                Rule::requiredIf(fn () => in_array($platform, request('platforms', []))),
            ];
            
            $rules[$platform . '.pages.*'] = [
                'integer',
                'exists:post_accounts,id',
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        $messages = [
            'content.required' => 'Please enter post content.',
            'content.min' => 'Post content must be at least 1 character.',
            'content.max' => 'Post content cannot exceed 5000 characters.',
            
            'platforms.required' => 'Please select at least one platform.',
            'platforms.min' => 'Please select at least one platform.',
            'platforms.*.in' => 'Invalid platform selected.',
            
            'category_id.required' => 'Please select a category.',
            'category_id.exists' => 'Selected category does not exist.',
            
            'media.max' => 'Media file cannot exceed 10MB.',
            
            'schedule_at.required_if' => 'Schedule date is required when scheduling is enabled.',
            'schedule_at.after' => 'Schedule date must be at least 10 minutes from now.',
            
            'expiry_at.required_if' => 'Expiry date is required when expiry is enabled.',
            'expiry_at.after' => 'Expiry date must be in the future.',

            'whatsapp_recipients.required' => 'Please enter at least one recipient phone number.',
            'whatsapp_template_name.required' => 'Please enter the name of an approved WhatsApp template.',
        ];

        // Add messages for each platform's pages
        $platforms = ['facebook', 'instagram', 'x', 'google', 'tiktok', 'youtube', 'linkedin', 'whatsapp', 'threads', 'pinterest'];
        foreach ($platforms as $platform) {
            $messages[$platform . '.pages.required_if'] = "Please select at least one page for {$platform}.";
            $messages[$platform . '.pages.*.exists'] = "Invalid page selected for {$platform}.";
        }

        return $messages;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convert comma-separated string to array if needed
        if ($this->has('platforms') && is_string($this->platforms)) {
            $this->merge([
                'platforms' => explode(',', $this->platforms)
            ]);
        }
    }

    /**
     * Get the validated data with additional processing.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        
        // Process page selections
        $selectedPages = [];
        $platforms = ['facebook', 'instagram', 'x', 'google', 'tiktok', 'youtube', 'linkedin', 'whatsapp', 'threads', 'pinterest'];
        
        foreach ($platforms as $platform) {
            if (isset($data[$platform]['pages']) && !empty($data[$platform]['pages'])) {
                $selectedPages[$platform] = $data[$platform]['pages'];
            }
        }
        
        // Add processed pages to data
        $data['selected_pages'] = $selectedPages;
        
        return $data;
    }
}