@props(['model', 'required' => false])

@php
    use App\Models\Commune;
    use App\Models\District;
    use App\Models\Province;
    use App\Models\Village;

    $provinceId = (int) old('province_id', $model->province_id);
    $districtId = (int) old('district_id', $model->district_id);
    $communeId = (int) old('commune_id', $model->commune_id);

    $levels = [
        ['name' => 'province_id', 'label' => __('Province / Capital'), 'options' => Province::options(), 'url' => null, 'child' => 'district_id'],
        ['name' => 'district_id', 'label' => __('District / Khan'), 'options' => District::options('province_id', $provinceId), 'url' => route('locations.districts'), 'child' => 'commune_id'],
        ['name' => 'commune_id', 'label' => __('Commune / Sangkat'), 'options' => Commune::options('district_id', $districtId), 'url' => route('locations.communes'), 'child' => 'village_id'],
        ['name' => 'village_id', 'label' => __('Village'), 'options' => Village::options('commune_id', $communeId), 'url' => route('locations.villages'), 'child' => null],
    ];
@endphp

{{-- Cascading address dropdowns; admin.js reloads each level when its parent changes. --}}
<div class="row" data-address-fields>
    @foreach ($levels as $level)
        <x-form.select :name="$level['name']" :label="$level['label']" :value="$model->{$level['name']}" :options="$level['options']"
            :required="$required" :placeholder="__('Select…')" wrapper="col-md-6 col-xl-3 mb-3"
            :data-location-url="$level['url']" :data-location-child="$level['child']" />
    @endforeach
    <x-form.input name="address" :label="__('House No. / Street')" :value="$model->address" wrapper="col-12 mb-3" :placeholder="__('e.g. House 12, Street 271')" />
</div>
