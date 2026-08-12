<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\BusinessCalendar;
use App\Support\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveBusinessCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();
        $calendarId = BusinessCalendar::query()->where('organization_id', $organizationId)->where('public_id', $this->route('calendar'))->value('id');

        return [
            'branch_public_id' => ['nullable', 'string', 'size:26', Rule::exists('branches', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('business_calendars', 'code')->where('organization_id', $organizationId)->ignore($calendarId)],
            'name_en' => ['required', 'string', 'max:160'],
            'name_my' => ['nullable', 'string', 'max:160'],
            'weekend_days' => ['required', 'array', 'max:7'],
            'weekend_days.*' => ['required', 'distinct', Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'dates' => ['present', 'array', 'max:366'],
            'dates.*.date' => ['required', 'date', 'distinct'],
            'dates.*.day_type' => ['required', Rule::in(['holiday', 'non_delivery', 'working_override'])],
            'dates.*.name_en' => ['required', 'string', 'max:160'],
            'dates.*.name_my' => ['nullable', 'string', 'max:160'],
            'version' => [Rule::requiredIf($this->route('calendar') !== null), 'nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $from = CarbonImmutable::parse($this->input('effective_from'))->startOfDay();
            $to = $this->input('effective_to') ? CarbonImmutable::parse($this->input('effective_to'))->startOfDay() : null;
            foreach ($this->input('dates', []) as $index => $exception) {
                $date = CarbonImmutable::parse($exception['date'])->startOfDay();
                if ($date->lt($from) || ($to && $date->gt($to))) {
                    $validator->errors()->add("dates.{$index}.date", 'Calendar exceptions must fall inside the effective date range.');
                }
            }
        }];
    }
}
