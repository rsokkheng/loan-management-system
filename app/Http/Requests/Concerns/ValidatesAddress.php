<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Province → district → commune → village, where each level must belong to the one above it.
 */
trait ValidatesAddress
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function addressRules(bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'address' => ['nullable', 'string', 'max:255'],
            'province_id' => [$presence, 'integer', Rule::exists('provinces', 'id')->withoutTrashed()],
            'district_id' => [$presence, 'integer', Rule::exists('districts', 'id')->withoutTrashed()->where('province_id', $this->integer('province_id'))],
            'commune_id' => [$presence, 'integer', Rule::exists('communes', 'id')->withoutTrashed()->where('district_id', $this->integer('district_id'))],
            'village_id' => [$presence, 'integer', Rule::exists('villages', 'id')->withoutTrashed()->where('commune_id', $this->integer('commune_id'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function addressAttributes(): array
    {
        return [
            'province_id' => __('Province / Capital'),
            'district_id' => __('District / Khan'),
            'commune_id' => __('Commune / Sangkat'),
            'village_id' => __('Village'),
        ];
    }
}
