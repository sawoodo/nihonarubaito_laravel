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

                    <br />

                    <button class="btn btn-custom">{{ $content->save_btn_label }}</button>

                </form>

            </div>
        </div>
    </div>
@endsection

@push('page-scripts')
    <script src="{{ mix('js/frontend-app-pages/home_subscribe.js') }}" defer></script>
@endpush
