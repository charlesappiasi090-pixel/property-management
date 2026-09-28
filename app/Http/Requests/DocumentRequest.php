<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/updating a document.
 *
 * The document is always attached to a parent model (property, tenant, lease,
 * payment, expense, maintenance request).  The request validates that the
 * parent exists and belongs to the active business, and that the uploaded file
 * meets basic criteria.
 */
class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $parent = $this->parent();

        return $parent instanceof \Illuminate\Database\Eloquent\Model
            && ($this->user()?->can('create', Document::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'parent_type' => [
                'required',
                'string',
                Rule::in([
                    Property::class,
                    Tenant::class,
                    Lease::class,
                    Payment::class,
                    Expense::class,
                    MaintenanceRequest::class,
                ]),
            ],
            'parent_id' => [
                'required',
                'exists:function(' . $this->input('parent_type') . ', id)',
                // Scope the parent to the active business – the route model
                // binding already does this, but we double‑check here.
                'whereHas', function ($q) {
                    $q->where('business_id', app(\App\Support\Tenancy\BusinessContext::class)->id());
                },
            ],
            'tag' => ['nullable', 'string', 'max:50'],

            'file' => [
                'required',
                'file',
                'mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'max:10240', // 10 MB max
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Only PDF, JPG, PNG, DOC and DOCX files are allowed.',
            'file.max'   => 'Maximum file size is 10 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_type' => 'parent model type',
            'parent_id'   => 'parent model id',
            'tag'         => 'tag (e.g. lease, inspection)',
            'file'        => 'document file',
        ];
    }

    /**
     * Values safe to hand to `Document`, normalised for storage.
     */
    public function documentAttributes(): array
    {
        $attributes = $this->safe()->only([
            'parent_type',
            'parent_id',
            'tag',
        ]);

        // File: store via the controller, we just return the uploaded file info.
        if ($this->hasFile('file')) {
            $file = $this->file('file');

            $attributes['storage_path'] = 'docs/' . $file->hashName();
            $attributes['original_name'] = $file->getClientOriginalName();
            $attributes['mime_type'] = $file->getClientMimeType();
            $attributes['size'] = $file->getSize();
        }

        return $attributes;
    }

    protected function parent(): ?\Illuminate\Database\Eloquent\Model
    {
        $type = $this->input('parent_type');
        $id   = $this->input('parent_id');

        if (! $type || ! $id) {
            return null;
        }

        $model = new $type; // just to get the class name; we'll resolve below

        return $model::find($id);
    }
}