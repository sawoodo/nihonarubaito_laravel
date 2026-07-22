@extends('layouts.frontend')

@section('content')
    <div class="section wb no-top-padding">
        <div class="container">
            <div class="row">

                @if (session('success'))
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-success text-center">
                                {{ session('success') }}
                            </div>
                        </div>
                    </div>
                @endif

                <form action="{{ url('subscribe') }}" method="POST" class="submit-form customform loginform">
                    @csrf
                    <h1 class="h4">{{ $content->form_heading }}</h1>

                    <div class="row">

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @error('first_name') style="margin-bottom: 0;" @enderror>
                                <span class="input-group-addon"><i class="fa fa-user-circle"></i></span>
                                <input type="text" name="first_name" class="form-control" value="{{ old('first_name', '') }}" placeholder="{{ $content->first_name_label }}" tabindex="1">
                            </div>
                            @error('first_name')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @error('last_name') style="margin-bottom: 0;" @enderror>
                                <span class="input-group-addon"><i class="fa fa-user-o"></i></span>
                                <input type="text" name="last_name" class="form-control" value="{{ old('last_name', '') }}" placeholder="{{ $content->last_name_label }}" tabindex="2">
                            </div>
                            @error('last_name')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                    </div>


                    <div class="row">

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @error('age') style="margin-bottom: 0;" @enderror>
                                <span class="input-group-addon"><i class="fa fa-birthday-cake"></i></span>
                                <input type="text" name="age" class="form-control" value="{{ old('age', '') }}" placeholder="{{ $content->age_label }}" tabindex="3">
                            </div>
                            @error('age')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @error('gender') style="margin-bottom: 0;" @enderror>
                                <span class="input-group-addon"><i class="fa fa-venus-mars"></i></span>
                                <select name="gender" class="form-control" tabindex="4">
                                    @foreach ($genders as $value => $label)
                                        <option value="{{ $value }}" {{ old('gender') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('gender')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                    </div>


                    <div class="row">

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @error('email') style="margin-bottom: 0;" @enderror>
                                <span class="input-group-addon"><i class="fa fa-envelope-o"></i></span>
                                <input type="text" name="email" class="form-control" value="{{ old('email', '') }}" placeholder="{{ $content->email_label }}" tabindex="5">
                            </div>
                            @error('email')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @error('phone') style="margin-bottom: 0;" @enderror>
                                <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                                <input type="tel" name="phone" class="form-control" value="{{ old('phone', '') }}" placeholder="{{ $content->phone_num_label }}" tabindex="6">
                            </div>
                            @error('phone')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                    </div>


                    <div class="row">

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @error('country_id') style="margin-bottom: 0;" @enderror>
                                <span class="input-group-addon"><i class="fa fa-globe"></i></span>
                                <select name="country_id" class="form-control" tabindex="7">
                                    @foreach ($country_list as $id => $name)
                                        <option value="{{ $id }}" {{ (int) old('country_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('country_id')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @error('japanese_level') style="margin-bottom: 0;" @enderror>
                                <span class="input-group-addon"><i class="fa fa-level-up"></i></span>
                                <select name="japanese_level" class="form-control" tabindex="8">
                                    @foreach ($levels as $value => $label)
                                        <option value="{{ $value }}" {{ old('japanese_level') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('japanese_level')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                    </div>


                    <div class="row">

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <label>{{ $content->job_categories_label }} <i class="fa fa-tags"></i></label>

                            @php $tabindex = 9 @endphp
                            <div class="checkbox checkbox-primary">
                                @foreach ($job_categories as $id => $val)
                                    <input
                                        id="check_{{ $id }}"
                                        type="checkbox"
                                        class="styled"
                                        name="job_category[]"
                                        value="{{ $id }}"
                                        {{ is_array(old('job_category')) && in_array($id, old('job_category')) ? 'checked' : '' }}
                                        tabindex="{{ $tabindex }}"
                                    >
                                    <label for="check_{{ $id }}" class="col-md-6 col-xs-12">
                                        <small><b><i class="fa fa-tag"></i> {{ $val }}</b></small>
                                    </label>
                                    @php $tabindex++ @endphp
                                @endforeach
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    @error('job_category')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>


                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <label>{{ $content->prefectures_label }} <i class="fa fa-location-arrow"></i></label>
                            <div class="input-group" @error('prefecture_id') style="margin-bottom: 0;" @enderror>
                                <span class="input-group-addon"><i class="fa fa-location-arrow"></i></span>
                                <select name="prefecture_id" id="prefecture_id" class="form-control" tabindex="13">
                                    @foreach ($prefectures as $id => $name)
                                        <option value="{{ $id }}" {{ (int) old('prefecture_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('prefecture_id')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                    </div>


                    <div class="row">

                        <div class="col-md-12">

                            <label>{{ $content->areas_label }} <i class="fa fa-map-marker"></i></label>

                            @foreach ($prefectures as $id => $prefecture)
                                @if ($id !== 0)
                                    <div class="row hidden" id="area-container-for-prefecture-{{ $id }}"></div>
                                @endif
                            @endforeach

                            @error('areas')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                    </div>

                    {{-- ── SECTION: Preferred Areas ── --}}
                    <div class="preference-section" style="margin-top: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin: 0 0 15px 0; color: #333;">
                            <i class="fa fa-map-marker"></i> Preferred Areas
                            <small style="color: #888; font-weight: normal;">(Optional - helps us send better matches)</small>
                        </h4>

                        <div class="form-group" id="enhanced-area-group" style="display: none;">
                            <label>Which areas do you want to work in?</label>
                            <select name="enhanced_area_ids[]" id="enhanced_area_ids" multiple class="form-control" style="height: 150px;">
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple areas. Leave empty for all areas.</small>
                        </div>

                        <div class="checkbox" style="margin-top: 10px;">
                            <label>
                                <input type="checkbox" name="commute_neighboring" value="1" {{ old('commute_neighboring') ? 'checked' : '' }}>
                                I can commute to neighboring prefectures
                            </label>
                        </div>
                    </div>

                    {{-- ── SECTION: Payment & Schedule ── --}}
                    <div class="preference-section" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin: 0 0 15px 0; color: #333;">
                            <i class="fa fa-money"></i> Payment & Schedule
                            <small style="color: #888; font-weight: normal;">(Optional)</small>
                        </h4>

                        <label style="display: block; margin-bottom: 10px; font-weight: 600;">Payment type:</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="wants_monthly_transfer" value="1" {{ old('wants_monthly_transfer', '1') ? 'checked' : '' }}>
                                Monthly bank transfer
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="wants_daily_payment" value="1" {{ old('wants_daily_payment') ? 'checked' : '' }}>
                                Daily payment
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="wants_hand_cash" value="1" {{ old('wants_hand_cash') ? 'checked' : '' }}>
                                Hand cash
                            </label>
                        </div>

                        <label style="display: block; margin-bottom: 10px; font-weight: 600;">Preferred shifts:</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="shift_any" value="1" {{ old('shift_any', '1') ? 'checked' : '' }} id="shift_any">
                                Any time
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="shift_morning" value="1" {{ old('shift_morning') ? 'checked' : '' }} class="shift-specific">
                                Morning (6-12)
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="shift_afternoon" value="1" {{ old('shift_afternoon') ? 'checked' : '' }} class="shift-specific">
                                Afternoon (12-18)
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="shift_evening" value="1" {{ old('shift_evening') ? 'checked' : '' }} class="shift-specific">
                                Evening (18-24)
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="shift_night" value="1" {{ old('shift_night') ? 'checked' : '' }} class="shift-specific">
                                Night (0-6)
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Minimum acceptable wage (per hour)</label>
                            <select name="min_wage" class="form-control" style="max-width: 250px;">
                                <option value="">No preference</option>
                                <option value="1000" {{ old('min_wage') == '1000' ? 'selected' : '' }}>&yen;1,000+</option>
                                <option value="1100" {{ old('min_wage') == '1100' ? 'selected' : '' }}>&yen;1,100+</option>
                                <option value="1200" {{ old('min_wage') == '1200' ? 'selected' : '' }}>&yen;1,200+</option>
                                <option value="1300" {{ old('min_wage') == '1300' ? 'selected' : '' }}>&yen;1,300+</option>
                                <option value="1400" {{ old('min_wage') == '1400' ? 'selected' : '' }}>&yen;1,400+</option>
                                <option value="1500" {{ old('min_wage') == '1500' ? 'selected' : '' }}>&yen;1,500+</option>
                            </select>
                        </div>
                    </div>

                    {{-- ── SECTION: About You ── --}}
                    <div class="preference-section" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin: 0 0 15px 0; color: #333;">
                            <i class="fa fa-user"></i> About You
                            <small style="color: #888; font-weight: normal;">(Optional - helps us match you better)</small>
                        </h4>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Visa type</label>
                                    <select name="visa_type" class="form-control">
                                        <option value="">Prefer not to say</option>
                                        <option value="student" {{ old('visa_type') == 'student' ? 'selected' : '' }}>Student visa</option>
                                        <option value="dependent" {{ old('visa_type') == 'dependent' ? 'selected' : '' }}>Dependent visa</option>
                                        <option value="working_holiday" {{ old('visa_type') == 'working_holiday' ? 'selected' : '' }}>Working Holiday</option>
                                        <option value="spouse" {{ old('visa_type') == 'spouse' ? 'selected' : '' }}>Spouse visa</option>
                                        <option value="permanent" {{ old('visa_type') == 'permanent' ? 'selected' : '' }}>Permanent resident</option>
                                        <option value="specified_skilled" {{ old('visa_type') == 'specified_skilled' ? 'selected' : '' }}>Specified Skilled Worker</option>
                                        <option value="other" {{ old('visa_type') == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Japanese level</label>
                                    <select name="enhanced_japanese_level" class="form-control">
                                        <option value="">Prefer not to say</option>
                                        <option value="none" {{ old('enhanced_japanese_level') == 'none' ? 'selected' : '' }}>No Japanese</option>
                                        <option value="basic" {{ old('enhanced_japanese_level') == 'basic' ? 'selected' : '' }}>Basic greetings only</option>
                                        <option value="conversational" {{ old('enhanced_japanese_level') == 'conversational' ? 'selected' : '' }}>Conversational</option>
                                        <option value="business" {{ old('enhanced_japanese_level') == 'business' ? 'selected' : '' }}>Business level (N2-N1)</option>
                                        <option value="fluent" {{ old('enhanced_japanese_level') == 'fluent' ? 'selected' : '' }}>Fluent / Native</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Max hours per week</label>
                                    <select name="max_hours_per_week" class="form-control">
                                        <option value="">No limit</option>
                                        <option value="15" {{ old('max_hours_per_week') == '15' ? 'selected' : '' }}>Up to 15 hours</option>
                                        <option value="20" {{ old('max_hours_per_week') == '20' ? 'selected' : '' }}>Up to 20 hours</option>
                                        <option value="28" {{ old('max_hours_per_week') == '28' ? 'selected' : '' }}>Up to 28 hours (student visa)</option>
                                        <option value="40" {{ old('max_hours_per_week') == '40' ? 'selected' : '' }}>Up to 40 hours</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── SECTION: Job Alerts ── --}}
                    <div class="preference-section" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin: 0 0 15px 0; color: #333;">
                            <i class="fa fa-bell"></i> Job Alerts
                            <small style="color: #888; font-weight: normal;">(Optional)</small>
                        </h4>

                        <div class="form-group">
                            <label>How often do you want job alerts?</label>
                            <select name="alert_frequency" class="form-control" style="max-width: 250px;">
                                <option value="weekly" {{ old('alert_frequency', 'weekly') == 'weekly' ? 'selected' : '' }}>Weekly digest (every Monday)</option>
                                <option value="daily" {{ old('alert_frequency') == 'daily' ? 'selected' : '' }}>Daily digest</option>
                                <option value="immediate" {{ old('alert_frequency') == 'immediate' ? 'selected' : '' }}>Immediately when a match is found</option>
                            </select>
                        </div>

                        <div style="margin-top: 10px;">
                            <label class="checkbox-inline" style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="alert_hand_cash" value="1" {{ old('alert_hand_cash') ? 'checked' : '' }}>
                                Alert me immediately when hand cash jobs appear in my area
                            </label>
                            <label class="checkbox-inline" style="display: block;">
                                <input type="checkbox" name="alert_high_wage" value="1" {{ old('alert_high_wage') ? 'checked' : '' }}>
                                Alert me when jobs above my minimum wage appear
                            </label>
                        </div>
                    </div>

                    <br />

                    <button class="btn btn-custom">{{ $content->save_btn_label }}</button>

                </form>

            </div>
        </div>
    </div>
@endsection

@push('page-scripts')
    <script src="{{ mix('js/frontend-app-pages/home_subscribe.js') }}" defer></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var prefSelect = document.getElementById('prefecture_id');
        var areaGroup = document.getElementById('enhanced-area-group');
        var areaSelect = document.getElementById('enhanced_area_ids');

        if (prefSelect && areaGroup && areaSelect) {
            prefSelect.addEventListener('change', function() {
                var prefId = this.value;
                if (prefId && prefId !== '0') {
                    areaGroup.style.display = 'block';
                    var csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfToken) return;
                    fetch('/jobs/areas/get', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken.content
                        },
                        body: JSON.stringify({prefecture_id: prefId})
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var areas = JSON.parse(data.areas || '[]');
                        var html = '';
                        areas.forEach(function(area) {
                            html += '<option value="' + area[0] + '">' + area[1] + '</option>';
                        });
                        areaSelect.innerHTML = html;
                    });
                } else {
                    areaGroup.style.display = 'none';
                    areaSelect.innerHTML = '';
                }
            });
        }

        // "Any time" shift toggle
        var shiftAny = document.getElementById('shift_any');
        var shiftSpecific = document.querySelectorAll('.shift-specific');

        if (shiftAny) {
            shiftAny.addEventListener('change', function() {
                if (this.checked) {
                    shiftSpecific.forEach(function(cb) { cb.checked = false; });
                }
            });

            shiftSpecific.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    if (this.checked) {
                        shiftAny.checked = false;
                    }
                });
            });
        }
    });
    </script>
@endpush
